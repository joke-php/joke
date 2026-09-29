<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Middleware;

use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Middleware\ClosureMiddleware;
use Vasoft\Joke\Middleware\ExceptionMiddleware;
use Vasoft\Joke\Middleware\MiddlewareCollection;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Middleware\StdMiddleware;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Tests\Fixtures\Middlewares\SingleMiddleware;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Middleware\MiddlewareCollection
 */
final class MiddlewareCollectionTest extends TestCase
{
    private function getMiddleware(int $id): MiddlewareInterface
    {
        return new readonly class ($id) implements MiddlewareInterface {
            public function __construct(private int $id) {}

            public function handle(HttpRequest $request, callable $next): mixed
            {
                return $next($request) . 'example' . $this->id;
            }
        };
    }

    public function testAddMiddleware(): void
    {
        $testMiddleware1 = $this->getMiddleware(1);
        $testMiddleware2 = $this->getMiddleware(2);
        $collection = new MiddlewareCollection(new ServiceContainer());
        $collection->addMiddleware(ExceptionMiddleware::class);
        $collection->addMiddleware($testMiddleware1, StdMiddleware::SESSION->value);

        $list = $collection->getMiddlewares();
        self::assertCount(2, $list);
        self::assertSame($testMiddleware1, $list[1]->middleware);

        $collection->addMiddleware($testMiddleware2, StdMiddleware::SESSION->value);
        $list = $collection->getMiddlewares();
        self::assertCount(2, $list, 'Must replace named middleware');
        self::assertSame($testMiddleware2, $list[1]->middleware, 'Must replace named middleware');

        $forRun = $collection->getArrayForRun();
        self::assertCount(2, $forRun);
        self::assertSame(
            $testMiddleware2,
            $forRun[0],
            'Must return the reversed array. The test middleware must be first',
        );
        self::assertSame(
            ExceptionMiddleware::class,
            $forRun[1],
            'Must return the reversed array. The ExceptionMiddleware middleware must be second',
        );

        $testMiddleware3 = $this->getMiddleware(3);
        $testMiddleware4 = $this->getMiddleware(4);

        $collection2 = new MiddlewareCollection(new ServiceContainer());
        $collection2->addMiddleware($testMiddleware3);
        $collection2->addMiddleware($testMiddleware4, StdMiddleware::SESSION->value);

        $collection3 = $collection->withMiddlewares($collection2->getMiddlewares());

        self::assertNotSame($collection, $collection3, 'Must create new instance');
        $list = $collection3->getMiddlewares();
        self::assertCount(3, $list);
        self::assertSame(ExceptionMiddleware::class, $list[0]->middleware);
        self::assertSame($testMiddleware4, $list[1]->middleware);
        self::assertSame($testMiddleware3, $list[2]->middleware);
    }

    public function testFilterMiddleware(): void
    {
        $testMiddleware1 = $this->getMiddleware(1);
        $testMiddleware2 = $this->getMiddleware(2);
        $testMiddleware3 = $this->getMiddleware(3);
        $testMiddleware4 = $this->getMiddleware(4);

        $collection1 = new MiddlewareCollection(new ServiceContainer());
        $collection1->addMiddleware($testMiddleware1, groups: ['post']);
        $collection1->addMiddleware($testMiddleware2, 'singleton', ['example']);
        $collection1->addMiddleware($testMiddleware3, groups: ['example']);

        $collection2 = new MiddlewareCollection(new ServiceContainer());
        $collection2->addMiddleware($testMiddleware4, 'singleton', ['post', 'token']);

        $collection3 = $collection1->withMiddlewares($collection2->getMiddlewares());

        $list = $collection3->getArrayForRun();
        self::assertCount(0, $list);
        $list = $collection3->getArrayForRun(['post']);
        self::assertCount(2, $list);

        $list = $collection3->getArrayForRun(['example']);
        self::assertCount(1, $list);

        $list = $collection3->getArrayForRun(['example', 'token']);
        self::assertCount(2, $list);
    }

    #[TestDox('Оборачивает замыкание в Middleware обертку')]
    public function testWrapCallable(): void
    {
        $collection = new MiddlewareCollection(new ServiceContainer());
        $collection->addMiddleware(static fn() => new SingleMiddleware());
        $list = $collection->getMiddlewares();
        self::assertCount(1, $list);
        self::assertInstanceOf(ClosureMiddleware::class, $list[0]->middleware);
    }
}
