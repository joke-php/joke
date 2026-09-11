<?php

declare(strict_types=1);

namespace Vasoft\Joke\Container;

use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Config\ConfigManager;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Contract\Container\ContainerInspectionInterface;
use Vasoft\Joke\Contract\Container\DiContainerInterface;
use Vasoft\Joke\Contract\Container\ResolverInterface;
use Vasoft\Joke\Container\Exceptions\ContainerException;

/**
 * Базовый контейнер внедрения зависимостей (DI Container).
 *
 * Управляет жизненным циклом сервисов, поддерживает синглтоны и прототипы,
 * автоматически разрешает зависимости через рефлексию.
 */
abstract class BaseContainer implements ContainerInspectionInterface
{
    /**
     * Регистр прототипов (новый экземпляр при каждом запросе).
     *
     * @var array<string, callable|class-string>
     */
    private array $serviceRegistry = [];
    /**
     * Регистр определений синглтонов.
     *
     * @var array<string, callable|class-string>
     */
    private array $singletonsRegistry = [];
    /**
     * Кэш созданных синглтонов.
     *
     * @var array<string, object>
     */
    private array $singletons = [];
    /**
     * Соответствие алиасов классам
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Конструктор контейнера.
     *
     * Регистрирует стандартные сервисы и самого себя
     */
    public function __construct()
    {
        $this->initDefault();
        $this->singletons[static::class] = $this;
        $this->singletons[DiContainerInterface::class] = $this;
        $this->singletonsRegistry[static::class] = static::class;
        $this->singletonsRegistry[DiContainerInterface::class] = static::class;
    }

    /**
     * Инициализирует стандартные сервисы по умолчанию.
     *
     * Регистрирует реализации ResolverInterface
     */
    protected function initDefault(): void
    {
        $this->registerSingleton(ResolverInterface::class, new ParameterResolver($this));
    }

    public function getParameterResolver(): ResolverInterface
    {
        return $this->getSingleton(ResolverInterface::class);
    }

    public function registerSingleton(string $name, callable|object|string $service): void
    {
        $this->singletonsRegistry[$name] = $service;
        if (is_object($service) && !($service instanceof \Closure)) {
            $this->singletons[$name] = $service;
        }
    }

    /**
     * @deprecated Передача вызываемых объектов (с помощью __invoke) будет рассматриваться как синглтоны в версии 2.0.
     *  Используйте \Closure для фабрик.
     */
    public function register(string $name, callable|object|string $service): void
    {
        if (!$this->isValidServiceName($name)) {
            trigger_error("Service name '{$name}' is not a valid class or interface.", E_USER_WARNING);
        }
        if (is_object($service)) {
            if (!is_callable($service)) {
                $this->registerSingleton($name, $service);
            } else {
                @trigger_error(
                    'Passing a callable object to register() is deprecated. '
                    . 'In v2.0 it will be treated as a singleton. '
                    . 'Use a Closure for factories: fn() => $obj().',
                    E_USER_DEPRECATED,
                );
                $this->serviceRegistry[$name] = $service;
            }
        } else {
            $this->serviceRegistry[$name] = $service;
        }
    }

    private function isValidServiceName(string $name): bool
    {
        if (!str_contains($name, '\\')) {
            return true;
        }

        return interface_exists($name) || class_exists($name);
    }

    public function get(string $name): ?object
    {
        $result = $this->getSingleton($name);
        if (null !== $result) {
            return $result;
        }
        $result = $this->getService($name);
        if (null !== $result) {
            return $result;
        }
        if (is_subclass_of($name, AbstractConfig::class) && $this->has(ConfigManager::class)) {
            $configManager = $this->get(ConfigManager::class);

            try {
                return $configManager->get($name);
            } catch (ConfigException $e) {
            }
        }
        @trigger_error(
            "Service '{$name}' not found. In v2.0 this will throw an exception.",
            E_USER_DEPRECATED,
        );

        return null;
    }

    /**
     * Создаёт новый экземпляр прототипа.
     *
     * @param string $name Имя сервиса
     *
     * @return null|object Экземпляр сервиса или null, если не зарегистрирован
     *
     * @throws ParameterResolveException При ошибках
     */
    private function getService(string $name): ?object
    {
        ['definition' => $definition] = $this->resolveEntry($name, $this->serviceRegistry);
        if (null === $definition) {
            return null;
        }

        return $this->make($definition);
    }

    /**
     * Создаёт новый экземпляр класса или вызывает фабрику с автоматическим внедрением зависимостей.
     *
     * В отличие от {@see get()}, всегда возвращает новый объект (для классов) или результат
     * выполнения callable, не используя кэш синглтонов. Используется контейнером внутри
     * для создания прототипов и синглтонов, а также может быть вызван напрямую для
     * получения объектов, не зарегистрированных в контейнере.
     *
     * @template T of object
     *
     * @param callable|class-string<T> $definition Имя класса для инстанцирования или фабрика (callable).
     *                                             Зависимости конструктора/фабрики разрешаются автоматически.
     *
     * @return ($definition is class-string<T> ? T : object) новый экземпляр класса или результат вызова фабрики
     *
     * @throws ParameterResolveException если не удалось разрешить зависимости конструктора или фабрики
     */
    public function make(callable|string $definition): object
    {
        $resolver = $this->getParameterResolver();
        if (is_callable($definition)) {
            $args = $resolver->resolveForCallable($definition);

            return $definition(...$args);
        }
        $args = $resolver->resolveForConstructor($definition);

        return new $definition(...$args);
    }

    /**
     * Получает или создаёт синглтон.
     *
     * @param class-string<T>|string $name Имя сервиса
     *
     * @template T of object
     *
     * @return ($name is class-string ? null|T : null|object) Экземпляр сервиса или null, если не найден
     *
     * @throws ParameterResolveException При ошибках рефлексии
     */
    private function getSingleton(string $name): ?object
    {
        if (isset($this->singletons[$name])) {
            return $this->singletons[$name];
        }
        ['definition' => $definition, 'canonicalName' => $canonicalName] = $this->resolveEntry(
            $name,
            $this->singletonsRegistry,
        );
        if (null !== $canonicalName && $name !== $canonicalName && isset($this->singletons[$canonicalName])) {
            $this->singletons[$name] = $this->singletons[$canonicalName];

            return $this->singletons[$name];
        }
        if (null === $definition) {
            return null;
        }
        $entity = $this->make($definition);
        $this->singletons[$name] = $entity;
        if ($name !== $canonicalName) {
            $this->singletons[$canonicalName] = $entity;
        }

        return $this->singletons[$name];
    }

    /**
     * Разрешает имя сервиса до определения и канонического имени.
     *
     * Выполняет поиск напрямую в реестре. Если не найдено — следует по цепочке алиасов.
     * Прекращает обход при первом совпадении в реестре.
     *
     * Защищён от циклических алиасов через массив $visited.
     *
     * @param string                               $name     Запрашиваемое имя (может быть алиасом)
     * @param array<string, callable|class-string> $registry Реестр сервисов (прототипов или синглтонов)
     *
     * @return array{definition: null|callable|class-string, canonicalName: null|string}
     *
     * @throws ContainerException если обнаружена циклическая зависимость алиасов
     */
    private function resolveEntry(string $name, array $registry): array
    {
        $visited = [];
        $current = $name;
        if (array_key_exists($name, $registry)) {
            return ['definition' => $registry[$name], 'canonicalName' => $name];
        }
        while (isset($this->aliases[$current])) {
            if (isset($visited[$current])) {
                throw new ContainerException('Circular alias detected: ' . implode('-', array_keys($visited)) . '.');
            }
            $visited[$current] = true;
            $current = $this->aliases[$current];
            if (array_key_exists($current, $registry)) {
                return ['definition' => $registry[$current], 'canonicalName' => $current];
            }
        }

        return ['definition' => null, 'canonicalName' => null];
    }

    /**
     * Регистрирует алиас для имени сервиса.
     *
     * Поддерживает цепочки алиасов (например: 'a' → 'b' → Service::class).
     * При разрешении запроса по алиасу контейнер рекурсивно следует по цепочке,
     * пока не найдёт зарегистрированный сервис или не достигнет конца.
     *
     * Циклические зависимости (например: 'a' → 'b', 'b' → 'a') приводят к выбросу
     * {@see ContainerException}.
     *
     * @param string $alias    Псевдоним, под которым будет доступен сервис
     * @param string $concrete Имя сервиса, интерфейса или другого алиаса
     */
    public function registerAlias(string $alias, string $concrete): static
    {
        if (!$this->isValidServiceName($concrete)) {
            trigger_error("Service name '{$concrete}' is not a valid class or interface.", E_USER_WARNING);
        }
        $this->aliases[$alias] = $concrete;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->serviceRegistry[$name])
            || isset($this->singletonsRegistry[$name])
            || isset($this->singletons[$name])
            || isset($this->aliases[$name]);
    }
}
