<?php
App::uses('Component', 'Controller');
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

	function prepareExtractFromFindResults($data, $excludePaths = array()) {
		$extract = array();
		// Go over every row in case some have keys missing
		foreach($data as $numericKey => $row){
			$this->addUniquePaths($row, $excludePaths, $extract);
		}
		return $extract;
	}

	public function addUniquePaths($dataRow, $excludePaths, &$extract, $parentPath = ''){
		foreach($dataRow as $key => $value){
			$fullPath = ($parentPath !== '')? $parentPath.'.'.$key : $key;
			if(is_numeric($key)){
				// ignore it - it's a nested hasMany association
			} else {
				if (is_array($value)){
								$this->addUniquePaths($value, $excludePaths, $extract, $fullPath);
							} else {
								if(array_search($fullPath, $extract) === false && array_search($fullPath, $excludePaths) === false){
									$extract[] = $fullPath;
								}
							}
			}
		}
	}

	public function prepareHeaderFromExtract($extract, $customHeaders = array()){
		$header = array();
		foreach($extract as $fullPath){
			if(!empty($customHeaders[$fullPath])){
				$header[] = $customHeaders[$fullPath];
			} else {
				$pathParts = explode('.', $fullPath);
				$model = $pathParts[count($pathParts)-2];
				$model = preg_replace('/(?<! )(?<!^)[A-Z]/',' $0', $model); // Add space before capitals, excluding first capital - eg. MyModel becomes My Model

				$column = $pathParts[count($pathParts)-1];
				$column = str_replace('_', ' ', $column);
				$column = ucwords($column);

				$header[] = $model.' '.$column;
			}
		}

		return $header;
	}

	public function quickExport($data, $excludePaths = array(), $customHeaders = array(), $includeHeader = true){
		$_serialize = 'data';
		$_extract = $this->prepareExtractFromFindResults($data, $excludePaths);
		if($includeHeader){
			$_header = $this->prepareHeaderFromExtract($_extract, $customHeaders);
		}
		$this->controller->viewClass = 'CsvView.Csv';
		$this->controller->set(compact('data' ,'_serialize', '_header', '_extract'));
	}
}