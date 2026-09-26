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
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
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

    private static CookieCollection $cookies;

    public static function setUpBeforeClass(): void
    {
        self::$cookies = new CookieCollection(new CookieConfig(), true);
    }

    protected function setUp(): void
    {
        $this->headerMock = $this->getFunctionMock('Vasoft\Joke\Http\Response', 'header');
    }

    public function testDefaultStatusIsOk(): void
    {
        $response = new HtmlResponse(self::$cookies);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    public function testSetStatus(): void
    {
        $response = new HtmlResponse(self::$cookies);
        $response->setStatus(ResponseStatus::NOT_FOUND);
        self::assertSame(ResponseStatus::NOT_FOUND, $response->status);
    }

    #[TestDox('При создании объекта ответа инициализируются заголовки типом контента')]
    public function testHeadersCollectionIsInitialized(): void
    {
        $response = new HtmlResponse(self::$cookies);
        self::assertInstanceOf(HeadersCollection::class, $response->headers);
        self::assertSame(['Content-Type' => 'text/html'], $response->headers->getAll());
    }
    #[TestDox('Заголовки с пустым значением не отправляются')]
    #[RunInSeparateProcess]
    public function testNotSendHeaderWithEmptyValue(): void
    {
        $response = new HtmlResponse(self::$cookies);
        $response->headers->set('Content-Type', '');
        $headerParams = [];
        $this->headerMock->expects(self::exactly(1))
            ->willReturnCallback(static function ($header) use (&$headerParams): void {
                $headerParams[] = $header;
            });
        ob_start();
        $response->send();
        ob_get_clean();

        self::assertSame(['HTTP/1.1 200 OK'], $headerParams);
    }

    #[RunInSeparateProcess]
    public function testSendCallsHeaderAndEchoesBody(): void
    {
        $response = new HtmlResponse(self::$cookies);
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
        $response = new HtmlResponse(self::$cookies);
        $returned = $response->send();
        self::assertSame($response, $returned);
    }
}
