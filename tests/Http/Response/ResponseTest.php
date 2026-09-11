<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use phpmock\phpunit\MockObjectProxy;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Collections\HeadersCollection;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\ResponseStatus;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\Response
 */
final class ResponseTest extends TestCase
{
    use PHPMock;

    private MockObject|MockObjectProxy $headerMock;

    protected function setUp(): void
    {
        $this->headerMock = $this->getFunctionMock('Vasoft\Joke\Http\Response', 'header');
    }

    public function testDefaultStatusIsOk(): void
    {
        $response = new HtmlResponse();
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    public function testSetStatus(): void
    {
        $response = new HtmlResponse();
        $response->setStatus(ResponseStatus::NOT_FOUND);
        self::assertSame(ResponseStatus::NOT_FOUND, $response->status);
    }

    #[TestDox('При создании объекта ответа инициализируются заголовки типом контента')]
    public function testHeadersCollectionIsInitialized(): void
    {
        $response = new HtmlResponse();
        self::assertInstanceOf(HeadersCollection::class, $response->headers);
        self::assertSame(['Content-Type' => 'text/html'], $response->headers->getAll());
    }

    public function testAddHeaderViaCollection(): void
    {
        $response = new HtmlResponse();
        $response->headers->set('Content-Type', 'application/json');

        self::assertSame(['Content-Type' => 'application/json'], $response->headers->getAll());
    }

    #[RunInSeparateProcess]
    public function testSendCallsHeaderAndEchoesBody(): void
    {
        $response = new HtmlResponse();
        $response->headers->set('X-Custom', 'test-value');
        $response->setBody('Hello, world!');

        $headerParams = [];
        $this->headerMock->expects(self::exactly(3))
            ->willReturnCallback(static function ($header) use (&$headerParams): void {
                $headerParams[] = $header;
            });
        ob_start();
        $response->send();
        $output = ob_get_clean();

        $expectedHeaders = [
            'Content-Type: text/html',
            'HTTP/1.1 200 OK',
            'X-Custom: test-value',
        ];

        sort($headerParams);
        sort($expectedHeaders);

        self::assertSame($expectedHeaders, $headerParams);

        self::assertSame('Hello, world!', $output);
    }

    public function testSendReturnsSelf(): void
    {
        $response = new HtmlResponse();
        $returned = $response->send();
        self::assertSame($response, $returned);
    }
}
