<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use phpmock\phpunit\PHPMock;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Response\HtmlResponse;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\HtmlResponse
 */
final class HtmlResponseTest extends TestCase
{
    use PHPMock;

    private static CookieCollection $cookies;

    public static function setUpBeforeClass(): void
    {
        self::$cookies = new CookieCollection(new CookieConfig(), true);
    }

    public function testGetBody(): void
    {
        $response = new HtmlResponse(self::$cookies);
        $response->setBody('<html></html>');
        $body1 = $response->getBody();
        $body2 = $response->getBodyAsString();
        self::assertSame('<html></html>', $body1);
        self::assertSame('<html></html>', $body2);
    }

    public function testDefaultContentType(): void
    {
        $response = new HtmlResponse(self::$cookies);
        self::assertSame('text/html', $response->headers->contentType);
    }

    public function testSendCookies(): void
    {
        $headers = [];
        $mockHeader = self::getFunctionMock('Vasoft\Joke\Http\Response', 'header');
        $mockHeader->expects(self::atLeastOnce())->willReturnCallback(
            static function (string $value) use (&$headers): void {
                $headers[] = $value;
            },
        );

        $response = new HtmlResponse(self::$cookies);
        $response->cookies->add('cookie1', 'value1');
        $response->cookies->add('cookie2', 'value2');

        $response->send();

        $hasCookie1 = false;
        $hasCookie2 = false;

        foreach ($headers as $header) {
            if (str_starts_with($header, 'Set-Cookie: cookie1=value1;')) {
                $hasCookie1 = true;
            }
            if (str_starts_with($header, 'Set-Cookie: cookie2=value2;')) {
                $hasCookie2 = true;
            }
        }

        self::assertTrue($hasCookie1, 'The Set-Cookie header for cookie1 is missing');
        self::assertTrue($hasCookie2, 'The Set-Cookie header for cookie2 is missing');
    }
}
