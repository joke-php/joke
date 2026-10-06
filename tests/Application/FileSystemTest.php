<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Application;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Exceptions\OutsideFileException;
use Vasoft\Joke\Support\FileSystem;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Exceptions\FileSystemException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Support\FileSystem
 */
#[CoversClass(FileSystem::class)]
#[TestDox('FileSystem — единый сервис знаний о путях проекта')]
final class FileSystemTest extends TestCase
{
    use PHPMock;

    private string $basePath;
    private FileSystem $fileSystem;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/fs_test_' . bin2hex(random_bytes(8));
        mkdir($this->basePath, 0o775, true);
        $this->fileSystem = new FileSystem($this->basePath);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->basePath);
    }

    #[TestDox('Конструктор принимает существующий абсолютный путь')]
    public function testConstructorAcceptsExistingAbsolutePath(): void
    {
        $fs = new FileSystem($this->basePath);
        self::assertSame(
            rtrim($this->basePath, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR,
            $fs->basePath,
        );
    }

    #[TestDox('Конструктор выбрасывает ConfigException для несуществующего пути')]
    public function testConstructorThrowsForNonExistentPath(): void
    {
        self::expectException(ConfigException::class);
        $path = $this->basePath . '/nonexistent';
        self::expectExceptionMessageIs("Path must be an existing directory: '{$path}'.");
        new FileSystem($path);
    }

    #[TestDox('Конструктор выбрасывает ConfigException для пути к файлу')]
    public function testConstructorThrowsForFilePath(): void
    {
        $file = $this->basePath . '/file.txt';
        touch($file);

        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs("Path must be an existing directory: '{$file}'.");
        new FileSystem($file);
    }

    #[TestDox('varPath возвращает basePath + var/')]
    public function testVarPath(): void
    {
        self::assertSame(
            $this->fileSystem->basePath . 'var' . \DIRECTORY_SEPARATOR,
            $this->fileSystem->varPath,
        );
    }

    #[TestDox('bootstrapPath возвращает basePath + bootstrap/')]
    public function testBootstrapPath(): void
    {
        self::assertSame(
            $this->fileSystem->basePath . 'bootstrap' . \DIRECTORY_SEPARATOR,
            $this->fileSystem->bootstrapPath,
        );
    }

    #[TestDox('cachePath возвращает varPath + cache/')]
    public function testCachePath(): void
    {
        self::assertSame(
            $this->fileSystem->varPath . 'cache' . \DIRECTORY_SEPARATOR,
            $this->fileSystem->cachePath,
        );
    }

    #[TestDox('logPath возвращает varPath + log/')]
    public function testLogPath(): void
    {
        self::assertSame(
            $this->fileSystem->varPath . 'log' . \DIRECTORY_SEPARATOR,
            $this->fileSystem->logPath,
        );
    }

    #[TestDox('publicPath возвращает basePath + public/')]
    public function testPublicPath(): void
    {
        self::assertSame(
            $this->fileSystem->basePath . 'public' . \DIRECTORY_SEPARATOR,
            $this->fileSystem->publicPath,
        );
    }

    #[TestDox('normalizeDir добавляет basePath к относительному пути и гарантирует завершающий разделитель')]
    public function testNormalizeDirRelative(): void
    {
        self::assertSame(
            $this->fileSystem->basePath . 'sub' . \DIRECTORY_SEPARATOR,
            $this->fileSystem->normalizeDir('sub'),
        );
    }

    #[TestDox('normalizeDir не меняет абсолютный путь, но добавляет завершающий разделитель')]
    public function testNormalizeDirAbsolute(): void
    {
        $abs = $this->fileSystem->basePath . 'sub';
        self::assertSame($abs . \DIRECTORY_SEPARATOR, $this->fileSystem->normalizeDir($abs));
    }

    #[TestDox('normalizeDir удаляет лишние завершающие разделители')]
    public function testNormalizeDirRemovesTrailingSeparators(): void
    {
        self::assertSame(
            $this->fileSystem->basePath . 'sub' . \DIRECTORY_SEPARATOR,
            $this->fileSystem->normalizeDir('sub///'),
        );
    }

    #[TestDox('normalizeFile добавляет basePath к относительному пути')]
    public function testNormalizeFileRelative(): void
    {
        self::assertSame(
            $this->fileSystem->basePath . 'file.txt',
            $this->fileSystem->normalizeFile('file.txt'),
        );
    }

    #[TestDox('normalizeFile возвращает абсолютный путь без изменений')]
    public function testNormalizeFileAbsolute(): void
    {
        $abs = $this->fileSystem->basePath . 'file.txt';
        self::assertSame($abs, $this->fileSystem->normalizeFile($abs));
    }

    #[TestDox('isAbsolute возвращает true для Unix-абсолютного пути')]
    public function testIsAbsoluteUnix(): void
    {
        self::assertTrue($this->fileSystem->isAbsolute('/var/www'));
    }

    #[TestDox('isAbsolute возвращает false для относительного пути')]
    public function testIsAbsoluteRelative(): void
    {
        self::assertFalse($this->fileSystem->isAbsolute('var/www'));
        self::assertFalse($this->fileSystem->isAbsolute('./var'));
        self::assertFalse($this->fileSystem->isAbsolute('../var'));
    }

    #[TestDox('atBase присоединяет путь к basePath')]
    public function testAtBase(): void
    {
        self::assertSame(
            $this->fileSystem->basePath . 'config' . \DIRECTORY_SEPARATOR . 'app.php',
            $this->fileSystem->atBase('config/app.php'),
        );
    }

    #[TestDox('atCache присоединяет путь к cachePath')]
    public function testAtCache(): void
    {
        self::assertSame(
            $this->fileSystem->cachePath . 'templates' . \DIRECTORY_SEPARATOR . 'index.php',
            $this->fileSystem->atCache('templates/index.php'),
        );
    }

    #[TestDox('atBootstrap присоединяет путь к bootstrapPath')]
    public function testAtBootstrap(): void
    {
        self::assertSame(
            $this->fileSystem->bootstrapPath . 'kernel.php',
            $this->fileSystem->atBootstrap('kernel.php'),
        );
    }

    #[TestDox('atLog присоединяет путь к logPath')]
    public function testAtLog(): void
    {
        self::assertSame($this->fileSystem->logPath . 'app.log', $this->fileSystem->atLog('app.log'));
    }

    #[TestDox('atVar присоединяет путь к varPath')]
    public function testAtVar(): void
    {
        self::assertSame(
            $this->fileSystem->varPath . 'sessions' . \DIRECTORY_SEPARATOR . 'data',
            $this->fileSystem->atVar('sessions/data'),
        );
    }

    #[TestDox('at присоединяет путь к произвольной нормализованной директории')]
    public function testAt(): void
    {
        $result = $this->fileSystem->at('custom_dir', 'file.txt');
        self::assertSame(
            $this->fileSystem->basePath . 'custom_dir' . \DIRECTORY_SEPARATOR . 'file.txt',
            $result,
        );
    }

    #[TestDox('Метод at бросает исключение для пути вне базы')]
    public function testAtExceptionOnOutside(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "../../file.txt" is outside of the "custom_dir".');
        $this->fileSystem->at('custom_dir', '../../file.txt');
    }

    #[TestDox('Методы at* не допускают абсолютного пути')]
    public function testAtAbsolutePathNotAllowed(): void
    {
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Absolute paths are not allowed in this context.');
        $this->fileSystem->at('custom_dir', '/file.txt');
    }

    #[TestDox('Методы at* не допускают абсолютного пути')]
    #[DataProvider('provideAtFamilyAbsolutePathNotAllowedCases')]
    public function testAtFamilyAbsolutePathNotAllowed(string $method): void
    {
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Absolute paths are not allowed in this context.');
        $this->fileSystem->{$method}('/file.txt');
    }

    public static function provideAtFamilyAbsolutePathNotAllowedCases(): iterable
    {
        yield ['atBase'];
        yield ['atCache'];
        yield ['atBootstrap'];
        yield ['atLog'];
        yield ['atVar'];
    }

    #[TestDox('ensureDirectory создаёт директорию внутри basePath')]
    public function testEnsureDirectoryCreates(): void
    {
        $dir = $this->fileSystem->basePath . 'new_dir';
        $this->fileSystem->ensureDirectory($dir);
        self::assertDirectoryExists($dir);
    }

    #[TestDox('ensureDirectory создаёт вложенные директории рекурсивно')]
    public function testEnsureDirectoryCreatesNested(): void
    {
        $dir = $this->fileSystem->basePath . 'a' . \DIRECTORY_SEPARATOR . 'b' . \DIRECTORY_SEPARATOR . 'c';
        $this->fileSystem->ensureDirectory($dir);
        self::assertDirectoryExists($dir);
    }

    #[TestDox('ensureDirectory не выбрасывает исключение для уже существующей директории')]
    public function testEnsureDirectoryExisting(): void
    {
        $dir = $this->fileSystem->basePath . 'existing';
        mkdir($dir, 0o775, true);
        $this->fileSystem->ensureDirectory($dir);
        self::assertDirectoryExists($dir);
    }

    #[TestDox('ensureDirectory выбрасывает исключение для пути вне basePath')]
    public function testEnsureDirectoryOutsideBasePath(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "/tmp/outside" is outside of the base path.');
        $this->fileSystem->ensureDirectory('/tmp/outside');
    }

    #[TestDox('ensureDirectory выбрасывает при ошибке создания директории')]
    #[RunInSeparateProcess]
    public function testEnsureDirectoryMakeDirException(): void
    {
        $dir = $this->fileSystem->basePath . 'new_needle_dir';
        $isDir = self::getFunctionMock('Vasoft\Joke\Support', 'is_dir');
        $isDir->expects(self::exactly(2))->willReturn(false);
        $mkDir = self::getFunctionMock('Vasoft\Joke\Support', 'mkdir');
        $mkDir->expects(self::exactly(1))->willReturn(false);
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs("Unable to create directory: '{$dir}'.");
        $this->fileSystem->ensureDirectory($dir);
    }

    #[TestDox('validatePath пропускает путь внутри basePath')]
    public function testValidatePathInsideBasePath(): void
    {
        $path = $this->fileSystem->basePath . 'file.txt';
        $this->fileSystem->validatePath($path);
        self::assertTrue(true); // no exception
    }

    #[TestDox('validatePath выбрасывает исключение для пути вне basePath')]
    public function testValidatePathOutsideBasePath(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "/etc/passwd" is outside of the base path.');
        $this->fileSystem->validatePath('/etc/passwd');
    }

    #[TestDox('validatePath выбрасывает исключение для path traversal через ..')]
    public function testValidatePathTraversal(): void
    {
        self::expectException(OutsideFileException::class);
        $path = $this->basePath . '/../../etc/passwd';
        self::expectExceptionMessageIs('Resolved "../../etc/passwd" is outside of the base path.');
        $this->fileSystem->validatePath($path);
    }

    #[TestDox('validatePath выбрасывает исключение для symlink, указывающего наружу')]
    public function testValidatePathSymlinkOutside(): void
    {
        $atBase = '/fs_outside_' . bin2hex(random_bytes(8));
        $outsideDir = sys_get_temp_dir() . $atBase;
        mkdir($outsideDir, 0o775, true);
        $outsideFile = $outsideDir . '/secret.txt';
        touch($outsideFile);

        $linkPath = $this->fileSystem->basePath . 'evil_link';
        symlink($outsideFile, $linkPath);

        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "evil_link" is outside of the base path.');
        $this->fileSystem->validatePath($linkPath);

        unlink($linkPath);
        unlink($outsideFile);
        rmdir($outsideDir);
    }

    #[TestDox('writeFile записывает данные в файл и возвращает количество байт')]
    public function testWriteFile(): void
    {
        $file = $this->fileSystem->basePath . 'test.txt';
        $written = $this->fileSystem->writeFile($file, 'hello world');
        self::assertSame(11, $written);
        self::assertFileExists($file);
        self::assertStringEqualsFile($file, 'hello world');
    }

    #[TestDox('writeFile выбрасывает исключение при записи вне basePath')]
    public function testWriteFileOutsideBasePath(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "/tmp/unauthorized.txt" is outside of the base path.');
        $this->fileSystem->writeFile('/tmp/unauthorized.txt', 'data');
    }

    #[TestDox('readFile читает данные из файла')]
    public function testReadFile(): void
    {
        $file = $this->fileSystem->basePath . 'test.txt';
        file_put_contents($file, 'hello world');
        self::assertSame('hello world', $this->fileSystem->readFile($file));
    }

    #[TestDox('readFile выбрасывает исключение для несуществующего файла')]
    #[RunInSeparateProcess]
    public function testReadFileNonExistent(): void
    {
        $fileGetContents = self::getFunctionMock('Vasoft\Joke\Support', 'file_get_contents');
        $fileGetContents->expects(self::exactly(1))->willReturn(false);
        self::expectException(FileSystemException::class);
        $path = $this->fileSystem->basePath . 'nonexistent.txt';
        self::expectExceptionMessageIs("Failed to read file: \"{$path}\".");
        $this->fileSystem->readFile($path);
    }

    #[TestDox('readFile выбрасывает исключение при чтении вне basePath')]
    public function testReadFileOutsideBasePath(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "/etc/passwd" is outside of the base path.');
        $this->fileSystem->readFile('/etc/passwd');
    }

    #[TestDox('includeFile подключает существующий PHP-файл')]
    public function testIncludeFile(): void
    {
        $file = $this->fileSystem->basePath . 'config1.php';
        file_put_contents($file, '<?php echo "hi1";');
        ob_start();
        $this->fileSystem->includeFile($file);
        $content = ob_get_clean();
        self::assertSame('hi1', $content);
    }

    #[TestDox('includeFile не выбрасывает исключение для несуществующего файла')]
    public function testIncludeFileNonExistent(): void
    {
        $file = $this->fileSystem->basePath . 'nonexistent.php';
        $this->fileSystem->includeFile($file);
        self::assertTrue(true); // no exception
    }

    #[TestDox('requireFile подключает существующий файл и не выбрасывает исключение')]
    public function testRequireFileExisting(): void
    {
        $file = $this->fileSystem->basePath . 'config2.php';
        file_put_contents($file, '<?php echo "hi2";');
        ob_start();
        $this->fileSystem->requireFile($file);
        $content = ob_get_clean();
        self::assertSame('hi2', $content);
    }

    #[TestDox('requireFile выбрасывает исключение для несуществующего файла')]
    public function testRequireFileNonExistent(): void
    {
        self::expectException(FileSystemException::class);
        $filename = $this->fileSystem->basePath . 'nonexistent.php';
        self::expectExceptionMessageIs("Unable to include file: '{$filename}'.");
        $this->fileSystem->requireFile($filename);
    }

    #[TestDox('requireFile использует переданное сообщение об ошибке')]
    public function testRequireFileCustomMessage(): void
    {
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Custom error message');
        $this->fileSystem->requireFile(
            $this->fileSystem->basePath . 'nonexistent.php',
            errorMessage: 'Custom error message',
        );
    }

    #[TestDox('requireFileOnce подключает один раз и передает переменные')]
    public function testRequireFileOnce(): void
    {
        $vars = ['a' => date('His')];
        $file = $this->fileSystem->basePath . 'require-once-' . uniqid() . '.php';
        file_put_contents($file, '<?php echo $a;');
        ob_start();
        $this->fileSystem->requireFileOnce($file, $vars);
        $content = ob_get_clean();
        self::assertSame($vars['a'], $content);

        ob_start();
        $this->fileSystem->requireFileOnce($file, ['a' => 'changed']);
        $content = ob_get_clean();
        self::assertSame('', $content);
    }

    #[TestDox('includeFileOnce подключает один раз и передает переменные')]
    public function testIncludeFileOnce(): void
    {
        $vars = ['a' => date('His')];
        $file = $this->fileSystem->basePath . 'include-once-' . uniqid() . '.php';
        file_put_contents($file, '<?php echo $a;');
        ob_start();
        $this->fileSystem->includeFileOnce($file, $vars);
        $content = ob_get_clean();
        self::assertSame($vars['a'], $content);

        ob_start();
        $this->fileSystem->includeFileOnce($file, ['a' => 'changed']);
        $content = ob_get_clean();
        self::assertSame('', $content);
    }

    #[TestDox('requireFile передает переменные')]
    public function testRequireFileVars(): void
    {
        $vars = ['a' => date('His')];
        $file = $this->fileSystem->basePath . 'require-once-' . uniqid() . '.php';
        file_put_contents($file, '<?php echo $a;');
        ob_start();
        $this->fileSystem->requireFile($file, $vars);
        $content = ob_get_clean();
        self::assertSame($vars['a'], $content);
    }

    #[TestDox('includeFile передает переменные')]
    public function testIncludeFileVars(): void
    {
        $vars = ['a' => date('His')];
        $file = $this->fileSystem->basePath . 'include-once-' . uniqid() . '.php';
        file_put_contents($file, '<?php echo $a;');
        ob_start();
        $this->fileSystem->includeFile($file, $vars);
        $content = ob_get_clean();
        self::assertSame($vars['a'], $content);
    }

    #[TestDox('validatePath корректно обрабатывает path traversal с множественными ..')]
    public function testCleanPathMultipleDotDot(): void
    {
        $deepDir = $this->fileSystem->basePath . 'a' . \DIRECTORY_SEPARATOR . 'b' . \DIRECTORY_SEPARATOR . 'c';
        mkdir($deepDir, 0o775, true);

        // a/b/c/../../.. Должен нормализоваться в basePath
        $path = $deepDir . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . '..';
        $this->fileSystem->validatePath($path);
        self::assertTrue(true); // no exception
    }

    #[TestDox('validatePath корректно обрабатывает путь состоящий только из точек')]
    public function testCleanPathDotDotOnly(): void
    {
        $this->fileSystem->validatePath('./');
        self::assertTrue(true); // no exception
    }

    #[TestDox('validatePath пустой путь считает допустимым')]
    public function testCleanPathCanNotBeEmpty(): void
    {
        $this->fileSystem->validatePath('');
        self::assertTrue(true); // no exception
    }

    #[TestDox('validatePath не даёт выйти за basePath через .. от вложенной директории')]
    public function testCleanPathDotDotEscape(): void
    {
        $deepDir = $this->fileSystem->basePath . 'sub';
        mkdir($deepDir, 0o775, true);
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "sub/../.." is outside of the base path.');
        $this->fileSystem->validatePath($deepDir . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . '..');
    }

    #[TestDox('normalizeDir  пустой путь считает допустимым')]
    public function testNormalizeDirEmpty(): void
    {
        self::assertSame($this->fileSystem->basePath, $this->fileSystem->normalizeDir(''));
        self::assertTrue(true); // no exception
    }

    #[TestDox('normalizeFile с пустой строкой бросает исключение')]
    public function testNormalizeFileEmpty(): void
    {
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Path cannot be empty.');

        self::assertSame($this->fileSystem->basePath, $this->fileSystem->normalizeFile(''));
    }

    #[TestDox('atBase с пустой строкой возвращает basePath')]
    public function testAtBaseEmpty(): void
    {
        self::assertSame(rtrim($this->fileSystem->basePath, \DIRECTORY_SEPARATOR), $this->fileSystem->atBase(''));
    }

    #[TestDox('writeFile с флагом FILE_APPEND дописывает в существующий файл')]
    public function testWriteFileAppend(): void
    {
        $file = $this->fileSystem->basePath . 'append.txt';
        $this->fileSystem->writeFile($file, 'first ');
        $this->fileSystem->writeFile($file, 'second', FILE_APPEND);
        self::assertStringEqualsFile($file, 'first second');
    }

    #[TestDox('writeFileSafe атомарно записывает данные в новый файл')]
    public function testWriteFileSafeCreatesFile(): void
    {
        $file = $this->fileSystem->basePath . 'safe.txt';
        $written = $this->fileSystem->writeFileSafe($file, 'hello world');
        self::assertSame(11, $written);
        self::assertFileExists($file);
        self::assertStringEqualsFile($file, 'hello world');
    }

    #[TestDox('writeFileSafe не поддерживает FILE_APPEND')]
    public function testWriteFileSafeNotSupportAppend(): void
    {
        $file = $this->fileSystem->basePath . 'safe.txt';
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('FILE_APPEND flag is not supported by writeFileSafe().');
        $this->fileSystem->writeFileSafe($file, 'hello world', FILE_APPEND);
    }

    #[TestDox('writeFileSafe атомарно перезаписывает существующий файл')]
    public function testWriteFileSafeOverwritesExisting(): void
    {
        $file = $this->fileSystem->basePath . 'safe.txt';
        file_put_contents($file, 'old content');
        $this->fileSystem->writeFileSafe($file, 'new content');
        self::assertStringEqualsFile($file, 'new content');
    }

    #[TestDox('writeFile выбрасывает исключение при ошибке записи')]
    #[RunInSeparateProcess]
    public function testWriteFileDoesNotCorruptOnFailure(): void
    {
        $filePutContents = self::getFunctionMock('Vasoft\Joke\Support', 'file_put_contents');
        $filePutContents->expects(self::exactly(1))->willReturn(false);
        $file = $this->fileSystem->basePath . 'some-file.txt';
        file_put_contents($file, 'original');

        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs("Failed to write file: '{$file}/'.");
        $this->fileSystem->writeFile($file . '/', 'data');
    }

    #[TestDox('writeFileSafe не повреждает существующий файл при ошибке rename')]
    #[RunInSeparateProcess]
    public function testWriteFileSafeDoesNotCorruptOnFailure(): void
    {
        $filePutContents = self::getFunctionMock('Vasoft\Joke\Support', 'file_put_contents');
        $filePutContents->expects(self::exactly(1))->willReturn(false);

        $file = $this->fileSystem->basePath . 'needle_wrong_safe.txt';
        file_put_contents($file, 'original');

        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs("Failed to write file: '{$file}'.");
        $this->fileSystem->writeFileSafe($file, 'data');

        self::assertStringEqualsFile($file, 'original');
    }

    #[TestDox('writeFileSafe выбрасывает исключение при записи вне basePath')]
    public function testWriteFileSafeOutsideBasePath(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "/tmp/unauthorized.txt" is outside of the base path.');
        $this->fileSystem->writeFileSafe('/tmp/unauthorized.txt', 'data');
    }

    #[TestDox('writeFileSafe записывает бинарные данные')]
    public function testWriteFileSafeBinaryData(): void
    {
        $file = $this->fileSystem->basePath . 'binary.bin';
        $binary = "\x00\x01\x02\xFF\xFE";
        $written = $this->fileSystem->writeFileSafe($file, $binary);
        self::assertSame(5, $written);
        self::assertStringEqualsFile($file, $binary);
    }

    #[TestDox('writeFileSafe записывает пустую строку')]
    public function testWriteFileSafeEmptyString(): void
    {
        $file = $this->fileSystem->basePath . 'empty.txt';
        $written = $this->fileSystem->writeFileSafe($file, '');
        self::assertSame(0, $written);
        self::assertFileExists($file);
        self::assertStringEqualsFile($file, '');
    }

    #[TestDox('readFile с offset и length читает часть файла')]
    public function testReadFilePartial(): void
    {
        $file = $this->fileSystem->basePath . 'partial.txt';
        file_put_contents($file, 'hello world');
        self::assertSame('world', $this->fileSystem->readFile($file, null, 6, 5));
    }

    #[TestDox('writeFileAppend добавляет данные в новый файл')]
    public function testWriteFileAppendCreatesFile(): void
    {
        $file = $this->fileSystem->basePath . 'append_new.txt';
        $written = $this->fileSystem->writeFileAppendSafe($file, 'hello');
        self::assertSame(5, $written);
        self::assertFileExists($file);
        self::assertStringEqualsFile($file, 'hello');
    }

    #[TestDox('writeFileAppend добавляет данные в существующий файл')]
    public function testWriteFileAppendToExisting(): void
    {
        $file = $this->fileSystem->basePath . 'append_existing.txt';
        file_put_contents($file, 'hello ');
        $written = $this->fileSystem->writeFileAppendSafe($file, 'world');
        self::assertSame(5, $written);
        self::assertStringEqualsFile($file, 'hello world');
    }

    #[TestDox('writeFileAppend добавляет данные несколько раз подряд')]
    public function testWriteFileAppendMultipleTimes(): void
    {
        $file = $this->fileSystem->basePath . 'append_multi.txt';
        $this->fileSystem->writeFileAppendSafe($file, 'first');
        $this->fileSystem->writeFileAppendSafe($file, ' ');
        $this->fileSystem->writeFileAppendSafe($file, 'second');
        $this->fileSystem->writeFileAppendSafe($file, ' ');
        $this->fileSystem->writeFileAppendSafe($file, 'third');
        self::assertStringEqualsFile($file, 'first second third');
    }

    #[TestDox('writeFileAppend выбрасывает исключение при добавлении вне basePath')]
    public function testWriteFileAppendOutsideBasePath(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "/tmp/unauthorized.txt" is outside of the base path.');
        $this->fileSystem->writeFileAppendSafe('/tmp/unauthorized.txt', 'data');
    }

    #[TestDox('writeFileAppend выбрасывает исключение при ошибке открытия файла')]
    #[RunInSeparateProcess]
    public function testWriteFileAppendOpenFailed(): void
    {
        $fopen = self::getFunctionMock('Vasoft\Joke\Support', 'fopen');
        $fopen->expects(self::exactly(1))->willReturn(false);
        $file = $this->fileSystem->basePath . 'open_fail.txt';
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs("Failed to open file for appending: '{$file}'.");
        $this->fileSystem->writeFileAppendSafe($file, 'data');
    }

    #[TestDox('writeFileAppend выбрасывает исключение при ошибке блокировки файла')]
    #[RunInSeparateProcess]
    public function testWriteFileAppendLockFailed(): void
    {
        $flock = self::getFunctionMock('Vasoft\Joke\Support', 'flock');
        $flock->expects(self::exactly(1))->willReturn(false);
        $file = $this->fileSystem->basePath . 'lock_fail.txt';
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs("Failed to acquire lock on file: '{$file}'.");
        $this->fileSystem->writeFileAppendSafe($file, 'data');
    }

    #[TestDox('writeFileAppend выбрасывает исключение при ошибке записи')]
    #[RunInSeparateProcess]
    public function testWriteFileAppendWriteFailed(): void
    {
        $fwrite = self::getFunctionMock('Vasoft\Joke\Support', 'fwrite');
        $fwrite->expects(self::exactly(1))->willReturn(false);
        $file = $this->fileSystem->basePath . 'write_fail.txt';
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs("Failed to write file: '{$file}'.");
        $this->fileSystem->writeFileAppendSafe($file, 'data');
    }

    #[TestDox('writeFileAppend записывает пустую строку')]
    public function testWriteFileAppendEmptyString(): void
    {
        $file = $this->fileSystem->basePath . 'append_empty.txt';
        file_put_contents($file, 'existing');
        $written = $this->fileSystem->writeFileAppendSafe($file, '');
        self::assertSame(0, $written);
        self::assertStringEqualsFile($file, 'existing');
    }

    #[TestDox('writeFileAppend записывает бинарные данные')]
    public function testWriteFileAppendBinaryData(): void
    {
        $file = $this->fileSystem->basePath . 'append_binary.bin';
        $binary1 = "\x00\x01\x02";
        $binary2 = "\xFF\xFE";
        $this->fileSystem->writeFileAppendSafe($file, $binary1);
        $this->fileSystem->writeFileAppendSafe($file, $binary2);
        self::assertStringEqualsFile($file, "\x00\x01\x02\xFF\xFE");
    }

    #[TestDox('resolveLogicalPath логически нормализует допустимы путь')]
    public function testResolveLogicalPath(): void
    {
        $expected = $this->fileSystem->basePath . 'test';
        self::assertSame($expected, $this->fileSystem->resolveLogicalPath('test', $this->fileSystem->basePath));
        $expected = rtrim($this->fileSystem->basePath, \DIRECTORY_SEPARATOR);
        self::assertSame(
            $expected,
            $this->fileSystem->resolveLogicalPath($this->fileSystem->basePath, $this->fileSystem->basePath),
        );
    }

    #[TestDox('resolveLogicalPath бросает исключение при недопустимом пути')]
    public function testResolveLogicalPathException(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "../../test" is outside of the base path.');
        $this->fileSystem->resolveLogicalPath('../../test', $this->fileSystem->basePath);
    }

    #[TestDox('resolveLogicalBasePath логически нормализует допустимы путь')]
    public function testResolveLogicalBasePath(): void
    {
        self::assertSame(
            $this->fileSystem->basePath,
            $this->fileSystem->resolveLogicalBasePath($this->fileSystem->basePath),
        );
        self::assertSame($this->fileSystem->basePath, $this->fileSystem->resolveLogicalBasePath(''));
    }

    #[TestDox('resolveLogicalPath бросает исключение при недопустимом пути')]
    public function testResolveLogicalBasePathException(): void
    {
        self::expectException(OutsideFileException::class);
        self::expectExceptionMessageIs('Resolved "../../test" is outside of the base path.');
        $this->fileSystem->resolveLogicalBasePath('../../test');
    }

    #[TestDox('clearDir() рекурсивно удаляет каталог')]
    public function testClearDirRemovesDirectoryTree(): void
    {
        $path = $this->fileSystem->atCache('test1/1_1');
        $cleanPath = $this->fileSystem->atCache('test1');
        $this->fileSystem->ensureDirectory($path);
        $this->fileSystem->writeFile($path . 'example.log', '1');
        $this->fileSystem->clearDir($cleanPath);
        self::assertDirectoryDoesNotExist($path);
        self::assertDirectoryExists($cleanPath);
    }

    #[TestDox('clearDir() рекурсивно удаляет каталог с основным')]
    public function testClearDirRemovesDirectoryTreeWithRoot(): void
    {
        $path = $this->fileSystem->atCache('test2/1_1');
        $cleanPath = $this->fileSystem->atCache('test2');
        $this->fileSystem->ensureDirectory($path);
        $this->fileSystem->writeFile($path . 'example.log', '1');
        $this->fileSystem->clearDir($cleanPath, true);
        self::assertDirectoryDoesNotExist($cleanPath);
    }

    #[TestDox('clearDir() исключение при попытке удалить базовый')]
    public function testClearExceptionOnBase(): void
    {
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Removing the base path root is not allowed via clearDir().');

        $this->fileSystem->clearDir($this->fileSystem->basePath, true);
    }

    #[TestDox('clearDir() исключение при попытке чистить не каталог')]
    public function testClearExceptionOnFile(): void
    {
        $path = $this->fileSystem->atCache('test2/1_1');
        $this->fileSystem->ensureDirectory($path);
        $this->fileSystem->writeFile($path . 'example.log', '1');


        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Cannot clear non-existent directory: "var/cache/test2/1_1example.log".');

        $this->fileSystem->clearDir($path . 'example.log');
    }

    #[TestDox('clearDir() останавливает выполнение на первой ошибке')]
    #[RunInSeparateProcess]
    public function testClearBreakOnFail(): void
    {
        $rmDir = self::getFunctionMock('Vasoft\Joke\Support', 'rmdir');
        $rmDir->expects(self::exactly(1))->willReturn(false);

        $cleanPath = $this->fileSystem->atCache('test1_');
        $path = $this->fileSystem->atCache('test1_/1_0');
        $this->fileSystem->ensureDirectory($path);
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Failed to delete item: "var/cache/test1_/1_0".');
        $this->fileSystem->clearDir($cleanPath);
    }

    #[TestDox('clearDir() выбрасывает исключение при ошибки удаления корня')]
    #[RunInSeparateProcess]
    public function testClearExceptionFail(): void
    {
        $rmDir = self::getFunctionMock('Vasoft\Joke\Support', 'rmdir');
        $rmDir->expects(self::exactly(1))->willReturn(false);

        $cleanPath = $this->fileSystem->atCache('test3_');
        $this->fileSystem->ensureDirectory($cleanPath);
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Failed to remove directory: "var/cache/test3_".');
        $this->fileSystem->clearDir($cleanPath, true);
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
