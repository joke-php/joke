<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Config;

use phpmock\phpunit\MockObjectProxy;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Config\EnvironmentLoader;
use Vasoft\Joke\Config\Exceptions\ConfigException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Config\Environment
 */
final class EnvironmentTest extends TestCase
{
    use PHPMock;

    public const string ENV_VAR_NAME = 'JK_ENV';
    private array $originalEnv;
    private array $originalServer;
    private MockObject|MockObjectProxy $mockGetEnv;
    private EnvironmentLoader|MockObject $mockLoader;

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $this->originalServer = $_SERVER;
        unset($_SERVER[self::ENV_VAR_NAME], $_ENV[self::ENV_VAR_NAME]);


        $this->mockLoader = self::getMockBuilder(EnvironmentLoader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockGetEnv = self::getFunctionMock('Vasoft\Joke\Config', 'getenv');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_ENV = $this->originalEnv;
    }

    public function testEnvDefault(): void
    {
        unset($_ENV[self::ENV_VAR_NAME], $_SERVER[self::ENV_VAR_NAME]);

        $this->mockGetEnv->expects(self::once())->willReturn(false);

        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::assertTrue($env->is('local'));
    }

    public function testEnvPriorityFromEnv(): void
    {
        $_ENV[self::ENV_VAR_NAME] = 'from_env';
        $_SERVER[self::ENV_VAR_NAME] = 'from_server';
        $this->mockGetEnv->expects(self::never());

        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::assertTrue($env->is('from_env'));
    }

    public function testEnvPriorityFromServer(): void
    {
        unset($_ENV[self::ENV_VAR_NAME]);
        $_SERVER[self::ENV_VAR_NAME] = 'from_server';
        $this->mockGetEnv->expects(self::never());

        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::assertTrue($env->is('from_server'));
    }

    public function testEnvPriorityFromGetEnv(): void
    {
        unset($_ENV[self::ENV_VAR_NAME], $_SERVER[self::ENV_VAR_NAME]);

        $this->mockGetEnv->expects(self::once())->willReturn('from_getenv');

        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::assertTrue($env->is('from_getenv'));
    }

    public function testDevelopment(): void
    {
        $propValue = '';
        $this->mockGetEnv->expects(self::once())
            ->willReturnCallback(static function (string $name) use (&$propValue) {
                $propValue = $name;

                return 'development';
            });

        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::assertSame(self::ENV_VAR_NAME, $propValue);
        self::assertTrue($env->is('development'));
        self::assertTrue($env->isDevelopment());
        self::assertFalse($env->isProduction());
        self::assertFalse($env->isTesting());
    }

    public function testProduction(): void
    {
        $this->mockGetEnv->expects(self::once())
            ->willReturn('production');

        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::assertTrue($env->is('production'));
        self::assertFalse($env->isDevelopment());
        self::assertTrue($env->isProduction());
        self::assertFalse($env->isTesting());
    }

    public function testTesting(): void
    {
        $this->mockGetEnv->expects(self::once())
            ->willReturn('testing');

        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::assertTrue($env->is('testing'));
        self::assertFalse($env->isDevelopment());
        self::assertFalse($env->isProduction());
        self::assertTrue($env->isTesting());
    }

    public function testCustom(): void
    {
        $this->mockGetEnv->expects(self::once())
            ->willReturn('custom');

        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::assertTrue($env->is('custom'));
        self::assertFalse($env->isDevelopment());
        self::assertFalse($env->isProduction());
        self::assertFalse($env->isTesting());
    }

    public function testHas(): void
    {
        $this->mockGetEnv->expects(self::once())->willReturn('custom');
        $this->mockLoader->expects(self::once())->method('load')
            ->willReturn(['PROPS' => true]);

        $env = new Environment($this->mockLoader);
        self::assertTrue($env->has('PROPS'));
        self::assertTrue($env->has('props'));
        self::assertFalse($env->has('other'));
    }

    public function testGet(): void
    {
        $this->mockGetEnv->expects(self::once())->willReturn('custom');
        $value = random_int(0, 10000);
        $this->mockLoader->expects(self::once())->method('load')
            ->willReturn(['PROP' => $value]);

        $env = new Environment($this->mockLoader);
        self::assertSame($value, $env->get('PROP'));
        self::assertSame($value, $env->get('prop'));
        self::assertSame($value, $env->getOrFail('prop'));
        self::assertSame('default', $env->get('other', 'default'));
        self::assertNull($env->get('other'));
    }

    public function testGetOrFail(): void
    {
        $this->mockGetEnv->expects(self::once())->willReturn('custom');
        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('The environment "PROPS" does not exist.');
        $value = $env->getOrFail('props');
    }

    public function testGetOrFailCustomMessage(): void
    {
        $this->mockGetEnv->expects(self::once())->willReturn('custom');
        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);

        $env = new Environment($this->mockLoader);
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('Not exists');
        $env->getOrFail('props', 'Not exists');
    }

    public function testBasePath(): void
    {
        $this->mockLoader->expects(self::once())->method('load')->willReturn([]);
        $this->mockLoader->expects(self::once())->method('getBasePath')->willReturn('/var/www/');

        $env = new Environment($this->mockLoader);
        self::assertSame('/var/www/', $env->getBasePath());
    }
}
