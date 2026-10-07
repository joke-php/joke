<?php

declare(strict_types=1);

namespace Vasoft\Joke\Cache;

use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Cache\Exceptions\CacheException;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Cache\CacheInterface;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;

/**
 * Менеджер для создания и управления экземплярами кэша.
 *
 * Использует DI-контейнер для инстанцирования реализаций CacheInterface,
 * обеспечивая внедрение зависимостей и изоляцию данных через префиксы.
 *
 * @todo Реализовать кеширование экземпляров в рамках хита, метода get
 */
class CacheManager
{
    /** @var class-string<CacheInterface> */
    private string $cacheClass;

    /**
     * @param ServiceContainer  $container Контейнер зависимостей для создания экземпляров
     * @param ApplicationConfig $config    Конфигурация приложения, содержащая имя класса кэша
     */
    public function __construct(
        private readonly ServiceContainer $container,
        ApplicationConfig $config,
    ) {
        $this->cacheClass = $config->cacheClass;
    }

    /**
     * Создает и возвращает экземпляр кэша для указанного префикса.
     *
     * Каждый вызов с новым префиксом создает изолированное пространство имен
     * для ключей кэша. Параметры передаются в конструктор реализации через контейнер.
     *
     * @param string                  $prefix         Префикс (подкаталог) для изоляции группы кэша
     * @param bool|list<class-string> $allowedClasses Разрешенные классы для десериализации
     *
     * @return CacheInterface Экземпляр кэша
     *
     * @throws ConfigException           Если настроенный класс не реализует CacheInterface
     * @throws CacheException
     * @throws ParameterResolveException Если контейнер не смог разрешить зависимости
     */
    public function build(string $prefix, bool|array $allowedClasses = false): CacheInterface
    {
        $cache = $this->container->make($this->cacheClass, [
            'allowedClasses' => $allowedClasses,
        ]);
        if ($cache instanceof CacheInterface) {
            $cache->setPrefix($prefix);

            return $cache;
        }

        // @phpstan-ignore deadCode.unreachable
        throw new ConfigException('Cache must be an instance of CacheInterface.');
    }
}
