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

/**
 * Example output of a find('all') call with:
 * a) nested belongsTo's and hasMany's
 * b) inconsistent keys across rows
 *
 * @var array
 */
	protected $_exampleNested = array(
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
					// 'Languages' key left out on purpose
				)
			)
		),
	);

/**
 * Expected output of prepareExtractFromFindResults for $_exampleNested array above
 * @var array
 */
	protected $_exampleExtract = array(
		'City.name',
		'City.population',
		'State.name',
		'State.Country.name',
		'State.Country.Continent.name',
	);

/**
 * Expected output of prepareHeaderFromExtract for $_exampleExtract array above
 * @var array
 */
	protected $_exampleHeader = array(
		'City Name',
		'Number of People', // overriding City.population
		'State Name',
		'Country Name',
		'Continent Name',
	);

/**
 * Example $_extract array, with multi word columns / models included
 * @var array
 */
	protected $_exampleExtract2 = array(
		'City.population',
		'State.name',
		'State.Country.multi_word_column',
		'State.Country.MultiWordModel.column',
	);

/**
 * Expected output of prepareHeaderFromExtract for $_exampleExtract2 array above
 * @var array
 */
	protected $__exampleHeader2 = array(
		'City Population',
		'My Custom Title', // overriding State.name
		'Country Multi Word Column',
		'Multi Word Model Column',
	);

/**
 * setUp method
 *
 * @return void
 */
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

/**
 * testPrepareExtractFromFindResults method
 *
 * @return void
 */
	public function testPrepareExtractFromFindResults() {
		$excludePaths = array('State.excluded_column');
		$extract = $this->CsvViewComponent->prepareExtractFromFindResults($this->_exampleNested, $excludePaths);
		$this->assertEqual($this->_exampleExtract, $extract);
	}

/**
 * testPrepareHeaderFromExtract method
 *
 * @return void
 */
	public function testPrepareHeaderFromExtract() {
		$customHeaders = array('State.name' => 'My Custom Title');
		$header = $this->CsvViewComponent->prepareHeaderFromExtract($this->_exampleExtract2, $customHeaders);
		$this->assertEqual($this->__exampleHeader2, $header);
	}

/**
 * testQuickExport method
 *
 * @return void
 */
	public function testQuickExport() {
		$excludePaths = array('State.excluded_column');
		$customHeaders = array('City.population' => 'Number of People');
		$header = $this->CsvViewComponent->quickExport($this->_exampleNested, $excludePaths, $customHeaders);

		$this->assertEqual($this->_exampleNested, $this->Controller->viewVars['data']);
		$this->assertEqual('data', $this->Controller->viewVars['_serialize']);
		$this->assertEqual($this->_exampleExtract, $this->Controller->viewVars['_extract']);
		$this->assertEqual($this->_exampleHeader, $this->Controller->viewVars['_header']);
		$this->assertEqual('CsvView.Csv', $this->Controller->viewClass);
	}

/**
 * testQuickExportNoHeaders method
 *
 * @return void
 */
	public function testQuickExportNoHeaders() {
		$excludePaths = array('State.excluded_column');
		$customHeaders = array('City.population' => 'Number of People');
		$header = $this->CsvViewComponent->quickExport($this->_exampleNested, $excludePaths, $customHeaders, false);

		$this->assertEqual($this->_exampleNested, $this->Controller->viewVars['data']);
		$this->assertEqual('data', $this->Controller->viewVars['_serialize']);
		$this->assertEqual($this->_exampleExtract, $this->Controller->viewVars['_extract']);
		$hasHeader = (empty($this->Controller->viewVars['_header'])) ? false : true;
		$this->assertFalse($hasHeader);
		$this->assertEqual('CsvView.Csv', $this->Controller->viewClass);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		// Clean up after we're done
		unset($this->CsvViewComponent);
		unset($this->Controller);
	}
}