<?php
declare(strict_types=1);

namespace CsvView\View;

use Cake\Core\Exception\CakeException;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Hash;
use Cake\View\SerializedView;
use Stringable;

/**
 * A view class that is used for CSV responses.
 *
 * By setting the 'serialize' view builder option, you can specify a view variable
 * that should be serialized to CSV and used as the response for the request.
 * This allows you to omit templates + layouts, if your just need to emit a single view
 * variable as the CSV response.
 *
 * In your controller, you could do the following:
 *
 * `$this->set(['posts' => $posts])->viewBuilder()->setOption('serialize', 'posts');`
 *
 * When the view is rendered, the `$posts` view variable will be serialized
 * into CSV.
 *
 * When rendering the data, the data should be a single, flat array. If this is not the case,
 * then you should also specify the `extract` view option:
 *
 * ```
 * $extract = [
 *   ['id', '%d'],       // Hash-compatible path, sprintf-compatible format
 *   'description',     // Hash-compatible path
 *   function ($row) {  // Callable
 *      //return value
 *   }
 * ];
 * ```
 *
 * You can also define `serialize` as an array. This will create a top level object containing
 * all the named view variables:
 *
 * ```
 * $this->set(compact('posts', 'users', 'stuff'));
 * $this->viewBuilder()->setOption('serialize', ['posts', 'users']);
 * ```
 *
 * Each of the view vars in `serialize` would then be output into the CSV output.
 *
 * If you don't use the `serialize` option, you will need a view. You can use extended
 * views to provide layout like functionality.
 *
 * When not using custom views, you may specify the following view options:
 *
 * - array `header`: (default null)    A flat array of header column names
 * - array `footer`: (default null)    A flat array of footer column names
 * - string `delimiter`: (default ',') CSV Delimiter, defaults to comma
 * - string `enclosure`: (default '"') CSV Enclosure for use with fputcsv()
 * - string `eol`: (default '\n')      End-of-line character the csv
 *
 * @link https://github.com/friendsofcake/cakephp-csvview
 */
class CsvView extends SerializedView
{
    /**
     * CSV layouts are located in the csv sub directory of `Layouts/`
     *
     * @var string
     */
    protected string $layoutPath = 'csv';

    /**
     * CSV views are always located in the 'csv' sub directory for a
     * controllers views.
     *
     * @var string
     */
    protected string $subDir = 'csv';

    /**
     * Aggregated CSV output for the current serialization pass.
     *
     * @var string
     */
    protected string $csv = '';

    /**
     * Temp stream used by fputcsv() to generate a single row.
     *
     * @var resource|null
     */
    protected $fp = null;

    /**
     * Iconv extension.
     *
     * @var string
     */
    public const EXTENSION_ICONV = 'iconv';

    /**
     * Mbstring extension.
     *
     * @var string
     */
    public const EXTENSION_MBSTRING = 'mbstring';

    /**
     * Transcoding mode: throw on any unconvertible byte / character (default).
     *
     * @var string
     */
    public const TRANSCODING_MODE_STRICT = 'strict';

    /**
     * Transcoding mode: silently drop unconvertible characters and keep going.
     * Maps to iconv's `//IGNORE` suffix and mbstring's substitute-char `'none'`.
     *
     * @var string
     */
    public const TRANSCODING_MODE_IGNORE = 'ignore';

    /**
     * Transcoding mode: transliterate where possible, ignore otherwise.
     * Maps to iconv's `//TRANSLIT//IGNORE` suffix. For mbstring this falls
     * back to ignore (mbstring has no transliteration).
     *
     * @var string
     */
    public const TRANSCODING_MODE_TRANSLITERATE = 'transliterate';

    /**
     * List of bom signs for encodings.
     *
     * @var array<string, string>
     */
    protected array $bomMap;

    /**
     * BOM first appearance
     *
     * @var bool
     */
    protected bool $isFirstBom = true;

    /**
     * Default config.
     *
     * - 'header': (default null)  A flat array of header column names
     * - 'footer': (default null)  A flat array of footer column names
     * - 'extract': (default null) An array of Hash-compatible paths or
     *     callable with matching 'sprintf' $format as follows:
     *     $extract = [
     *         [$path, $format],
     *         [$path],
     *         $path,
     *         function () { ... } // Callable
     *      ];
     *
     *     If a string or unspecified, the format default is '%s'.
     * - 'delimiter': (default ',')      CSV Delimiter, defaults to comma
     * - 'enclosure': (default '"')      CSV Enclosure for use with fputcsv()
     * - 'newline': (default '\n')       CSV Newline replacement for use with fputcsv()
     * - 'escape': (default '')          CSV escape character for use with fputcsv().
     *     Empty string is RFC 4180 compliant and avoids PHP 8.4's
     *     deprecation warning for non-empty escape values. Set to '\\' for
     *     legacy PHP-style escaping (will emit E_DEPRECATED on PHP 8.4+).
     * - 'eol': (default '\n')           End-of-line character the csv
     * - 'bom': (default false)          Adds BOM (byte order mark) header
     * - 'setSeparator': (default false) Adds sep=[_delimiter] in the first line
     * - 'csvEncoding': (default 'UTF-8') CSV file encoding
     * - 'dataEncoding': (default 'UTF-8') Encoding of data to be serialized
     * - 'transcodingExtension': (default 'iconv') PHP extension to use for character encoding conversion
     * - 'excel': (default false)  Shorthand for an Excel-friendly UTF-8 export.
     *     When true, sets `bom => true`, `eol => "\r\n"`, and `csvEncoding => 'UTF-8'`.
     *     These specific keys are forced; if you need a different combination
     *     do not enable `excel` and set them individually instead.
     * - 'transcodingMode': (default 'strict') How to handle source bytes that
     *     cannot be encoded in the target encoding. One of:
     *     - 'strict': throw a CakeException naming the source/target encoding.
     *     - 'ignore': silently drop unconvertible characters and continue.
     *     - 'transliterate': transliterate where possible (e.g. é → e), ignore
     *       otherwise. For iconv only; mbstring falls back to 'ignore'.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'extract' => null,
        'footer' => null,
        'header' => null,
        'serialize' => null,
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
    ];

    /**
     * Initalize View
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->bomMap = [
            'UTF-32BE' => chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF),
            'UTF-32LE' => chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00),
            'UTF-16BE' => chr(0xFE) . chr(0xFF),
            'UTF-16LE' => chr(0xFF) . chr(0xFE),
            'UTF-8' => chr(0xEF) . chr(0xBB) . chr(0xBF),
        ];

        if (
            $this->getConfig('transcodingExtension') === static::EXTENSION_ICONV &&
            !extension_loaded(self::EXTENSION_ICONV)
        ) {
            $this->setConfig('transcodingExtension', static::EXTENSION_MBSTRING);
        }

        parent::initialize();
    }

    /**
     * Mime-type this view class renders as.
     *
     * @return string The CSV content type.
     */
    public static function contentType(): string
    {
        return 'text/csv';
    }

    /**
     * Serialize view vars.
     *
     * @param array<string>|string $serialize The name(s) of the view variable(s) that
     *   need(s) to be serialized
     * @return string The serialized data or false.
     */
    protected function _serialize(array|string $serialize): string
    {
        $this->resetState();
        $this->_applyExcelPreset();

        $this->_renderRow($this->getConfig('header'));
        $this->_renderContent();
        $this->_renderRow($this->getConfig('footer'));
        $content = $this->csv;

        $this->resetState();

        return $content;
    }

    /**
     * Reset accumulated state so the same view instance can render multiple
     * times in a single request (queue worker, multi-file export, etc.).
     */
    protected function resetState(): void
    {
        $this->csv = '';
        $this->isFirstBom = true;
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
        $this->fp = null;
    }

    /**
     * @inheritDoc
     */
    public function __destruct()
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
            $this->fp = null;
        }
    }

    /**
     * Apply the `excel` shorthand if enabled: BOM + CRLF EOL + UTF-8 encoding,
     * the three options Excel needs to open a UTF-8 CSV correctly on Windows.
     *
     * Applied at serialize-time (rather than `initialize()`) so the preset
     * takes effect regardless of when `excel` is set — including the test
     * pattern of constructing the view and then calling `setConfig()`.
     *
     * @return void
     */
    protected function _applyExcelPreset(): void
    {
        if (!$this->getConfig('excel')) {
            return;
        }

        $this->setConfig([
            'bom' => true,
            'eol' => "\r\n",
            'csvEncoding' => 'UTF-8',
        ]);
    }

    /**
     * Renders the body of the data to the csv
     *
     * @return void
     * @throws \Cake\Core\Exception\CakeException
     */
    protected function _renderContent(): void
    {
        $extract = $this->getConfig('extract');
        $serialize = $this->getConfig('serialize');

        if ($serialize === true) {
            $serialize = array_keys($this->viewVars);
        }

        foreach ((array)$serialize as $viewVar) {
            if (is_scalar($this->viewVars[$viewVar])) {
                throw new CakeException("'" . $viewVar . "' is not an array or iterable object.");
            }

            foreach ($this->viewVars[$viewVar] as $_data) {
                if ($_data instanceof EntityInterface) {
                    $_data = $_data->toArray();
                }

                if ($extract === null) {
                    $this->_renderRow($_data);
                    continue;
                }

                $values = [];
                foreach ($extract as $formatter) {
                    if (!is_string($formatter) && is_callable($formatter)) {
                        $value = $formatter($_data);
                        $pathForError = '<callable>';
                    } else {
                        $path = $formatter;
                        $format = null;
                        if (is_array($formatter)) {
                            [$path, $format] = $formatter;
                        }
                        $pathForError = (string)$path;

                        $value = Hash::get($_data, $path);

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
                $this->_renderRow($values);
            }
        }
    }

    /**
     * Aggregates the rows into a single csv
     *
     * @param array<scalar|\Stringable|null>|null $row Row data
     * @return string CSV with all data to date
     */
    protected function _renderRow(?array $row = null): string
    {
        $this->csv .= (string)$this->_generateRow($row);

        return $this->csv;
    }

    /**
     * Generates a single row in a csv from an array of
     * data by writing the array to a temporary file and
     * returning its contents
     *
     * @param array<scalar|\Stringable|null>|null $row Row data
     * @return string|false String with the row in csv-syntax, false on fputscv failure
     */
    protected function _generateRow(?array $row = null): string|false
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
                fwrite($this->fp, 'sep=' . $setSeparator . "\n");
            }
        } else {
            ftruncate($this->fp, 0);
        }

        $null = $this->getConfig('null');
        if ($null) {
            foreach ($row as &$field) {
                if ($field === null) {
                    $field = $null;
                }
            }
        }

        $delimiter = $this->getConfig('delimiter');
        $enclosure = $this->getConfig('enclosure');
        $newline = $this->getConfig('newline');
        $escape = $this->getConfig('escape');

        /** @phpstan-ignore-next-line */
        $row = str_replace(["\r\n", "\n", "\r"], $newline, $row);
        if ($enclosure === '') {
            // fputcsv does not support empty enclosure
            if (fputs($this->fp, implode($delimiter, $row) . "\n") === false) {
                return false;
            }
        } else {
            if (fputcsv($this->fp, $row, $delimiter, $enclosure, $escape) === false) {
                return false;
            }
        }

        rewind($this->fp);
        unset($row);

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
            $csv = $this->_transcode($csv, $dataEncoding, $csvEncoding);
        }

        // BOM must be added after encoding
        $bom = $this->getConfig('bom');
        if ($bom && $this->isFirstBom) {
            $csv = $this->getBom($csvEncoding) . $csv;
            $this->isFirstBom = false;
        }

        return $csv;
    }

    /**
     * Returns the BOM for the encoding given.
     *
     * @param string $csvEncoding The encoding you want the BOM for
     * @return string
     */
    protected function getBom(string $csvEncoding): string
    {
        $csvEncoding = strtoupper($csvEncoding);

        return $this->bomMap[$csvEncoding] ?? '';
    }

    /**
     * Transcode a row's worth of CSV between encodings, honoring the
     * configured `transcodingMode` (strict / ignore / transliterate).
     *
     * @param string $csv The current CSV chunk.
     * @param string $dataEncoding Source encoding.
     * @param string $csvEncoding Target encoding.
     * @return string Transcoded CSV chunk.
     * @throws \Cake\Core\Exception\CakeException When mode is `strict` and the
     *  transcoder reports a conversion failure.
     */
    protected function _transcode(string $csv, string $dataEncoding, string $csvEncoding): string
    {
        $extension = $this->getConfig('transcodingExtension');
        $mode = $this->getConfig('transcodingMode');

        if ($extension === static::EXTENSION_ICONV) {
            $targetSpec = match ($mode) {
                static::TRANSCODING_MODE_IGNORE => $csvEncoding . '//IGNORE',
                static::TRANSCODING_MODE_TRANSLITERATE => $csvEncoding . '//TRANSLIT//IGNORE',
                default => $csvEncoding,
            };
            // iconv() emits an E_NOTICE / E_WARNING immediately before returning
            // false on unconvertible input. Install a no-op handler for the
            // duration of the call so we surface the failure via our own
            // (strict-mode) exception below rather than as two near-duplicate
            // signals. PHPUnit's own error handler is restored on `finally`.
            set_error_handler(static fn(): bool => true, E_NOTICE | E_WARNING);
            try {
                $converted = iconv($dataEncoding, $targetSpec, $csv);
            } finally {
                restore_error_handler();
            }
            if ($converted === false) {
                if ($mode === static::TRANSCODING_MODE_STRICT) {
                    throw new CakeException(sprintf(
                        'iconv() failed to transcode row from `%s` to `%s`. '
                        . 'Check that the source data is valid `%s` and that both '
                        . 'encodings are supported by your iconv build, or set '
                        . '`transcodingMode` to `ignore` or `transliterate` to '
                        . 'tolerate unconvertible characters.',
                        $dataEncoding,
                        $csvEncoding,
                        $dataEncoding,
                    ));
                }

                return '';
            }

            return $converted;
        }

        if ($extension === static::EXTENSION_MBSTRING) {
            $previousSubstitute = null;
            if ($mode !== static::TRANSCODING_MODE_STRICT) {
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
