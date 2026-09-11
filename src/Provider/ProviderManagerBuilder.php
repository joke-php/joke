<?php

declare(strict_types=1);

namespace Vasoft\Joke\Provider;

use Vasoft\Joke\Contract\Container\DiContainerInterface;
use Vasoft\Joke\Contract\Container\ResolverInterface;
use Vasoft\Joke\Contract\Provider\ServiceProviderInterface;
use Vasoft\Joke\Provider\Exceptions\ProviderException;

/**
 * Билдер для создания и настройки экземпляра ProviderManager.
 * Отвечает за инстанцирование провайдеров через DI-контейнер и валидацию их типов.
 */
readonly class ProviderManagerBuilder
{
    private ResolverInterface $resolver;

    /**
     * @param DiContainerInterface $container контейнер с поддержкой проверки наличия сервисов
     */
    private function __construct(
        private DiContainerInterface $container,
    ) {
        $this->resolver = $container->getParameterResolver();
    }

    /**
     * Статический фабричный метод для создания настроенного менеджера провайдеров.
     *
     * @param DiContainerInterface $container         экземпляр контейнера
     * @param list<class-string>   $providers         список классов обычных провайдеров
     * @param list<class-string>   $deferredProviders список классов отложенных провайдеров
     *
     * @throws ProviderException если провайдер не найден, не реализует нужный интерфейс
     *                           или присутствует в обоих списках одновременно
     */
    public static function build(
        DiContainerInterface $container,
        array $providers,
        array $deferredProviders,
    ): ProviderManager {
        return new self($container)->process($providers, $deferredProviders);
    }

    /**
     * Обрабатывает списки классов, создавая экземпляры провайдеров и проверяя их уникальность.
     *
     * @param list<class-string> $providers
     * @param list<class-string> $deferredProviders
     *
     * @throws ProviderException
     */
    private function process(
        array $providers,
        array $deferredProviders,
    ): ProviderManager {
        $intersect = array_intersect($providers, $deferredProviders);
        if (!empty($intersect)) {
            throw new ProviderException('Providers cannot be both regular and deferred: ' . implode(', ', $intersect));
        }
        $providerEntity = $this->buildProviders($providers);
        $providerDeferredEntity = $this->buildProviders($deferredProviders);

        return new ProviderManager($this->container, $providerEntity, $providerDeferredEntity);
    }

    /**
     * Создает экземпляры провайдеров из списка классов, внедряя зависимости через резолвер.
     *
     * @param list<class-string> $classes список имен классов
     *
     * @return list<ServiceProviderInterface>
     *
     * @throws ProviderException
     */
    private function buildProviders(array $classes): array
    {
        $result = [];
        foreach ($classes as $providerClass) {
            $item = $this->buildProvider($providerClass);
            if (!$item instanceof ServiceProviderInterface) {
                throw new ProviderException(
                    sprintf('Provider class "%s" must implement %s', $providerClass, ServiceProviderInterface::class),
                );
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Инстанцирует один класс провайдера, разрешая его зависимости конструктора.
     *
     * @param class-string $providerClass
     *
     * @throws ProviderException
     */
    private function buildProvider(string $providerClass): object
    {
        try {
            $args = $this->resolver->resolveForConstructor($providerClass);

            return new $providerClass(...$args);
        } catch (\Throwable $e) {
            throw new ProviderException(
                "Failed to instantiate provider '{$providerClass}'. Maybe some constructor arguments are not resolvable.",
                previous: $e,
            );
        }
    }
}
