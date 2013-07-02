<?php
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('CsvViewComponent', 'CsvView.Controller/Component');

// A fake controller to test against
class TestCsvViewController extends Controller {
	public $paginate = null;
}

class CsvViewComponentTest extends CakeTestCase {
	public $CsvViewComponent = null;
	public $Controller = null;

	// Example output of a find('all') call with nested belongsTo's
	private $exampleNested = array(
		array(
			'City' => array(
				'name' => 'Sydney',
				'population' => '4.6m',
			),
			'State' => array(
				'name' => 'NSW',
				'excluded_column' => 'this will be excluded in the test',
				'Country' => array(
					'name' => 'Australia',
					// 'Continent' key left out on purpose - to make sure it's still included from the second row.
					'Languages' => array( // As a nested hasMany, these should be ignored in the export.
						array('name' => 'English'),
						array('name' => 'French')
					)
				)
			)
		),
		array(
			'City' => array(
				'name' => 'Melbourne',
				'population' => '4.1m'
			),
			'State' => array(
				'name' => 'Victoria',
				'Country' => array(
					'name' => 'Australia',
					'Continent' => array('name' => 'Australasia')
				)
			)
		),
	);

	private $exampleExtract = array(
		'City.name',
		'City.population',
		'State.name',
		'State.Country.name',
		'State.Country.Continent.name',
	);

	private $exampleHeader = array(
		'City Name',
		'Number of People', // overriding City.population
		'State Name',
		'Country Name',
		'Continent Name',
	);

	private $exampleExtract2 = array(
		'City.population',
		'State.name',
		'State.Country.multi_word_column',
		'State.Country.MultiWordModel.column',
	);

	private $exampleHeader2 = array(
		'City Population',
		'My Custom Title', // overriding State.name
		'Country Multi Word Column',
		'Multi Word Model Column',
	);

	public function setUp() {
		parent::setUp();
		// Setup our component and fake test controller
		$Collection = new ComponentCollection();
		$this->CsvViewComponent = new CsvViewComponent($Collection);
		$CakeRequest = new CakeRequest();
		$CakeResponse = new CakeResponse();
		$this->Controller = new TestCsvViewController($CakeRequest, $CakeResponse);
		$this->CsvViewComponent->startup($this->Controller);
	}

	public function testPrepareExtractFromFindResults() {
		$excludePaths = array('State.excluded_column');
		$extract = $this->CsvViewComponent->prepareExtractFromFindResults($this->exampleNested, $excludePaths);
		$this->assertEqual($this->exampleExtract, $extract);
	}

	public function testPrepareHeaderFromExtract() {
		$customHeaders = array('State.name' => 'My Custom Title');
		$header = $this->CsvViewComponent->prepareHeaderFromExtract($this->exampleExtract2, $customHeaders);
		$this->assertEqual($this->exampleHeader2, $header);
	}

	public function testQuickExport() {
		$excludePaths = array('State.excluded_column');
		$customHeaders = array('City.population' => 'Number of People');
		$header = $this->CsvViewComponent->quickExport($this->exampleNested, $excludePaths, $customHeaders);

		$this->assertEqual($this->exampleNested, $this->Controller->viewVars['data']);
		$this->assertEqual('data', $this->Controller->viewVars['_serialize']);
		$this->assertEqual($this->exampleExtract, $this->Controller->viewVars['_extract']);
		$this->assertEqual($this->exampleHeader, $this->Controller->viewVars['_header']);
		$this->assertEqual('CsvView.Csv', $this->Controller->viewClass);
	}

	public function testQuickExportNoHeaders() {
		$excludePaths = array('State.excluded_column');
		$customHeaders = array('City.population' => 'Number of People');
		$header = $this->CsvViewComponent->quickExport($this->exampleNested, $excludePaths, $customHeaders, false);

		$this->assertEqual($this->exampleNested, $this->Controller->viewVars['data']);
		$this->assertEqual('data', $this->Controller->viewVars['_serialize']);
		$this->assertEqual($this->exampleExtract, $this->Controller->viewVars['_extract']);
		$hasHeader = (empty($this->Controller->viewVars['_header']))? false : true;
		$this->assertFalse($hasHeader);
		$this->assertEqual('CsvView.Csv', $this->Controller->viewClass);
	}

	public function tearDown() {
		parent::tearDown();
		// Clean up after we're done
		unset($this->CsvViewComponent);
		unset($this->Controller);
	}
}