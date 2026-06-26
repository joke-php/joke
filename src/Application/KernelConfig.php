<?php

declare(strict_types=1);

namespace Vasoft\Joke\Application;

use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Container\BaseContainer;
use Vasoft\Joke\Container\Exceptions\ContainerException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Contract\Logging\LoggerInterface;
use Vasoft\Joke\Contract\Provider\ServiceProviderInterface;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Logging\Handlers\StreamHandler;
use Vasoft\Joke\Logging\Logger;
use Vasoft\Joke\Routing\RouterServiceProvider;
use Vasoft\Joke\Support\Normalizers\Path;

/**
 * Конфигурация ядра приложения.
 *
 * Отвечает за регистрацию сервис-провайдеров, определяющих структуру приложения.
 * Поддерживает разделение на обычные провайдеры (загружаются сразу) и отложенные (ленивые).
 *
 * Используется в файле `bootstrap/kernel.php` для декларативной настройки приложения.
 * Если файл отсутствует, используются провайдеры по умолчанию.
 *
 * @see ServiceProviderInterface
 */
class KernelConfig extends AbstractConfig
{
    private LoggerInterface|\Closure|null $logger = null;
    /**
     * Список классов обычных сервис-провайдеров.
     * Провайдеры из этого списка инициируются сразу при старте приложения.
     * Включает провайдеры ядра и маршрутизации по умолчанию.
     *
     * @var array<class-string<ServiceProviderInterface>,true>
     */
    private array $providers = [
        KernelServiceProvider::class => true,
        RouterServiceProvider::class => true,
    ];

    public function setLogger(\Closure|LoggerInterface $logger): static
    {
        $this->guard();
        $this->logger = $logger;

        return $this;
    }

    /**
     * Регистрирует логгер в DI-контейнере.
     *
     * Если логгер не был задан явно через {@see setLogger()}, создаётся экземпляр по умолчанию, записывающий сообщения
     * в файл `var/log/error.log`.
     *
     * Логгер регистрируется как синглтон под интерфейсом {@see LoggerInterface} и алиасом `'logger'`
     * для удобства получения из контейнера.
     *
     * @param BaseContainer $container DI-контейнер приложения
     *
     * @throws ContainerException        В случае ошибок DI контейнера
     * @throws ParameterResolveException В случае ошибок определения параметров
     */
    public function registerLogger(BaseContainer $container): void
    {
        if (null === $this->logger) {
            /** @var Path $path */
            $path = $container->get(Path::class);
            $this->logger = static fn(): Logger => new Logger([
                new StreamHandler($path->logPath . 'error.log'),
            ]);
        }
        $container->registerSingleton(LoggerInterface::class, $this->logger);
        $container->registerAlias('logger', LoggerInterface::class);
    }

    /**
     *  Список классов отложенных (ленивых) сервис-провайдеров.
     *  Провайдеры из этого списка могут быть инициированы только при обращении к предоставляемым ими сервисам.
     *
     * @var array<class-string<ServiceProviderInterface>,true>
     */
    private array $deferredProviders = [];
    /**
     * Путь к директории с базовыми конфигурациями.
     */
    private string $baseConfigPath = 'config';
    /**
     * Путь к директории с ленивыми конфигурациями.
     */
    private string $lazyConfigPath = 'config/lazy';

    /**
     * Добавляет класс провайдера в список обычных провайдеров.
     *
     * @param class-string<ServiceProviderInterface> $class Класс провайдера
     *
     * @return $this
     *
     * @throws ConfigException Если конфигурация заблокирована для изменений
     */
    public function addProvider(string $class): self
    {
        $this->guard();
        $this->providers[$class] = true;

        return $this;
    }

    /**
     * Устанавливает полный список обычных провайдеров, заменяя предыдущий.
     *
     * @param list<class-string<ServiceProviderInterface>> $classes Класс провайдера
     *
     * @return $this
     *
     * @throws ConfigException Если конфигурация заблокирована для изменений
     */
    public function setProviders(array $classes): self
    {
        $this->guard();
        $this->providers = [];
        foreach ($classes as $class) {
            $this->addProvider($class);
        }

        return $this;
    }

    /**
     * Добавляет класс провайдера в список отложенных (ленивых) провайдеров.
     *
     * @param class-string<ServiceProviderInterface> $class Класс провайдера
     *
     * @return $this
     *
     * @throws ConfigException Если конфигурация заблокирована для изменений
     */
    public function addDeferredProvider(string $class): self
    {
        $this->guard();
        $this->deferredProviders[$class] = true;

        return $this;
    }

    /**
     * Устанавливает полный список отложенных провайдеров, заменяя предыдущий.
     *
     * @param list<class-string<ServiceProviderInterface>> $classes Класс провайдера
     *
     * @return $this
     *
     * @throws ConfigException Если конфигурация заблокирована для изменений
     */
    public function setDeferredProviders(array $classes): self
    {
        $this->guard();
        $this->deferredProviders = [];
        foreach ($classes as $class) {
            $this->addDeferredProvider($class);
        }

        return $this;
    }

    /**
     * Возвращает список зарегистрированных обычных провайдеров.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function getProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Возвращает список зарегистрированных отложенных провайдеров.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function getDeferredProviders(): array
    {
        return array_keys($this->deferredProviders);
    }

    /**
     * Устанавливает путь к директории с базовыми конфигурациями. Абсолютный или относительно корня проекта.
     *
     * @param string $baseConfigPath Путь к директории
     *
     * @throws ConfigException Если конфигурация заблокирована для изменений
     */
    public function setBaseConfigPath(string $baseConfigPath): self
    {
        $this->guard();
        $this->baseConfigPath = $baseConfigPath;

        return $this;
    }

    /**
     * Возвращает путь к директории с базовыми конфигурациями. Абсолютный или относительно корня проекта.
     */
    public function getBaseConfigPath(): string
    {
        return $this->baseConfigPath;
    }

    /**
     * Устанавливает путь к директории с ленивыми конфигурациями. Абсолютный или относительно корня проекта.
     *
     * @param string $lazyConfigPath Путь к директории
     *
     * @throws ConfigException Если конфигурация заблокирована для изменений
     */
    public function setLazyConfigPath(string $lazyConfigPath): self
    {
        $this->guard();
        $this->lazyConfigPath = $lazyConfigPath;

        return $this;
    }

    /**
     * Возвращает путь к директории с ленивыми конфигурациями. Абсолютный или относительно корня проекта.
     */
    public function getLazyConfigPath(): string
    {
        return $this->lazyConfigPath;
    }
}
