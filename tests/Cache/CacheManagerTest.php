<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Cache\CacheManager;
use Vasoft\Joke\Cache\FileCache;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Cache\CacheInterface;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Cache\CacheManager
 */
#[TestDox('Фабрика кеша Cache')]
#[CoversClass(CacheManager::class)]
final class CacheManagerTest extends TestCase
{
    #[TestDox('Возвращает реализацию CacheInterface, созданную контейнером')]
    public function testBuildReturnsCacheFromContainer(): void
    {
        $config = new ApplicationConfig();
        $config->setCacheClass(FileCache::class);

        $cacheImpl = self::createStub(CacheInterface::class);

        $container = self::createMock(ServiceContainer::class);
        $container->expects(self::once())
            ->method('make')
            ->with(FileCache::class, ['allowedClasses' => false])
            ->willReturn($cacheImpl);

        $cache = new CacheManager($container, $config);

        self::assertSame($cacheImpl, $cache->build('test'));
    }

    #[TestDox('Пробрасывает allowedClasses в контейнер')]
    public function testBuildPassesAllowedClasses(): void
    {
        $config = new ApplicationConfig();
        $config->setCacheClass(FileCache::class);

        $cacheImpl = self::createStub(CacheInterface::class);

        $container = self::createMock(ServiceContainer::class);
        $container->expects(self::once())
            ->method('make')
            ->with(FileCache::class, ['allowedClasses' => [\stdClass::class]])
            ->willReturn($cacheImpl);

        $cache = new CacheManager($container, $config);

        self::assertSame($cacheImpl, $cache->build('page', [\stdClass::class]));
    }

    #[TestDox('Бросает ConfigException, если контейнер вернул не CacheInterface')]
    public function testBuildThrowsWhenResultIsNotCacheInterface(): void
    {
        $config = new ApplicationConfig();
        $config->setCacheClass(\stdClass::class);

        $container = self::createStub(ServiceContainer::class);
        $container->method('make')->willReturn(new \stdClass());

        $cache = new CacheManager($container, $config);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIs('Cache must be an instance of CacheInterface.');

        $cache->build('test');
    }
}
