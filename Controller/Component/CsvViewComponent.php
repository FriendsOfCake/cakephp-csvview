<?php
App::uses('Component', 'Controller');

/**
 * A component to help export CSV's using CsvView.php
 *
 * @link https://github.com/friendsofcake/cakephp-csvview
 */
class CsvViewComponent extends Component {

/**
 * The calling Controller
 *
 * @var Controller
 */
	public $controller;

/**
 * Starts up ExportComponent for use in the controller
 *
 * @param Controller $controller A reference to the instantiating controller object
 * @return void
 */
	public function startup(Controller $controller) {
		$this->controller = $controller;
	}

/**
 * Prepares an array of all unique Hash::extract() compatible paths,
 * from the results of a model find('all') call.
 *
 * @param array $data the results of a model find('all') call.
 * @param array $excludePaths [description]
 * @return array an array of Hash::extract() compatible paths
 */
	public function prepareExtractFromFindResults($data, $excludePaths = array()) {
		$extract = array();
		foreach ($data as $numericKey => $row) {
			$this->_addUniquePaths($extract, $row, $excludePaths);
		}
		return $extract;
	}

/**
 * Recursively searches a single row from the results of a model find('all') and
 * adds all unique Hash::extract() compatible paths to $extract
 *
 * @param array &$extract reference to the array containing all unique paths
 * @param array $dataRow a single row from the results of a model find('all')
 * @param array $excludePaths an array of Hash::extract() compatible paths to be excluded
 * @param string $parentPath Hash::extract() compatible string of all paths up until this point (for deep nested arrays)
 * @return void
 */
	protected function _addUniquePaths(&$extract, $dataRow, $excludePaths, $parentPath = '') {
		foreach ($dataRow as $key => $value) {
			$fullPath = $key;
			if ($parentPath !== '') {
				$fullPath = $parentPath . '.' . $key;
			}

			if (is_numeric($key)) {
				continue;
			}

			if (is_array($value)) {
				$this->_addUniquePaths($extract, $value, $excludePaths, $fullPath);
			} elseif (array_search($fullPath, $extract) === false && array_search($fullPath, $excludePaths) === false) {
				$extract[] = $fullPath;
			}
		}
	}

/**
 * Prepare an array of user-friendly column titles based on an array of Hash::extract() compatible paths
 *
 * @param array $extract an array of Hash::extract() compatible paths
 * @param array $customHeaders array of 'Hash.Path' => 'Custom Title' pairs, to override default generated titles
 * @param array $options an array of options:
 *    'includeClassname' => if true, the class name will be included in the default generated titles,
 *    'humanReadable' => if true, underscores in variable names will be replaced by spaces,
 *    and the first character of each word will be uppercased 
 * @return array an array of user-friendly headers, matching the passed in $extract array
 */
	public function prepareHeaderFromExtract($extract, $customHeaders = array(), $options = array()) {
		$defaults = array('includeClassname' => true, 'humanReadable' => true);
		$options += $defaults;

		$header = array();
		foreach ($extract as $fullPath) {
			if (!empty($customHeaders[$fullPath])) {
				$header[] = $customHeaders[$fullPath];
			} else {
				$pathParts = explode('.', $fullPath);

				$column = $pathParts[count($pathParts) - 1];

				if ($options['humanReadable']) {
					$column = str_replace('_', ' ', $column);
					$column = ucwords($column);
				}

				if ($options['includeClassname']) {
					$model = $pathParts[count($pathParts) - 2];

					if ($options['humanReadable']) {
						$model = preg_replace('/(?<! )(?<!^)[A-Z]/', ' $0', $model);
						$header[] = $model . ' ' . $column;
					} else {
						$header[] = $model . '.' . $column;
					}
				} else {
					$header[] = $column;
				}
			}
		}

		return $header;
	}

/**
 * Quickly export the results of a model find('all') call with a single line of code.
 *
 * @param array $data the results of a model find('all') call.
 * @param array $excludePaths an array of Hash::extract() compatible paths to be excluded
 * @param array $customHeaders array of 'Hash.Path' => 'Custom Title' pairs, to override default generated titles
 * @param array $options an array of options:
 *    'includeHeader' => if true, a header will be included in the exported CSV,
 *    'includeClassname' => if true, the class name will be included in the default generated titles,
 *    'humanReadable' => if true, underscores in variable names will be replaced by spaces,
 *    and the first character of each word will be uppercased
 * @return void
 */
	public function quickExport($data, $excludePaths = array(), $customHeaders = array(), $options = array()) {
		// Maintain backwards compatiblity if someone passes a boolean includeHeader instead of an options array
		if (!is_array($options)) {
			$options = array('includeHeader' => (bool)$options);
		}

		$defaults = array('includeHeader' => true, 'includeClassname' => true, 'humanReadable' => true);
		$options += $defaults;

		$_serialize = 'data';
		$_extract = $this->prepareExtractFromFindResults($data, $excludePaths);
		if ($options['includeHeader']) {
			$_header = $this->prepareHeaderFromExtract($_extract, $customHeaders, $options);
		}
		$this->controller->viewClass = 'CsvView.Csv';
		$this->controller->set(compact('data', '_serialize', '_header', '_extract'));
	}
}
