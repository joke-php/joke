<?php

declare(strict_types=1);

namespace Vasoft\Joke\Middleware;

use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;

/**
 * Адаптер для выполнения middleware, заданного в виде callable (замыкания).
 *
 * Обеспечивает ленивое создание экземпляра middleware через контейнер
 * непосредственно перед его выполнением.
 */
readonly class ClosureMiddleware implements MiddlewareInterface
{
    /**
     * @var callable
     */
    private mixed $factory;

    /**
     * @param callable         $factory   Замыкание или callable-фабрика
     * @param ServiceContainer $container Контейнер для разрешения зависимостей фабрики
     */
    public function __construct(
        callable $factory,
        private ServiceContainer $container,
    ) {
        $this->factory = $factory;
    }

    /**
     * @throws ConfigException           Если результат работы фабрики не реализует MiddlewareInterface
     * @throws ParameterResolveException В случае ошибок автосвязывания параметров
     */
    public function handle(HttpRequest $request, callable $next): mixed
    {
        $middleware = $this->container->make($this->factory);
        if (!$middleware instanceof MiddlewareInterface) {
            throw new ConfigException('Middleware must implement MiddlewareInterface');
        }

        return $middleware->handle($request, $next);
    }
}
