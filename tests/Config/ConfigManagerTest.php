<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Config;

use Vasoft\Joke\Tests\Fixtures\Config\ConfigProvider;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\ConfigManager;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Config\EnvironmentLoader;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Config\Exceptions\WrongConfigException;
use Vasoft\Joke\Config\Exceptions\WrongConfigFileException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Support\Normalizers\Path;
use Vasoft\Joke\Tests\Fixtures\Config\SecondSingleConfig;
use Vasoft\Joke\Tests\Fixtures\Config\Other;
use Vasoft\Joke\Tests\Fixtures\Config\SingleConfig;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Config\ConfigManager
 */
final class ConfigManagerTest extends TestCase
{
    //    use PHPMock;

    private static string $basePath = '';
    private static array $dirForClean = [];
    private static ?Environment $env = null;
    private static ?ServiceContainer $container = null;
    private static string $name = '';
    private static string $base = '';

    public static function setUpBeforeClass(): void
    {
        self::$name = 'Config' . random_int(1, 100);
        self::$base = dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR;
        self::$basePath = self::$base . self::$name . \DIRECTORY_SEPARATOR;
        mkdir(self::$basePath);
    }

    protected function setUp(): void
    {
        self::$env = new Environment(new EnvironmentLoader(self::$base));

        $pathNormalizer = new Path(self::$basePath);

        self::$container = new ServiceContainer();
        self::$container->registerSingleton(Path::class, $pathNormalizer);
        self::$container->registerAlias('normalizer.path', Path::class);

        $environment = new Environment(new EnvironmentLoader($pathNormalizer->basePath));
        self::$container->registerSingleton(Environment::class, $environment);
        self::$container->registerAlias('env', Environment::class);
    }

    public static function tearDownAfterClass(): void
    {
        self::cleanDir(self::$basePath);
    }

    private static function cleanDir(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = scandir($dir);
        if (!is_array($files)) {
            return;
        }
        $items = array_diff($files, ['.', '..']);

        foreach ($items as $item) {
            $path = $dir . \DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::cleanDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    protected function tearDown(): void
    {
        foreach (self::$dirForClean as $dir) {
            self::cleanDir($dir);
        }
        self::$dirForClean = [];
    }

    protected function writeConfigFile(string $subDir, string $name, string $content): void
    {
        $path = self::$basePath . $subDir . \DIRECTORY_SEPARATOR;
        if (!file_exists($path)) {
            mkdir($path, recursive: true);
        }
        self::$dirForClean[] = $path;
        $fileName = $path . $name . '.php';
        file_put_contents($fileName, '<?php return ' . $content);
    }

    protected function writeConfigFileWithEnv(string $subDir, string $name, string $configContent): void
    {
        $path = self::$basePath . $subDir . \DIRECTORY_SEPARATOR;
        if (!file_exists($path)) {
            mkdir($path, recursive: true);
        }
        self::$dirForClean[] = $path;
        $fileName = $path . $name . '.php';
        file_put_contents($fileName, '<?php ' . $configContent);
    }

    public function testLoading(): void
    {
        $this->writeConfigFile(
            'config',
            'first',
            'new Vasoft\Joke\Tests\Fixtures\Config\SingleConfig();',
        );
        $this->writeConfigFile(
            'config',
            'second',
            <<<'PHP'
                [
                    Vasoft\Joke\Tests\Fixtures\Config\SecondSingleConfig::class => function() {return new Vasoft\Joke\Tests\Fixtures\Config\SecondSingleConfig();},
                    Vasoft\Joke\Tests\Fixtures\Config\Other\SecondSingleConfig::class => new Vasoft\Joke\Tests\Fixtures\Config\Other\SecondSingleConfig(),
                ];
                PHP,
        );
        new ConfigManager(self::$container, 'config', '');
        self::assertInstanceOf(SingleConfig::class, self::$container->get(SingleConfig::class));
        self::assertInstanceOf(SecondSingleConfig::class, self::$container->get(SecondSingleConfig::class));
        self::assertInstanceOf(Other\SecondSingleConfig::class, self::$container->get(Other\SecondSingleConfig::class));
    }

    public function testLoadingOneLevelOnly(): void
    {
        $this->writeConfigFile(
            'config',
            'first',
            'new Vasoft\Joke\Tests\Fixtures\Config\SingleConfig();',
        );
        $this->writeConfigFile(
            'config/lazy',
            'second',
            'new Vasoft\Joke\Tests\Fixtures\Config\SecondSingleConfig();',
        );
        new ConfigManager(self::$container, 'config', '');
        self::assertInstanceOf(SingleConfig::class, self::$container->get(SingleConfig::class));
        self::assertFalse(self::$container->has(SecondSingleConfig::class));
    }

    public function testLazySuccess(): void
    {
        $this->writeConfigFile(
            'config/lazy',
            'SingleConfig',
            'new Vasoft\Joke\Tests\Fixtures\Config\SingleConfig();',
        );
        $this->writeConfigFile(
            'config/lazy',
            'SecondSingleConfig',
            '["Vasoft\Joke\Tests\Fixtures\Config\SecondSingleConfig" => new Vasoft\Joke\Tests\Fixtures\Config\SecondSingleConfig()];',
        );
        $loader = new ConfigManager(self::$container, 'config', self::$basePath . 'config/lazy');
        self::assertFalse(self::$container->has(SingleConfig::class));
        self::assertFalse(self::$container->has(SecondSingleConfig::class));

        self::assertInstanceOf(SingleConfig::class, $loader->get(SingleConfig::class));
        self::assertInstanceOf(SecondSingleConfig::class, $loader->get(SecondSingleConfig::class));

        self::assertTrue(self::$container->has(SingleConfig::class));
        self::assertTrue(self::$container->has(SecondSingleConfig::class));
    }

    public function testLazyWrongClosure(): void
    {
        $this->writeConfigFile(
            'config/lazy',
            'WrongConfig',
            '["Vasoft\Configs\WrongConfig" => fn() => new stdClass()];',
        );
        $loader = new ConfigManager(self::$container, 'config', self::$basePath . 'config/lazy');

        self::expectException(WrongConfigException::class);
        self::expectExceptionMessageIs(
            'Wrong config for Vasoft\Configs\WrongConfig must return a instance of Vasoft\Joke\Config\AbstractConfig',
        );
        $loader->get('Vasoft\Configs\WrongConfig');
    }

    public function testWrongClosure(): void
    {
        $this->writeConfigFile(
            'config',
            'app',
            '["Vasoft\Configs\WrongConfig" => fn() => new stdClass()];',
        );
        new ConfigManager(self::$container, 'config', '');

        self::expectException(WrongConfigException::class);
        self::expectExceptionMessageIs(
            'Wrong config for Vasoft\Configs\WrongConfig must return a instance of Vasoft\Joke\Config\AbstractConfig',
        );
        self::$container->get('Vasoft\Configs\WrongConfig');
    }

    public function testEnvironmentVariablesInConfig(): void
    {
        /**
         * @var Environment $env
         */
        $env = self::getMockBuilder(Environment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $env->expects(self::once())->method('get')
            ->with('DB_USERNAME', 'default_user')
            ->willReturn('test_user');
        $this->writeConfigFile(
            'config',
            'database',
            'new \Vasoft\Joke\Tests\Fixtures\Config\SingleConfig($env);',
        );
        $pathNormalizer = new Path(self::$basePath);

        $container = new ServiceContainer();
        $container->registerSingleton(Path::class, $pathNormalizer);
        $container->registerAlias('normalizer.path', Path::class);

        $container->registerSingleton(Environment::class, $env);
        $container->registerAlias('env', Environment::class);

        new ConfigManager($container, 'config', '');
        /** @var SingleConfig $config */
        $config = $container->get(SingleConfig::class);
        self::assertSame('test_user', $config->getEnvValue('DB_USERNAME', 'default_user'));
    }

    public function testWrongFile(): void
    {
        $this->writeConfigFile(
            'config',
            'first',
            'new stdClass();',
        );

        $this->expectException(WrongConfigFileException::class);
        $this->expectExceptionMessageIs(
            'Config file config/first.php must return a instance of Vasoft\Joke\Config\AbstractConfig',
        );
        new ConfigManager(self::$container, 'config', self::$basePath . 'config/lazy');
    }

    public function testWrongFileClosure(): void
    {
        $this->writeConfigFile(
            'config',
            'first',
            'fn() => new \Vasoft\Joke\Tests\Fixtures\Config\SingleConfig();',
        );
        $this->expectException(WrongConfigFileException::class);
        $this->expectExceptionMessageIs(
            'Config file config/first.php must return a instance of Vasoft\Joke\Config\AbstractConfig',
        );
        new ConfigManager(self::$container, 'config', self::$basePath . 'config/lazy');
    }

    public function testWrongFileLazy(): void
    {
        $this->writeConfigFile(
            'config/lazy',
            'UnknownConfig',
            'new stdClass();',
        );
        $loader = new ConfigManager(self::$container, 'config', self::$basePath . 'config/lazy');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIs(
            'Config file config/lazy/UnknownConfig.php must return a instance of Vasoft\Joke\Config\AbstractConfig',
        );
        $loader->get('\Fixtures\Example\UnknownConfig');
    }

    public function testDefaultConfig(): void
    {
        $loader = new ConfigManager(self::$container, 'config', '');
        $loader->registerProviders([ConfigProvider::class]);
        /** @var SecondSingleConfig $config */
        $config = $loader->get(SecondSingleConfig::class);
        self::assertSame('DefaultBuilder', $config->context);
    }

    public function testGetReturnsInstanceFromContainer(): void
    {
        $expectedName = 'ExpectedInstance';
        $loader = new ConfigManager(self::$container, 'config', '');
        self::$container->registerSingleton(SecondSingleConfig::class, new SecondSingleConfig($expectedName));
        $loader->registerProviders([ConfigProvider::class]);
        /** @var SecondSingleConfig $config */
        $config = $loader->get(SecondSingleConfig::class);
        self::assertSame($expectedName, $config->context);
    }

    public function testProviderReturnWrongConfig(): void
    {
        $loader = new ConfigManager(self::$container, 'config', '');
        $loader->registerProviders([ConfigProvider::class]);
        self::expectException(WrongConfigException::class);
        self::expectExceptionMessageIsOrContains(
            'Provider Vasoft\Joke\Tests\Fixtures\Config\ConfigProvider returned invalid type for wrong',
        );

        /** @var SecondSingleConfig $config */
        $loader->get('wrong');
    }

    public function testUnknownConfig(): void
    {
        $loader = new ConfigManager(self::$container, 'config', '');
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs(
            'Unknown config class: Vasoft\Joke\Tests\Fixtures\Config\SecondSingleConfig',
        );

        /** @var SecondSingleConfig $config */
        $loader->get(SecondSingleConfig::class);
    }
}
