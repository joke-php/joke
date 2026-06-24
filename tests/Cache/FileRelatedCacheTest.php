<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Cache;

use Vasoft\Joke\Cache\FileRelatedCache;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Cache\FileRelatedCache
 */
final class FileRelatedCacheTest extends TestCase
{
    private string $cachePath;

    public function testCache(): void
    {
        $content = microtime();
        $cachedFile = $this->cachePath . 'example1.php';

        file_put_contents($cachedFile, $content);
        $cache = new FileRelatedCache($this->cachePath, $cachedFile, 30);
        self::assertFalse($cache->exists());
        $cache->set($content);
        self::assertTrue($cache->exists());
        self::assertSame($content, file_get_contents($cache->path));
    }

    public function testCacheTtl(): void
    {
        $content = microtime();
        $cachedFile = $this->cachePath . 'example2.php';

        file_put_contents($cachedFile, $content);
        $cache = new FileRelatedCache($this->cachePath, $cachedFile, 0);
        $cache->set($content);
        self::assertFalse($cache->exists());
    }

    public function testUpdateFile(): void
    {
        $content = microtime();
        $cachedFile = $this->cachePath . 'example3.php';
        file_put_contents($cachedFile, $content);
        $cache = new FileRelatedCache($this->cachePath, $cachedFile, 100);
        $cache->set($content);
        $cacheKey1 = $cache->path;
        self::assertSame($content, file_get_contents($cache->path));
        touch($cachedFile, time() + 1);

        $cache2 = new FileRelatedCache($this->cachePath, $cachedFile, 200);
        self::assertSame($cacheKey1, $cache2->path);
        self::assertFalse($cache2->exists());
        $cache2->set($content);
        self::assertSame($content, file_get_contents($cache2->path));
    }

    public function testClean(): void
    {
        $content = microtime();
        $cachedFile = $this->cachePath . 'example4.php';
        file_put_contents($cachedFile, $content);
        $cache = new FileRelatedCache($this->cachePath, $cachedFile, 100);
        $cache->set($content);
        self::assertFileExists($cache->path);
        $cache->clear();
        self::assertFileDoesNotExist($cache->path);
    }

    protected function setUp(): void
    {
        $this->ensureDir();
    }

    protected function tearDown(): void
    {
        $this->clean();
    }

    private function ensureDir(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/joke-test-cache-' . uniqid() . '/';
        mkdir($this->cachePath, 0o755, true);
    }

    private function clean(): void
    {
        if (!file_exists($this->cachePath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cachePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->cachePath);
    }
}
