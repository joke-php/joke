<?php

declare(strict_types=1);

namespace Vasoft\Joke\Provider;

use Vasoft\Joke\Contract\Container\DiContainerInterface;
use Vasoft\Joke\Contract\Provider\ServiceProviderInterface;
use Vasoft\Joke\Provider\Exceptions\MultipleProvideException;
use Vasoft\Joke\Provider\Exceptions\ProviderException;
use Vasoft\Joke\Provider\Exceptions\ServiceNotFoundException;

/**
 * Менеджер жизненного цикла сервис-провайдеров.
 *
 * Отвечает за регистрацию, инициализацию (boot) и ленивую загрузку отложенных провайдеров.
 * Гарантирует правильный порядок выполнения на основе графа зависимостей (requires)
 * и защищает от циклических зависимостей.
 */
class ProviderManager
{
    /** @var array<class-string,true> Карта зарегистрированных провайдеров (выполнен метод register). */
    private array $registeredProviders = [];
    /** @var array<class-string,true> Карта загруженных провайдеров (выполнен метод boot). */
    private array $loadedProviders = [];
    /** @var array<class-string,true> Стек блокировок для детектирования циклических зависимостей. */
    private array $locked = [];
    /** @var array<class-string,ServiceProviderInterface> Карта сервисов: имя сервиса -> провайдер, который его предоставляет. */
    private array $provide = [];

    /**
     * @param DiContainerInterface           $container        контейнер с поддержкой проверки наличия сервисов
     * @param list<ServiceProviderInterface> $providers        список обычных провайдеров
     * @param list<ServiceProviderInterface> $providerDiffered список отложенных провайдеров
     */
    public function __construct(
        private readonly DiContainerInterface $container,
        private readonly array $providers,
        private readonly array $providerDiffered,
    ) {}

    /**
     * Запускает процесс регистрации всех обычных провайдеров и подготовку карты для отложенных.
     *
     * @throws MultipleProvideException если два разных провайдера заявляют предоставление одного и того же сервиса
     * @throws ServiceNotFoundException если обычный провайдер требует сервис, который не найден ни в одном провайдере, ни в контейнере
     * @throws ProviderException        если обнаружена циклическая зависимость между провайдерами при регистрации
     */
    public function register(): void
    {
        foreach ($this->providers as $provider) {
            $this->addToMap($provider);
        }
        foreach ($this->providerDiffered as $provider) {
            $this->addToMap($provider);
        }
        foreach ($this->providers as $provider) {
            $this->ensureRegistered($provider);
        }
    }

    /**
     * Гарантирует регистрацию провайдера и его зависимостей.
     * Использует блокировку для защиты от циклов.
     *
     * @throws ServiceNotFoundException если одна из зависимостей провайдера отсутствует в системе
     * @throws ProviderException        если обнаружен цикл зависимостей в графе регистрации
     */
    private function ensureRegistered(ServiceProviderInterface $provider): void
    {
        $this->lock($provider, 'register');

        try {
            $this->registerProvider($provider);
        } finally {
            $this->unlock($provider);
        }
    }

    /**
     * Добавляет сервисы провайдера в общую карту предоставления.
     * Работает только для отложенных провайдеров.
     *
     * @throws MultipleProvideException если сервис уже предоставлен другим провайдером (не тем же самым экземпляром или классом)
     */
    private function addToMap(ServiceProviderInterface $provider): void
    {
        foreach ($provider->provides() as $className) {
            if (array_key_exists($className, $this->provide)) {
                $existingProvider = $this->provide[$className];

                if ($existingProvider === $provider || $existingProvider::class === $provider::class) {
                    continue;
                }

                throw new MultipleProvideException($className, [$existingProvider::class, $provider::class]);
            }
            $this->provide[$className] = $provider;
        }
    }

    /**
     * Рекурсивно регистрирует провайдер и все его зависимости.
     *
     * @throws ServiceNotFoundException если зависимость не найдена ни в карте провайдеров, ни в контейнере
     * @throws ProviderException        если обнаружен цикл зависимостей
     */
    private function registerProvider(ServiceProviderInterface $provider): void
    {
        if (array_key_exists($provider::class, $this->registeredProviders)) {
            return;
        }
        foreach ($provider->requires() as $requiredService) {
            $requiredProvider = $this->findProvider($requiredService);
            if (null !== $requiredProvider) {
                $this->ensureRegistered($requiredProvider);
            } elseif (!$this->container->has($requiredService)) {
                throw new ServiceNotFoundException($provider::class, $requiredService);
            }
        }
        $provider->register();
        $this->registeredProviders[$provider::class] = true;
    }

    /**
     * Рекурсивно загружает (вызывает boot) провайдер и его зависимости.
     *
     * @throws ServiceNotFoundException если зависимость необходима для boot, но не найдена в системе
     * @throws ProviderException        если обнаружен цикл зависимостей при загрузке
     */
    private function bootProvider(ServiceProviderInterface $provider): void
    {
        if ($this->isLoaded($provider)) {
            return;
        }
        foreach ($provider->requires() as $requiredService) {
            $requiredProvider = $this->findProvider($requiredService);
            if (null !== $requiredProvider) {
                $this->ensureLoaded($requiredProvider);
            } elseif (!$this->container->has($requiredService)) {
                throw new ServiceNotFoundException($provider::class, $requiredService);
            }
        }
        $provider->boot();
        $this->markLoaded($provider);
    }

    /**
     * Запускает процесс инициализации (boot) для всех обычных провайдеров.
     *
     * @throws ServiceNotFoundException если какой-либо обычный провайдер имеет неустранимую зависимость
     * @throws ProviderException        если обнаружен цикл зависимостей среди обычных провайдеров
     */
    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $this->ensureLoaded($provider);
        }
    }

    /**
     * Гарантирует загрузку провайдера и его зависимостей.
     * Использует блокировку для защиты от циклов.
     *
     * @throws ServiceNotFoundException если зависимость недоступна
     * @throws ProviderException        если обнаружен цикл зависимостей
     */
    private function ensureLoaded(ServiceProviderInterface $provider): void
    {
        $this->lock($provider, 'boot');

        try {
            $this->bootProvider($provider);
        } finally {
            $this->unlock($provider);
        }
    }

    /**
     * Лениво загружает отложенный провайдер по имени сервиса.
     * Выполняет регистрацию и инициализацию при первом запросе.
     *
     * @param string $className имя сервиса (интерфейса или класса)
     *
     * @return bool true если провайдер был загружен (или уже был загружен), False если провайдер не найден
     *
     * @throws ServiceNotFoundException если найденный провайдер требует отсутствующую зависимость
     * @throws ProviderException        если обнаружен цикл зависимостей при ленивой загрузке
     */
    public function bootDeferredFor(string $className): bool
    {
        $provider = $this->findProvider($className);
        if (null === $provider) {
            return false;
        }
        if ($this->isLoaded($provider)) {
            return true;
        }
        $this->lock($provider, 'deferred boot');

        try {
            $this->registerProvider($provider);
            $this->bootProvider($provider);
        } finally {
            $this->unlock($provider);
        }

        return true;
    }

    /**
     * Устанавливает блокировку на провайдера для детектирования циклов.
     *
     * @param string $context контекст операции ('register', 'boot', etc)
     *
     * @throws ProviderException если провайдер уже находится в состоянии загрузки (обнаружен цикл)
     */
    private function lock(ServiceProviderInterface $provider, string $context): void
    {
        if (array_key_exists($provider::class, $this->locked)) {
            $className = $provider::class;

            throw new ProviderException("Circular dependency detected involving {$className} at {$context}");
        }
        $this->locked[$provider::class] = true;
    }

    /**
     * Снимает блокировку с провайдера.
     */
    private function unlock(ServiceProviderInterface $provider): void
    {
        unset($this->locked[$provider::class]);
    }

    /**
     * Помечает провайдер как загруженный.
     */
    private function markLoaded(ServiceProviderInterface $provider): void
    {
        $this->loadedProviders[$provider::class] = true;
    }

    /**
     * Проверяет, загружен ли провайдер.
     */
    private function isLoaded(ServiceProviderInterface $provider): bool
    {
        return array_key_exists($provider::class, $this->loadedProviders);
    }

    /**
     * Находит провайдер, предоставляющий указанный сервис.
     *
     * @param string $className имя сервиса
     */
    private function findProvider(string $className): ?ServiceProviderInterface
    {
        return $this->provide[$className] ?? null;
    }

    /**
     * Возвращает список классов зарегистрированных провайдеров.
     *
     * @return list<class-string>
     */
    public function getRegisteredProviders(): array
    {
        return array_keys($this->registeredProviders);
    }

    /**
     * Возвращает список классов загруженных провайдеров.
     *
     * @return list<class-string>
     */
    public function getLoadedProviders(): array
    {
        return array_keys($this->loadedProviders);
    }

    /**
     * Возвращает список имен сервисов, доступных для ленивой загрузки.
     *
     * @return list<class-string>
     */
    public function getProvidedServices(): array
    {
        return array_keys($this->provide);
    }
}
