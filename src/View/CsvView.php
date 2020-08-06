<?php
declare(strict_types=1);

namespace CsvView\View;

use Cake\Datasource\EntityInterface;
use Cake\Utility\Hash;
use Cake\View\SerializedView;
use Exception;

/**
 * A view class that is used for CSV responses.
 *
 * By setting the '_serialize' key in your controller, you can specify a view variable
 * that should be serialized to CSV and used as the response for the request.
 * This allows you to omit views + layouts, if your just need to emit a single view
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
 * Each of the viewVars in `serialize` would then be output into the csv
 *
 * If you don't use the `serialize` option, you will need a view. You can use extended
 * views to provide layout like functionality.
 *
 * When not using custom views, you may specify the following view options:
 *
 * - array `$header`: (default null)    A flat array of header column names
 * - array `$footer`: (default null)    A flat array of footer column names
 * - string `$delimiter`: (default ',') CSV Delimiter, defaults to comma
 * - string `$enclosure`: (default '"') CSV Enclosure for use with fputcsv()
 * - string `$eol`: (default '\n')      End-of-line character the csv
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
    protected $layoutPath = 'csv';

    /**
     * CSV views are always located in the 'csv' sub directory for a
     * controllers views.
     *
     * @var string
     */
    protected $subDir = 'csv';

    /**
     * Response type.
     *
     * @var string
     */
    protected $_responseType = 'text/csv';

    /**
     * Whether or not to reset static variables in use
     *
     * @var bool
     */
    protected $_resetStaticVariables = false;

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
     * List of bom signs for encodings.
     *
     * @var array
     */
    protected $bomMap;

    /**
     * BOM first appearance
     *
     * @var bool
     */
    protected $isFirstBom = true;

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
     * - 'eol': (default '\n')           End-of-line character the csv
     * - 'bom': (default false)          Adds BOM (byte order mark) header
     * - 'setSeparator': (default false) Adds sep=[_delimiter] in the first line
     * - 'csvEncoding': (default 'UTF-8') CSV file encoding
     * - 'dataEncoding': (default 'UTF-8') Encoding of data to be serialized
     * - 'transcodingExtension': (default 'iconv') PHP extension to use for character encoding conversion
     *
     * @var array
     */
    protected $_defaultConfig = [
        'extract' => null,
        'footer' => null,
        'header' => null,
        'serialize' => null,
        'delimiter' => ',',
        'enclosure' => '"',
        'newline' => "\n",
        'eol' => PHP_EOL,
        'null' => '',
        'bom' => false,
        'setSeparator' => false,
        'csvEncoding' => 'UTF-8',
        'dataEncoding' => 'UTF-8',
        'transcodingExtension' => self::EXTENSION_ICONV,
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
     * Serialize view vars.
     *
     * @param array|string $serialize The name(s) of the view variable(s) that
     *   need(s) to be serialized
     * @return string The serialized data or false.
     */
    protected function _serialize($serialize): string
    {
        $this->_renderRow($this->getConfig('header'));
        $this->_renderContent();
        $this->_renderRow($this->getConfig('footer'));
        $content = $this->_renderRow();
        $this->_resetStaticVariables = true;
        $this->_renderRow();

        return $content;
    }

    /**
     * Renders the body of the data to the csv
     *
     * @return void
     * @throws \Exception
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
                throw new Exception("'" . $viewVar . "' is not an array or iteratable object.");
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
                    } else {
                        $path = $formatter;
                        $format = null;
                        if (is_array($formatter)) {
                            [$path, $format] = $formatter;
                        }

                        if (strpos($path, '.') === false) {
                            $value = $_data[$path];
                        } else {
                            $value = Hash::get($_data, $path);
                        }

                        if ($format) {
                            $value = sprintf($format, $value);
                        }
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
     * @param array|null $row Row data
     * @return string CSV with all data to date
     */
    protected function _renderRow(?array $row = null): string
    {
        static $csv = '';

        if ($this->_resetStaticVariables) {
            $csv = '';
            $this->_resetStaticVariables = false;

            return '';
        }

        $csv .= (string)$this->_generateRow($row);

        return $csv;
    }

    /**
     * Generates a single row in a csv from an array of
     * data by writing the array to a temporary file and
     * returning it's contents
     *
     * @param array|null $row Row data
     * @return string|false String with the row in csv-syntax, false on fputscv failure
     */
    protected function _generateRow(?array $row = null)
    {
        static $fp = false;

        if (empty($row)) {
            return '';
        }

        if ($fp === false) {
            $fp = fopen('php://temp', 'r+');

            $setSeparator = $this->getConfig('setSeparator');
            if ($setSeparator) {
                fwrite($fp, 'sep=' . $setSeparator . "\n");
            }
        } else {
            ftruncate($fp, 0);
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

        $row = str_replace(["\r\n", "\n", "\r"], $newline, $row);
        if ($enclosure === '') {
            // fputcsv does not supports empty enclosure
            if (fputs($fp, implode($delimiter, $row) . "\n") === false) {
                return false;
            }
        } else {
            if (fputcsv($fp, $row, $delimiter, $enclosure) === false) {
                return false;
            }
        }

        rewind($fp);

        $csv = '';
        while (($buffer = fgets($fp, 4096)) !== false) {
            $csv .= $buffer;
        }

        $eol = $this->getConfig('eol');
        if ($eol !== "\n") {
            $csv = str_replace("\n", $eol, $csv);
        }

        $dataEncoding = $this->getConfig('dataEncoding');
        $csvEncoding = $this->getConfig('csvEncoding');
        if ($dataEncoding !== $csvEncoding) {
            $extension = $this->getConfig('transcodingExtension');
            if ($extension === static::EXTENSION_ICONV) {
                $csv = iconv($dataEncoding, $csvEncoding, $csv);
            } elseif ($extension === static::EXTENSION_MBSTRING) {
                $csv = mb_convert_encoding($csv, $csvEncoding, $dataEncoding);
            }
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
}
