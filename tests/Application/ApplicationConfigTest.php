<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Application\ApplicationConfig;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Cache\FileCache;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Contract\Cache\CacheInterface;
use Vasoft\Joke\Contract\Mail\TransportInterface;
use Vasoft\Joke\Http\Response\HtmlPageResponse;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\JsonResponse;
use Vasoft\Joke\Mail\Email;
use Vasoft\Joke\Mail\NativeTransport;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Application\ApplicationConfig
 */
final class ApplicationConfigTest extends TestCase
{
    #[DataProvider('provideFrozenCases')]
    public function testFrozen(string $setter, mixed $value): void
    {
        $config = new ApplicationConfig();
        $config->freeze();
        self::expectException(ConfigException::class);
        $config->{$setter}($value);
    }

    public static function provideFrozenCases(): iterable
    {
        yield ['setFileRoutes', 'file.php'];
        yield ['setResponseClass', null];
        yield ['setCacheClass', FileCache::class];
        yield ['setMailTransportClass', NativeTransport::class];
    }

    #[DataProvider('provideSetAndGetCases')]
    public function testSetAndGet(string $setName, string $getName, mixed $value): void
    {
        $config = new ApplicationConfig();
        $config->{$setName}($value);
        self::assertSame($value, $config->{$getName}());
    }

    public static function provideSetAndGetCases(): iterable
    {
        yield ['setFileRoutes', 'getFileRoutes', 'file.php'];
        yield ['setResponseClass', 'getResponseClass', HtmlResponse::class];
    }

    #[DataProvider('provideSetAndGetPropertyCases')]
    public function testSetAndGetProperty(string $setName, string $getName, mixed $value): void
    {
        $config = new ApplicationConfig();
        $config->{$setName}($value);
        self::assertSame($value, $config->{$getName});
    }

    public static function provideSetAndGetPropertyCases(): iterable
    {
        yield ['setCacheClass', 'cacheClass', NopCache::class];
        yield ['setMailTransportClass', 'mailTransportClass', NopTransport::class];
    }

    #[TestDox('Хранит заданные типы ответов по умолчанию по имени группы')]
    public function testSetGroupResponseClass(): void
    {
        $config = new ApplicationConfig();
        $config
            ->setGroupResponseClass('web', HtmlPageResponse::class)
            ->setGroupResponseClass('api', JsonResponse::class);
        self::assertSame(HtmlPageResponse::class, $config->getGroupResponseClass('web'));
        self::assertSame(JsonResponse::class, $config->getGroupResponseClass('api'));
        self::assertNull($config->getGroupResponseClass('unknown'));
    }

    #[TestDox('Устанавливает тип кеширования по умолчанию файловый кеш')]
    public function testDefaultCacheTypeAndSet(): void
    {
        $config = new ApplicationConfig();
        self::assertSame(FileCache::class, $config->cacheClass);
        // Конфиг не проверяет тип
        $config
            ->setCacheClass(HtmlPageResponse::class);
        self::assertSame(HtmlPageResponse::class, $config->cacheClass);
    }

    #[TestDox('Пустую строку преобразует в null')]
    public function testSetGroupResponseClassEmptyStringAsNull(): void
    {
        $config = new ApplicationConfig();
        $config->setGroupResponseClass('api', ' ');
        self::assertNull($config->getGroupResponseClass('api'));
    }

    #[TestDox('setGroupResponseClass бросает исключение если конфиг заморожен')]
    public function testSetGroupResponseClassIfFrozen(): void
    {
        $config = new ApplicationConfig();
        $config->freeze();
        self::expectException(ConfigException::class);
        $config->setGroupResponseClass('api', ' ');
    }
}

class NopCache implements CacheInterface
{
    public function setPrefix(string $prefix): static
    {
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return [];
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return true;
    }
}

class NopTransport implements TransportInterface
{
    public function send(Email $email): void {}
}
