<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Exceptions\WrongRequestMethodException;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\ResponseStatus;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\HttpRequest
 */
final class HttpRequestTest extends TestCase
{
    use PHPMock;

    public function testFromGlobals(): void
    {
        $_GET = ['getVariable' => 'getValue'];
        $_POST = ['postVariable' => 'postValue'];
        $_SERVER = ['serverVariable' => 'serverValue', 'HTTP_HEADER_VARIABLE' => 'headerValue'];
        $_COOKIE = ['cookieVariable' => 'cookieValue'];
        $_FILES = [
            [
                'name' => 'fileName',
                'type' => 'fileType',
                'tmp_name' => 'tmpName',
                'error' => 0,
                'size' => 1234,
            ],
        ];
        $request = HttpRequest::fromGlobals();
        self::assertSame('getValue', $request->get->get('getVariable'));
        self::assertSame('postValue', $request->post->get('postVariable'));
        self::assertSame('serverValue', $request->server->get('serverVariable'));
        self::assertSame('headerValue', $request->headers->get('Header-Variable'));
        self::assertSame('cookieValue', $request->cookies->get('cookieVariable'));
    }

    public function testGetMethodDefault(): void
    {
        $request = HttpRequest::fromGlobals();
        self::assertSame(HttpMethod::GET, $request->method);
    }

    public function testGetMethod(): void
    {
        $request = new HttpRequest(server: ['REQUEST_METHOD' => 'PUT']);
        self::assertSame(HttpMethod::PUT, $request->method);
    }

    public function testGetMethodUnknown(): void
    {
        $request = new HttpRequest(server: ['REQUEST_METHOD' => 'wrong']);
        self::expectException(WrongRequestMethodException::class);
        self::expectExceptionMessageIs('Wrong request method: WRONG');
        $test = $request->method;
    }

    public function testGetMethodUnknownResponseStatus(): void
    {
        $request = new HttpRequest(server: ['REQUEST_METHOD' => 'wrong']);

        try {
            $test = $request->method;
        } catch (WrongRequestMethodException $e) {
            self::assertSame(ResponseStatus::METHOD_NOT_ALLOWED, $e->getResponseStatus());
        }
    }

    public function testResetProps(): void
    {
        $request = new HttpRequest();
        self::assertEmpty($request->props->getAll());
        $newData = ['string' => 'someTest', 'int' => 2];
        $request->setProps($newData);
        self::assertSame($newData, $request->props->getAll());
    }

    public function testGetUri(): void
    {
        $request = new HttpRequest(server: ['REQUEST_URI' => 'some/uri']);
        self::assertSame('some/uri', $request->getPath());
    }

    public function testGetUriDefault(): void
    {
        $request = new HttpRequest();
        self::assertSame('/', $request->getPath());
    }

    public function testJson(): void
    {
        $expect = ['example' => 1, 'stringValue' => 'someValue', 'boolValue' => true];
        $request = new HttpRequest(
            server: ['REQUEST_URI' => 'some/uri', 'CONTENT_TYPE' => 'application/json'],
            rawBody: json_encode($expect),
        );
        self::assertSame($expect, $request->json);
    }

    public function testUrlencoded(): void
    {
        $request = new HttpRequest(
            server: ['REQUEST_URI' => 'some/uri', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            rawBody: 'name=Alex&age=30',
        );
        self::assertSame(['name' => 'Alex', 'age' => '30'], $request->post->getAll());
    }

    public function testNotHttps(): void
    {
        $request = new HttpRequest(
            server: ['REQUEST_URI' => 'some/uri', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        );
        self::assertFalse($request->isSecure());
    }

    #[DataProvider('provideIsHttpsCases')]
    public function testIsHttps(string $value, bool $expect): void
    {
        $request = new HttpRequest(
            server: ['REQUEST_URI' => 'some/uri', 'HTTPS' => $value],
        );
        self::assertSame($expect, $request->isSecure());
    }

    public static function provideIsHttpsCases(): iterable
    {
        yield ['', false];
        yield ['test', false];
        yield ['on', true];
        yield ['1', true];
        yield ['10', false];
    }

    public function testIsHttpsByPort(): void
    {
        $request = new HttpRequest(
            server: ['REQUEST_URI' => 'some/uri', 'SERVER_PORT' => 443],
        );
        self::assertTrue($request->isSecure());
    }

    #[DataProvider('provideGetOriginCases')]
    public function testGetOrigin(array $header, string $expected): void
    {
        $request = new HttpRequest(server: $header);
        self::assertSame($expected, $request->getOrigin());
    }

    public static function provideGetOriginCases(): iterable
    {
        yield 'success' => [['HTTP_ORIGIN' => 'https://example.com:8001/'], 'https://example.com:8001/'];
        yield 'wrong url' => [['HTTP_ORIGIN' => 'example.com'], ''];
        yield 'not CORS request' => [[], ''];
    }

    #[RunInSeparateProcess]
    public function testGetOriginCached(): void
    {
        $filterVar = self::getFunctionMock('Vasoft\Joke\Http', 'filter_var');
        $filterVar->expects(self::once())->willReturn(true);
        $request = new HttpRequest(server: ['HTTP_ORIGIN' => 'https://example.com:8001/']);
        $request->getOrigin();
        self::assertSame('https://example.com:8001/', $request->getOrigin());
    }
}
