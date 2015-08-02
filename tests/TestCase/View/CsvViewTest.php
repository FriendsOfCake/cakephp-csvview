<?php
namespace CsvView\Test\TestCase\View;

use Cake\Controller\Controller;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * CsvViewTest
 */
class CsvViewTest extends TestCase
{
    public $fixtures = ['core.Articles', 'core.Authors'];

    /**
     * testRenderWithoutView method
     *
     * @return void
     */
    public function testRenderWithoutView()
    {
        $Request = new Request();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);
        $data = [['user', 'fake', 'list', 'item1', 'item2']];
        $Controller->set(['data' => $data, '_serialize' => 'data']);
        $Controller->viewClass = 'CsvView.Csv';
        $View = $Controller->createView();
        $output = $View->render(false);

        $this->assertSame('user,fake,list,item1,item2' . PHP_EOL, $output);
        $this->assertSame('text/csv', $Response->type());
    }

    /**
     * Test render with an array in _serialize
     *
     * @return void
     */
    public function testRenderWithoutViewMultiple()
    {
        $Request = new Request();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);
        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['you', 'and', 'me'],
        ];
        $_serialize = 'data';
        $Controller->set('data', $data);
        $Controller->set(['_serialize' => 'data']);
        $Controller->viewClass = 'CsvView.Csv';
        $View = $Controller->createView();
        $output = $View->render(false);

        $this->assertSame('a,b,c' . PHP_EOL . '1,2,3' . PHP_EOL . 'you,and,me' . PHP_EOL, $output);
        $this->assertSame('text/csv', $Response->type());
    }

    /**
     * Test render with a custom EOL char.
     *
     * @return void
     */
    public function testRenderWithCustomEol()
    {
        $Request = new Request();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);
        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['you', 'and', 'me'],
        ];
        $_serialize = 'data';
        $Controller->set('data', $data);
        $Controller->set(['_serialize' => 'data']);
        $Controller->viewClass = 'CsvView.Csv';
        $View = $Controller->createView();
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
    public function testRenderWithView()
    {
        $Request = new Request();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);
        $Controller->name = $Controller->viewPath = 'Posts';

        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['you', 'and', 'me'],
        ];

        $Controller->set('user', $data);
        $Controller->viewClass = 'CsvView.Csv';
        $View = $Controller->createView();
        $output = $View->render('index');

        $this->assertSame('TEST OUTPUT' . PHP_EOL, $output);
        $this->assertSame('text/csv', $Response->type());
    }

    /**
     * CsvViewTest::testRenderViaExtract()
     *
     * @return void
     */
    public function testRenderViaExtract()
    {
        $Request = new Request();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);
        $Controller->name = $Controller->viewPath = 'Posts';

        $data = [
            [
                'User' => [
                    'username' => 'jose'
                ],
                'Item' => [
                    'name' => 'beach',
                ]
            ],
            [
                'User' => [
                    'username' => 'drew'
                ],
                'Item' => [
                    'name' => 'ball',
                ]
            ]
        ];
        $_extract = ['User.username', 'Item.name'];
        $Controller->set(['user' => $data, '_extract' => $_extract]);
        $Controller->set(['_serialize' => 'user']);
        $Controller->viewClass = 'CsvView.Csv';
        $View = $Controller->createView();
        $output = $View->render(false);

        $this->assertSame('jose,beach' . PHP_EOL . 'drew,ball' . PHP_EOL, $output);
        $this->assertSame('text/csv', $Response->type());
    }

    /**
     * CsvViewTest::testRenderViaExtractOptionalField()
     *
     * @return void
     */
    public function testRenderViaExtractOptionalField()
    {
        $Request = new Request();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);
        $Controller->name = $Controller->viewPath = 'Posts';

        $data = [
            [
                'User' => [
                    'username' => 'jose'
                ],
                'Item' => [
                    'type' => 'beach',
                ]
            ],
            [
                'User' => [
                    'username' => 'drew'
                ],
                'Item' => [
                    'name' => 'ball',
                    'type' => 'fun'
                ]
            ]
        ];
        $_extract = ['User.username', 'Item.name', 'Item.type'];
        $Controller->set(['user' => $data, '_extract' => $_extract]);
        $Controller->set(['_serialize' => 'user']);
        $Controller->viewClass = 'CsvView.Csv';
        $View = $Controller->createView();
        $output = $View->render(false);

        $this->assertSame('jose,NULL,beach' . PHP_EOL . 'drew,ball,fun' . PHP_EOL, $output);
        $this->assertSame('text/csv', $Response->type());
    }

    /**
     * CsvViewTest::testRenderWithSpecialCharacters()
     *
     * @return void
     */
    public function testRenderWithSpecialCharacters()
    {
        $Request = new Request();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);
        $Controller->name = $Controller->viewPath = 'Posts';

        $data = [
            [
                'User' => [
                    'username' => 'José'
                ],
                'Item' => [
                    'type' => 'äöü',
                ]
            ],
            [
                'User' => [
                    'username' => 'Including,Comma'
                ],
                'Item' => [
                    'name' => 'Containing"char',
                    'type' => 'Containing\'char'
                ]
            ],
            [
                'User' => [
                    'username' => 'Some Space'
                ],
                'Item' => [
                    'name' => "A\nNewline",
                    'type' => "A\tTab"
                ]
            ]
        ];
        $_extract = ['User.username', 'Item.name', 'Item.type'];
        $Controller->set(['user' => $data, '_extract' => $_extract]);
        $Controller->set(['_serialize' => 'user']);
        $Controller->viewClass = 'CsvView.Csv';
        $View = $Controller->createView();
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

    /**
     * [testPassingQueryAsData description]
     *
     * @return void
     */
    public function testPassingQueryAsData()
    {
        $Request = new Request();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);

        $articles = TableRegistry::get('Articles');
        $query = $articles->find();

        $Controller->set(['data' => $query, '_serialize' => 'data']);
        $Controller->viewClass = 'CsvView.Csv';
        $View = $Controller->createView();
        $output = $View->render(false);

        $articles->belongsTo('Authors');
        $query = $articles->find('all', ['contain' => 'Authors']);
        $_extract = ['title', 'body', 'author.name'];
        $View->set(['data' => $query, '_extract' => $_extract, '_serialize' => 'data']);
        $output = $View->render(false);

        $expected = '"First Article","First Article Body",mariano' . PHP_EOL .
            '"Second Article","Second Article Body",larry' . PHP_EOL .
            '"Third Article","Third Article Body",mariano' . PHP_EOL;
        $this->assertSame($expected, $output);
    }
}
