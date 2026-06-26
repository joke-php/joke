<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response\Html\Asset;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\Response\Html\Asset\AssetFileManager;
use PHPUnit\Framework\MockObject\Rule\AnyInvokedCount;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\Html\Asset\AssetFileManager
 */
#[RunTestsInSeparateProcesses]
final class AssetFileManagerTest extends TestCase
{
    use PHPMock;

    private static string $projectPath = '';
    private static string $documentRoot = '';
    private static string $outsideFile = '';
    private static string $insideFile = '';
    private static string $assetUri = 'custom';
    private static string $externalFile = '';

    public static function setUpBeforeClass(): void
    {
        self::$externalFile = dirname(__DIR__, 5) . \DIRECTORY_SEPARATOR . 'var' . \DIRECTORY_SEPARATOR . 'test.js';

        $name = 'Asset' . random_int(1000, 9999);
        $base = dirname(__DIR__, 4) . \DIRECTORY_SEPARATOR . 'Fixtures/cache' . \DIRECTORY_SEPARATOR;
        self::$projectPath = $base . $name . \DIRECTORY_SEPARATOR;
        self::$documentRoot = self::$projectPath . 'www' . \DIRECTORY_SEPARATOR;
        mkdir(self::$documentRoot . self::$assetUri . \DIRECTORY_SEPARATOR, recursive: true);
        mkdir(self::$projectPath . 'modules' . \DIRECTORY_SEPARATOR, recursive: true);
        self::$outsideFile = self::$projectPath . 'modules/outside.css';
        self::$insideFile = self::$documentRoot . 'inside.js';
        file_put_contents(self::$outsideFile, 'b{color:blue;}');
        file_put_contents(self::$insideFile, "console.log('test');");
    }

    protected static function createExternalFile(): void
    {
        file_put_contents(self::$externalFile, "console.log('test');");
    }

    public static function tearDownAfterClass(): void
    {
        self::cleanDir(self::$projectPath);
    }

    private static function cleanDir(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = scandir($dir);
        if (is_array($files)) {
            $items = array_diff($files, ['.', '..']);
            foreach ($items as $item) {
                $path = $dir . \DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    self::cleanDir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }

    protected function setUp(): void
    {
        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'md5')
            ->expects(new AnyInvokedCount())
            ->willReturn('hash');
    }

    public function testSingleUrl(): void
    {
        $manager = new AssetFileManager('', '');
        self::assertSame('https://yandex.ru', $manager->process('https://yandex.ru', ''));
    }

    public function testOutsideFileNotExists(): void
    {
        $file = '/some/path/to/' . random_int(1000, 9999) . '-file.php';
        $manager = new AssetFileManager('', '');
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('Asset file not found: ' . $file);
        $manager->process($file, '');
    }

    public function testOutsideProjectFile(): void
    {
        $file = '/some/path/to/' . random_int(1000, 9999) . '-file.php';
        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('Asset file not found: ' . $file);
        $manager->process($file, self::$assetUri);
    }

    public function testInsideFileNotExists(): void
    {
        $file = self::$documentRoot . random_int(1000, 9999) . '-file.php';
        $manager = new AssetFileManager('', '');
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('Asset file not found: ' . $file);
        $manager->process($file, '');
    }

    public function testOutsideAllowedDirectory(): void
    {
        self::createExternalFile();
        $file = self::$externalFile;
        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);

        self::expectException(JokeException::class);
        self::expectExceptionMessageIs("Asset path outside allowed {$file} directory.");
        $manager->process($file, self::$assetUri);
    }

    public function testFileOutsideRootShouldBeCopyAbsolutePath(): void
    {
        $expectedUri = '/' . self::$assetUri . '/modules/hash_outside.css?v=';
        $expectedFile = self::$documentRoot . self::$assetUri . \DIRECTORY_SEPARATOR . 'modules/hash_outside.css';

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $uri = $manager->process(self::$outsideFile, self::$assetUri);

        self::assertFileExists($expectedFile);
        self::assertStringStartsWith($expectedUri, $uri);
    }

    public function testReplacementAnsLowercase(): void
    {
        $expectedUri = '/' . self::$assetUri . '/assets/hash_outside.css?v=';
        $expectedFile = self::$documentRoot . self::$assetUri . \DIRECTORY_SEPARATOR . 'assets/hash_outside.css';

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $manager->registerDirectoryReplace('/modules/', '/ASSets/');
        $uri = $manager->process(self::$outsideFile, self::$assetUri);

        self::assertFileExists($expectedFile);
        self::assertStringStartsWith($expectedUri, $uri);
    }

    public function testFileInsideRoot(): void
    {
        $expectedUri = '/inside.js?test=value&v=';

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $uri = $manager->process(self::$insideFile . '?test=value', self::$assetUri);

        self::assertStringStartsWith($expectedUri, $uri);
    }

    public function testDoesNotCopyIfNotUpdated(): void
    {
        $expectedUri = '/' . self::$assetUri . '/modules/hash_outside.css?v=';
        $expectedFile = self::$documentRoot . self::$assetUri . \DIRECTORY_SEPARATOR . 'modules/hash_outside.css';

        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'file_exists')
            ->expects(self::exactly(1))
            ->willReturn(true);
        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'filemtime')
            ->expects(self::exactly(2))
            ->willReturnCallback(static fn(string $name) => match ($name) {
                self::$outsideFile => 1,
                $expectedFile => 2,
                default => throw new JokeException($name),
            });

        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'fopen')
            ->expects(self::never());

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $uri = $manager->process(self::$outsideFile, self::$assetUri);

        self::assertStringStartsWith($expectedUri, $uri);
    }

    public function testUnableLocking(): void
    {
        $expectedFile = self::$documentRoot . self::$assetUri . \DIRECTORY_SEPARATOR . 'modules/hash_outside.css';

        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'fopen')
            ->expects(self::once())
            ->willReturn(false);

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);

        self::expectException(JokeException::class);
        self::expectExceptionMessageIs("Unable to open file for locking: {$expectedFile}.");

        $manager->process(self::$outsideFile, self::$assetUri);
    }

    public function testUnableCopy(): void
    {
        $expectedFile = self::$documentRoot . self::$assetUri . \DIRECTORY_SEPARATOR . 'modules/hash_outside.css';

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'copy')
            ->expects(self::once())
            ->willReturn(false);
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('Unable to copy asset from ' . self::$outsideFile . " to {$expectedFile}");
        $manager->process(self::$outsideFile, self::$assetUri);
    }

    public function testEnsureDir(): void
    {
        $expectedDir = self::$documentRoot . self::$assetUri . \DIRECTORY_SEPARATOR . 'modules/';
        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'mkdir')
            ->expects(self::atLeastOnce())
            ->willReturnCallback(static function (string $path) {
                static $checked = 0;
                ++$checked;

                return 1 !== $checked;
            });
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs("Unable to create directory '{$expectedDir}'.");
        $manager->process(self::$outsideFile, self::$assetUri);
    }
}
