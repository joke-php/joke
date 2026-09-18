<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\JsonResponse;
use Vasoft\Joke\Routing\Route;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Tests\Fixtures\FakeExample;
use Vasoft\Joke\Tests\Fixtures\Controllers\InvokeController;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Routing\Route
 */
final class RouteTest extends TestCase
{
    protected static ServiceContainer $serviceContainer;

    public static function setUpBeforeClass(): void
    {
        self::$serviceContainer = new ServiceContainer();
        parent::setUpBeforeClass();
    }

    public function testCompilePattern(): void
    {
        $route = new Route(
            self::$serviceContainer,
            '/api/{section}/{num:int}/{page:int}/{filter:slug}',
            HttpMethod::GET,
            static function (): void {},
        );
        self::assertSame(
            '#^/api/(?P<section>[^/]+)/(?P<num>\d+)/(?P<page>\d+)/(?P<filter>[a-z0-9\-_]+)$#i',
            $route->compiledPattern,
        );
    }

    public function testWithMethod(): void
    {
        $route = new Route(self::$serviceContainer, '/', HttpMethod::GET, static function (): void {});
        self::assertSame(HttpMethod::GET, $route->method);
        $route2 = $route->withMethod(HttpMethod::POST);
        self::assertSame(HttpMethod::POST, $route2->method);
        self::assertNotSame($route, $route2);
    }

    public function testMatchesSuccess(): void
    {
        $route = new Route(
            self::$serviceContainer,
            '/api/{section}/{num:int}/{page:int}/{filter:slug}',
            HttpMethod::GET,
            static function (): void {},
        );
        $request = new HttpRequest(server: [
            'REQUEST_URI' => '/api/orders/1/2/closed',
        ]);
        self::assertTrue($route->matches($request));
        self::assertSame([
            'section' => 'orders',
            'num' => '1',
            'page' => '2',
            'filter' => 'closed',
        ], $request->props->getAll());
    }

    public function testMatchesFail(): void
    {
        $route = new Route(
            self::$serviceContainer,
            '/api/{section}/{num:int}/{page:int}/{filter:slug}',
            HttpMethod::GET,
            static function (): void {},
        );
        $request = new HttpRequest(server: [
            'REQUEST_URI' => '/rest/orders/1/2/closed',
        ]);
        self::assertFalse($route->matches($request));
    }

    #[DataProvider('provideRunCases')]
    public function testRun($closure): void
    {
        $route = new Route(self::$serviceContainer, '/api/{num:int}/{page:int}', HttpMethod::GET, $closure);
        $request = new HttpRequest(server: ['REQUEST_URI' => '/api/1/2']);
        $route->matches($request);
        self::assertSame(3, $route->run($request));
    }

    public static function provideRunCases(): iterable
    {
        $testObject = new FakeExample(0);

        return [
            [
                static fn($num, $page) => $num + $page,
            ],
            [FakeExample::exampleClosureStatic(...)],
            [$testObject->exampleClosure(...)],
            ['\Vasoft\Joke\Tests\Fixtures\FakeExample::exampleClosureStatic'],
            ['exampleClosureFunction'],
            [[FakeExample::class, 'exampleClosureStatic']],
            [[$testObject, 'exampleClosure']],
        ];
    }

    public function testInvoke(): void
    {
        $route = new Route(self::$serviceContainer, '/invoke/{prop}', HttpMethod::GET, InvokeController::class);
        $request = new HttpRequest(server: ['REQUEST_URI' => '/invoke/property']);
        $route->matches($request);
        self::assertSame([
            'ServiceContainer' => spl_object_id(self::$serviceContainer),
            'propValue' => 'property',
        ], $route->run($request));
    }

    public function testMergeGroups(): void
    {
        $route = new Route(self::$serviceContainer, '/invoke/{prop}', HttpMethod::GET, InvokeController::class);
        $route->addGroup('test1');
        self::assertSame(['test1'], $route->getGroups());
        $route->mergeGroup(['test1', 'test2']);
        self::assertSame(['test1', 'test2'], $route->getGroups());
    }

    #[TestDox('Передача пустой строки в качестве класса ответа по умолчанию эквивалентно null')]
    public function testSetDefaultResponseClassEmptyStringToNull(): void
    {
        $route = new Route(self::$serviceContainer, '/invoke/{prop}', HttpMethod::GET, InvokeController::class);
        $route->setDefaultResponseClass(JsonResponse::class);
        self::assertSame(JsonResponse::class, $route->defaultResponseClass);
        $route->setDefaultResponseClass(' ');
        self::assertNull($route->defaultResponseClass);
    }

    #[TestDox('setDefaultGroup устанавливает основную группу маршрута')]
    public function testSetDefaultGroup(): void
    {
        $route = new Route(self::$serviceContainer, '/invoke/{prop}', HttpMethod::GET, InvokeController::class);
        $route
            ->addGroup('test1')
            ->addGroup('test2')
            ->addGroup('test3')
            ->setDefaultGroup('test2');
        self::assertSame('test2', $route->defaultGroup);
    }

    #[TestDox('setDefaultGroup если группа еще не добавлена выбрасывается исключение')]
    public function testSetDefaultGroupExceptionIfNotDefinedGroup(): void
    {
        $route = new Route(self::$serviceContainer, '/invoke/{prop}', HttpMethod::GET, InvokeController::class);
        $route->addGroup('test1');
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('Group "test2" does not exist.');
        $route->setDefaultGroup('test2');
    }

    #[TestDox('Если группа не установлена - возвращается первая заданная')]
    public function testDefaultGroup(): void
    {
        $route = new Route(self::$serviceContainer, '/invoke/{prop}', HttpMethod::GET, InvokeController::class);
        $route
            ->addGroup('test1')
            ->addGroup('test2')
            ->addGroup('test3');
        self::assertSame('test1', $route->defaultGroup);
    }
}
