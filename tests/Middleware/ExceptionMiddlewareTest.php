<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Application\Application;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Logging\LoggerInterface;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\JsonResponse;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Logging\NullLogger;
use Vasoft\Joke\Middleware\ExceptionMiddleware;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\ResponseStatus;
use Vasoft\Joke\Routing\Exceptions\NotFoundException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Middleware\ExceptionMiddleware
 */
final class ExceptionMiddlewareTest extends TestCase
{
    private static ServiceContainer $container;

    public static function setUpBeforeClass(): void
    {
        self::$container = new ServiceContainer();
        new Application(dirname(__DIR__, 2), self::$container);
        self::$container->registerSingleton(LoggerInterface::class, NullLogger::class);
        self::$container->registerAlias('logger', LoggerInterface::class);
        self::$container->registerSingleton(
            ResponseBuilder::class,
            new ResponseBuilder(new ApplicationConfig(), self::$container),
        );
    }

    public function testHandleSuccess(): void
    {
        $foo = static fn() => 'Success';
        $middleware = new ExceptionMiddleware(self::$container);
        $output = $middleware->handle(new HttpRequest(), $foo);
        self::assertSame('Success', $output);
    }

    public function testHandleJokeException(): void
    {
        $foo = static function (): void {
            throw new NotFoundException('Route not found');
        };

        $middleware = new ExceptionMiddleware(self::$container);
        $output = $middleware->handle(new HttpRequest(), $foo);
        self::assertInstanceOf(HtmlResponse::class, $output);
        self::assertSame(ResponseStatus::NOT_FOUND, $output->status);
        self::assertSame('Route not found', $output->getBody());
    }

    public function testHandlePHPExceptionAndJsonResponse(): void
    {
        $appConfig = new ApplicationConfig()->setResponseClass(JsonResponse::class);
        $container = new ServiceContainer();
        new Application(dirname(__DIR__, 2), $container);
        $container->registerSingleton(LoggerInterface::class, NullLogger::class);
        $container->registerAlias('logger', LoggerInterface::class);
        $container->registerSingleton(ResponseBuilder::class, new ResponseBuilder($appConfig, $container));

        $foo = static function (): void {
            throw new \Exception('Some exception');
        };
        $middleware = new ExceptionMiddleware($container);
        $output = $middleware->handle(new HttpRequest(), $foo);
        self::assertInstanceOf(JsonResponse::class, $output);
        self::assertSame(ResponseStatus::INTERNAL_SERVER_ERROR, $output->status);
        self::assertSame(['message' => 'Some exception'], $output->getBody());
    }

    public function testExceptionMessageInProduction(): void
    {
        $envMock = self::getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $envMock->expects(self::once())->method('isProduction')->willReturn(true);
        $appConfig = new ApplicationConfig()->setResponseClass(JsonResponse::class);
        $container = new ServiceContainer();
        new Application(dirname(__DIR__, 2), $container);
        $container->registerSingleton(LoggerInterface::class, NullLogger::class);
        $container->registerAlias('logger', LoggerInterface::class);
        $container->registerSingleton(ResponseBuilder::class, new ResponseBuilder($appConfig, $container));
        $container->registerSingleton(Environment::class, $envMock);

        $foo = static function (): void {
            throw new \Exception('Some exception');
        };
        $middleware = new ExceptionMiddleware($container);
        $output = $middleware->handle(new HttpRequest(), $foo);
        self::assertInstanceOf(JsonResponse::class, $output);
        self::assertSame(ResponseStatus::INTERNAL_SERVER_ERROR, $output->status);
        self::assertSame(['message' => 'Internal Error'], $output->getBody());
    }
}
