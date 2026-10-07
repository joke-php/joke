<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Cache;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub\Stub;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Cache\Exceptions\CacheException;
use Vasoft\Joke\Cache\FileCache;
use Vasoft\Joke\Exceptions\FileSystemException;
use Vasoft\Joke\Support\FileSystem;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Cache\FileCache
 */
#[TestDox('Файловый кеш FileCache')]
#[CoversClass(FileCache::class)]
final class FileCacheTest extends TestCase
{
    use PHPMock;

    private string $baseDir;
    private FileSystem $fs;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/joke-cache-' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0o777, true);
        $this->fs = new FileSystem($this->baseDir);
        $this->cache = new FileCache($this->fs);
        $this->cache->setPrefix('test');
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->baseDir);
    }

    #[TestDox('set() записывает файл и возвращает true')]
    public function testSetWritesFile(): void
    {
        self::assertTrue($this->cache->set('key', 'value'));

        $file = $this->filePath('key');
        self::assertFileExists($file);
        self::assertStringContainsString('value', (string) file_get_contents($file));
    }

    #[TestDox('get() возвращает default для отсутствующего ключа')]
    public function testGetReturnsDefaultForMissingKey(): void
    {
        self::assertSame('default', $this->cache->get('missing', 'default'));
    }

    #[TestDox('set принимает TTL в секундах')]
    #[RunInSeparateProcess]
    public function testTtlInSeconds(): void
    {
        $times = [100, 120, 140];
        $timeMock = self::getFunctionMock('Vasoft\Joke\Cache', 'time');
        $timeMock->expects(self::exactly(4))->willReturnCallback(static function () use (&$times) {
            return array_shift($times);
        });

        $this->cache->set('key', 'data', 25);
        $times = [120, 140];
        self::assertSame('data', $this->cache->get('key', 'no'));
        self::assertSame('no', $this->cache->get('key', 'no'));
    }

    #[TestDox('set принимает TTL как интервал')]
    public function testCalculateExpirationPrivateMethod(): void
    {
        $reflection = new \ReflectionClass(FileCache::class);
        $method = $reflection->getMethod('calculateExpiration');

        $cache = new FileCache($this->fs);
        $cache->setPrefix('test');
        $interval = new \DateInterval('PT2H');

        $result = $method->invoke($cache, $interval);

        $expectedMin = time() + 7200 - 1;
        $expectedMax = time() + 7200 + 1;
        self::assertGreaterThanOrEqual($expectedMin, $result);
        self::assertLessThanOrEqual($expectedMax, $result);
    }

    #[TestDox('get() возвращает сохранённое значение')]
    public function testGetReturnsStoredValue(): void
    {
        $this->cache->set('key', 'data');

        self::assertSame('data', $this->cache->get('key'));
    }

    #[TestDox('get() корректно возвращает сохранённый false (не путает с отсутствием)')]
    public function testGetReturnsFalseValue(): void
    {
        $this->cache->set('key', false);

        self::assertFalse($this->cache->get('key', 'default'));
    }

    #[TestDox('get() удаляет испорченный файл и возвращает default')]
    public function testGetReturnsDefaultOnCorruptedFile(): void
    {
        $file = $this->filePath('corrupt');
        $this->fs->ensureDirectory(dirname($file));
        file_put_contents($file, 'this is not serialized data');

        self::assertSame('default', $this->cache->get('corrupt', 'default'));
        self::assertFileDoesNotExist($file);
    }

    #[TestDox('get() удаляет испорченный файл если при десериализации брошено исключение и возвращает default')]
    #[RunInSeparateProcess]
    public function testGetReturnsDefaultOnUnserializeFail(): void
    {
        $unserialize = self::getFunctionMock('Vasoft\Joke\Cache', 'unserialize');
        $unserialize->expects(self::once())->with('this is not serialized data')->willThrowException(
            new \RuntimeException('test'),
        );
        $file = $this->filePath('corrupt');
        $this->fs->ensureDirectory(dirname($file));
        file_put_contents($file, 'this is not serialized data');

        self::assertSame('default', $this->cache->get('corrupt', 'default'));
        self::assertFileDoesNotExist($file);
    }

    #[TestDox('get() удаляет истёкшую запись и возвращает default')]
    public function testGetReturnsDefaultOnExpiredEntry(): void
    {
        $this->cache->set('expired', 'value', -10);

        self::assertSame('default', $this->cache->get('expired', 'default'));
        self::assertFileDoesNotExist($this->filePath('expired'));
    }

    #[TestDox('get() не десериализует объекты при allowed_classes=false')]
    public function testGetBlocksObjectInjection(): void
    {
        $file = $this->filePath('object');
        $this->fs->ensureDirectory(dirname($file));
        file_put_contents($file, serialize(['value' => new \stdClass(), 'expires' => null]));

        self::assertSame('default', $this->cache->get('object', 'default'));
        self::assertFileDoesNotExist($file);
    }

    #[TestDox('get() разрешает объекты из whitelist allowed_classes')]
    public function testGetAllowsWhitelistedClasses(): void
    {
        $cache = new FileCache($this->fs, [\stdClass::class]);
        $cache->set('object', new \stdClass());

        $value = $cache->get('object', 'default');

        self::assertInstanceOf(\stdClass::class, $value);
    }

    #[TestDox('has() отражает наличие и отсутствие ключа')]
    public function testHas(): void
    {
        $this->cache->set('key', 'value');

        self::assertTrue($this->cache->has('key'));
        self::assertFalse($this->cache->has('missing'));

        $this->cache->delete('key');

        self::assertFalse($this->cache->has('key'));
    }

    #[TestDox('delete() удаляет файл и возвращает true, включая отсутствующий ключ')]
    public function testDelete(): void
    {
        $this->cache->set('key', 'value');

        self::assertTrue($this->cache->delete('key'));
        self::assertFileDoesNotExist($this->filePath('key'));
        self::assertTrue($this->cache->delete('key'));
    }

    #[TestDox('getMultiple() возвращает значения и default для отсутствующих ключей')]
    public function testGetMultiple(): void
    {
        $this->cache->set('a', '1');
        $this->cache->set('b', '2');

        self::assertSame(
            ['a' => '1', 'b' => '2', 'c' => 'default'],
            $this->cache->getMultiple(['a', 'b', 'c'], 'default'),
        );
    }

    #[TestDox('setMultiple() и deleteMultiple() работают по всем ключам')]
    public function testSetAndDeleteMultiple(): void
    {
        self::assertTrue($this->cache->setMultiple(['a' => '1', 'b' => '2']));
        self::assertTrue($this->cache->has('a'));
        self::assertTrue($this->cache->has('b'));

        self::assertTrue($this->cache->deleteMultiple(['a', 'b']));
        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
    }

    #[TestDox('deleteMultiple() возвращает false если не удалось удалить один из ключей')]
    #[RunInSeparateProcess]
    public function testDeleteMultipleFalseOnFail(): void
    {
        $unlink = self::getFunctionMock('Vasoft\Joke\Cache', 'unlink');
        $unlink->expects(self::exactly(2))->willReturnCallback(static function (string $name) {
            static $count = 0;
            ++$count;
            if (1 === $count) {
                return false;
            }

            return \unlink($name);
        });
        $this->cache->setMultiple(['c' => '1', 'd' => '2']);

        self::assertFalse($this->cache->deleteMultiple(['c', 'd']));
        self::assertTrue($this->cache->has('c'));
        self::assertFalse($this->cache->has('d'));
    }

    #[TestDox('setMultiple() возвращает false если не удалось сохранить один из ключей')]
    #[RunInSeparateProcess]
    public function testSetMultipleFalseOnFail(): void
    {
        $filePutContents = self::getFunctionMock('Vasoft\Joke\Support', 'file_put_contents');
        $filePutContents->expects(self::exactly(2))
            ->willReturnCallback(static function ($filname, $data, $flags, $context) {
                static $count = 0;
                ++$count;
                if (1 === $count) {
                    return 0;
                }

                return \file_put_contents($filname, $data, $flags, $context);
            });
        self::assertFalse($this->cache->setMultiple(['e' => '1', 'f' => '2']));

        self::assertFalse($this->cache->has('e'));
        self::assertTrue($this->cache->has('f'));
    }

    #[TestDox('clear() удаляет всё дерево кеша, включая вложенные подкаталоги')]
    public function testClearRemovesDirectoryTree(): void
    {
        $this->cache->set('alpha', '1');   // md5('alpha') даст свою подпапку
        $this->cache->set('beta', '2');    // и md5('beta') — другую

        self::assertTrue($this->cache->clear());

        self::assertDirectoryDoesNotExist($this->fs->atCache('test'));
        self::assertSame('default', $this->cache->get('alpha', 'default'));
        self::assertSame('default', $this->cache->get('beta', 'default'));
    }

    #[TestDox('clear() возвращает false если не удалось удалить один из вложенных ключей')]
    #[RunInSeparateProcess]
    public function testClearFalseOnFail(): void
    {
        $unlink = self::getFunctionMock('Vasoft\Joke\Cache', 'unlink');
        $unlink->expects(self::exactly(2))->willReturnCallback(static function (string $name) {
            static $count = 0;
            ++$count;
            if (1 === $count) {
                return false;
            }

            return \unlink($name);
        });

        $this->cache->set('alpha-ext', '1');
        $this->cache->set('beta-ext', '2');

        self::assertFalse($this->cache->clear());

        self::assertDirectoryExists($this->fs->atCache('test'));
        self::assertSame('default', $this->cache->get('alpha-ext', 'default'));
        self::assertSame('2', $this->cache->get('beta-ext', 'default'));
    }

    #[TestDox('clear не даст очистить весь проект')]
    #[RunInSeparateProcess]
    public function testExceptionOnClearRoot(): void
    {
        $cache = new FileCache($this->fs);
        $cache->setPrefix('../');
        self::expectException(CacheException::class);
        self::expectExceptionMessageIs('Resolved "../" is outside of the "var/cache/".');

        $cache->clear();
    }

    #[TestDox('clear при пустом префиксе не удаляет всю директорию кеша')]
    #[RunInSeparateProcess]
    public function testEmptyPrefix(): void
    {
        $cache = new FileCache($this->fs);
        $this->fs->ensureDirectory($this->fs->cachePath);
        $cache->clear();
        self::assertDirectoryExists($this->fs->cachePath);
    }

    #[TestDox('clear() возвращает false если не удалось удалить основной каталог')]
    #[RunInSeparateProcess]
    public function testClearFalseOnFailMainDelete(): void
    {
        $rmdir = self::getFunctionMock('Vasoft\Joke\Cache', 'rmdir');
        $rmdir->expects(self::exactly(3))->willReturnCallback(static function (string $name) {
            static $count = 0;
            ++$count;
            if (3 === $count) {
                return false;
            }

            return \rmdir($name);
        });

        $this->cache->set('alpha1', '1');
        $this->cache->set('beta1', '2');

        self::assertFalse($this->cache->clear());

        self::assertDirectoryExists($this->fs->atCache('test'));
        self::assertSame('default', $this->cache->get('alpha1', 'default'));
        self::assertSame('default', $this->cache->get('beta1', 'default'));
    }

    #[TestDox('clear() возвращает true, если каталога кеша нет')]
    public function testClearWhenNoCacheDirectory(): void
    {
        self::assertTrue($this->cache->clear());
    }

    #[TestDox('Пустой ключ вызывает CacheException')]
    #[DataProvider('provideInvalidKeyThrowsCacheExceptionCases')]
    public function testInvalidKeyThrowsCacheException(string $key): void
    {
        self::expectException(CacheException::class);

        $this->cache->get($key);
    }

    public static function provideInvalidKeyThrowsCacheExceptionCases(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
    }

    #[TestDox('set() Оборачивает исключение сервиса файловой системы')]
    public function testSetExceptionOnFileSystemException(): void
    {
        /** @var FileSystem|Stub $fs */
        $fs = self::createStub(FileSystem::class);
        $fs->method('atCache')->willThrowException(new FileSystemException(__METHOD__));

        $cache = new FileCache($fs);
        self::expectException(CacheException::class);
        self::expectExceptionMessageIs(__METHOD__);
        $cache->set('value', 1);
    }

    #[TestDox('delete() Оборачивает исключение сервиса файловой системы')]
    public function testDeleteExceptionOnFileSystemException(): void
    {
        /** @var FileSystem|Stub $fs */
        $fs = self::createStub(FileSystem::class);
        $fs->method('atCache')->willThrowException(new FileSystemException(__METHOD__));

        $cache = new FileCache($fs);
        self::expectException(CacheException::class);
        self::expectExceptionMessageIs(__METHOD__);
        $cache->delete('value');
    }

    #[TestDox('get() Оборачивает исключение сервиса файловой системы')]
    public function testGetExceptionOnFileSystemException(): void
    {
        /** @var FileSystem|Stub $fs */
        $fs = self::createStub(FileSystem::class);
        $fs->method('atCache')->willThrowException(new FileSystemException(__METHOD__));

        $cache = new FileCache($fs);
        self::expectException(CacheException::class);
        self::expectExceptionMessageIs(__METHOD__);
        $cache->get('value');
    }

    /** Возвращает путь к файлу кеша для ключа (та же логика, что в FileCache). */
    private function filePath(string $key): string
    {
        $hash = md5($key);

        return $this->fs->atCache('test' . \DIRECTORY_SEPARATOR . substr($hash, 0, 2))
            . \DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
