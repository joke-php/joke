<?php

declare(strict_types=1);

namespace Vasoft\Joke\Middleware;

use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;

/**
 * Коллекция middleware.
 *
 * Обеспечивает хранение набора middleware и единственность экземпляров для именованных
 */
class MiddlewareCollection
{
    /**
     * @var array<MiddlewareDto>
     */
    private array $middlewares = [];

    public function __construct(private readonly ServiceContainer $container) {}

    /**
     * Добавляет middleware в коллекцию
     * Если middleware именованный производится поиск, и, если найден, производится замена middleware и групп в той же позиции где
     * и был найден.
     *
     * @param class-string|MiddlewareInterface $middleware Мидлвар
     * @param array<string>                    $groups     Привязка middleware к набору групп. (Имеет значение в middleware привязанных к маршруту)
     *
     * @return $this
     */
    public function addMiddleware(
        callable|MiddlewareInterface|string $middleware,
        string $name = '',
        array $groups = [],
    ): static {
        if (is_callable($middleware)) {
            $middleware = new ClosureMiddleware($middleware, $this->container);
        }
        if ('' === $name) {
            $this->middlewares[] = new MiddlewareDto($middleware, groups: $groups);

            return $this;
        }
        foreach ($this->middlewares as $exits) {
            if ($name === $exits->name) {
                $exits->middleware = $middleware;
                $exits->groups = $groups;

                return $this;
            }
        }
        $this->middlewares[] = new MiddlewareDto($middleware, $name, $groups);

        return $this;
    }

    /**
     * @return MiddlewareDto[] Список middleware
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Клонирует коллекцию с дополнительными наборами, для именованных обеспечивается единственность экземпляра и
     * позиция в списке как при первом добавлении.
     *
     * @param array<MiddlewareDto> $middlewares
     */
    public function withMiddlewares(array $middlewares): static
    {
        $instance = clone $this;
        foreach ($middlewares as $middleware) {
            $instance->addMiddleware($middleware->middleware, $middleware->name, $middleware->groups);
        }

        return $instance;
    }

    /**
     * Возвращает развернутый список middleware для запуска.
     *
     * @param array<string> $group Массив групп для фильтрации. Если передан пустой массив будут возвращены только
     *                             middleware с пустым списком групп, если не пустой, то с пересечением либо если у middleware
     *                             нет групп
     *
     * @return array<class-string|MiddlewareInterface>
     */
    public function getArrayForRun(array $group = []): array
    {
        $filtered = empty($group)
            ? array_filter(
                $this->getMiddlewares(),
                static fn($middleware) => empty($middleware->groups),
            )
            : array_filter(
                $this->getMiddlewares(),
                static fn($middleware) => empty($middleware->groups)
                    || !empty(array_intersect($middleware->groups, $group)),
            );
        $result = array_map(static fn(MiddlewareDto $middleware) => $middleware->middleware, $filtered);

        return array_reverse($result);
    }
}
