<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Support\Normalizers;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Support\Normalizers\Path;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Support\Normalizers\Path
 */
final class PathTest extends TestCase
{
    use PHPMock;

    #[RunInSeparateProcess]
    public function testConstructorSuccess(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(true);

        $path = '/var/www/project';
        $normalizer = new Path($path);

        $expectedBase = '/var/www/project' . \DIRECTORY_SEPARATOR;
        self::assertSame($expectedBase, $normalizer->basePath);
        self::assertSame($expectedBase . 'bootstrap' . \DIRECTORY_SEPARATOR, $normalizer->bootstrapPath);
        self::assertSame($expectedBase . 'public' . \DIRECTORY_SEPARATOR, $normalizer->publicPath);
        $expectedBase = $expectedBase . 'var' . \DIRECTORY_SEPARATOR;
        self::assertSame($expectedBase, $normalizer->varPath);
        self::assertSame($expectedBase . 'cache' . \DIRECTORY_SEPARATOR, $normalizer->cachePath);
        self::assertSame($expectedBase . 'log' . \DIRECTORY_SEPARATOR, $normalizer->logPath);
    }

    public function testConstructorFailsOnRelativePath(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Path must be absolute: config/app');
        $path = 'config/app';
        new Path($path);
    }

    #[RunInSeparateProcess]
    public function testConstructorFailsOnNonExistentDirectory(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Path must be a directory: /fake/path');

        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(false);

        new Path('/fake/path');
    }

    #[RunInSeparateProcess]
    public function testNormalizeDirPathRelative(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(true);

        $normalizer = new Path('/base');

        $result = $normalizer->normalizeDir('storage/logs');

        $expected = '/base' . \DIRECTORY_SEPARATOR . 'storage/logs' . \DIRECTORY_SEPARATOR;
        self::assertSame($expected, $result);
    }

    #[RunInSeparateProcess]
    public function testNormalizeDirPathAbsolute(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(true);

        $normalizer = new Path('/base');
        $input = '/etc/my-logs/';
        $result = $normalizer->normalizeDir($input);

        $expected = '/etc/my-logs' . \DIRECTORY_SEPARATOR;
        self::assertSame($expected, $result);
    }

    #[RunInSeparateProcess]
    public function testNormalizeDirPathTrailingSlashes(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(true);

        $normalizer = new Path('/base');

        $result = $normalizer->normalizeDir('folder///');

        $expected = '/base' . \DIRECTORY_SEPARATOR . 'folder' . \DIRECTORY_SEPARATOR;
        self::assertSame($expected, $result);
    }

    #[RunInSeparateProcess]
    public function testNormalizeFilePathRelative(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(true);

        $normalizer = new Path('/base');

        $result = $normalizer->normalizeFile('config/app.php');

        $expected = '/base' . \DIRECTORY_SEPARATOR . 'config/app.php';
        self::assertSame($expected, $result);
    }

    #[RunInSeparateProcess]
    public function testNormalizeFilePathAbsolute(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(true);

        $normalizer = new Path('/base');

        $input = '/etc/config.json';
        $result = $normalizer->normalizeFile($input);

        self::assertSame($input, $result);
    }

    #[RunInSeparateProcess]
    public function testIsAbsoluteUnix(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::atLeastOnce())->willReturn(true);
        $substrMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'substr');
        $substrMock->expects(self::atLeastOnce())->willReturn('LIN');

        $normalizer = new Path('/base');

        self::assertTrue($normalizer->isAbsolute('/var/log'));
        self::assertFalse($normalizer->isAbsolute('var/log'));
        self::assertFalse($normalizer->isAbsolute('C:/Windows'));
    }

    #[RunInSeparateProcess]
    public function testIsAbsoluteWindows(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(true);
        $substrMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'substr');
        $substrMock->expects(self::once())->willReturn('WIN');

        $normalizer = new Path('C:/Project');

        self::assertTrue($normalizer->isAbsolute('C:/Windows/System32'));
        self::assertTrue($normalizer->isAbsolute('d:/data'));
        self::assertFalse($normalizer->isAbsolute('relative/path'));
        self::assertFalse($normalizer->isAbsolute('/unix/style/path'));
    }

    /**
     * Тест нормализации путей на Windows (с буквой диска).
     */
    #[RunInSeparateProcess]
    public function testWindowsPathNormalization(): void
    {
        $isDirMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'is_dir');
        $isDirMock->expects(self::once())->willReturn(true);

        $substrMock = $this->getFunctionMock('Vasoft\Joke\Support\Normalizers', 'substr');
        $substrMock->expects(self::once())->willReturn('WIN');

        $normalizer = new Path('C:/Project');
        self::assertSame('C:/Project' . \DIRECTORY_SEPARATOR, $normalizer->basePath);

        $res = $normalizer->normalizeFile('config/app.php');
        self::assertSame('C:/Project' . \DIRECTORY_SEPARATOR . 'config/app.php', $res);
    }
}
