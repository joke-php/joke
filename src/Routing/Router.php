<?php

declare(strict_types=1);

namespace Vasoft\Joke\Routing;

use Vasoft\Joke\Contract\Routing\RouterInterface;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Routing\Exceptions\NotFoundException;
use Vasoft\Joke\Container\ServiceContainer;

/**
 *  Управляет HTTP-маршрутами и направляет входящие запросы соответствующим обработчикам.
 *
 *  Класс должен поддерживать именованные маршруты, несколько HTTP-методов для одного маршрута
 *  и использует ServiceContainer для разрешения и вызова коллбэков контроллеров.
 *  Маршруты хранятся с индексацией по методу и имени для эффективного сопоставления
 *  и генерации URL.
 *
 * @todo Продумать вопрос аналога маршрутов для консоли. Возможно стоит отойти от HttpMethod на string для универсальности
 */
class Router implements RouterInterface
{
    /** @var array<string> Автоматически добавляемые группы для маршрутов */
    private array $autoGroups = [];
    /**
     * Хранилище маршрутов сгруппированных по HTTP методу (например, 'GET', 'POST').
     *
     * Структура: ['GET' => [Route, Route, ...], 'POST' => [...], ...]
     *
     * @var array<string, list<Route>>
     */
    protected array $routes = [];
    /**
     * Хранилище именованных маршрутов для легкого доступа по имени.
     *
     * @var array<string, Route>
     */
    protected array $namedRoutes = [];

    /**
     * @param ServiceContainer $serviceContainer DI-контейнер, используемый для разрешения зависимостей маршрутов
     */
    public function __construct(protected readonly ServiceContainer $serviceContainer) {}

    public function post(string $path, array|object|string $handler, string $name = ''): Route
    {
        return $this->match([HttpMethod::POST], $path, $handler, $name);
    }

    public function get(string $path, array|object|string $handler, string $name = ''): Route
    {
        return $this->match([HttpMethod::GET], $path, $handler, $name);
    }

    public function put(string $path, array|object|string $handler, string $name = ''): Route
    {
        return $this->match([HttpMethod::PUT], $path, $handler, $name);
    }

    public function delete(string $path, array|object|string $handler, string $name = ''): Route
    {
        return $this->match([HttpMethod::DELETE], $path, $handler, $name);
    }

    public function patch(string $path, array|object|string $handler, string $name = ''): Route
    {
        return $this->match([HttpMethod::PATCH], $path, $handler, $name);
    }

    public function options(string $path, array|object|string $handler, string $name = ''): Route
    {
        return $this->match([HttpMethod::OPTIONS], $path, $handler, $name);
    }

    public function head(string $path, array|object|string $handler, string $name = ''): Route
    {
        return $this->match([HttpMethod::HEAD], $path, $handler, $name);
    }

    public function any(string $path, array|object|string $handler, string $name = ''): Route
    {
        return $this->match(HttpMethod::cases(), $path, $handler, $name);
    }

    public function match(array $methods, string $path, array|object|string $handler, string $name = ''): Route
    {
        if ('' === $name) {
            $name = $this->getRouteIndex($methods, $path);
        }
        $this->namedRoutes[$name] = new Route($this->serviceContainer, $path, $methods[0], $handler)->mergeGroup(
            $this->autoGroups,
        );
        foreach ($methods as $method) {
            $this->routes[$method->value][] = &$this->namedRoutes[$name];
        }

        return $this->namedRoutes[$name];
    }

    /**
     * Генерация имени для безымянного маршрута.
     *
     * Формат: "method1#method2|/path"
     *
     * @param list<HttpMethod> $methods Список метод обрабатываемых маршрутом
     * @param string           $path    паттерн URI (например, '/users')
     *
     * @return string Имя маршрута
     */
    private function getRouteIndex(array $methods, string $path): string
    {
        $parts = array_map(static fn(HttpMethod $part) => strtolower($part->value), $methods);

        return implode('#', $parts) . '|' . $path;
    }

    /**
     * @inherit
     */
    public function dispatch(HttpRequest $request): mixed
    {
        $route = $this->findRoute($request);
        if (null === $route) {
            throw new NotFoundException('Route not found');
        }

        return $route->run($request);
    }

    public function findRoute(HttpRequest $request): ?Route
    {
        $method = $request->method;
        if (!isset($this->routes[$method->value])) {
            if (HttpMethod::OPTIONS === $method) {
                return $this->getDefaultOptionsRoute();
            }

            return null;
        }
        foreach ($this->routes[$method->value] as $route) {
            if ($route->matches($request)) {
                return $method !== $route->method ? $route->withMethod($method) : $route;
            }
        }

        return null;
    }

    private function getDefaultOptionsRoute(): Route
    {
        /** @var ResponseBuilder $responseBuilder */
        $responseBuilder = $this->serviceContainer->get(ResponseBuilder::class);

        return new Route(
            $this->serviceContainer,
            '{*}',
            HttpMethod::OPTIONS,
            static function () use ($responseBuilder) {
                $response = $responseBuilder->makeDefault();

                $allowed = implode(
                    ', ',
                    array_map(static fn(HttpMethod $method) => $method->value, HttpMethod::cases()),
                );
                $response->headers->set('Allow', $allowed);

                return $response;
            },
        );
    }

    public function route(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    public function addAutoGroups(array $groups): static
    {
        $this->autoGroups = array_merge($this->autoGroups, $groups);

        return $this;
    }

    public function cleanAutoGroups(): static
    {
        $this->autoGroups = [];

        return $this;
    }
}
