<?php
namespace CsvView\Test\TestCase\View;

use Cake\Http\Response;
use Cake\Http\ServerRequest as Request;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CsvView\View\CsvView;

/**
 * CsvViewTest
 */
class CsvViewTest extends TestCase
{

    public $fixtures = ['core.Articles', 'core.Authors'];

    public function setUp()
    {
        Time::setToStringFormat('yyyy-MM-dd HH:mm:ss');

        $this->request = new Request();
        $this->response = new Response();

        $this->view = new CsvView($this->request, $this->response);
    }

    /**
     * testRenderWithoutView method
     *
     * @return void
     */
    public function testRenderWithoutView()
    {
        $data = [['user', 'fake', 'list', 'item1', 'item2']];
        $this->view->set(['data' => $data, '_serialize' => 'data']);
        $output = $this->view->render(false);

        $this->assertSame('user,fake,list,item1,item2' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * testBom method
     *
     * @return void
     */
    public function testBom()
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped(
                'The mbstring extension is not available.'
            );
        }

        $data = [['test']];
        $this->view->set(['data' => $data, '_serialize' => 'data', '_bom' => true, '_csvEncoding' => 'UTF-16LE']);
        $output = $this->view->render(false);

        $expected = chr(0xFF) . chr(0xFE) . mb_convert_encoding('test' . PHP_EOL, 'UTF-16LE', 'UTF-8');
        $this->assertSame($expected, $output);
    }

    /**
     * Test BOM appears only in the first row.
     *
     * @return void
     */
    public function testBomMultipleContentRows()
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped(
                'The mbstring extension is not available.'
            );
        }

        $data = [
            ['test'],
            ['test2'],
            ['test3'],
        ];
        $this->view->set(['data' => $data, '_serialize' => 'data', '_bom' => true, '_csvEncoding' => 'UTF-8']);
        $output = $this->view->render(false);

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $expected = $bom . 'test' . PHP_EOL . 'test2' . PHP_EOL . 'test3' . PHP_EOL;
        $this->assertSame($expected, $output);
    }

    /**
     * Test BOM appears only in the first row even it has a header.
     *
     * @return void
     */
    public function testBomMultipleContentRowsWithHeader()
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped(
                'The mbstring extension is not available.'
            );
        }

        $header = ['column1'];
        $data = [
            ['test'],
            ['test2'],
        ];
        $this->view->set(['data' => $data, '_header' => $header, '_serialize' => 'data', '_bom' => true, '_csvEncoding' => 'UTF-8']);
        $output = $this->view->render(false);

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $expected = $bom . 'column1' . PHP_EOL . 'test' . PHP_EOL . 'test2' . PHP_EOL;
        $this->assertSame($expected, $output);
    }

    /**
     * Test render with an array in _serialize
     *
     * @return void
     */
    public function testRenderWithoutViewMultiple()
    {
        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['you', 'and', 'me'],
        ];
        $this->view->set('data', $data);
        $this->view->set(['_serialize' => 'data']);
        $output = $this->view->render(false);

        $expected = 'a,b,c' . PHP_EOL . '1,2,3' . PHP_EOL . 'you,and,me' . PHP_EOL;
        $this->assertSame($expected, $output);
        $this->assertSame('text/csv', $this->view->response->getType());

        $this->view->set('_serialize', true);
        $output = $this->view->render(false);
        $this->assertSame($expected, $output);
    }

    /**
     * Test render with a custom EOL char.
     *
     * @return void
     */
    public function testRenderWithCustomEol()
    {
        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['you', 'and', 'me'],
        ];
        $this->view->set('data', $data);
        $this->view->set(['_serialize' => 'data']);
        $this->view->viewVars['_eol'] = '~';
        $output = $this->view->render(false);

        $this->assertSame('a,b,c~1,2,3~you,and,me~', $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * Test render with a custom encoding.
     *
     * @return void
     */
    public function testRenderWithCustomEncoding()
    {
        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['あなた', 'と', '私'],
        ];
        $this->view->set('data', $data);
        $this->view->set(['_serialize' => 'data']);
        $this->view->viewVars['_dataEncoding'] = 'UTF-8';
        $this->view->viewVars['_csvEncoding'] = 'SJIS';
        $output = $this->view->render(false);

        $expected = iconv('UTF-8', 'SJIS', 'a,b,c' . PHP_EOL . '1,2,3' . PHP_EOL . 'あなた,と,私' . PHP_EOL);

        $this->assertSame($expected, $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * Test render with mbstring extension.
     *
     * @return void
     */
    public function testRenderWithMbstring()
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped(
                'The mbstring extension is not available.'
            );
        }
        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['あなた', 'と', '私'],
        ];
        $this->view->set('data', $data);
        $this->view->set(['_serialize' => 'data']);
        $this->view->viewVars['_dataEncoding'] = 'UTF-8';
        $this->view->viewVars['_csvEncoding'] = 'SJIS';
        $this->view->viewVars['_extension'] = 'mbstring';
        $output = $this->view->render(false);

        $expected = mb_convert_encoding('a,b,c' . PHP_EOL . '1,2,3' . PHP_EOL . 'あなた,と,私' . PHP_EOL, 'SJIS', 'UTF-8');

        $this->assertSame($expected, $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * testRenderWithView method
     *
     * @return void
     */
    public function testRenderWithView()
    {
        $this->view->setTemplatePath('Posts');

        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['you', 'and', 'me'],
        ];

        $this->view->set('user', $data);
        $output = $this->view->render('index');

        $this->assertSame('TEST OUTPUT' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * CsvViewTest::testRenderViaExtract()
     *
     * @return void
     */
    public function testRenderViaExtract()
    {
        $this->view->setTemplatePath('Posts');

        $data = [
            [
                'User' => [
                    'username' => 'jose',
                    'created' => new Time('2010-01-05')
                ],
                'Item' => [
                    'name' => 'beach',
                ]
            ],
            [
                'User' => [
                    'username' => 'drew',
                    'created' => null
                ],
                'Item' => [
                    'name' => 'ball',
                ]
            ]
        ];
        $_extract = ['User.username', 'User.created', 'Item.name'];
        $this->view->set(['user' => $data, '_extract' => $_extract]);
        $this->view->set(['_serialize' => 'user']);
        $output = $this->view->render(false);

        $this->assertSame('jose,"2010-01-05 00:00:00",beach' . PHP_EOL . 'drew,,ball' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * CsvViewTest::testRenderViaExtractOptionalField()
     *
     * @return void
     */
    public function testRenderViaExtractOptionalField()
    {
        $this->view->setTemplatePath('Posts');

        $data = [
            [
                'User' => [
                    'id' => 1,
                    'username' => 'jose',
                ],
                'Item' => [
                    'type' => 'beach',
                ]
            ],
            [
                'User' => [
                    'id' => 2,
                    'username' => 'drew'
                ],
                'Item' => [
                    'name' => 'ball',
                    'type' => 'fun'
                ]
            ]
        ];
        $_extract = [['User.id', '%d'], 'User.username', 'Item.name', 'Item.type'];
        $this->view->set(['user' => $data, '_extract' => $_extract]);
        $this->view->set(['_serialize' => 'user']);
        $output = $this->view->render(false);

        $this->assertSame('1,jose,,beach' . PHP_EOL . '2,drew,ball,fun' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * CsvViewTest::testRenderViaExtractWithCallable()
     *
     * @return void
     */
    public function testRenderViaExtractWithCallable()
    {
        $this->view->setTemplatePath('Posts');

        $data = [
            [
                'username' => 'jose',
                'created' => new Time('2010-01-05'),
                'item' => [
                    'name' => 'beach',
                ]
            ],
            [
                'username' => 'drew',
                'created' => null,
                'item' => [
                    'name' => 'ball',
                ]
            ]
        ];
        $_extract = [
            'username',
            'created',
            function ($row) {
                return 'my-' . $row['item']['name'];
            }
        ];
        $this->view->set(['user' => $data, '_extract' => $_extract]);
        $this->view->set(['_serialize' => 'user']);
        $output = $this->view->render(false);

        $this->assertSame('jose,"2010-01-05 00:00:00",my-beach' . PHP_EOL . 'drew,,my-ball' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * CsvViewTest::testRenderWithSpecialCharacters()
     *
     * @return void
     */
    public function testRenderWithSpecialCharacters()
    {
        $this->view->setTemplatePath('Posts');

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
        $this->view->set(['user' => $data, '_extract' => $_extract]);
        $this->view->set(['_serialize' => 'user']);
        $output = $this->view->render(false);

        $expected = <<<CSV
José,,äöü
"Including,Comma","Containing""char",Containing'char
"Some Space","A
Newline","A\tTab"

CSV;
        $this->assertTextEquals($expected, $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * [testPassingQueryAsData description]
     *
     * @return void
     */
    public function testPassingQueryAsData()
    {
        $articles = TableRegistry::getTableLocator()->get('Articles');
        $query = $articles->find();

        $this->view->set(['data' => $query, '_serialize' => 'data']);
        $output = $this->view->render(false);

        $articles->belongsTo('Authors');
        $query = $articles->find('all', ['contain' => 'Authors']);
        $_extract = ['title', 'body', 'author.name'];
        $this->view->set(['data' => $query, '_extract' => $_extract, '_serialize' => 'data']);
        $output = $this->view->render(false);

        $expected = '"First Article","First Article Body",mariano' . PHP_EOL .
            '"Second Article","Second Article Body",larry' . PHP_EOL .
            '"Third Article","Third Article Body",mariano' . PHP_EOL;
        $this->assertSame($expected, $output);
    }

    /**
     * CsvViewTest::testRenderEnclosure()
     *
     * @return void
     */
    public function testRenderEnclosure()
    {
        $data = [['user', 'fake apple', 'list', 'a b c', 'item2']];
        $testData = [
            '"' => 'user,"fake apple",list,"a b c",item2' . PHP_EOL,
            "'" => "user,'fake apple',list,'a b c',item2" . PHP_EOL,
            '' => "user,fake apple,list,a b c,item2" . PHP_EOL,
        ];

        foreach ($testData as $enclosure => $expected) {
            $this->view->set('data', $data);
            $this->view->set(['_serialize' => 'data']);
            $this->view->viewVars['_enclosure'] = $enclosure;
            $output = $this->view->render(false);

            $this->assertSame($expected, $output);
            $this->assertSame('text/csv', $this->view->response->getType());
        }
    }

    /**
     * Test render with a custom NULL option.
     *
     * @return void
     */
    public function testRenderWithCustomNull()
    {
        $data = [
            ['a', 'b', 'c'],
            [1, 2, null],
            ['you', null, 'me'],
        ];
        $this->view->set('data', $data);
        $this->view->set(['_serialize' => 'data']);
        $this->view->viewVars['_null'] = 'NULL';
        $this->view->viewVars['_eol'] = '~';
        $output = $this->view->render(false);

        $this->assertSame('a,b,c~1,2,NULL~you,NULL,me~', $output);
        $this->assertSame('text/csv', $this->view->response->getType());
    }

    /**
     * CsvViewTest::testInvalidViewVarThrowsException()
     *
     * @expectedException Exception
     * @return void
     */
    public function testInvalidViewVarThrowsException()
    {
        $this->view->set(['data' => 'invaliddata', '_serialize' => 'data']);
        $this->view->render(false);
    }
}
