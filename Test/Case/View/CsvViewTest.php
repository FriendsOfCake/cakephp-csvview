<?php
/**
 * PHP 5
 *
 * CakePHP(tm) Tests <http://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://book.cakephp.org/2.0/en/development/testing.html CakePHP(tm) Tests
 * @since         CakePHP(tm) v 2.1.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('CsvView', 'CsvView.View');

/**
 * CsvViewTest
 */
class CsvViewTest extends CakeTestCase {

/**
 * testRenderWithoutView method
 *
 * @return void
 */
	public function testRenderWithoutView() {
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$Controller = new Controller($Request, $Response);
		$data = array(array('user', 'fake', 'list', 'item1', 'item2'));
		$Controller->set(array('data' => $data, '_serialize' => 'data'));
		$View = new CsvView($Controller);
		$output = $View->render(false);

		$this->assertSame('user,fake,list,item1,item2' . PHP_EOL, $output);
		$this->assertSame('text/csv', $Response->type());
	}

/**
 * Test render with an array in _serialize
 *
 * @return void
 */
	public function testRenderWithoutViewMultiple() {
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$Controller = new Controller($Request, $Response);
		$data = array(
			array('a', 'b', 'c'),
			array(1, 2, 3),
			array('you', 'and', 'me'),
		);
		$_serialize = 'data';
		$Controller->set('data', $data);
		$Controller->set(array('_serialize' => 'data'));
		$View = new CsvView($Controller);
		$output = $View->render(false);

		$this->assertSame('a,b,c' . PHP_EOL . '1,2,3' . PHP_EOL . 'you,and,me' . PHP_EOL, $output);
		$this->assertSame('text/csv', $Response->type());
	}

/**
 * Test render with a custom EOL char.
 *
 * @return void
 */
	public function testRenderWithCustomEol() {
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$Controller = new Controller($Request, $Response);
		$data = array(
			array('a', 'b', 'c'),
			array(1, 2, 3),
			array('you', 'and', 'me'),
		);
		$_serialize = 'data';
		$Controller->set('data', $data);
		$Controller->set(array('_serialize' => 'data'));
		$View = new CsvView($Controller);
		$View->viewVars['_eol'] = '~';
		$output = $View->render(false);

		$this->assertSame('a,b,c~1,2,3~you,and,me~', $output);
		$this->assertSame('text/csv', $Response->type());
	}

/**
 * testRenderWithView method
 *
 * @return void
 */
	public function testRenderWithView() {
		App::build(array(
			'View' => realpath(dirname(__FILE__) . DS . '..' . DS . '..' . DS . 'test_app' . DS . 'View' . DS) . DS,
		));
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$Controller = new Controller($Request, $Response);
		$Controller->name = $Controller->viewPath = 'Posts';

		$data = array(
			array('a', 'b', 'c'),
			array(1, 2, 3),
			array('you', 'and', 'me'),
		);

		$Controller->set('user', $data);
		$View = new CsvView($Controller);
		$output = $View->render('index');

		$this->assertSame('TEST OUTPUT' . PHP_EOL, $output);
		$this->assertSame('text/csv', $Response->type());
	}

/**
 * CsvViewTest::testRenderViaExtract()
 *
 * @return void
 */
	public function testRenderViaExtract() {
		App::build(array(
			'View' => realpath(dirname(__FILE__) . DS . '..' . DS . '..' . DS . 'test_app' . DS . 'View' . DS) . DS,
		));
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$Controller = new Controller($Request, $Response);
		$Controller->name = $Controller->viewPath = 'Posts';

		$data = array(
			array(
				'User' => array(
					'username' => 'jose'
				),
				'Item' => array(
					'name' => 'beach',
				)
			),
			array(
				'User' => array(
					'username' => 'drew'
				),
				'Item' => array(
					'name' => 'ball',
				)
			)
		);
		$_extract = array('User.username', 'Item.name');
		$Controller->set(array('user' => $data, '_extract' => $_extract));
		$Controller->set(array('_serialize' => 'user'));
		$View = new CsvView($Controller);
		$output = $View->render(false);

		$this->assertSame('jose,beach' . PHP_EOL . 'drew,ball' . PHP_EOL, $output);
		$this->assertSame('text/csv', $Response->type());
	}

/**
 * CsvViewTest::testRenderViaExtractOptionalField()
 *
 * @return void
 */
	public function testRenderViaExtractOptionalField() {
		App::build(array(
			'View' => realpath(dirname(__FILE__) . DS . '..' . DS . '..' . DS . 'test_app' . DS . 'View' . DS) . DS,
		));
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$Controller = new Controller($Request, $Response);
		$Controller->name = $Controller->viewPath = 'Posts';

		$data = array(
			array(
				'User' => array(
					'username' => 'jose'
				),
				'Item' => array(
					'type' => 'beach',
				)
			),
			array(
				'User' => array(
					'username' => 'drew'
				),
				'Item' => array(
					'name' => 'ball',
					'type' => 'fun'
				)
			)
		);
		$_extract = array('User.username', 'Item.name', 'Item.type');
		$Controller->set(array('user' => $data, '_extract' => $_extract));
		$Controller->set(array('_serialize' => 'user'));
		$View = new CsvView($Controller);
		$output = $View->render(false);

		$this->assertSame('jose,NULL,beach' . PHP_EOL . 'drew,ball,fun' . PHP_EOL, $output);
		$this->assertSame('text/csv', $Response->type());
	}

/**
 * CsvViewTest::testRenderWithSpecialCharacters()
 *
 * @return void
 */
	public function testRenderWithSpecialCharacters() {
		App::build(array(
			'View' => realpath(dirname(__FILE__) . DS . '..' . DS . '..' . DS . 'test_app' . DS . 'View' . DS) . DS,
		));
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$Controller = new Controller($Request, $Response);
		$Controller->name = $Controller->viewPath = 'Posts';

		$data = array(
			array(
				'User' => array(
					'username' => 'José'
				),
				'Item' => array(
					'type' => 'äöü',
				)
			),
			array(
				'User' => array(
					'username' => 'Including,Comma'
				),
				'Item' => array(
					'name' => 'Containing"char',
					'type' => 'Containing\'char'
				)
			),
			array(
				'User' => array(
					'username' => 'Some Space'
				),
				'Item' => array(
					'name' => "A\nNewline",
					'type' => "A\tTab"
				)
			)
		);
		$_extract = array('User.username', 'Item.name', 'Item.type');
		$Controller->set(array('user' => $data, '_extract' => $_extract));
		$Controller->set(array('_serialize' => 'user'));
		$View = new CsvView($Controller);
		$output = $View->render(false);

		$expected = <<<CSV
José,NULL,äöü
"Including,Comma","Containing""char",Containing'char
"Some Space","A
Newline","A\tTab"

CSV;
		$this->assertTextEquals($expected, $output);
		$this->assertSame('text/csv', $Response->type());
	}

}
