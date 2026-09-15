<?php

declare(strict_types=1);

namespace Vasoft\Joke\Application;

use Vasoft\Joke\Config\ConfigManager;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\Exceptions\ContainerException;
use Vasoft\Joke\Contract\Logging\LoggerInterface;
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Exceptions\FileSystemException;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Middleware\Exceptions\MiddlewareException;
use Vasoft\Joke\Middleware\Exceptions\WrongMiddlewareException;
use Vasoft\Joke\Middleware\MiddlewareCollection;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Provider\Exceptions\MultipleProvideException;
use Vasoft\Joke\Provider\Exceptions\ProviderException;
use Vasoft\Joke\Provider\Exceptions\ServiceNotFoundException;
use Vasoft\Joke\Provider\ProviderManagerBuilder;
use Vasoft\Joke\Routing\Exceptions\NotFoundException;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Config\EnvironmentLoader;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Support\FileSystem;

/**
 * Основной класс приложения Joke.
 *
 * Является центральным оркестратором фреймворка, управляющим загрузкой маршрутов,
 * выполнением middleware и обработкой HTTP-запросов от начала до конца.
 * Интегрирует DI-контейнер, маршрутизатор и систему middleware в единый workflow.
 */
class Application
{
    /**
     * Коллекция глобальных middleware.
     *
     * Выполняются до определения маршрута. Используются для обработки ошибок,
     * CORS, логирования и других кросс-функциональных задач.
     */
    protected MiddlewareCollection $middlewares {
        get => $this->middlewares;
    }
    /**
     * Коллекция middleware маршрутизатора.
     *
     * Выполняются после определения маршрута, но до его обработчика.
     * Могут быть привязаны к группам маршрутов.
     */
    protected MiddlewareCollection $routeMiddlewares {
        get => $this->routeMiddlewares;
    }
    private readonly FileSystem $paths;

    /**
     * Конструктор приложения.
     *
     * Автоматически регистрирует стандартные middleware:
     * - ExceptionMiddleware (глобальный уровень)
     * - SessionMiddleware и CsrfMiddleware (уровень маршрутизатора, группа 'web')
     *
     * @param string           $basePath         Базовый путь приложения (обычно корень проекта)
     * @param ServiceContainer $serviceContainer DI-контейнер
     *
     * @throws ConfigException
     * @throws ContainerException
     * @throws MiddlewareException
     * @throws MultipleProvideException
     * @throws ParameterResolveException
     * @throws ProviderException
     * @throws ServiceNotFoundException
     * @throws \Throwable
     */
    public function __construct(
        string $basePath,
        public readonly ServiceContainer $serviceContainer,
    ) {
        $this->paths = new FileSystem($basePath);
        $serviceContainer->registerSingleton(FileSystem::class, $this->paths);
        $serviceContainer->registerAlias('normalizer.path', FileSystem::class);
        $serviceContainer->registerAlias('paths', FileSystem::class);

        $environment = new Environment(new EnvironmentLoader($this->paths->basePath));
        $serviceContainer->registerSingleton(Environment::class, $environment);
        $serviceContainer->registerAlias('env', Environment::class);

        $kernelConfig = $this->initKernelConfig($environment, $this->paths);
        $kernelConfig->registerLogger($this->serviceContainer);

        try {
            $configManager = new ConfigManager(
                $this->serviceContainer,
                $kernelConfig->getBaseConfigPath(),
                $kernelConfig->getLazyConfigPath(),
            )
                ->registerProviders($kernelConfig->getProviders())
                ->registerProviders($kernelConfig->getDeferredProviders());
            $serviceContainer->registerSingleton(ConfigManager::class, $configManager);
            $serviceContainer->registerAlias('config', ConfigManager::class);

            $providerManager = ProviderManagerBuilder::build(
                $this->serviceContainer,
                $kernelConfig->getProviders(),
                $kernelConfig->getDeferredProviders(),
            );
            $providerManager->register();
            $providerManager->boot();
            $this->middlewares = $this->getNamedMiddlewareCollection('global');
            $this->routeMiddlewares = $this->getNamedMiddlewareCollection('route');
        } catch (\Throwable $exception) {
            try {
                /** @var LoggerInterface $config */
                $config = $this->serviceContainer->get('logger');
                $config->error($exception);
            } catch (\Throwable $e) {
                error_log($exception->getMessage());

                throw $exception;
            }
        }
    }

    /**
     * Получает коллекцию middleware по имени из контейнера.
     *
     * Извлекает сервис по ключу "middleware.{name}" и гарантирует,
     * что возвращённый объект является экземпляром MiddlewareCollection.
     *
     * @param string $name Идентификатор коллекции (например, 'global' или 'route')
     *
     * @return MiddlewareCollection Экземпляр коллекции middleware
     *
     * @throws ParameterResolveException При ошибках резолвера
     * @throws MiddlewareException       Если сервис не найден или имеет неверный тип
     * @throws ContainerException        При ошибках контейнера
     */
    private function getNamedMiddlewareCollection(string $name): MiddlewareCollection
    {
        $instance = $this->serviceContainer->get('middleware.' . $name);
        if (!$instance instanceof MiddlewareCollection) {
            throw new MiddlewareException('middleware.' . $name . ' is not instance of MiddlewareCollection.');
        }

        return $instance;
    }

    /**
     * Инициализирует конфигурацию ядра приложения.
     *
     * Загружает пользовательскую конфигурацию из файла `bootstrap/kernel.php`, если он существует.
     * Внутри этого файла доступна переменная `$env` типа {@see Environment}, содержащая данные окружения приложения.
     *
     * Файл `kernel.php` должен возвращать экземпляр {@see KernelConfig}. Если файл отсутствует,
     * создаётся конфигурация по умолчанию.
     *
     * После загрузки конфигурация "замораживается" (становится неизменяемой)
     * и регистрируется в DI-контейнере как синглтон.
     *
     * @param Environment $env   Окружение приложения, передаётся в `kernel.php` через замыкание
     * @param FileSystem  $paths Сервис файловой системы, передаётся в `kernel.php` через замыкание
     *
     * @return KernelConfig Инициализированная и замороженная конфигурация ядра
     *
     * @throws ConfigException     Если файл `kernel.php` существует, но не возвращает корректный объект
     * @throws FileSystemException При ошибках файлового сервиса
     */
    private function initKernelConfig(Environment $env, FileSystem $paths): KernelConfig
    {
        $file = $this->paths->bootstrapPath . 'kernel.php';
        if (file_exists($file)) {
            $config = $paths->includeFile($file, [
                'env' => $env,
                'paths' => $paths,
            ]);
            if (!$config instanceof KernelConfig) {
                throw new ConfigException('kernel.php must return a KernelConfig instance.');
            }
        } else {
            $config = new KernelConfig();
        }
        $config->freeze();
        $this->serviceContainer->registerSingleton(KernelConfig::class, $config);

        return $config;
    }

    /**
     * Добавляет глобальный middleware в коллекцию
     * Если middleware именованный производится поиск, и, если найден, производится замена middleware в той же позиции где
     * и был найден.
     *
     * @param class-string|MiddlewareInterface $middleware Экземпляр или класс middleware
     * @param string                           $name       Наименование middleware для тех, которые могут быть только в единственном экземпляре
     *
     * @return $this
     */
    public function addMiddleware(MiddlewareInterface|string $middleware, string $name = ''): static
    {
        $this->middlewares->addMiddleware($middleware, $name);

        return $this;
    }

    /**
     * Добавляет middleware маршрутизатора.
     *
     * Может быть привязан к определённым группам маршрутов.
     * Именованные middleware с существующим именем будут заменены, сохраняя свою позицию в цепочке выполнения.
     *
     * @param class-string|MiddlewareInterface $middleware Класс или экземпляр middleware
     * @param string                           $name       Имя middleware (для возможности переопределения)
     * @param array<string>                    $groups     Список групп маршрутов, к которым применяется middleware
     */
    public function addRouteMiddleware(
        MiddlewareInterface|string $middleware,
        string $name = '',
        array $groups = [],
    ): static {
        $this->routeMiddlewares->addMiddleware($middleware, $name, $groups);

        return $this;
    }

    /**
     * Обрабатывает входящий HTTP-запрос.
     *
     * Выполняет следующие шаги:
     * 1. Запускает глобальные middleware
     * 2. Определяет маршрут
     * 3. Запускает middleware маршрутизатора и маршрута
     * 4. Выполняет обработчик маршрута
     * 5. Отправляет ответ клиенту
     *
     * @param HttpRequest $request Входящий HTTP-запрос
     *
     * @throws ContainerException
     * @throws ParameterResolveException
     * @throws WrongMiddlewareException  Если middleware не реализует MiddlewareInterface
     */
    public function handle(HttpRequest $request): void
    {
        $this->serviceContainer->registerSingleton(Request::class, $request);
        $next = fn() => $this->handleRoute($request);
        $response = $this->processMiddlewares($request, $this->middlewares->getArrayForRun(), $next);
        $responseBuilder = $this->serviceContainer->get(ResponseBuilder::class);
        $responseBuilder->make($response)->send();
    }

    /**
     * Обрабатывает запрос после определения маршрута.
     *
     * Регистрирует текущий запрос в DI-контейнере, находит подходящий маршрут,
     * собирает цепочку middleware и выполняет обработчик.
     *
     * @param HttpRequest $request Входящий HTTP-запрос
     *
     * @return mixed Результат выполнения обработчика маршрута
     *
     * @throws NotFoundException
     * @throws ParameterResolveException
     * @throws WrongMiddlewareException
     * @throws JokeException
     */
    private function handleRoute(HttpRequest $request): mixed
    {
        $this->serviceContainer->registerSingleton(HttpRequest::class, $request);
        $route = $this->serviceContainer->getRouter()->findRoute($request);
        if (null === $route) {
            throw new NotFoundException('Route not found');
        }
        $responseBuilder = $this->serviceContainer->get(ResponseBuilder::class);

        $next = static fn() => $responseBuilder->make($route->run($request));
        $middlewareCollection = $this->routeMiddlewares->withMiddlewares($route->getMiddlewares());

        $middlewares = $middlewareCollection->getArrayForRun($route->getGroups());

        return $this->processMiddlewares($request, $middlewares, $next);
    }

    /**
     * Выполняет цепочку middleware.
     *
     * Строит вложенную структуру вызовов, где каждый middleware оборачивает
     * результат следующего звена цепочки.
     *
     * @param HttpRequest                             $request     Входящий HTTP-запрос
     * @param array<class-string|MiddlewareInterface> $middlewares Список middleware для выполнения
     * @param callable                                $next        Функция следующего звена цепочки
     *
     * @return mixed Результат выполнения цепочки
     *
     * @throws ParameterResolveException
     * @throws WrongMiddlewareException  Если middleware не реализует MiddlewareInterface
     */
    private function processMiddlewares(
        HttpRequest $request,
        array $middlewares,
        callable $next,
    ): mixed {
        foreach ($middlewares as $middleware) {
            $next = function () use ($middleware, $next, $request) {
                $instance = ($middleware instanceof MiddlewareInterface)
                    ? $middleware
                    : $this->resolveMiddleware($middleware);
                if (null === $instance) {
                    throw new WrongMiddlewareException($middleware);
                }

                return $instance->handle(
                    $request,
                    $next,
                );
            };
        }

        return $next();
    }

    /**
     * Создаёт экземпляр middleware через DI-контейнер.
     *
     * @param class-string $middleware Имя класса middleware
     *
     * @return null|MiddlewareInterface Экземпляр middleware или null, если класс не реализует интерфейс
     *
     * @throws ParameterResolveException
     */
    private function resolveMiddleware(string $middleware): ?MiddlewareInterface
    {
        $resolver = $this->serviceContainer->getParameterResolver();
        $args = $resolver->resolveForConstructor($middleware);
        $instance = new $middleware(...$args);

        return $instance instanceof MiddlewareInterface ? $instance : null;
    }
}
