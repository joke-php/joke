<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Cache\Exceptions\CacheException;
use Vasoft\Joke\Cache\FileCache;
use Vasoft\Joke\Support\FileSystem;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Cache\FileCache
 */
#[TestDox('FileSystem безопасная модификация кеша')]
#[CoversClass(FileCache::class)]
final class FileCacheModifyTest extends TestCase
{
    private FileSystem $fs;
    private FileCache $cache;
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/joke_cache_modify_' . uniqid();
        mkdir($this->testDir, 0o775, true);

        $this->fs = new FileSystem($this->testDir);
        $this->cache = new FileCache($this->fs);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->testDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->testDir);
        }
    }

    #[TestDox('Базовый функционал')]
    public function testModifyExistingKey(): void
    {
        $this->cache->set('counter', 10);

        $result = $this->cache->modify('counter', static fn(mixed $v): int => (int) $v + 5);

        self::assertSame(15, $result);
        self::assertSame(15, $this->cache->get('counter'));
    }

    #[TestDox('Если ключ не существует создает его')]
    public function testModifyNonExistentKeyPassesNull(): void
    {
        $receivedValue = 'not_called';

        $result = $this->cache->modify('new_key', static function (mixed $value) use (&$receivedValue): string {
            $receivedValue = $value;

            return 'initialized';
        });

        self::assertNull($receivedValue);
        self::assertSame('initialized', $result);
        self::assertSame('initialized', $this->cache->get('new_key'));
    }

    #[TestDox('modify изменяет время истечения')]
    public function testModifyRestartsTtl(): void
    {
        $this->cache->set('expiring', 'old', 1);
        $result = $this->cache->modify('expiring', static fn(mixed $v): string => $v . ' new', 3600);
        self::assertSame('old new', $result);
        sleep(2);
        self::assertTrue($this->cache->has('expiring'));
        self::assertSame('old new', $this->cache->get('expiring'));
    }

    #[TestDox('Modify может уменьшать время жизни')]
    public function testModifyWithZeroTtlDeletesEffectively(): void
    {
        $this->cache->set('key', 'value', 3600);

        $this->cache->modify('key', static fn(mixed $v): string => 'gone', 0);
        sleep(1);

        self::assertFalse($this->cache->has('key'));
        self::assertNull($this->cache->get('key'));
    }

    #[TestDox('Исключение если модификатор возвращает не сереализуемое значение')]
    public function testModifyThrowsOnNonSerializableReturn(): void
    {
        $this->cache->set('key', 'value');

        $this->expectException(CacheException::class);
        $this->expectExceptionMessageIs('Modifier returned non-serializable type: "Closure".');

        $this->cache->modify('key', static fn(mixed $v): \Closure => static fn() => 'oops');
    }

    #[TestDox('modify Оборачивает исключения в CacheException')]
    public function testWrapException(): void
    {
        $this->cache->set('key', 'value');

        $this->expectException(CacheException::class);
        $this->expectExceptionMessageIs('Test exception.');

        $this->cache->modify('key', static function (mixed $v): never {
            throw  new \Exception('Test exception.');
        });
    }

    #[TestDox('Исключение если модификатор возвращает ресурс')]
    public function testModifyThrowsOnResourceReturn(): void
    {
        $this->cache->set('key', 'value');

        $this->expectException(CacheException::class);
        $this->expectExceptionMessageIs('Modifier returned non-serializable type: "resource (stream)".');

        $this->cache->modify('key', static fn(mixed $v) => fopen('php://memory', 'r'));
    }

    #[TestDox('При ошибке модификации сохраняется прежнее значение')]
    public function testModifyPreservesValueOnModifierException(): void
    {
        $this->cache->set('safe', 'original');

        try {
            $this->cache->modify('safe', static function (mixed $v): never {
                throw new \RuntimeException('Business logic failed');
            });
            self::fail('Expected RuntimeException');
        } catch (CacheException) {
            // Ожидаемо
        }

        self::assertSame('original', $this->cache->get('safe'));
    }

    #[TestDox('Просроченное значение не используется')]
    public function testModifyExpiredKeyPassesNullToModifier(): void
    {
        $this->cache->set('expired', 'old_value', 1);
        sleep(2);

        $receivedValue = 'not_called';
        $result = $this->cache->modify('expired', static function (mixed $value) use (&$receivedValue): string {
            $receivedValue = $value;

            return 'refreshed';
        }, 3600);

        self::assertNull($receivedValue);
        self::assertSame('refreshed', $result);
        self::assertTrue($this->cache->has('expired'));
    }

    #[TestDox('Работает с различными типами значений')]
    #[DataProvider('provideModifyWorksWithVariousTypesCases')]
    public function testModifyWorksWithVariousTypes(mixed $initial, mixed $modified): void
    {
        $this->cache->set('typed', $initial);

        $result = $this->cache->modify('typed', static fn(mixed $v): mixed => $modified);

        self::assertSame($modified, $result);
        self::assertSame($modified, $this->cache->get('typed'));
    }

    public static function provideModifyWorksWithVariousTypesCases(): iterable
    {
        yield 'int' => [0, 42];
        yield 'float' => [0.0, 3.14];
        yield 'string' => ['', 'hello'];
        yield 'bool' => [false, true];
        yield 'null_to_array' => [null, ['a' => 1]];
        yield 'array_merge' => [['a' => 1], ['a' => 1, 'b' => 2]];
        yield 'nested_array' => [[], ['level1' => ['level2' => 'deep']]];
    }
}
