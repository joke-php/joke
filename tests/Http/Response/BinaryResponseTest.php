<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Routing\Exceptions\NotFoundException;
use Vasoft\Joke\Support\FileSystem;
use Vasoft\Joke\Tests\Fixtures\Http\Response\DummyFileResponse;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\BinaryResponse
 */
final class BinaryResponseTest extends TestCase
{
    private static CookieCollection $cookies;
    private string $basePath;
    private FileSystem $fileSystem;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/joke_bin_resp_test_' . bin2hex(random_bytes(8));
        mkdir($this->basePath, 0o775, true);
        $this->fileSystem = new FileSystem($this->basePath);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->basePath);
    }

    public static function setUpBeforeClass(): void
    {
        self::$cookies = new CookieCollection(new CookieConfig(), true);
    }

    public function testEmpty(): void
    {
        $instance = new DummyFileResponse(self::$cookies, $this->fileSystem);
        ob_start();
        $instance->send();
        ob_end_clean();
        self::assertSame(
            [
                'Content-Type' => 'application/test',
                'Content-Length' => 0,
                'Content-Disposition' => 'attachment; filename=""',
            ],
            $instance->sentHeaders,
        );
        self::assertSame('', $instance->getBodyAsString());
    }

    public function testSentFromFile(): void
    {
        [$tempFile, $length, $baseName] = $this->writeTempFile('testSentFromFile');

        $instance = new DummyFileResponse(self::$cookies, $this->fileSystem);
        $instance->load($tempFile);
        ob_start();
        $instance->send();
        ob_end_clean();
        self::assertSame(
            [
                'Content-Type' => 'application/test',
                'Content-Length' => $length,
                'Content-Disposition' => 'attachment; filename="' . $baseName . '"',
            ],
            $instance->sentHeaders,
        );
    }

    #[TestDox('Нельзя отправить файл находящийся вне директории проекта')]
    public function testSentFromFileExceptionIfFileNotInProject(): void
    {
        $length = random_int(22, 256);
        $baseName = 'testSentFromFileExceptionIfFileNotInProject' . $length . '.joke';
        $tempFile = dirname(__DIR__, 2) . '/Fixtures/cache/' . $baseName;

        file_put_contents($tempFile, str_repeat('*', $length));

        try {
            $instance = new DummyFileResponse(self::$cookies, $this->fileSystem);
            self::expectException(NotFoundException::class);
            self::expectExceptionMessageIs('File not found.');
            $instance->load($tempFile);
            ob_start();
            $instance->send();
            ob_end_clean();
        } finally {
            unlink($tempFile);
        }
    }

    public function testSentFromFileWithCustomName(): void
    {
        [$tempFile, $length] = $this->writeTempFile('testSentFromFileWithCustomName');

        $instance = new DummyFileResponse(self::$cookies, $this->fileSystem);
        $instance->load($tempFile);
        $instance->filename = '/example/base.pdf';
        ob_start();
        $instance->send();
        ob_end_clean();
        self::assertSame(
            [
                'Content-Type' => 'application/test',
                'Content-Length' => $length,
                'Content-Disposition' => 'attachment; filename="base.pdf"',
            ],
            $instance->sentHeaders,
        );
    }

    public function testCustomRewriteLoadedContent(): void
    {
        [$tempFile, , $baseName] = $this->writeTempFile('testCustomRewriteLoadedContent');

        $instance = new DummyFileResponse(self::$cookies, $this->fileSystem);
        $instance->load($tempFile);
        $instance->setBody('test');
        ob_start();
        $instance->send();
        ob_end_clean();
        self::assertSame(
            [
                'Content-Type' => 'application/test',
                'Content-Length' => 4,
                'Content-Disposition' => 'attachment; filename="' . $baseName . '"',
            ],
            $instance->sentHeaders,
        );
    }

    public function testFileNotfound(): void
    {
        [$tempFile] = $this->writeTempFile('testFileNotfound');
        unlink($tempFile);
        $instance = new DummyFileResponse(self::$cookies, $this->fileSystem);
        self::expectException(NotFoundException::class);
        self::expectExceptionMessageIs('File not found.');
        $instance->load($tempFile);
    }

    public function testCustomContent(): void
    {
        $instance = new DummyFileResponse(self::$cookies, $this->fileSystem);
        $instance->setBody('test');
        $instance->filename = 'test.pdf';
        ob_start();
        $instance->send();
        ob_end_clean();
        self::assertSame(
            [
                'Content-Type' => 'application/test',
                'Content-Length' => 4,
                'Content-Disposition' => 'attachment; filename="test.pdf"',
            ],
            $instance->sentHeaders,
        );
    }

    public function testBody(): void
    {
        $instance = new DummyFileResponse(self::$cookies, $this->fileSystem);
        $instance->setBody('test');
        self::assertSame($instance->getBodyAsString(), $instance->getBody());
    }

    private function writeTempFile(string $name): array
    {
        $length = random_int(22, 256);
        $baseName = $name . $length . '.joke';
        $tempFile = $this->basePath . '/' . $baseName;

        file_put_contents($tempFile, str_repeat('*', $length));

        return [$tempFile, $length, $baseName];
    }

    private function cleanDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
