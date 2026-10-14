<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\RateLimiter\Storage;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\RateLimiter\Exceptions\RateLimiterException;
use Vasoft\Joke\Http\RateLimiter\Storage\FileStorage;
use Vasoft\Joke\Support\FileSystem;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\RateLimiter\Storage\FileStorage
 */
#[CoversClass(FileStorage::class)]
#[TestDox('FileStorage хранение счетчиков в файлах')]
final class FileStorageTest extends TestCase
{
    use PHPMock;

    private string $tmpDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/joke_rate_lim_fs_' . uniqid();
        mkdir($this->tmpDir, 0o755, true);
        $fs = new FileSystem($this->tmpDir);
        $this->storage = new FileStorage($fs);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tmpDir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tmpDir);
    }

    #[TestDox('Выполняет увеличение счетчика')]
    public function testIncrement(): void
    {
        $result = $this->storage->increment('test_key');
        self::assertSame(1, $result);

        $result = $this->storage->increment('test_key');
        self::assertSame(2, $result);
    }

    #[TestDox('Истечение срока действия')]
    public function testTtlExpiry(): void
    {
        $value = $this->storage->increment('ttl_key', 1);
        self::assertSame(1, $value);

        sleep(2);
        $value = $this->storage->increment('ttl_key', 1);
        self::assertSame(1, $value);
    }

    #[TestDox('Не смешивает счетчики и ttl')]
    public function testMultipleKeys(): void
    {
        $this->storage->increment('key1');
        self::assertSame(2, $this->storage->increment('key1', 1));
        self::assertSame(1, $this->storage->increment('key2', 100));
        sleep(2);
        self::assertSame(1, $this->storage->increment('key1', 1));
        self::assertSame(2, $this->storage->increment('key2', 100));
    }

    #[TestDox('Оборачивает исключения системы кеширования')]
    #[RunInSeparateProcess]
    public function testWrapException(): void
    {
        $foo = self::getFunctionMock('Vasoft\joke\Cache', 'is_array');
        $foo->expects(self::once())->willThrowException(new \RuntimeException('Test Exception'));
        self::expectException(RateLimiterException::class);
        self::expectExceptionMessageIs('Test Exception');

        $this->storage->increment('key1');
    }
}
