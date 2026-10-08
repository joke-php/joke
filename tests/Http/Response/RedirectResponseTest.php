<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Response\RedirectResponse;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Response\ResponseStatus;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\RedirectResponse
 */
#[TestDox('RedirectResponse - редирект')]
#[CoversClass(RedirectResponse::class)]
final class RedirectResponseTest extends TestCase
{
    use PHPMock;

    private static CookieCollection $cookies;

    public static function setUpBeforeClass(): void
    {
        self::$cookies = new CookieCollection(new CookieConfig(), true);
    }

    #[TestDox('По умолчанию отдает статус 302')]
    public function testDefaultStatus302(): void
    {
        $response = new RedirectResponse(self::$cookies, '/login');
        self::assertSame(ResponseStatus::FOUND, $response->status);
    }

    #[TestDox('URL для переадресации не может быть пустым')]
    public function testExceptionIfEmptyURL(): void
    {
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('Redirect URL cannot be empty.');
        new RedirectResponse(self::$cookies, '');
    }

    #[TestDox('Статусом можно управлять через конструктор')]
    public function testDefaultStatusCustom(): void
    {
        $response = new RedirectResponse(self::$cookies, '/login', ResponseStatus::SEE_OTHER);
        self::assertSame(ResponseStatus::SEE_OTHER, $response->status);
    }

    #[TestDox('Тип контента должен возвращать пустую строку')]
    public function testDefaultContentType(): void
    {
        $response = new RedirectResponse(self::$cookies, '/login');
        self::assertSame('', $response->headers->contentType);
    }

    #[TestDox('Тело ответа пустая строка')]
    public function testGetBody(): void
    {
        $response = new RedirectResponse(self::$cookies, '/login');
        $response->setBody('example');
        self::assertSame('', $response->getBody());
        self::assertSame('', $response->getBodyAsString());
    }

    #[TestDox('Может отправлять куки')]
    #[RunInSeparateProcess]
    public function testSendCookies(): void
    {
        $headers = [];
        $mockHeader = self::getFunctionMock('Vasoft\Joke\Http\Response', 'header');
        $mockHeader->expects(self::atLeastOnce())->willReturnCallback(
            static function (string $value) use (&$headers): void {
                $headers[] = $value;
            },
        );

        $response = new RedirectResponse(self::$cookies, '/login');
        $response->cookies->add('cookie1', 'value1');

        $response->send();

        $hasCookie1 = false;

        foreach ($headers as $header) {
            if (str_starts_with($header, 'Set-Cookie: cookie1=value1;')) {
                $hasCookie1 = true;
            }
        }
        self::assertTrue($hasCookie1);
    }

    #[TestDox('Добавляет необходимые для переадресации заголовки')]
    #[RunInSeparateProcess]
    public function testSetRedirectHeaders(): void
    {
        $headers = [];
        $mockHeader = self::getFunctionMock('Vasoft\Joke\Http\Response', 'header');
        $mockHeader->expects(self::atLeastOnce())->willReturnCallback(
            static function (string $value) use (&$headers): void {
                $headers[] = $value;
            },
        );
        $response = new RedirectResponse(self::$cookies, '/login');
        $response->send();

        $expected = ['location: /login', 'HTTP/1.1 302 Found'];
        sort($headers);
        sort($expected);

        self::assertSame($expected, $headers);
    }
}
