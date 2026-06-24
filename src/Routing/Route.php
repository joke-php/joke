<?php

declare(strict_types=1);

namespace Vasoft\Joke\Routing;

use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Contract\Routing\RouteInterface;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Middleware\MiddlewareCollection;
use Vasoft\Joke\Middleware\MiddlewareDto;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Container\ServiceContainer;

/**
 * Реализация маршрута HTTP-запроса.
 *
 * Представляет собой сопоставление URI-паттерна, HTTP-метода и обработчика.
 * Поддерживает параметризованные маршруты, группы, middleware и различные типы обработчиков.
 */
class Route implements RouteInterface
{
    /**
     * Коллекция middleware, привязанных к маршруту.
     */
    protected MiddlewareCollection $middlewares;

    /**
     * Список групп, к которым принадлежит маршрут.
     *
     * Используется для фильтрации middleware на уровне маршрутизатора.
     *
     * @var array<string, bool>
     */
    protected array $groups = [];
    /**
     * Правила валидации параметров маршрута.
     *
     * Используются для построения регулярных выражений при компиляции URI-паттерна.
     * Формат: /catalog/{code:slug} — правило 'slug' применяется к параметру 'code'.
     *
     * @var array<string, string>
     */
    protected array $rules = [
        'default' => '[^/]+',
        'slug' => '[a-z0-9\-_]+',
        'int' => '\d+',
    ];
    /**
     * Скомпилированный регулярный шаблон URI.
     *
     * Лениво компилируется при первом обращении.
     */
    public private(set) ?string $compiledPattern = null {
        get => $this->compiledPattern ??= $this->compilePattern();
    }
    /**
     * HTTP-метод, который обрабатывает маршрут.
     */
    public private(set) HttpMethod $method {
        get => $this->method;
    }

    /**
     * Конструктор маршрута.
     *
     * @param ServiceContainer                                          $serviceContainer DI-контейнер для разрешения зависимостей
     * @param string                                                    $path             URI-паттерн маршрута (например, '/user/{id:int}')
     * @param HttpMethod                                                $method           HTTP-метод
     * @param array{class-string|object,non-empty-string}|object|string $handler          Обработчик маршрута (callable любого поддерживаемого типа)
     * @param string                                                    $name             Имя маршрута (опционально, для программного доступа)
     *
     * @todo Разобраться с необходимостью параметра name
     */
    public function __construct(
        private readonly ServiceContainer $serviceContainer,
        private readonly string $path,
        HttpMethod $method,
        private readonly array|object|string $handler,
        public readonly string $name = '',
    ) {
        $this->method = $method;
        $this->middlewares = new MiddlewareCollection();
    }

    /**
     * Добавляет middleware к маршруту.
     *
     * @param class-string|MiddlewareInterface $middleware Класс middleware или его экземпляр
     * @param string                           $name       Имя middleware (для возможности переопределения)
     */
    public function addMiddleware(MiddlewareInterface|string $middleware, string $name = ''): static
    {
        $this->middlewares->addMiddleware($middleware, $name);

        return $this;
    }

    /**
     * Компилирует URI-паттерн в регулярное выражение.
     *
     * Поддерживает параметры с правилами валидации: {name}, {id:int}, {slug:slug}.
     *
     * @return string Скомпилированный шаблон в формате '#^...$#i'
     */
    protected function compilePattern(): string
    {
        /** @var list<string> $tokens */
        $tokens = preg_split('/(\{[^}]+\})/', $this->path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $regex = '';
        foreach ($tokens as $token) {
            if (str_starts_with($token, '{') && str_ends_with($token, '}')) {
                $inner = substr($token, 1, -1);
                if ('*' === $inner) {
                    $regex .= '(?P<path>.*)';

                    continue;
                }

                if (str_contains($inner, ':')) {
                    [$name, $ruleName] = explode(':', $inner, 2);
                } else {
                    $name = $inner;
                    $ruleName = 'default';
                }
                $rule = $this->rules[$ruleName] ?? $this->rules['default'];

                $regex .= "(?P<{$name}>{$rule})";
            } else {
                $regex .= preg_quote($token, '#');
            }
        }

        return '#^' . $regex . '$#i';
    }

    /**
     * Создаёт новый маршрут с изменённым HTTP-методом.
     *
     * Используется при регистрации маршрутов для нескольких методов.
     *
     * @param HttpMethod $method Новый HTTP-метод
     */
    public function withMethod(HttpMethod $method): static
    {
        return new static($this->serviceContainer, $this->path, $method, $this->handler, $this->name);
    }

    /**
     * Проверяет, соответствует ли текущий запрос URI-паттерну маршрута.
     *
     * При совпадении извлекает параметры и сохраняет их в свойствах запроса.
     *
     * @param HttpRequest $request Входящий HTTP-запрос
     *
     * @return bool true, если маршрут совпадает с запросом
     */
    public function matches(HttpRequest $request): bool
    {
        $matches = [];
        if (!preg_match($this->compiledPattern, $request->getPath(), $matches)) {
            return false;
        }
        $request->setProps(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

        return true;
    }

    /**
     * Выполняет обработчик маршрута.
     *
     * Поддерживает все типы callable:
     * - замыкания
     * - строки-функции
     * - статические методы ('Class::method')
     * - массивы [Class::class, 'method']
     * - invokable-классы.
     *
     * Автоматически внедряет зависимости через DI-контейнер и передаёт параметры маршрута.
     *
     * @param HttpRequest $request Входящий HTTP-запрос (с уже извлечёнными параметрами)
     *
     * @return mixed Результат выполнения обработчика (строка, массив, Response и т.д.)
     *
     * @throws ParameterResolveException
     *
     * @todo Декомпозировать метод
     */
    public function run(HttpRequest $request): mixed
    {
        if (is_string($this->handler) && !str_contains($this->handler, '::')) {
            if (class_exists($this->handler)) {
                $constructorArgs = $this->serviceContainer->getParameterResolver()
                    ->resolveForConstructor($this->handler, $request->props->getAll());

                $controller = new $this->handler(...$constructorArgs);
                $handler = [$controller, '__invoke'];
                $args = $this->serviceContainer->getParameterResolver()
                    ->resolveForCallable($handler, $request->props->getAll());
                if (!is_callable($controller)) {
                    throw new ParameterResolveException('Not a callable handler');
                }

                return $controller(...$args);
            }
            $args = $this->serviceContainer->getParameterResolver()
                ->resolveForCallable($this->handler, $request->props->getAll());
            if (!is_callable($this->handler)) {
                throw new ParameterResolveException('Not a callable handler');
            }

            return ($this->handler)(...$args);
        }
        $args = $this->serviceContainer->getParameterResolver()
            ->resolveForCallable($this->handler, $request->props->getAll());

        if ($this->handler instanceof \Closure) {
            return ($this->handler)(...$args);
        }
        if (is_array($this->handler)) {
            [$target, $method] = $this->handler;
            if (is_object($target)) {
                return $target->{$method}(...$args);
            }
            $constructorArgs = $this->serviceContainer->getParameterResolver()
                ->resolveForConstructor($target, $request->props->getAll());

            $target = new $target(...$constructorArgs);

            return $target->{$method}(...$args);
        }
        if (!is_string($this->handler)) {
            throw new JokeException('Unsupported route handler.');
        }
        [$class, $method] = explode('::', $this->handler, 2);

        return $class::$method(...$args);
    }

    /**
     * Возвращает список групп маршрута.
     *
     * @return array<string> Массив имён групп
     */
    public function getGroups(): array
    {
        return array_keys($this->groups);
    }

    /**
     * Добавляет маршрут в указанную группу.
     *
     * @param string $groupName Имя группы
     */
    public function addGroup(string $groupName): static
    {
        $this->groups[$groupName] = true;

        return $this;
    }

    /**
     * Добавляет маршрут в несколько групп одновременно.
     *
     * @param array<string> $groups Список имён групп
     */
    public function mergeGroup(array $groups): static
    {
        foreach ($groups as $group) {
            $this->addGroup($group);
        }

        return $this;
    }

    /**
     * Возвращает middleware, привязанные к маршруту.
     *
     * @return array<MiddlewareDto>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares->getMiddlewares();
    }
}
