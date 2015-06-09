<?php
namespace CsvView\Test\TestCase\View;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\TestSuite\TestCase;
use CsvView\Controller\Component\CsvViewComponent;

// A fake controller to test against
class TestCsvViewController extends Controller
{

    public $paginate = null;
}

class CsvViewComponentTest extends TestCase
{

    public $CsvViewComponent = null;

    public $Controller = null;

    /**
     * Example output of a find('all') call with:
     * a) nested belongsTo's and hasMany's
     * b) inconsistent keys across rows
     *
     * @var array
     */
    protected $_exampleNested = [
        [
            'City' => [
                'name' => 'Sydney',
                'population' => '4.6m',
            ],
            'State' => [
                'name' => 'NSW',
                'excluded_column' => 'this will be excluded in the test',
                'Country' => [
                    'name' => 'Australia',
                    // 'Continent' key left out on purpose - to make sure it's still included from the second row.
                    'Languages' => [ // As a nested hasMany, these should be ignored in the export.
                        ['name' => 'English'],
                        ['name' => 'French']
                    ]
                ]
            ]
        ],
        [
            'City' => [
                'name' => 'Melbourne',
                'population' => '4.1m'
            ],
            'State' => [
                'name' => 'Victoria',
                'Country' => [
                    'name' => 'Australia',
                    'Continent' => ['name' => 'Australasia']
                    // 'Languages' key left out on purpose
                ]
            ]
        ],
    ];

    /**
     * Expected output of prepareExtractFromFindResults for $_exampleNested array above
     *
     * @var array
     */
    protected $_exampleExtract = [
        'City.name',
        'City.population',
        'State.name',
        'State.Country.name',
        'State.Country.Continent.name',
    ];

    /**
     * Expected output of prepareHeaderFromExtract for $_exampleExtract array above
     *
     * @var array
     */
    protected $_exampleHeader = [
        'City Name',
        'Number of People', // overriding City.population
        'State Name',
        'Country Name',
        'Continent Name',
    ];

    /**
     * Expected output of prepareHeaderFromExtract for $_exampleExtract array above with includeClassname=false
     *
     * @var array
     */
    protected $_exampleHeaderIncludeClassnameFalse = [
        'Name',
        'Number of People', // overriding City.population
        'Name',
        'Name',
        'Name',
    ];

    /**
     * Expected output of prepareHeaderFromExtract for $_exampleExtract array above with humanReadable=false
     *
     * @var array
     */
    protected $_exampleHeaderHumanReadableFalse = [
        'City.name',
        'Number of People', // overriding City.population
        'State.name',
        'Country.name',
        'Continent.name',
    ];

    /**
     * Expected output of prepareHeaderFromExtract for $_exampleExtract array above with includeClassname=false
     * and humanReadable=false
     *
     * @var array
     */
    protected $_exampleHeaderIncludeClassnameFalseHumanReadableFalse = [
        'name',
        'Number of People', // overriding City.population
        'name',
        'name',
        'name',
    ];

    /**
     * Example $_extract array, with multi word columns / models included
     *
     * @var array
     */
    protected $_exampleExtract2 = [
        'City.population',
        'State.name',
        'State.Country.multi_word_column',
        'State.Country.MultiWordModel.column',
    ];

    /**
     * Expected output of prepareHeaderFromExtract for $_exampleExtract2 array above
     *
     * @var array
     */
    protected $_exampleHeader2 = [
        'City Population',
        'My Custom Title', // overriding State.name
        'Country Multi Word Column',
        'Multi Word Model Column',
    ];

    /**
     * Expected output of prepareHeaderFromExtract for $_exampleExtract2 array above with includeClassname=false
     *
     * @var array
     */
    protected $_exampleHeader2IncludeClassnameFalse = [
        'Population',
        'My Custom Title', // overriding State.name
        'Multi Word Column',
        'Column',
    ];

    /**
     * Expected output of prepareHeaderFromExtract for $_exampleExtract2 array above with humanReadable=false
     *
     * @var array
     */
    protected $_exampleHeader2HumanReadableFalse = [
        'City.population',
        'My Custom Title', // overriding State.name
        'Country.multi_word_column',
        'MultiWordModel.column',
    ];

    /**
     * Expected output of prepareHeaderFromExtract for $_exampleExtract2 array above with includeClassname=false
     * and humanReadable=false
     *
     * @var array
     */
    protected $_exampleHeader2IncludeClassnameFalseHumanReadableFalse = [
        'population',
        'My Custom Title', // overriding State.name
        'multi_word_column',
        'column',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        // Setup our component and fake test controller
        $this->Controller = new TestCsvViewController(new Request(), new Response());
        $ComponentRegistry = new ComponentRegistry($this->Controller);
        $this->CsvViewComponent = new CsvViewComponent($ComponentRegistry);
    }

    /**
     * testPrepareExtractFromFindResults method
     *
     * @return void
     */
    public function testPrepareExtractFromFindResults()
    {
        $excludePaths = ['State.excluded_column'];
        $extract = $this->CsvViewComponent->prepareExtractFromFindResults($this->_exampleNested, $excludePaths);
        $this->assertEquals($this->_exampleExtract, $extract);
    }

    /**
     * testPrepareHeaderFromExtract method
     *
     * @return void
     */
    public function testPrepareHeaderFromExtract()
    {
        $customHeaders = ['State.name' => 'My Custom Title'];
        $header = $this->CsvViewComponent->prepareHeaderFromExtract($this->_exampleExtract2, $customHeaders);
        $this->assertEquals($this->_exampleHeader2, $header);
    }

    /**
     * testPrepareHeaderFromExtractWithOptions method
     *
     * @return void
     */
    public function testPrepareHeaderFromExtractWithOptions()
    {
        $customHeaders = ['State.name' => 'My Custom Title'];

        $header = $this->CsvViewComponent->prepareHeaderFromExtract($this->_exampleExtract2, $customHeaders, ['includeClassname' => false]);
        $this->assertEquals($this->_exampleHeader2IncludeClassnameFalse, $header);

        $header = $this->CsvViewComponent->prepareHeaderFromExtract($this->_exampleExtract2, $customHeaders, ['humanReadable' => false]);
        $this->assertEquals($this->_exampleHeader2HumanReadableFalse, $header);

        $header = $this->CsvViewComponent->prepareHeaderFromExtract($this->_exampleExtract2, $customHeaders, ['includeClassname' => false, 'humanReadable' => false]);
        $this->assertEquals($this->_exampleHeader2IncludeClassnameFalseHumanReadableFalse, $header);
    }

    /**
     * testQuickExport method
     *
     * @return void
     */
    public function testQuickExport()
    {
        $excludePaths = ['State.excluded_column'];
        $customHeaders = ['City.population' => 'Number of People'];
        $header = $this->CsvViewComponent->quickExport($this->_exampleNested, $excludePaths, $customHeaders);

        $this->assertEquals($this->_exampleNested, $this->Controller->viewVars['data']);
        $this->assertEquals('data', $this->Controller->viewVars['_serialize']);
        $this->assertEquals($this->_exampleExtract, $this->Controller->viewVars['_extract']);
        $this->assertEquals($this->_exampleHeader, $this->Controller->viewVars['_header']);
        $this->assertEquals('CsvView.Csv', $this->Controller->viewClass);
    }

    /**
     * testQuickExportNoHeaders method
     *
     * @return void
     */
    public function testQuickExportNoHeaders()
    {
        $excludePaths = ['State.excluded_column'];
        $customHeaders = ['City.population' => 'Number of People'];
        $header = $this->CsvViewComponent->quickExport($this->_exampleNested, $excludePaths, $customHeaders, ['includeHeader' => false]);

        $this->assertEquals($this->_exampleNested, $this->Controller->viewVars['data']);
        $this->assertEquals('data', $this->Controller->viewVars['_serialize']);
        $this->assertEquals($this->_exampleExtract, $this->Controller->viewVars['_extract']);
        $hasHeader = (empty($this->Controller->viewVars['_header'])) ? false : true;
        $this->assertFalse($hasHeader);
        $this->assertEquals('CsvView.Csv', $this->Controller->viewClass);
    }

    /**
     * testQuickExportNoHeadersBC method (tests backwards compatibility)
     *
     * @return void
     */
    public function testQuickExportNoHeadersBC()
    {
        $excludePaths = ['State.excluded_column'];
        $customHeaders = ['City.population' => 'Number of People'];
        $header = $this->CsvViewComponent->quickExport($this->_exampleNested, $excludePaths, $customHeaders, false);

        $this->assertEquals($this->_exampleNested, $this->Controller->viewVars['data']);
        $this->assertEquals('data', $this->Controller->viewVars['_serialize']);
        $this->assertEquals($this->_exampleExtract, $this->Controller->viewVars['_extract']);
        $hasHeader = (empty($this->Controller->viewVars['_header'])) ? false : true;
        $this->assertFalse($hasHeader);
        $this->assertEquals('CsvView.Csv', $this->Controller->viewClass);
    }

    /**
     * testQuickExportNoHeadersWithOptions method
     *
     * @return void
     */
    public function testQuickExportWithOptions()
    {
        $excludePaths = ['State.excluded_column'];
        $customHeaders = ['City.population' => 'Number of People'];

        $header = $this->CsvViewComponent->quickExport($this->_exampleNested, $excludePaths, $customHeaders, ['includeClassname' => false]);
        $this->assertEquals($this->_exampleHeaderIncludeClassnameFalse, $this->Controller->viewVars['_header']);

        $header = $this->CsvViewComponent->quickExport($this->_exampleNested, $excludePaths, $customHeaders, ['humanReadable' => false]);
        $this->assertEquals($this->_exampleHeaderHumanReadableFalse, $this->Controller->viewVars['_header']);

        $header = $this->CsvViewComponent->quickExport($this->_exampleNested, $excludePaths, $customHeaders, ['includeClassname' => false, 'humanReadable' => false]);
        $this->assertEquals($this->_exampleHeaderIncludeClassnameFalseHumanReadableFalse, $this->Controller->viewVars['_header']);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        // Clean up after we're done
        unset($this->CsvViewComponent);
        unset($this->Controller);
    }
}
