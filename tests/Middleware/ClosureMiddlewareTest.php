<?php

declare(strict_types=1);

namespace Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Middleware\ClosureMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Middleware\ClosureMiddleware
 */
#[TestDox('ClosureMiddleware - адаптер для замыканий')]
#[CoversClass(ClosureMiddleware::class)]
final class ClosureMiddlewareTest extends TestCase
{
    #[TestDox('При инициализации экземпляра проверяет тип')]
    public function testCheckInstanceType(): void
    {
        $middleware = new ClosureMiddleware(static fn() => new \stdClass(), new ServiceContainer());
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('Middleware must implement MiddlewareInterface');
        $middleware->handle(new HttpRequest(), static fn(Request $request, callable $next) => '');
    }

    #[TestDox('Экземпляр создается при выполнении')]
    public function testInitOnHandler(): void
    {
        TestingClosureClosureMiddleware::$instanceCount = 0;
        $middleware = new ClosureMiddleware(
            static fn() => new TestingClosureClosureMiddleware(),
            new ServiceContainer(),
        );
        self::assertSame(0, TestingClosureClosureMiddleware::$instanceCount);
        $middleware->handle(new HttpRequest(), static fn() => '');
        self::assertSame(1, TestingClosureClosureMiddleware::$instanceCount);
    }

    #[TestDox('Выполняет и использует Di')]
    public function testInitAndUseDI(): void
    {
        $someObject = new TestingClosureClosureMiddleware();
        $container = new ServiceContainer();
        $container->registerSingleton(TestingClosureClosureMiddleware::class, $someObject);
        $middleware = new ClosureMiddleware(
            static function (TestingClosureClosureMiddleware $otherObject) {
                $result = new TestingClosureClosureMiddleware();
                $result->object = $otherObject;

                return $result;
            },
            $container,
        );
        $result = $middleware->handle(new HttpRequest(), static fn() => '');
        self::assertSame($someObject, $result);
    }

    #[TestDox('Передает следующую middleware')]
    public function testRunNextMiddleware(): void
    {
        $someObject = new TestingClosureClosureMiddleware();
        $container = new ServiceContainer();
        $container->registerSingleton(TestingClosureClosureMiddleware::class, $someObject);
        $middleware = new ClosureMiddleware(
            static fn(TestingClosureClosureMiddleware $otherObject) => new TestingClosureClosureMiddleware(),
            $container,
        );
        $result = $middleware->handle(new HttpRequest(), static fn() => ' next');
        self::assertSame('test next', $result);
    }
}

final class TestingClosureClosureMiddleware implements MiddlewareInterface
{
    public static int $instanceCount = 0;
    public mixed $object = null;

    public function __construct()
    {
        ++self::$instanceCount;
    }

    public function handle(HttpRequest $request, callable $next): mixed
    {
        if (null !== $this->object) {
            return $this->object;
        }

        return 'test' . $next();
    }
}
