<?php
App::uses('View', 'View');

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
 * `$this->set(array('posts' => $posts, '_serialize' => 'posts'));`
 *
 * When the view is rendered, the `$posts` view variable will be serialized
 * into CSV.
 *
 * When rendering the data, the data should be a single, flat array. If this is not the case,
 * then you should also specify an `_extract` variable:
 *
 * {{{
 * $_extract = array(
 *   array('Post.id', '%d'),   // Hash-compatible path, sprintf-compatible format
 *   array('Post.title'),      // Uses `%s` for sprintf-formatting
 *   'Post.description',       // Uses `%s` for sprintf-formatting
 * );
 * }}}
 *
 * You can also define `'_serialize'` as an array. This will create a top level object containing
 * all the named view variables:
 *
 * {{{
 * $this->set(compact('posts', 'users', 'stuff'));
 * $this->set('_serialize', array('posts', 'users'));
 * }}}
 *
 * Each of the viewVars in `_serialize` would then be output into the csv
 *
 * If you don't use the `_serialize` key, you will need a view. You can use extended
 * views to provide layout like functionality.
 *
 * When not using custom views, you may specify the following view variables:
 *
 * - array `$_header`: (default null)    A flat array of header column names
 * - array `$_footer`: (default null)    A flat array of footer column names
 * - string `$_delimiter`: (default ',') CSV Delimiter, defaults to comma
 * - string `$_enclosure`: (default '"') CSV Enclosure for use with fputscsv()
 * - string `$_eol`: (default '\n')       End-of-line character the csv
 *
 * @link https://github.com/friendsofcake/cakephp-csvview
 */
class CsvView extends View {

/**
 * CSV views are always located in the 'csv' sub directory for a
 * controllers views.
 *
 * @var string
 */
	public $subDir = 'csv';

/**
 * Whether or not to reset static variables in use
 *
 * @var boolean
 */
	protected $_resetStaticVariables = false;

/**
 * Constructor
 *
 * @param Controller $controller The currently active Controller.
 */
	public function __construct(Controller $controller = null) {
		parent::__construct($controller);
		if (isset($controller->response) && $controller->response instanceof CakeResponse) {
			$controller->response->type('csv');
		}
	}

/**
 * Render a CSV view.
 *
 * Uses the special '_serialize' parameter to convert a set of
 * view variables into a CSV response. Makes generating simple
 * CSV responses very easy. If you omit the '_serialize' parameter,
 * and use a normal view + layout as well.
 *
 * Also has support for specifying headers and footers in '_header'
 * and '_footer' variables, respectively.
 *
 * @param string $view The view being rendered.
 * @param string $layout The layout being rendered.
 * @return string The rendered view.
 * @throws Exception
 */
	public function render($view = null, $layout = null) {
		$this->_setupViewVars();

		if ($this->viewVars['_serialize'] === null) {
			if ($view !== false && $viewFileName = $this->_getViewFileName($view)) {
				$content = $this->_render($viewFileName);
				if (isset($this->Blocks)) {
					$this->Blocks->set('content', $content);
				}
				return $content;
			}

			throw new Exception("Unable to properly generate CSV");
		}

		$this->_renderRow($this->viewVars['_header']);
		$this->_renderContent();
		$this->_renderRow($this->viewVars['_footer']);
		$content = $this->_renderRow(false);
		if (isset($this->Blocks)) {
			$this->Blocks->set('content', $content);
		}
		$this->_resetStaticVariables = true;
		$this->_renderRow();
		return $content;
	}

/**
 * Setup defaults for CsvView view variables
 *
 * The following variables can be retrieved from '$this->viewVars'
 * for use in configuring this view:
 *
 * - array '_header': (default null)  A flat array of header column names
 * - array '_footer': (default null)  A flat array of footer column names
 * - array '_extract': (default null) An array of Hash-compatible 'paths' with
 *                                    matching 'sprintf' $format as follows:
 *
 *                                    $_extract = array(
 *                                      array($path, $format),
 *                                      array($path),
 *                                      $path,
 *                                    );
 *
 *                                    If a string or unspecified, the format
 *                                    default is '%s'.
 * - '_delimiter': (default ',')      CSV Delimiter, defaults to comma
 * - '_enclosure': (default '"')      CSV Enclosure for use with fputscsv()
 * - '_newline': (default '\n')       CSV Newline replacement for use with fputscsv()
 * - '_eol': (default '\n')           End-of-line character the csv
 * - '_bom': (default false)          Adds BOM (byte order mark) header
 * - '_setSeparator: (default false)  Adds sep=[_delimiter] in the first line
 *
 * @return void
 **/
	protected function _setupViewVars() {
		$required = array(
			'_extract',
			'_footer',
			'_header',
			'_serialize',
			'_delimiter',
			'_enclosure',
			'_newline',
			'_eol',
			'_null',
			'_bom',
			'_setSeparator'
		);
		foreach ($required as $viewVar) {
			if (!isset($this->viewVars[$viewVar])) {
				$this->viewVars[$viewVar] = null;
			}
		}

		if ($this->viewVars['_delimiter'] === null) {
			$this->viewVars['_delimiter'] = ',';
		}

		if ($this->viewVars['_enclosure'] === null) {
			$this->viewVars['_enclosure'] = '"';
		}

		if ($this->viewVars['_newline'] === null) {
			$this->viewVars['_newline'] = "\n";
		}

		if ($this->viewVars['_eol'] === null) {
			$this->viewVars['_eol'] = PHP_EOL;
		}

		if ($this->viewVars['_null'] === null) {
			$this->viewVars['_null'] = 'NULL';
		}

		if ($this->viewVars['_bom'] === null) {
			$this->viewVars['_bom'] = false;
		}

		if ($this->viewVars['_setSeparator'] === null) {
			$this->viewVars['_setSeparator'] = false;
		}

		if ($this->viewVars['_extract'] !== null) {
			$this->viewVars['_extract'] = (array)$this->viewVars['_extract'];
			foreach ($this->viewVars['_extract'] as $i => $extract) {
				$this->viewVars['_extract'][$i] = (array)$extract;
				if (count($this->viewVars['_extract'][$i]) !== 2) {
					$this->viewVars['_extract'][$i][1] = '%s';
				}
			}
		}

		if ($this->viewVars['_serialize'] !== null) {
			$this->viewVars['_serialize'] = (array)$this->viewVars['_serialize'];
		}
	}

/**
 * Renders the body of the data to the csv
 *
 * @return void
 **/
	protected function _renderContent() {
		$extract = $this->viewVars['_extract'];
		$serialize = $this->viewVars['_serialize'];

		foreach ($serialize as $viewVar) {
			foreach ($this->viewVars[$viewVar] as $_data) {
				if ($extract === null) {
					$this->_renderRow($_data);
				} else {
					$values = array();
					foreach ($extract as $e) {
						list($path, $format) = $e;
						$value = Hash::extract($_data, $path);
						if (isset($value[0])) {
							$values[] = sprintf($format, $value[0]);
						} else {
							$values[] = $this->viewVars['_null'];
						}
					}
					$this->_renderRow($values);
				}
			}
		}
	}

/**
 * Aggregates the rows into a single csv
 *
 * @param array $row Row data
 * @return CSV with all data to date
 **/
	protected function _renderRow($row = null) {
		static $csv = '';
		if ($this->_resetStaticVariables) {
			$csv = '';
			$this->_resetStaticVariables = false;
			return;
		}

		$csv .= (string)$this->_generateRow($row);
		return $csv;
	}

/**
 * Generates a single row in a csv from an array of
 * data by writing the array to a temporary file and
 * returning it's contents
 *
 * @param array $row Row data
 * @return mixed string with the row in csv-syntax, false on fputscv failure
 **/
	protected function _generateRow($row = null) {
		static $fp = false;
		if ($fp === false) {
			$fp = fopen('php://temp', 'r+');

			if ($this->viewVars['_bom']) {
				fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
			}
			if ($this->viewVars['_setSeparator']) {
				fwrite($fp, "sep=" . $this->viewVars['_delimiter'] . "\n");
			}
		} else {
			ftruncate($fp, 0);
		}

		if ($row === false || $row === null) {
			return '';
		}

		$delimiter = $this->viewVars['_delimiter'];
		$enclosure = $this->viewVars['_enclosure'];
		$newline = $this->viewVars['_newline'];

		$row = str_replace(array("\r\n", "\n", "\r"), $newline, $row);
		if (fputcsv($fp, $row, $delimiter, $enclosure) === false) {
			return false;
		}

		rewind($fp);

		$csv = '';
		while (($buffer = fgets($fp, 4096)) !== false) {
			$csv .= $buffer;
		}

		$eol = $this->viewVars['_eol'];
		if ($eol !== "\n") {
			$csv = str_replace("\n", $eol, $csv);
		}

		return $csv;
	}

}
