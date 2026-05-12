<?php
declare(strict_types=1);

namespace CsvView\Test\TestCase\Http\Response;

use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use CsvView\Http\Response\CsvStreamResponse;
use InvalidArgumentException;
use Throwable;

/**
 * Tests for {@see \CsvView\Http\Response\CsvStreamResponse}.
 */
class CsvStreamResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::setConfig('csvstreamtest', ['className' => ArrayLog::class]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Log::drop('csvstreamtest');
    }

    /**
     * Capture body output emitted by the streaming callback.
     */
    protected function getStreamedBody(CsvStreamResponse $response): string
    {
        ob_start();
        try {
            (string)$response->getBody();
        } catch (Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return ob_get_clean() ?: '';
    }

    public function testSimpleArrayStreaming(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $response = new CsvStreamResponse($data);
        $body = $this->getStreamedBody($response);

        $this->assertSame("1,Alice\n2,Bob\n", $body);
        $this->assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('no', $response->getHeaderLine('X-Accel-Buffering'));
    }

    public function testWithHeaderRow(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $response = new CsvStreamResponse($data, [
            'header' => ['id', 'name'],
        ]);

        $this->assertSame(
            "id,name\n1,Alice\n2,Bob\n",
            $this->getStreamedBody($response),
        );
    }

    public function testWithFooterRow(): void
    {
        $data = [
            ['id' => 1, 'amount' => 10],
            ['id' => 2, 'amount' => 20],
        ];

        $response = new CsvStreamResponse($data, [
            'header' => ['id', 'amount'],
            'footer' => ['total', 30],
        ]);

        $this->assertSame(
            "id,amount\n1,10\n2,20\ntotal,30\n",
            $this->getStreamedBody($response),
        );
    }

    public function testExtractByPath(): void
    {
        $data = [
            ['user' => ['id' => 1, 'name' => 'Alice'], 'secret' => 'x'],
            ['user' => ['id' => 2, 'name' => 'Bob'], 'secret' => 'y'],
        ];

        $response = new CsvStreamResponse($data, [
            'header' => ['id', 'name'],
            'extract' => ['user.id', 'user.name'],
        ]);

        $body = $this->getStreamedBody($response);
        $this->assertSame("id,name\n1,Alice\n2,Bob\n", $body);
        $this->assertStringNotContainsString('secret', $body);
    }

    public function testExtractWithFormat(): void
    {
        $data = [
            ['id' => 1, 'amount' => 5.5],
            ['id' => 2, 'amount' => 42.25],
        ];

        $response = new CsvStreamResponse($data, [
            'extract' => ['id', ['amount', '%.2f']],
        ]);

        $this->assertSame(
            "1,5.50\n2,42.25\n",
            $this->getStreamedBody($response),
        );
    }

    public function testExtractWithCallable(): void
    {
        $data = [
            (object)['first' => 'Alice', 'last' => 'Smith'],
            (object)['first' => 'Bob', 'last' => 'Jones'],
        ];

        $response = new CsvStreamResponse($data, [
            'extract' => [
                fn($row) => $row->first . ' ' . $row->last,
            ],
        ]);

        $this->assertSame(
            "\"Alice Smith\"\n\"Bob Jones\"\n",
            $this->getStreamedBody($response),
        );
    }

    public function testEmptyIterableEmitsHeaderAndFooterOnly(): void
    {
        $response = new CsvStreamResponse([], [
            'header' => ['id', 'name'],
            'footer' => ['done', ''],
        ]);

        $this->assertSame(
            "id,name\ndone,\n",
            $this->getStreamedBody($response),
        );
    }

    public function testEmptyIterableNoHeaderEmitsNothing(): void
    {
        $response = new CsvStreamResponse([]);

        $this->assertSame('', $this->getStreamedBody($response));
    }

    public function testGeneratorInput(): void
    {
        $generator = function () {
            yield ['id' => 1];
            yield ['id' => 2];
            yield ['id' => 3];
        };

        $response = new CsvStreamResponse($generator());

        $this->assertSame(
            "1\n2\n3\n",
            $this->getStreamedBody($response),
        );
    }

    public function testCustomDelimiterAndEol(): void
    {
        $data = [
            ['a', 'b'],
            ['c', 'd'],
        ];

        $response = new CsvStreamResponse($data, [
            'delimiter' => ';',
            'eol' => "\r\n",
        ]);

        $this->assertSame("a;b\r\nc;d\r\n", $this->getStreamedBody($response));
    }

    public function testBomAddedOnFirstRowOnly(): void
    {
        $data = [
            ['a', 'b'],
            ['c', 'd'],
        ];

        $response = new CsvStreamResponse($data, [
            'bom' => true,
            'csvEncoding' => 'UTF-8',
        ]);

        $body = $this->getStreamedBody($response);
        $expectedBom = chr(0xEF) . chr(0xBB) . chr(0xBF);

        $this->assertStringStartsWith($expectedBom, $body);
        $this->assertSame($expectedBom . "a,b\nc,d\n", $body);
        // BOM appears once
        $this->assertSame(1, substr_count($body, $expectedBom));
    }

    public function testExcelPresetForcesBomCrlfEolAndUtf8(): void
    {
        $data = [['id' => 1, 'name' => 'Alice']];

        $response = new CsvStreamResponse($data, [
            'header' => ['id', 'name'],
            'excel' => true,
        ]);

        $body = $this->getStreamedBody($response);
        $expectedBom = chr(0xEF) . chr(0xBB) . chr(0xBF);

        $this->assertStringStartsWith($expectedBom, $body);
        $this->assertStringContainsString("id,name\r\n", $body);
        $this->assertStringContainsString("1,Alice\r\n", $body);
    }

    public function testSetSeparatorLineEmittedBeforeHeader(): void
    {
        $data = [['a', 'b']];

        $response = new CsvStreamResponse($data, [
            'delimiter' => ';',
            'setSeparator' => true,
        ]);

        $body = $this->getStreamedBody($response);
        $this->assertStringStartsWith("sep=;\n", $body);
        $this->assertStringContainsString("a;b\n", $body);
    }

    public function testEncodingTranscodeDataToCsv(): void
    {
        if (!extension_loaded('iconv')) {
            $this->markTestSkipped('iconv is required for this test');
        }

        $data = [['Grüße', 'Café']];

        $response = new CsvStreamResponse($data, [
            'dataEncoding' => 'UTF-8',
            'csvEncoding' => 'ISO-8859-1',
        ]);

        $body = $this->getStreamedBody($response);

        $expected = iconv('UTF-8', 'ISO-8859-1', "Grüße,Café\n");
        $this->assertSame($expected, $body);
    }

    public function testTearsCleanlyOnUnrenderableExtractValue(): void
    {
        $data = [
            ['ok' => 'first'],
            ['ok' => ['array', 'not', 'scalar']],
            ['ok' => 'never reached'],
        ];

        $response = new CsvStreamResponse($data, [
            'extract' => ['ok'],
        ]);

        $body = $this->getStreamedBody($response);

        // First row written, second row triggers the error -> tear, third row never reached
        $this->assertStringStartsWith("first\n", $body);
        $this->assertStringNotContainsString('never', $body);

        $messages = Log::engine('csvstreamtest')->read();
        $this->assertNotEmpty($messages);
        $this->assertStringContainsString(
            'CsvStreamResponse encoding failed at index 1',
            implode("\n", $messages),
        );
    }

    public function testTearOmitsFooterRow(): void
    {
        $data = [
            ['ok' => 'first'],
            ['ok' => ['array', 'not', 'scalar']],
        ];

        $response = new CsvStreamResponse($data, [
            'extract' => ['ok'],
            'footer' => ['total'],
        ]);

        $body = $this->getStreamedBody($response);
        $this->assertStringNotContainsString('total', $body);
    }

    public function testNullCellReplacement(): void
    {
        $data = [
            ['id' => 1, 'name' => null],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $response = new CsvStreamResponse($data, [
            'null' => 'NULL',
        ]);

        $this->assertSame("1,NULL\n2,Bob\n", $this->getStreamedBody($response));
    }

    public function testEscapedNewlineInsideField(): void
    {
        $data = [
            ['id' => 1, 'note' => "line1\nline2"],
        ];

        $response = new CsvStreamResponse($data, [
            'newline' => ' / ',
        ]);

        // fputcsv with the default empty-string escape (RFC 4180) quotes fields
        // that contained a newline before replacement, so the post-replacement
        // value still arrives quoted to the wire.
        $this->assertSame("1,\"line1 / line2\"\n", $this->getStreamedBody($response));
    }

    public function testCustomEnclosureWithFputcsv(): void
    {
        // Enclosure '"' is the default; verify it triggers when fields contain commas
        $data = [
            ['hello, world', 'plain'],
        ];

        $response = new CsvStreamResponse($data);
        $this->assertSame("\"hello, world\",plain\n", $this->getStreamedBody($response));
    }

    public function testInvalidFlushEveryThrowsFromAbstract(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`flushEvery` must be an integer greater than or equal to 1');

        new CsvStreamResponse([], ['flushEvery' => 0]);
    }

    public function testStrictTranscodingFailureLogs(): void
    {
        if (!extension_loaded('iconv')) {
            $this->markTestSkipped('iconv is required for this test');
        }

        $data = [
            ['ok' => 'first'],
            // ✦ (U+2728) has no ISO-8859-1 representation; strict mode raises
            ['ok' => "second \u{2728}"],
            ['ok' => 'never reached'],
        ];

        $response = new CsvStreamResponse($data, [
            'extract' => ['ok'],
            'dataEncoding' => 'UTF-8',
            'csvEncoding' => 'ISO-8859-1',
            'transcodingMode' => CsvStreamResponse::TRANSCODING_MODE_STRICT,
        ]);

        $body = $this->getStreamedBody($response);

        $this->assertStringStartsWith('first', $body);
        $this->assertStringNotContainsString('never', $body);

        $messages = Log::engine('csvstreamtest')->read();
        $this->assertNotEmpty($messages);
        $this->assertStringContainsString(
            'CsvStreamResponse encoding failed at index 1',
            implode("\n", $messages),
        );
    }

    public function testIgnoreTranscodingDropsUnconvertibleCharacters(): void
    {
        if (!extension_loaded('iconv')) {
            $this->markTestSkipped('iconv is required for this test');
        }

        $data = [['hello \u{2728} world']];

        $response = new CsvStreamResponse($data, [
            'dataEncoding' => 'UTF-8',
            'csvEncoding' => 'ISO-8859-1',
            'transcodingMode' => CsvStreamResponse::TRANSCODING_MODE_IGNORE,
        ]);

        $body = $this->getStreamedBody($response);

        // The ✦ character is dropped; the surrounding text survives
        $this->assertStringContainsString('hello', $body);
        $this->assertStringContainsString('world', $body);
    }

    public function testContentTypeIsTextCsv(): void
    {
        $response = new CsvStreamResponse([]);

        $this->assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }
}
