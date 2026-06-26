<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Config\Environment;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\EnvironmentLoader;
use Vasoft\Joke\Config\Exceptions\ConfigException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Config\EnvironmentLoader
 */
final class EnvironmentLoaderTest extends TestCase
{
    use PHPMock;

    private string $basePath = '';
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR;
    }

    protected function writeEnvFile(string $name, string $content): void
    {
        $fileName = $this->basePath . '.env';
        if ('' !== $name) {
            $fileName .= '.' . $name;
        }
        $this->createdFiles[] = $fileName;
        file_put_contents($fileName, $content);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            unlink($file);
        }
    }

    public function testNoFiles(): void
    {
        $loader = new EnvironmentLoader($this->basePath);
        $vars = $loader->load('dev', 'local', 'testing');
        self::assertCount(0, $vars);
    }

    public function testAllFiles(): void
    {
        $this->writeEnvFile('', 'FROM_GLOBAL=1');
        $this->writeEnvFile('dev', 'FROM_DEV=1');
        $this->writeEnvFile('local', 'FROM_LOCAL=1');
        $loader = new EnvironmentLoader($this->basePath);
        $vars = $loader->load('dev', 'local', 'testing');
        self::assertCount(3, $vars);
        self::assertArrayHasKey('FROM_GLOBAL', $vars);
        self::assertArrayHasKey('FROM_DEV', $vars);
        self::assertArrayHasKey('FROM_LOCAL', $vars);
    }

    public function testOverrideGlobalEnv(): void
    {
        $this->writeEnvFile('', 'OVERRIDE=1');
        $this->writeEnvFile('custom', "OVERRIDE=2\n");
        $loader = new EnvironmentLoader($this->basePath);
        $vars = $loader->load('custom', 'local', 'testing');
        self::assertCount(1, $vars);
        self::assertSame(2, $vars['OVERRIDE']);
    }

    public function testOverrideGlobalEnvLocal(): void
    {
        $this->writeEnvFile('', 'OVERRIDE=1');
        $this->writeEnvFile('custom', "OVERRIDE=2\n");
        $this->writeEnvFile('local', "OVERRIDE=3\n");
        $loader = new EnvironmentLoader($this->basePath);
        $vars = $loader->load('custom', 'local', 'testing');
        self::assertCount(1, $vars);
        self::assertSame(3, $vars['OVERRIDE']);
    }

    public function testOtherEnvNotRead(): void
    {
        $this->writeEnvFile('', 'OVERRIDE=1');
        $this->writeEnvFile('custom', "OVERRIDE=2\n");
        $loader = new EnvironmentLoader($this->basePath);
        $vars = $loader->load('dev', 'local', 'testing');
        self::assertCount(1, $vars);
        self::assertSame(1, $vars['OVERRIDE']);
    }

    public function testLocalNotReadInTesting(): void
    {
        $this->writeEnvFile('', 'OVERRIDE=1');
        $this->writeEnvFile('local', "OVERRIDE=2\n");
        $loader = new EnvironmentLoader($this->basePath);
        $vars = $loader->load('testing', 'local', 'testing');
        self::assertCount(1, $vars);
        self::assertSame(1, $vars['OVERRIDE']);
    }

    public function testCustomLocal(): void
    {
        $this->writeEnvFile('', 'OVERRIDE=1');
        $this->writeEnvFile('custom', "OVERRIDE=2\n");
        $loader = new EnvironmentLoader($this->basePath);
        $vars = $loader->load('dev', 'custom', 'testing');
        self::assertCount(1, $vars);
        self::assertSame(2, $vars['OVERRIDE']);
    }

    public function testTypes(): void
    {
        $this->writeEnvFile(
            '',
            <<<'TEXT'
                #COMMENTED=1
                INTEGER=1
                FLOAT_1=1.2
                FLOAT_2=1e2
                FLOAT_3=1E3
                STRING=1.1.1.1
                STRING_1="2"
                STRING_2='1'
                STRING_3=Long string's value
                STRING_4='Long string\'s value'
                STRING_5="Long \"string's\" value"
                BOOLEAN_TRUE=true
                BOOLEAN_FALSE=false
                NULLABLE_NULL=null
                EMPTY=
                NULLABLE_FLAG
                EMPTY_STRING=''
                lower_case=Example
                TEXT,
        );
        $loader = new EnvironmentLoader($this->basePath);
        $vars = $loader->load('dev', 'custom', 'testing');
        self::assertArrayNotHasKey('COMMENTED', $vars);
        self::assertSame(1, $vars['INTEGER']);
        self::assertSame(1.2, $vars['FLOAT_1']);
        self::assertSame(1e2, $vars['FLOAT_2']);
        self::assertSame(1E3, $vars['FLOAT_3']);
        self::assertSame('1.1.1.1', $vars['STRING']);
        self::assertSame('2', $vars['STRING_1']);
        self::assertSame('1', $vars['STRING_2']);
        self::assertSame("Long string's value", $vars['STRING_3']);
        self::assertSame("Long string's value", $vars['STRING_4']);
        self::assertSame("Long \"string's\" value", $vars['STRING_5']);
        self::assertTrue($vars['BOOLEAN_TRUE']);
        self::assertFalse($vars['BOOLEAN_FALSE']);
        self::assertNull($vars['NULLABLE_NULL']);
        self::assertNull($vars['EMPTY']);
        self::assertNull($vars['NULLABLE_FLAG']);
        self::assertSame('', $vars['EMPTY_STRING']);
        self::assertArrayHasKey('LOWER_CASE', $vars);
    }

    #[RunInSeparateProcess]
    public function testUnableLoad(): void
    {
        $this->writeEnvFile('', 'FROM_GLOBAL=1');
        $loader = new EnvironmentLoader($this->basePath);

        $fileMock = self::getFunctionMock('Vasoft\Joke\Config', 'file');
        $fileMock->expects(self::once())->willReturn(false);
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIsOrContains('Unable to load file: ');

        $loader->load('dev', 'local', 'testing');
    }
}
