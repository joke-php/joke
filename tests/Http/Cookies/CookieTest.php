<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Cookies;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Vasoft\Joke\Http\Cookies\Cookie;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Cookies\Exceptions\CookieException;
use Vasoft\Joke\Http\Cookies\SameSiteOption;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Cookies\Cookie
 */
final class CookieTest extends TestCase
{
    use PHPMock;

    public function testDefaultValues(): void
    {
        $cookie = new Cookie('example', 'Test value');
        self::assertSame('example=Test%20value; HttpOnly; SameSite=Lax', $cookie->headerValue());
    }

    #[RunInSeparateProcess]
    public function testNormalizedValues(): void
    {
        $lifetime = 100;
        $fixedTime = mktime(0, 0, 0, 1, 1, 2000);
        $expectedExpires = gmdate('D, d M Y H:i:s T', $fixedTime + $lifetime);

        $timeMock = self::getFunctionMock('Vasoft\Joke\Http\Cookies', 'time');
        $timeMock->expects(self::once())->willReturn($fixedTime);
        $cookie = new Cookie(
            ' test ',
            " example value\n multiline ",
            $lifetime,
            ' admin',
            ' expect.eu',
            false,
            false,
            SameSiteOption::Lax,
        );
        self::assertSame(
            "test=%20example%20value%0A%20multiline%20; Expires={$expectedExpires}; Path=/admin; Domain=expect.eu; SameSite=Lax",
            $cookie->headerValue(),
        );
    }

    #[RunInSeparateProcess]
    public function testNegativeLifetime(): void
    {
        $lifetime = -1;
        $fixedTime = mktime(0, 0, 0, 1, 1, 2000);
        $expectedExpires = gmdate('D, d M Y H:i:s T', $fixedTime - 3600);

        $timeMock = self::getFunctionMock('Vasoft\Joke\Http\Cookies', 'time');
        $timeMock->expects(self::once())->willReturn($fixedTime);
        $cookie = new Cookie('Test', 'example', $lifetime, httpOnly: false, sameSite: SameSiteOption::Strict);
        self::assertSame(
            "Test=example; Expires={$expectedExpires}; SameSite=Strict",
            $cookie->headerValue(),
        );
    }

    #[RunInSeparateProcess]
    public function testAutoSecure(): void
    {
        $lifetime = 1000;
        $fixedTime = mktime(0, 0, 0, 1, 1, 2000);
        $expectedExpires = gmdate('D, d M Y H:i:s T', $fixedTime + $lifetime);

        $timeMock = self::getFunctionMock('Vasoft\Joke\Http\Cookies', 'time');
        $timeMock->expects(self::once())->willReturn($fixedTime);
        $cookie = new Cookie(
            'Test',
            'example',
            $lifetime,
            secure: false,
            httpOnly: false,
            sameSite: SameSiteOption::None,
        );
        self::assertSame(
            "Test=example; Expires={$expectedExpires}; Secure; SameSite=None",
            $cookie->headerValue(),
        );
    }

    public function testInvalidPath(): void
    {
        self::expectException(CookieException::class);
        self::expectExceptionMessageIsOrContains('example;.com is not a valid path.');
        new Cookie('example', 'Test value', path: 'example;.com');
    }

    public function testInvalidName(): void
    {
        self::expectException(CookieException::class);
        self::expectExceptionMessageIs('Inva%lid is not a valid cookie name.');
        new Cookie('Inva%lid', 'Test value');
    }

    public function testInvalidDomain(): void
    {
        self::expectException(CookieException::class);
        self::expectExceptionMessageIs('example;.com is not a valid domain.');
        new Cookie('example', 'Test value', domain: 'example;.com');
    }

    public function testEmptyDomain(): void
    {
        self::expectException(CookieException::class);
        self::expectExceptionMessageIs('Domain cannot be empty.');
        new Cookie('example', 'Test value', domain: '');
    }
}
