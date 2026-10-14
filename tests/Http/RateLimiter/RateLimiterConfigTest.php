<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\RateLimiter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Http\RateLimiter\RateLimiterConfig;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\RateLimiter\RateLimiterConfig
 */
#[TestDox('RateLimiterConfig конфигурация ограничителя запросов')]
#[CoversClass(RateLimiterConfig::class)]
final class RateLimiterConfigTest extends TestCase
{
    #[TestDox('Проверка значений по умолчанию')]
    public function testDefaultValues(): void
    {
        $config = new RateLimiterConfig();
        self::assertFalse($config->enabled);
        self::assertFalse($config->withHeaders);
    }

    #[DataProvider('provideSetAndGetCases')]
    #[TestDox('Установка и чтение параметров')]
    public function testSetAndGet(string $setName, string $getName, mixed $value): void
    {
        $config = new RateLimiterConfig();
        $config->{$setName}($value);
        self::assertSame($value, $config->{$getName});
    }

    public static function provideSetAndGetCases(): iterable
    {
        yield ['setEnabled', 'enabled', true];
        yield ['setWithHeaders', 'withHeaders', true];
    }

    #[TestDox('Установка после заморозки недопустима')]
    #[DataProvider('provideSetAndGetCases')]
    public function testFrozen(string $setName, string $getName, mixed $value): void
    {
        $config = new RateLimiterConfig();
        $config->freeze();
        self::expectException(ConfigException::class);
        $config->{$setName}($value);
    }
}
