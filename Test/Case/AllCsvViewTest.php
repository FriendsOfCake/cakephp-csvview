<?php
/**
 * All CsvView plugin tests
 *
 * @package       CsvView.Test.Case
 */
class AllCsvViewTest extends CakeTestCase {

/**
 * Suite define the tests for this suite
 *
 * @return void
 */
	public static function suite() {
		$suite = new CakeTestSuite('All CsvView test');

		$path = CakePlugin::path('CsvView') . 'Test' . DS . 'Case' . DS;
		$suite->addTestDirectoryRecursive($path);

		return $suite;
	}
}
