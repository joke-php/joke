<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Logging\Handlers;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Logging\Handlers\StreamHandler;
use Vasoft\Joke\Logging\LogLevel;
use Vasoft\Joke\Logging\Exception\LogException;
use Vasoft\Joke\Contract\Logging\MessageFormatterInterface;

/**
 * @internal
 *
 * @coversDefaultClass  \Vasoft\Joke\Logging\Handlers\StreamHandler
 */
final class StreamHandlerTest extends TestCase
{
    use PHPMock;

    private string $tempDir;
    private string $logFile;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/joke_stream_handler_test_' . uniqid();
        mkdir($this->tempDir);
        $this->logFile = $this->tempDir . '/test.log';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testWritesToFile(): void
    {
        $handler = new StreamHandler($this->logFile);
        $handler->write(LogLevel::INFO, 'Test message', []);

        $content = file_get_contents($this->logFile);
        self::assertStringContainsString('[INFO] Test message', $content);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[INFO\] Test message\n$/',
            $content,
        );
    }

    public function testFiltersByMinLevel(): void
    {
        $handler = new StreamHandler($this->logFile, LogLevel::WARNING);
        $handler->write(LogLevel::INFO, 'Info message', []);
        $handler->write(LogLevel::ERROR, 'Error message', []);

        $content = file_get_contents($this->logFile);
        self::assertStringNotContainsString('Info message', $content);
        self::assertStringContainsString('Error message', $content);
    }

    public function testFiltersByMaxLevel(): void
    {
        $handler = new StreamHandler($this->logFile, LogLevel::DEBUG, LogLevel::NOTICE);
        $handler->write(LogLevel::INFO, 'Info message', []);
        $handler->write(LogLevel::ERROR, 'Error message', []);

        $content = file_get_contents($this->logFile);
        self::assertStringContainsString('Info message', $content);
        self::assertStringNotContainsString('Error message', $content);
    }

    public function testHandlesReverseLevelOrder(): void
    {
        $handler = new StreamHandler($this->logFile, LogLevel::ERROR, LogLevel::WARNING);
        $handler->write(LogLevel::CRITICAL, 'Critical', []);
        $handler->write(LogLevel::ERROR, 'Error', []);
        $handler->write(LogLevel::WARNING, 'Warning', []);
        $handler->write(LogLevel::INFO, 'Info', []);

        $content = file_get_contents($this->logFile);
        self::assertStringContainsString('Error', $content);
        self::assertStringContainsString('Warning', $content);
        self::assertStringNotContainsString('Critical', $content);
        self::assertStringNotContainsString('Info', $content);
    }

    public function testCreatesDirectoryIfNotExists(): void
    {
        $nestedLogFile = $this->tempDir . '/subdir/test.log';
        $handler = new StreamHandler($nestedLogFile);
        $handler->write(LogLevel::INFO, 'Message', []);

        self::assertFileExists($nestedLogFile);
        $content = file_get_contents($nestedLogFile);
        self::assertStringContainsString('Message', $content);
    }

    public function testRotatesLogFile(): void
    {
        $maxSize = 50;
        file_put_contents($this->logFile, str_repeat('x', $maxSize + 1));

        $handler = new StreamHandler($this->logFile, maxFileSize: $maxSize);
        $handler->write(LogLevel::INFO, 'New message', []);

        self::assertFileExists($this->logFile . '.old');
        self::assertFileExists($this->logFile);
        self::assertStringContainsString('New message', file_get_contents($this->logFile));
    }

    public function testUsesCustomFormatter(): void
    {
        $formatter = $this->createMock(MessageFormatterInterface::class);
        $formatter->expects(self::once())
            ->method('interpolate')
            ->with('raw template')
            ->willReturn('formatted message');

        $handler = new StreamHandler($this->logFile, formatter: $formatter);
        $handler->write(LogLevel::INFO, 'already formatted', ['rawMessage' => 'raw template']);

        $content = file_get_contents($this->logFile);
        self::assertStringContainsString('formatted message', $content);
    }

    public function testUsesPreformattedMessageWhenNoRawMessage(): void
    {
        $formatter = $this->createMock(MessageFormatterInterface::class);
        $formatter->expects(self::never())->method('interpolate');

        $handler = new StreamHandler($this->logFile, formatter: $formatter);
        $handler->write(LogLevel::INFO, 'preformatted message', []);

        $content = file_get_contents($this->logFile);
        self::assertStringContainsString('preformatted message', $content);
    }

    #[RunInSeparateProcess]
    public function testUnableCreateDirectory(): void
    {
        $isDir = self::getFunctionMock('Vasoft\Joke\Logging\Handlers', 'is_dir');
        $isDir->expects(self::exactly(2))->willReturn(false);
        $mkDir = self::getFunctionMock('Vasoft\Joke\Logging\Handlers', 'mkdir');
        $mkDir->expects(self::exactly(1))->willReturn(false);

        $salt = random_int(1000, 99999);
        $path = "/nonexistent/{$salt}/dir";
        self::expectException(LogException::class);
        self::expectExceptionMessageIs("Unable to create directory '{$path}'.");
        new StreamHandler($path . '/file.log');
    }

    #[RunInSeparateProcess]
    public function testUnableCreateFile(): void
    {
        $salt = random_int(1000, 99999);
        $file = $this->tempDir . '/subdir/' . $salt . '/test.log';

        $fOpen = self::getFunctionMock('Vasoft\Joke\Logging\Handlers', 'fopen');
        $fOpen->expects(self::once())->willReturn(false);

        self::expectException(LogException::class);
        self::expectExceptionMessageIs("Unable to open '{$file}'.");
        new StreamHandler($file);
    }
}
