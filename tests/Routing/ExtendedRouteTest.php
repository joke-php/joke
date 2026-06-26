<?php

declare(strict_types=1);

namespace Routing;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Vasoft\Joke\Container\ParameterResolver;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Routing\Route;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;

class SimpleParameterBag
{
    public function __construct(private array $parameters = []) {}

    public function getAll(): array
    {
        return $this->parameters;
    }
}

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Routing\Route
 */
final class ExtendedRouteTest extends TestCase
{
    private ServiceContainer $container;
    private ParameterResolver $resolver;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(ParameterResolver::class);

        $this->container = self::createStub(ServiceContainer::class);
        $this->container->method('getParameterResolver')
            ->willReturn($this->resolver);

        $this->request = self::createStub(HttpRequest::class);
        $this->request->method('getPath')->willReturn('/test');
    }

    public function testRunThrowsExceptionWhenStringHandlerIsNonInvokableClass(): void
    {
        $handler = \stdClass::class;

        $route = new Route(
            $this->container,
            '/test',
            HttpMethod::GET,
            $handler,
        );

        $this->resolver->expects(self::once())
            ->method('resolveForConstructor')
            ->with($handler, [])
            ->willReturn([]);

        $this->expectException(ParameterResolveException::class);
        $this->expectExceptionMessageIs('Not a callable handler');

        $route->run($this->request);
    }

    public function testRunThrowsExceptionWhenStringHandlerIsNotExistingFunction(): void
    {
        $handler = 'non_existent_function_xyz_123';

        $route = new Route(
            $this->container,
            '/test',
            HttpMethod::GET,
            $handler,
        );

        $this->resolver->expects(self::once())
            ->method('resolveForCallable')
            ->with($handler, [])
            ->willReturn([]);

        $this->expectException(ParameterResolveException::class);
        $this->expectExceptionMessageIs('Not a callable handler');

        $route->run($this->request);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunThrowsExceptionForNonCallableObjectHandler(): void
    {
        $handler = new \stdClass();

        $route = new Route(
            $this->container,
            '/test',
            HttpMethod::GET,
            $handler,
        );

        $this->expectException(JokeException::class);
        $this->expectExceptionMessageIs('Unsupported route handler.');

        $route->run($this->request);
    }
}
