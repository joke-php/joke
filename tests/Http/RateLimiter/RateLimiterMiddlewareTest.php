<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\RateLimiter;

use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\RateLimiter\Exceptions\RateLimiterException;
use Vasoft\Joke\Http\RateLimiter\RateLimiterConfig;
use Vasoft\Joke\Http\RateLimiter\RateLimiterMiddleware;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Http\Response\ResponseStatus;
use Vasoft\Joke\Tests\Fixtures\Http\RateLimiter\ArrayStorage;
use Vasoft\Joke\Tests\Fixtures\Http\RateLimiter\TestingIdentifier;

/**
 * @internal
 *
 * @coversNothing
 */
final class RateLimiterMiddlewareTest extends TestCase
{
    private static ServiceContainer $container;
    private static ApplicationConfig $appConfig;
    private static HttpRequest $request;

    public static function setUpBeforeClass(): void
    {
        self::$appConfig = new ApplicationConfig()->setResponseClass(HtmlResponse::class);
        self::$container = new ServiceContainer();
        self::$container->registerSingleton(CookieConfig::class, CookieConfig::class);
        self::$request = new HttpRequest();
        self::$container->registerSingleton(Request::class, self::$request);
    }

    #[TestDox('По умолчанию отключен и идентификация не выполняется')]
    public function testNotEnabled(): void
    {
        $identifier = new TestingIdentifier();
        $mw = new RateLimiterMiddleware(
            new ArrayStorage(),
            $identifier,
            new RateLimiterConfig(),
            new ResponseBuilder(self::$appConfig, self::$container),
        );
        $result = $mw->handle(self::$request, static fn() => 'OK');
        self::assertSame('OK', $result);
        self::assertSame(0, $identifier->count);
    }

    #[TestDox('При исключении в инкременторе просто выполняется следующий')]
    public function testIncrementorFail(): void
    {
        $config = new RateLimiterConfig()->setEnabled(true)->setWithHeaders(true);
        $identifier = new TestingIdentifier();
        $mw = new RateLimiterMiddleware(
            new ArrayStorage(exception: true),
            $identifier,
            $config,
            new ResponseBuilder(self::$appConfig, self::$container),
        );
        $result = $mw->handle(self::$request, static fn() => 'OK');
        self::assertSame('OK', $result);
        self::assertSame(1, $identifier->count);
    }

    #[TestDox('Блокировка без заголовков не выполняет основной обработчик и не отправляет заголовки')]
    public function testBlockedWithOutHeaders(): void
    {
        $config = new RateLimiterConfig()->setEnabled(true);
        $identifier = new TestingIdentifier();
        $mw = new RateLimiterMiddleware(
            new ArrayStorage(1000, 11),
            $identifier,
            $config,
            new ResponseBuilder(self::$appConfig, self::$container),
            10,
        );

        /** @var HtmlResponse $result */
        $result = $mw->handle(self::$request, static fn() => 'OK');
        self::assertSame('', $result->getBodyAsString());
        $headers = $result->headers->getAll();
        self::assertArrayNotHasKey('x-rate-limit-limit', $headers);
        self::assertArrayNotHasKey('x-rate-limit-remaining', $headers);
        self::assertArrayNotHasKey('x-rate-limit-reset', $headers);
        self::assertArrayNotHasKey('retry-after', $headers);
        self::assertSame(ResponseStatus::TOO_MANY_REQUESTS, $result->status);
    }

    #[TestDox('Блокировка с заголовками не выполняет основной обработчик и отправляет заголовки')]
    public function testBlockedWithHeaders(): void
    {
        $config = new RateLimiterConfig()->setEnabled(true)->setWithHeaders(true);
        $identifier = new TestingIdentifier();
        $mw = new RateLimiterMiddleware(
            new ArrayStorage(1000, 11),
            $identifier,
            $config,
            new ResponseBuilder(self::$appConfig, self::$container),
            10,
        );

        /** @var HtmlResponse $result */
        $result = $mw->handle(self::$request, static fn() => 'OK');
        self::assertSame('', $result->getBodyAsString());
        self::assertSame(1, $identifier->count);
        $headers = $result->headers->getAll();
        self::assertArrayHasKey('x-rate-limit-limit', $headers);
        self::assertArrayHasKey('x-rate-limit-remaining', $headers);
        self::assertArrayHasKey('x-rate-limit-reset', $headers);
        self::assertArrayHasKey('retry-after', $headers);
        self::assertSame(ResponseStatus::TOO_MANY_REQUESTS, $result->status);
    }

    #[TestDox('Удачная проверка без заголовков возвращает результат следующего обработчика')]
    public function testSuccessWithOutHeaders(): void
    {
        $config = new RateLimiterConfig()->setEnabled(true);
        $identifier = new TestingIdentifier();
        $mw = new RateLimiterMiddleware(
            new ArrayStorage(),
            $identifier,
            $config,
            new ResponseBuilder(self::$appConfig, self::$container),
            10,
        );

        $result = $mw->handle(self::$request, static fn() => 'OK');
        self::assertSame('OK', $result);
    }

    #[TestDox('Удачная проверка с заголовками возвращает объект HttpResponse и добавляет заголовки')]
    public function testSuccessWithHeaders(): void
    {
        $config = new RateLimiterConfig()->setEnabled(true)->setWithHeaders(true);
        $identifier = new TestingIdentifier();
        $time = time();
        $mw = new RateLimiterMiddleware(
            new ArrayStorage($time),
            $identifier,
            $config,
            new ResponseBuilder(self::$appConfig, self::$container),
            99,
            100,
        );

        /** @var HtmlResponse $result */
        $result = $mw->handle(self::$request, static fn() => 'OK');
        self::assertSame('OK', $result->getBodyAsString());
        $headers = $result->headers->getAll();
        self::assertArrayHasKey('x-rate-limit-limit', $headers);
        self::assertArrayHasKey('x-rate-limit-remaining', $headers);
        self::assertArrayHasKey('x-rate-limit-reset', $headers);
        self::assertArrayNotHasKey('retry-after', $headers);

        self::assertSame(99, $headers['x-rate-limit-limit']);
        self::assertSame(98, $headers['x-rate-limit-remaining']);

        $expectedMin = $time + 100;
        $expectedMax = $time + 100 + 1;

        self::assertGreaterThanOrEqual($expectedMin, $headers['x-rate-limit-reset']);
        self::assertLessThanOrEqual($expectedMax, $headers['x-rate-limit-reset']);

        self::assertSame(ResponseStatus::OK, $result->status);
    }

    #[TestDox('выбрасывает исключение если передан некорректный лимит')]
    public function testExceptionOnWrongLimit(): void
    {
        $config = new RateLimiterConfig()->setEnabled(true)->setWithHeaders(true);
        $identifier = new TestingIdentifier();

        self::expectException(RateLimiterException::class);
        self::expectExceptionMessageIs('Rate limit must be greater than zero.');
        $mw = new RateLimiterMiddleware(
            new ArrayStorage(),
            $identifier,
            $config,
            new ResponseBuilder(self::$appConfig, self::$container),
            0,
        );
    }

    #[TestDox('выбрасывает исключение если передан некорректное окно')]
    public function testExceptionOnWrongWindow(): void
    {
        $config = new RateLimiterConfig()->setEnabled(true)->setWithHeaders(true);
        $identifier = new TestingIdentifier();

        self::expectException(RateLimiterException::class);
        self::expectExceptionMessageIs('Window duration must be greater than zero.');
        new RateLimiterMiddleware(
            new ArrayStorage(),
            $identifier,
            $config,
            new ResponseBuilder(self::$appConfig, self::$container),
            window: 0,
        );
    }
}
