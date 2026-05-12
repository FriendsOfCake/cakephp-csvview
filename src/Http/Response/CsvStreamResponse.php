<?php
declare(strict_types=1);

namespace CsvView\Http\Response;

use Cake\Core\Exception\CakeException;
use Cake\Datasource\EntityInterface;
use Cake\Http\Response\AbstractStreamResponse;
use Cake\Utility\Hash;
use Stringable;
use Throwable;

/**
 * Response class for streaming large CSV exports memory-efficiently.
 *
 * Emits rows as they are produced rather than building the entire CSV in
 * memory first, so memory use stays constant regardless of dataset size and
 * time-to-first-byte drops to "after first row". Extends
 * {@see \Cake\Http\Response\AbstractStreamResponse} which owns the streaming
 * lifecycle (CallbackStream wiring, output/flush primitives, X-Accel-Buffering
 * header, flush threshold, optional Log::error shim). This class owns the CSV
 * wire format: BOM, optional `sep=` line, header / footer rows, row encoding
 * via `fputcsv`, end-of-line replacement, and transcoding between data and
 * file encoding.
 *
 * ### Usage
 *
 * ```php
 * public function export()
 * {
 *     $rows = $this->Articles->find()->disableBufferedResults();
 *
 *     return new CsvStreamResponse($rows, [
 *         'header' => ['id', 'title', 'created'],
 *         'extract' => ['id', 'title', ['created', '%s']],
 *     ]);
 * }
 * ```
 *
 * ### Options
 *
 * - `header` (array|null, default: null): A flat array of header column names
 * - `footer` (array|null, default: null): A flat array of footer column names
 * - `extract` (array|null, default: null): Hash-compatible paths and / or
 *   callables describing how to flatten each row.
 * - `delimiter` (string, default: ','): CSV column delimiter
 * - `enclosure` (string, default: '"'): CSV value enclosure
 * - `escape` (string, default: ''): CSV escape character. Empty string is
 *   RFC 4180 compliant and avoids PHP 8.4's deprecation warning for non-empty
 *   escape values.
 * - `newline` (string, default: "\n"): replacement for newline characters
 *   found inside a field
 * - `eol` (string, default: PHP_EOL): end-of-line written between rows
 * - `null` (string, default: ''): replacement for null cells
 * - `bom` (bool, default: false): Prepend a UTF-* BOM to the response
 * - `setSeparator` (bool, default: false): Emit `sep={delimiter}\n` before the
 *   header. Excel-only hint.
 * - `csvEncoding` (string, default: 'UTF-8'): Target encoding of the response
 * - `dataEncoding` (string, default: 'UTF-8'): Source encoding of the rows
 * - `transcodingExtension` (string, default: 'iconv'): 'iconv' or 'mbstring'
 * - `transcodingMode` (string, default: 'strict'): 'strict', 'ignore' or
 *   'transliterate' — controls how unconvertible characters are handled when
 *   transcoding rows.
 * - `excel` (bool, default: false): Shorthand for an Excel-friendly UTF-8
 *   export. When true forces `bom => true`, `eol => "\r\n"`,
 *   `csvEncoding => 'UTF-8'`.
 * - `flushEvery` (int, default: 1): Flush output buffers every N items
 *   (inherited from {@see AbstractStreamResponse})
 *
 * ### Mid-stream errors
 *
 * A row that cannot be encoded (eg. a path that resolves to an unrenderable
 * non-scalar value, or a transcode failure under `strict` mode) is logged
 * via `Log::error()` and the stream is torn cleanly — no further rows are
 * written, the footer is omitted, and the client receives a truncated CSV.
 * This trades a partial response for valid up-to-the-error CSV: callers can
 * detect truncation server-side via the log entry.
 *
 * @see \CsvView\View\CsvView The non-streaming sibling for smaller datasets.
 * @see \Cake\Http\Response\AbstractStreamResponse The streaming base class.
 */
class CsvStreamResponse extends AbstractStreamResponse
{
    /**
     * Iconv extension identifier.
     */
    public const EXTENSION_ICONV = 'iconv';

    /**
     * Mbstring extension identifier.
     */
    public const EXTENSION_MBSTRING = 'mbstring';

    /**
     * Transcoding mode: throw on any unconvertible byte / character (default).
     */
    public const TRANSCODING_MODE_STRICT = 'strict';

    /**
     * Transcoding mode: silently drop unconvertible characters and keep going.
     * Maps to iconv's `//IGNORE` suffix and mbstring's substitute-char `'none'`.
     */
    public const TRANSCODING_MODE_IGNORE = 'ignore';

    /**
     * Transcoding mode: transliterate where possible, ignore otherwise.
     * Maps to iconv's `//TRANSLIT//IGNORE` suffix. For mbstring this falls
     * back to ignore (mbstring has no transliteration).
     */
    public const TRANSCODING_MODE_TRANSLITERATE = 'transliterate';

    /**
     * Default streaming options.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'extract' => null,
        'footer' => null,
        'header' => null,
        'delimiter' => ',',
        'enclosure' => '"',
        'newline' => "\n",
        'escape' => '',
        'eol' => PHP_EOL,
        'null' => '',
        'bom' => false,
        'setSeparator' => false,
        'csvEncoding' => 'UTF-8',
        'dataEncoding' => 'UTF-8',
        'transcodingExtension' => self::EXTENSION_ICONV,
        'excel' => false,
        'transcodingMode' => self::TRANSCODING_MODE_STRICT,
        'flushEvery' => 1,
    ];

    /**
     * BOM byte sequences by target encoding.
     *
     * @var array<string, string>
     */
    protected array $bomMap = [];

    /**
     * Whether the next row should be prefixed with a BOM.
     *
     * @var bool
     */
    protected bool $isFirstBom = true;

    /**
     * Cached `php://temp` stream reused by `generateRow()` to format each row.
     *
     * @var resource|null
     */
    protected $fp = null;

    /**
     * @param iterable<mixed> $data The rows to stream (array, generator, ResultSet, …).
     * @param array<string, mixed> $options Streaming options; see the class docblock.
     */
    public function __construct(iterable $data, array $options = [])
    {
        parent::__construct($data, $options);

        $this->bomMap = [
            'UTF-32BE' => chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF),
            'UTF-32LE' => chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00),
            'UTF-16BE' => chr(0xFE) . chr(0xFF),
            'UTF-16LE' => chr(0xFF) . chr(0xFE),
            'UTF-8' => chr(0xEF) . chr(0xBB) . chr(0xBF),
        ];

        if ($this->getConfig('excel')) {
            $this->setConfig([
                'bom' => true,
                'eol' => "\r\n",
                'csvEncoding' => 'UTF-8',
            ]);
        }

        if (
            $this->getConfig('transcodingExtension') === self::EXTENSION_ICONV
            && !extension_loaded(self::EXTENSION_ICONV)
        ) {
            $this->setConfig('transcodingExtension', self::EXTENSION_MBSTRING);
        }
    }

    /**
     * Close the cached row-formatting stream when the response is destroyed.
     */
    public function __destruct()
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
            $this->fp = null;
        }
    }

    /**
     * @inheritDoc
     */
    protected function contentType(): string
    {
        return 'text/csv';
    }

    /**
     * @inheritDoc
     */
    protected function streamData(): void
    {
        $header = $this->getConfig('header');
        $footer = $this->getConfig('footer');

        if ($header !== null) {
            $row = $this->generateRow($header);
            if ($row !== '') {
                $this->outputAndFlush($row);
            }
        }

        $completed = $this->streamRows();

        if ($completed && $footer !== null) {
            $row = $this->generateRow($footer);
            if ($row !== '') {
                $this->outputAndFlush($row, force: true);
            }
        }

        $this->flushOutputBuffers();
    }

    /**
     * Iterate the data, emitting one CSV row per item.
     *
     * @return bool true if the iteration completed without an encoding failure;
     *   false if an error was logged and the stream was torn.
     */
    protected function streamRows(): bool
    {
        $index = 0;
        foreach ($this->data as $item) {
            try {
                $values = $this->extractRowValues($item);
                $row = $this->generateRow($values);
            } catch (Throwable $exception) {
                $this->logStreamError($exception->getMessage(), $index);

                return false;
            }

            if ($row !== '') {
                $this->outputAndFlush($row);
            }
            $index++;
        }

        return true;
    }

    /**
     * Flatten a single item into the array of values that will form one CSV row.
     *
     * @param mixed $item The current item from the input iterable.
     * @return array<int, scalar|\Stringable|null>
     */
    protected function extractRowValues(mixed $item): array
    {
        if ($item instanceof EntityInterface) {
            $item = $item->toArray();
        }

        $extract = $this->getConfig('extract');
        if ($extract === null) {
            return array_values((array)$item);
        }

        $values = [];
        foreach ($extract as $formatter) {
            if (!is_string($formatter) && is_callable($formatter)) {
                $value = $formatter($item);
                $pathForError = '<callable>';
            } else {
                $path = $formatter;
                $format = null;
                if (is_array($formatter)) {
                    [$path, $format] = $formatter;
                }
                $pathForError = (string)$path;

                $value = Hash::get($item, $path);

                if ($format !== null) {
                    $value = sprintf($format, $value);
                }
            }

            if (
                $value !== null
                && !is_scalar($value)
                && !($value instanceof Stringable)
            ) {
                throw new CakeException(sprintf(
                    'Extract path `%s` resolved to a non-scalar `%s`. '
                    . 'Use a callable formatter to flatten it, or adjust the extract path.',
                    $pathForError,
                    get_debug_type($value),
                ));
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * Generate a single row of CSV text from an array of cell values.
     *
     * Mirrors {@see \CsvView\View\CsvView::_generateRow()} so the streaming
     * and non-streaming paths emit byte-identical output for the same config.
     *
     * @param array<scalar|\Stringable|null>|null $row Row data.
     * @return string CSV-formatted row including the configured `eol`, or
     *   empty string if `$row` is null or empty.
     */
    protected function generateRow(?array $row): string
    {
        if (!$row) {
            return '';
        }

        if ($this->fp === null) {
            $stream = 'php://temp';
            $fp = fopen($stream, 'r+');
            if ($fp === false) {
                throw new CakeException(sprintf('Cannot open stream `%s`', $stream));
            }
            $this->fp = $fp;

            $setSeparator = $this->getConfig('setSeparator');
            if ($setSeparator) {
                fwrite($this->fp, 'sep=' . $this->getConfig('delimiter') . "\n");
            }
        } else {
            ftruncate($this->fp, 0);
        }

        $null = $this->getConfig('null');
        if ($null !== '') {
            foreach ($row as &$field) {
                if ($field === null) {
                    $field = $null;
                }
            }
            unset($field);
        }

        $delimiter = $this->getConfig('delimiter');
        $enclosure = $this->getConfig('enclosure');
        $newline = $this->getConfig('newline');
        $escape = $this->getConfig('escape');

        /** @phpstan-ignore-next-line */
        $row = str_replace(["\r\n", "\n", "\r"], $newline, $row);
        if ($enclosure === '') {
            if (fputs($this->fp, implode($delimiter, $row) . "\n") === false) {
                throw new CakeException('fputs() failed writing CSV row');
            }
        } else {
            if (fputcsv($this->fp, $row, $delimiter, $enclosure, $escape) === false) {
                throw new CakeException('fputcsv() failed writing CSV row');
            }
        }

        rewind($this->fp);

        $csv = '';
        while (($buffer = fgets($this->fp, 4096)) !== false) {
            $csv .= $buffer;
        }

        $eol = $this->getConfig('eol');
        if ($eol !== "\n") {
            $csv = str_replace("\n", $eol, $csv);
        }

        $dataEncoding = $this->getConfig('dataEncoding');
        $csvEncoding = $this->getConfig('csvEncoding');
        if ($dataEncoding !== $csvEncoding) {
            $csv = $this->transcode($csv, $dataEncoding, $csvEncoding);
        }

        $bom = $this->getConfig('bom');
        if ($bom && $this->isFirstBom) {
            $csv = $this->getBom($csvEncoding) . $csv;
            $this->isFirstBom = false;
        }

        return $csv;
    }

    /**
     * Return the BOM byte sequence for the configured target encoding, or an
     * empty string for unsupported encodings.
     */
    protected function getBom(string $csvEncoding): string
    {
        $csvEncoding = strtoupper($csvEncoding);

        return $this->bomMap[$csvEncoding] ?? '';
    }

    /**
     * Transcode a CSV row between encodings honoring the configured mode.
     *
     * Mirrors {@see \CsvView\View\CsvView::_transcode()}.
     *
     * @throws \Cake\Core\Exception\CakeException When mode is `strict` and
     *   iconv reports a conversion failure.
     */
    protected function transcode(string $csv, string $dataEncoding, string $csvEncoding): string
    {
        $extension = $this->getConfig('transcodingExtension');
        $mode = $this->getConfig('transcodingMode');

        if ($extension === self::EXTENSION_ICONV) {
            $targetSpec = match ($mode) {
                self::TRANSCODING_MODE_IGNORE => $csvEncoding . '//IGNORE',
                self::TRANSCODING_MODE_TRANSLITERATE => $csvEncoding . '//TRANSLIT//IGNORE',
                default => $csvEncoding,
            };
            set_error_handler(static fn(): bool => true, E_NOTICE | E_WARNING);
            try {
                $converted = iconv($dataEncoding, $targetSpec, $csv);
            } finally {
                restore_error_handler();
            }
            if ($converted === false) {
                if ($mode === self::TRANSCODING_MODE_STRICT) {
                    throw new CakeException(sprintf(
                        'iconv() failed to transcode row from `%s` to `%s`.',
                        $dataEncoding,
                        $csvEncoding,
                    ));
                }

                return '';
            }

            return $converted;
        }

        if ($extension === self::EXTENSION_MBSTRING) {
            $previousSubstitute = null;
            if ($mode !== self::TRANSCODING_MODE_STRICT) {
                $previousSubstitute = mb_substitute_character();
                mb_substitute_character('none');
            }
            try {
                $converted = mb_convert_encoding($csv, $csvEncoding, $dataEncoding);
            } finally {
                if ($previousSubstitute !== null) {
                    mb_substitute_character($previousSubstitute);
                }
            }

            return $converted;
        }

        return $csv;
    }
}
