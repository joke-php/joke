<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Support;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Exceptions\FileSystemException;
use Vasoft\Joke\Exceptions\OutsideFileException;
use Vasoft\Joke\Support\FileSystem;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Support\FileSystem
 */
#[TestDox('FileSystem тестирование безопасной модификации файла')]
#[CoversClass(FileSystem::class)]
final class FileSystemModifySafeTest extends TestCase
{
    use PHPMock;

    private FileSystem $fs;
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/joke_fs_safe_test_' . uniqid();
        mkdir($this->testDir, 0o775, true);
        $this->fs = new FileSystem($this->testDir);
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

    #[TestDox('Базовая модификация: содержимое трансформируется корректно.')]
    public function testModifyReturnsTransformedContent(): void
    {
        $file = $this->fs->atBase('counter.txt');
        file_put_contents($file, '10');

        $result = $this->fs->modifyFileSafe($file, static fn(string $content): string => (string) ((int) $content + 5));

        self::assertSame('15', $result);
        self::assertSame('15', file_get_contents($file));
    }

    #[TestDox('Несуществующий файл создаётся, модификатор получает пустую строку')]
    public function testModifyCreatesFileIfNotExists(): void
    {
        $file = $this->fs->atBase('new_counter.txt');

        $result = $this->fs->modifyFileSafe($file, static fn(string $content): string => 'initialized:' . $content);

        self::assertSame('initialized:', $result);
        self::assertFileExists($file);
        self::assertSame('initialized:', file_get_contents($file));
    }

    #[TestDox('Модификатор возвращает не-string - исключение, файл не повреждён')]
    public function testModifyThrowsOnNonStringReturn(): void
    {
        $file = $this->fs->atBase('safe.txt');
        file_put_contents($file, 'original');

        $this->expectException(FileSystemException::class);
        $this->expectExceptionMessageIs('Modifier must return a string, got: "int".');

        /** @phpstan-ignore-next-line Намеренная передача неверного типа */
        $this->fs->modifyFileSafe($file, static fn(string $c): int => 42);
    }

    #[TestDox('Исключение в модификаторе не повреждает файл')]
    public function testModifyPreservesContentOnModifierException(): void
    {
        $file = $this->fs->atBase('protected.txt');
        file_put_contents($file, 'safe_content');

        try {
            $this->fs->modifyFileSafe($file, static function (string $c): string {
                throw new \RuntimeException('Modifier failed');
            });
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException) {
            // Ожидаемо
        }

        self::assertSame('safe_content', file_get_contents($file));
    }

    #[TestDox('Путь вне basePath - OutsideFileException')]
    public function testModifyRejectsPathOutsideBase(): void
    {
        $this->expectException(OutsideFileException::class);

        $this->fs->modifyFileSafe('/etc/passwd', static fn(string $c): string => $c);
    }

    #[TestDox('modifyFileSafe бросает исключение при невозможности открыть файл')]
    #[RunInSeparateProcess]
    public function testModifyFileSafeOpenException(): void
    {
        $fOpen = self::getFunctionMock('Vasoft\Joke\Support', 'fopen');
        $fOpen->expects(self::once())->willReturn(false);
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Failed to open file: "fail-on-open.txt".');

        $this->fs->modifyFileSafe('fail-on-open.txt', static fn(string $c): string => $c);
    }

    #[TestDox('modifyFileSafe бросает исключение при невозможности блокировки файла')]
    #[RunInSeparateProcess]
    public function testModifyFileSafeLockException(): void
    {
        $fLock = self::getFunctionMock('Vasoft\Joke\Support', 'flock');
        $fLock->expects(self::once())->willReturn(false);
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Failed to acquire lock on file: "fail-on-lock.txt".');

        $this->fs->modifyFileSafe('fail-on-lock.txt', static fn(string $c): string => $c);
    }

    #[TestDox('modifyFileSafe бросает исключение при невозможности прочитать файл')]
    #[RunInSeparateProcess]
    public function testModifyFileSafeReadException(): void
    {
        $streamGetContents = self::getFunctionMock('Vasoft\Joke\Support', 'stream_get_contents');
        $streamGetContents->expects(self::once())->willReturn(false);
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Failed to read file: "fail-on-read.txt".');

        $this->fs->modifyFileSafe('fail-on-read.txt', static fn(string $c): string => $c);
    }

    #[TestDox('modifyFileSafe бросает исключение при невозможности сохранить файл')]
    #[RunInSeparateProcess]
    public function testModifyFileSafeWriteException(): void
    {
        $fWrite = self::getFunctionMock('Vasoft\Joke\Support', 'fwrite');
        $fWrite->expects(self::once())->willReturn(false);
        self::expectException(FileSystemException::class);
        self::expectExceptionMessageIs('Failed to write modified content to file: "fail-on-write.txt".');

        $this->fs->modifyFileSafe('fail-on-write.txt', static fn(string $c): string => $c);
    }

    /**
     * N процессов одновременно инкрементируют счётчик.
     * С flock: результат === N (ни одной потери обновления).
     * Без flock: результат < N (потери из-за race condition).
     */
    #[TestDox('Кокурентный тест')]
    #[RunInSeparateProcess]
    public function testConcurrentModifyDoesNotLoseUpdates(): void
    {
        if (!extension_loaded('pcntl')) {
            self::markTestSkipped('pcntl extension is required for concurrency test.');
        }

        $file = $this->fs->atBase('concurrent_counter.txt');
        file_put_contents($file, '0');

        $processCount = 20;
        $incrementsPerProcess = 50;
        $expectedTotal = $processCount * $incrementsPerProcess;

        $pids = [];

        for ($i = 0; $i < $processCount; ++$i) {
            $pid = pcntl_fork();

            if (-1 === $pid) {
                self::fail('Failed to fork process.');
            }

            if (0 === $pid) {
                // Дочерний процесс: создаёт свой экземпляр FileSystem
                $childFs = new FileSystem($this->testDir);

                for ($j = 0; $j < $incrementsPerProcess; ++$j) {
                    $childFs->modifyFileSafe(
                        $file,
                        static fn(string $content): string => (string) ((int) $content + 1),
                    );
                }

                exit(0);
            }

            $pids[] = $pid;
        }

        // Родитель ждёт завершения всех дочерних процессов
        $failedChildren = 0;
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            if (!pcntl_wifexited($status) || 0 !== pcntl_wexitstatus($status)) {
                ++$failedChildren;
            }
        }

        self::assertSame(0, $failedChildren, 'Some child processes failed.');

        $actualTotal = (int) file_get_contents($file);

        self::assertSame(
            $expectedTotal,
            $actualTotal,
            sprintf(
                'Concurrency data loss detected! Expected %d, got %d. Lost %d updates.',
                $expectedTotal,
                $actualTotal,
                $expectedTotal - $actualTotal,
            ),
        );
    }
}
