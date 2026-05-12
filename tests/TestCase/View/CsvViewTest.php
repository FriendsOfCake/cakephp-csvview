<?php
declare(strict_types=1);

namespace CsvView\Test\TestCase\View;

use Cake\Core\Exception\CakeException;
use Cake\Http\Response;
use Cake\Http\ServerRequest as Request;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use CsvView\View\CsvView;
use Exception;

/**
 * CsvViewTest
 */
class CsvViewTest extends TestCase
{
    protected array $fixtures = ['plugin.CsvView.Articles', 'plugin.CsvView.Authors'];

    /**
     * @var \CsvView\View\CsvView
     */
    protected $view;

    /**
     * @var \Cake\Http\ServerRequest
     */
    protected $request;

    /**
     * @var \Cake\Http\Response
     */
    protected $response;

    public function setUp(): void
    {
        parent::setUp();

        DateTime::setToStringFormat('yyyy-MM-dd HH:mm:ss');

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
        $this->view->set(['data' => $data])
            ->setConfig('serialize', 'data');
        $output = $this->view->render();

        $this->assertSame('user,fake,list,item1,item2' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
                'The mbstring extension is not available.',
            );
        }

        $data = [['test']];
        $this->view->set(['data' => $data])
            ->setConfig(['serialize' => 'data', 'bom' => true, 'csvEncoding' => 'UTF-16LE']);
        $output = $this->view->render();

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
                'The mbstring extension is not available.',
            );
        }

        $data = [
            ['test'],
            ['test2'],
            ['test3'],
        ];
        $this->view->set(['data' => $data])
            ->setConfig(['serialize' => 'data', 'bom' => true, 'csvEncoding' => 'UTF-8']);
        $output = $this->view->render();

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
                'The mbstring extension is not available.',
            );
        }

        $header = ['column1'];
        $data = [
            ['test'],
            ['test2'],
        ];
        $this->view->set(['data' => $data])
            ->setConfig(['header' => $header, 'serialize' => 'data', 'bom' => true, 'csvEncoding' => 'UTF-8']);
        $output = $this->view->render();

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
        $this->view->setConfig(['serialize' => 'data']);
        $output = $this->view->render();

        $expected = 'a,b,c' . PHP_EOL . '1,2,3' . PHP_EOL . 'you,and,me' . PHP_EOL;
        $this->assertSame($expected, $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());

        $this->view->setConfig('serialize', true);
        $output = $this->view->render();
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
        $this->view
            ->set('data', $data)
            ->setConfig(['serialize' => 'data', 'eol' => '~']);

        $output = $this->view->render();

        $this->assertSame('a,b,c~1,2,3~you,and,me~', $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
        $this->view
            ->set('data', $data)
            ->setConfig(['serialize' => 'data', 'dataEncoding' => 'UTF-8', 'csvEncoding' => 'SJIS']);
        $output = $this->view->render();

        $expected = iconv('UTF-8', 'SJIS', 'a,b,c' . PHP_EOL . '1,2,3' . PHP_EOL . 'あなた,と,私' . PHP_EOL);

        $this->assertSame($expected, $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
                'The mbstring extension is not available.',
            );
        }
        $data = [
            ['a', 'b', 'c'],
            [1, 2, 3],
            ['あなた', 'と', '私'],
        ];
        $this->view
            ->set('data', $data)
            ->setConfig(['serialize' => 'data', 'dataEncoding' => 'UTF-8', 'csvEncoding' => 'SJIS', 'extension' => 'mbstring']);
        $output = $this->view->render();

        $expected = mb_convert_encoding('a,b,c' . PHP_EOL . '1,2,3' . PHP_EOL . 'あなた,と,私' . PHP_EOL, 'SJIS', 'UTF-8');

        $this->assertSame($expected, $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
                    'created' => new DateTime('2010-01-05'),
                ],
                'Item' => [
                    'name' => 'beach',
                ],
            ],
            [
                'User' => [
                    'username' => 'drew',
                    'created' => null,
                ],
                'Item' => [
                    'name' => 'ball',
                ],
            ],
        ];
        $_extract = ['User.username', 'User.created', 'Item.name'];
        $this->view->set(['user' => $data]);
        $this->view->setConfig(['serialize' => 'user', 'extract' => $_extract]);
        $output = $this->view->render();

        $this->assertSame('jose,"2010-01-05 00:00:00",beach' . PHP_EOL . 'drew,,ball' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
                ],
            ],
            [
                'User' => [
                    'id' => 2,
                    'username' => 'drew',
                ],
                'Item' => [
                    'name' => 'ball',
                    'type' => 'fun',
                ],
            ],
        ];
        $_extract = [['User.id', '%d'], 'User.username', 'Item.name', 'Item.type'];
        $this->view->set(['user' => $data]);
        $this->view->setConfig(['serialize' => 'user', 'extract' => $_extract]);
        $output = $this->view->render();

        $this->assertSame('1,jose,,beach' . PHP_EOL . '2,drew,ball,fun' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
                'created' => new DateTime('2010-01-05'),
                'item' => [
                    'name' => 'beach',
                ],
            ],
            [
                'username' => 'drew',
                'created' => null,
                'item' => [
                    'name' => 'ball',
                ],
            ],
        ];
        $_extract = [
            'username',
            'created',
            function ($row) {
                return 'my-' . $row['item']['name'];
            },
        ];
        $this->view->set(['user' => $data]);
        $this->view->setConfig(['serialize' => 'user', 'extract' => $_extract]);
        $output = $this->view->render();

        $this->assertSame('jose,"2010-01-05 00:00:00",my-beach' . PHP_EOL . 'drew,,my-ball' . PHP_EOL, $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
                    'username' => 'José',
                ],
                'Item' => [
                    'type' => 'äöü',
                ],
            ],
            [
                'User' => [
                    'username' => 'Including,Comma',
                ],
                'Item' => [
                    'name' => 'Containing"char',
                    'type' => 'Containing\'char',
                ],
            ],
            [
                'User' => [
                    'username' => 'Some Space',
                ],
                'Item' => [
                    'name' => "A\nNewline",
                    'type' => "A\tTab",
                ],
            ],
        ];
        $_extract = ['User.username', 'Item.name', 'Item.type'];
        $this->view->set(['user' => $data]);
        $this->view->setConfig(['serialize' => 'user', 'extract' => $_extract]);
        $output = $this->view->render();

        $expected = <<<CSV
José,,äöü
"Including,Comma","Containing""char",Containing'char
"Some Space","A
Newline","A\tTab"

CSV;
        $this->assertTextEquals($expected, $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
    }

    /**
     * [testPassingQueryAsData description]
     *
     * @return void
     */
    public function testPassingQueryAsData()
    {
        $articles = $this->getTableLocator()->get('Articles');
        $query = $articles->find();

        $this->view->set(['data' => $query])
            ->setConfig(['serialize' => 'data']);
        $output = $this->view->render();

        $articles->belongsTo('Authors');
        $query = $articles->find('all', contain: 'Authors');
        $_extract = ['title', 'body', 'author.name'];
        $this->view->set(['data' => $query])
            ->setConfig(['extract' => $_extract, 'serialize' => 'data']);
        $output = $this->view->render();

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
            '' => 'user,fake apple,list,a b c,item2' . PHP_EOL,
        ];

        foreach ($testData as $enclosure => $expected) {
            $this->view
                ->set('data', $data)
                ->setConfig([
                    'serialize' => 'data',
                    'enclosure' => $enclosure,
                ]);
            $output = $this->view->render();

            $this->assertSame($expected, $output);
            $this->assertSame('text/csv', $this->view->getResponse()->getType());
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
        $this->view
            ->set('data', $data)
            ->setConfig([
                'serialize' => 'data',
                'null' => 'NULL',
                'eol' => '~',
            ]);
        $output = $this->view->render();

        $this->assertSame('a,b,c~1,2,NULL~you,NULL,me~', $output);
        $this->assertSame('text/csv', $this->view->getResponse()->getType());
    }

    /**
     * CsvViewTest::testInvalidViewVarThrowsException()
     *
     * @return void
     */
    public function testInvalidViewVarThrowsException()
    {
        $this->expectException(Exception::class);

        $this->view->set(['data' => 'invaliddata']);
        $this->view->setConfig('serialize', 'data');
        $this->view->render();
    }

    /**
     * Rendering the same instance twice must produce clean output both times
     * (no stale BOM state, no leftover writer state).
     *
     * @return void
     */
    public function testRenderTwiceWithSameInstance()
    {
        $data = [['a', 'b'], ['c', 'd']];
        $this->view->set(['data' => $data])
            ->setConfig(['serialize' => 'data', 'bom' => true, 'csvEncoding' => 'UTF-8']);

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $expected = $bom . 'a,b' . PHP_EOL . 'c,d' . PHP_EOL;

        $this->assertSame($expected, $this->view->render());
        $this->assertSame($expected, $this->view->render());
    }

    /**
     * Simple (non-dotted) extract paths must fall through Hash::get() so a
     * missing key resolves to null instead of triggering an undefined-key
     * warning.
     *
     * @return void
     */
    public function testRenderViaExtractMissingSimpleKey()
    {
        $data = [
            ['name' => 'alice', 'email' => 'a@example.com'],
            ['name' => 'bob'], // missing 'email'
        ];
        $this->view->set(['users' => $data])
            ->setConfig([
                'serialize' => 'users',
                'extract' => ['name', 'email'],
            ]);

        $expected = 'alice,a@example.com' . PHP_EOL . 'bob,' . PHP_EOL;
        $this->assertSame($expected, $this->view->render());
    }

    /**
     * An extract path that resolves to an array (e.g. a hasMany association)
     * must throw a clear exception instead of silently producing "Array to
     * string conversion" notices and corrupted CSV. Regression for #131.
     *
     * @return void
     */
    public function testRenderViaExtractArrayValueThrows()
    {
        $data = [
            [
                'id' => 1,
                'tags' => [['name' => 'php'], ['name' => 'cakephp']],
            ],
        ];
        $this->view->set(['rows' => $data])
            ->setConfig([
                'serialize' => 'rows',
                'extract' => ['id', 'tags'],
            ]);

        try {
            $this->view->render();
            $this->fail('Expected exception for array-valued extract path.');
        } catch (Exception $e) {
            // SerializedView wraps our CakeException in SerializationFailureException.
            $previous = $e->getPrevious() ?? $e;
            $this->assertInstanceOf(CakeException::class, $previous);
            $this->assertStringContainsString(
                'Extract path `tags` resolved to a non-scalar `array`',
                $previous->getMessage(),
            );
        }
    }

    /**
     * `excel => true` is a shorthand that forces the three options Excel
     * needs to open a UTF-8 CSV correctly on Windows: BOM, CRLF line
     * endings, and UTF-8 encoding.
     *
     * @return void
     */
    public function testExcelPresetEmitsBomCrlfAndUtf8()
    {
        $data = [['Möhre', 'café'], ['ü', 'ß']];
        $this->view->set(['data' => $data])
            ->setConfig(['serialize' => 'data', 'excel' => true]);

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $expected = $bom . 'Möhre,café' . "\r\n" . 'ü,ß' . "\r\n";

        $this->assertSame($expected, $this->view->render());
    }

    /**
     * The Excel preset wins for the three keys it controls even when the
     * user has explicitly set them to other values. `excel => true` is a
     * single switch; for a different combination set the individual keys
     * yourself instead of enabling the preset.
     *
     * @return void
     */
    public function testExcelPresetOverridesIndividualKeys()
    {
        $data = [['a', 'b']];
        $this->view->set(['data' => $data])
            ->setConfig([
                'serialize' => 'data',
                'excel' => true,
                'bom' => false,
                'eol' => "\n",
            ]);

        $output = $this->view->render();
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $this->assertStringStartsWith($bom, $output);
        $this->assertStringEndsWith("\r\n", $output);
    }

    /**
     * The default `escape` value is `''` (RFC 4180 compliant) to avoid
     * PHP 8.4's deprecation warning for any non-empty escape passed to
     * `fputcsv()`. Rendering a row with a quote in it must produce
     * doubled-quote escaping rather than legacy backslash escaping, and
     * must not raise E_DEPRECATED.
     *
     * @return void
     */
    public function testDefaultEscapeIsRfc4180()
    {
        $deprecations = [];
        set_error_handler(function ($severity, $message) use (&$deprecations) {
            $deprecations[] = $message;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $data = [['contains "quote"']];
            $this->view->set(['data' => $data])
                ->setConfig(['serialize' => 'data']);
            $output = $this->view->render();
        } finally {
            restore_error_handler();
        }

        // RFC 4180: quote is escaped by doubling, not by backslash.
        $this->assertSame('"contains ""quote"""' . PHP_EOL, $output);
        $this->assertSame(
            [],
            $deprecations,
            'fputcsv() raised an unexpected deprecation: ' . implode(', ', $deprecations),
        );
    }

    public function testIconvFailureThrows()
    {
        if (!extension_loaded('iconv')) {
            $this->markTestSkipped('The iconv extension is not available.');
        }

        $data = [['hello']];
        $this->view->set(['data' => $data])
            ->setConfig([
                'serialize' => 'data',
                'dataEncoding' => 'UTF-8',
                // Bogus target encoding name. iconv returns false for this.
                'csvEncoding' => 'NOT-A-REAL-ENCODING',
                'transcodingExtension' => CsvView::EXTENSION_ICONV,
            ]);

        try {
            $this->view->render();
            $this->fail('Expected exception for iconv() returning false.');
        } catch (Exception $e) {
            $previous = $e->getPrevious() ?? $e;
            $this->assertInstanceOf(CakeException::class, $previous);
            $this->assertStringContainsString(
                'iconv() failed to transcode',
                $previous->getMessage(),
            );
        }
    }

    /**
     * `transcodingMode => 'ignore'` must keep generating the CSV when iconv
     * cannot convert a character: the unconvertible character is dropped and
     * the rest of the row is preserved instead of throwing.
     *
     * @return void
     */
    public function testIconvIgnoreModeDropsUnconvertibleChars()
    {
        if (!extension_loaded('iconv')) {
            $this->markTestSkipped('The iconv extension is not available.');
        }

        // `あ` cannot be represented in ASCII; in ignore mode it is dropped.
        $data = [['hello あ world']];
        $this->view->set(['data' => $data])
            ->setConfig([
                'serialize' => 'data',
                'dataEncoding' => 'UTF-8',
                'csvEncoding' => 'ASCII',
                'transcodingExtension' => CsvView::EXTENSION_ICONV,
                'transcodingMode' => CsvView::TRANSCODING_MODE_IGNORE,
            ]);

        $output = $this->view->render();
        $this->assertStringContainsString('hello ', $output);
        $this->assertStringContainsString(' world', $output);
        $this->assertStringNotContainsString('あ', $output);
    }

    /**
     * `transcodingMode => 'transliterate'` must convert what it can (e.g.
     * accented Latin → ASCII equivalents).
     *
     * @return void
     */
    public function testIconvTransliterateModeConvertsAccentedChars()
    {
        if (!extension_loaded('iconv')) {
            $this->markTestSkipped('The iconv extension is not available.');
        }

        $data = [['café Möhre']];
        $this->view->set(['data' => $data])
            ->setConfig([
                'serialize' => 'data',
                'dataEncoding' => 'UTF-8',
                'csvEncoding' => 'ASCII',
                'transcodingExtension' => CsvView::EXTENSION_ICONV,
                'transcodingMode' => CsvView::TRANSCODING_MODE_TRANSLITERATE,
            ]);

        $output = $this->view->render();
        // iconv//TRANSLIT typically produces `cafe` and `Mohre`; the exact
        // output varies by libiconv build, but neither é nor ö should
        // survive.
        $this->assertStringNotContainsString('é', $output);
        $this->assertStringNotContainsString('ö', $output);
    }
}
