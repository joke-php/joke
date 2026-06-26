<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Application;

use phpmock\phpunit\PHPMock;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Config\EnvironmentLoader;
use Vasoft\Joke\Container\ParameterResolver;
use Vasoft\Joke\Contract\Logging\LoggerInterface;
use Vasoft\Joke\Logging\Logger;
use Vasoft\Joke\Logging\LogLevel;
use Vasoft\Joke\Http\Csrf\CsrfConfig;
use Vasoft\Joke\Middleware\Exceptions\MiddlewareException;
use Vasoft\Joke\Tests\Fixtures\Logger\FakeLogger;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Vasoft\Joke\Application\KernelConfig;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Contract\Routing\RouterInterface;
use Vasoft\Joke\Application\Application;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Routing\Router;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Support\Normalizers\Path;
use Vasoft\Joke\Tests\Fixtures\Middlewares\SingleMiddleware;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Application\Application
 */
final class ApplicationTest extends TestCase
{
    use PHPMock;

    public static string $basePath = '';
    public static string $bootstrapPath = '';

    public static function setUpBeforeClass(): void
    {
        $name = 'Config' . random_int(1, 100);
        $base = dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR;
        self::$basePath = $base . $name . \DIRECTORY_SEPARATOR;
        self::$bootstrapPath = self::$basePath . 'bootstrap' . \DIRECTORY_SEPARATOR;
        mkdir(self::$bootstrapPath, recursive: true);
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

    protected function writeKernelBootstrap(string $content): void
    {
        file_put_contents(self::$bootstrapPath . 'kernel.php', '<?php return ' . $content);
    }

    public function testLoadingEnvironment(): void
    {
        $di = new ServiceContainer();
        new Application(
            dirname(__DIR__, 2),
            'routes/web.php',
            $di,
        );
        $byAlias = $di->get('env');
        $byClass = $di->get(Environment::class);
        self::assertInstanceOf(Environment::class, $byAlias);
        self::assertSame($byAlias, $byClass);
    }

    public function testExecuteDefaultHtml(): void
    {
        $container = new ServiceContainer();
        $container->registerSingleton(CsrfConfig::class, CsrfConfig::class);

        $app = new Application(dirname(__DIR__, 2), '', $container);
        ob_start();
        $request = new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $app->handle($request);
        $output = ob_get_clean();
        self::assertStringContainsString('<li><a href="/name/Alex">Hi Alex</a>', $output);
        self::assertSame($request, $container->get(HttpRequest::class));
    }

    public function testExecuteDefaultJson(): void
    {
        $container = new ServiceContainer();
        $container->registerSingleton(CsrfConfig::class, CsrfConfig::class);
        $container->registerSingleton(LoggerInterface::class, Logger::class);
        $app = new Application(dirname(__DIR__, 2), 'routes/web.php', $container);
        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/json/Alex']));
        $output = ob_get_clean();
        self::assertSame('{"fio":"Alex"}', $output);
    }

    public function testDefaultMiddleware(): void
    {
        $app = new Application(
            dirname(__DIR__) . \DIRECTORY_SEPARATOR . '/Fixtures/no-wildcard',
            '/tests/Fixtures/no-wildcard/routes/web.php',
            new ServiceContainer(),
        );
        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/not-found-url']));
        $output = ob_get_clean();
        self::assertSame('Route not found', $output);
    }

    public function testWildCard(): void
    {
        $container = new ServiceContainer();
        $container->registerSingleton(CsrfConfig::class, CsrfConfig::class);
        $container->registerSingleton(LoggerInterface::class, new FakeLogger());
        $app = new Application(dirname(__DIR__, 2), 'routes/web.php', $container);
        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/not-found-url']));
        $output = ob_get_clean();
        self::assertSame('Запрошен несуществующий путь: not-found-url', $output);
    }

    #[RunInSeparateProcess]
    public function testDefaultRouteMiddleware(): void
    {
        $container = new ServiceContainer();
        $container->registerSingleton(CsrfConfig::class, CsrfConfig::class);
        $app = new Application(
            dirname(__DIR__, 2),
            'routes/web.php',
            $container,
        );
        self::assertSame(PHP_SESSION_NONE, session_status());
        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/name/Alex']));
        $output = ob_get_clean();
        self::assertSame(PHP_SESSION_ACTIVE, session_status(), 'Session middleware is not active');
        self::assertSame('Hi Alex', $output);
    }

    public function testAddMiddleware(): void
    {
        SingleMiddleware::clean();
        $middleware = new SingleMiddleware();
        $middleware->index = 3;
        $container = new ServiceContainer();
        $container->registerSingleton(CsrfConfig::class, CsrfConfig::class);
        $container->registerSingleton(LoggerInterface::class, new FakeLogger());
        $app = new Application(dirname(__DIR__, 2), 'routes/web.php', $container)
            ->addMiddleware(SingleMiddleware::class)
            ->addMiddleware($middleware);
        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/name/jons']));
        $output = ob_get_clean();
        self::assertSame('Middleware 0 begin#Middleware 3 begin#Hi jons#Middleware 3 end#Middleware 0 end', $output);
    }

    public function testAddMiddlewareAndRouteMiddleware(): void
    {
        SingleMiddleware::clean();
        $middleware = new SingleMiddleware();
        $middleware->index = 3;
        $routeMiddleware = new SingleMiddleware();
        $routeMiddleware->index = 4;
        $routeMiddleware2 = new SingleMiddleware();
        $routeMiddleware2->index = 5;

        $diContainer = new ServiceContainer();
        $diContainer->registerSingleton(CsrfConfig::class, CsrfConfig::class);
        $app = new Application(
            dirname(__DIR__, 2),
            'routes/web.php',
            $diContainer,
        )
            ->addMiddleware(SingleMiddleware::class)
            ->addMiddleware($middleware)
            ->addRouteMiddleware($routeMiddleware);
        /** @var Router $router */
        $router = $diContainer->get(RouterInterface::class);
        $route = $router->route('hiName');
        $route->addMiddleware($routeMiddleware2);

        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/name/jons']));
        $output = ob_get_clean();
        self::assertSame(
            'Middleware 0 begin#Middleware 3 begin#Middleware 4 begin#Middleware 5 begin#Hi jons#Middleware 5 end#Middleware 4 end#Middleware 3 end#Middleware 0 end',
            $output,
        );
    }

    public function testAddMiddlewareAndRouteMiddlewareFilter(): void
    {
        SingleMiddleware::clean();
        $middleware = new SingleMiddleware();
        $middleware->index = 3;
        $routeMiddleware1 = new SingleMiddleware();
        $routeMiddleware1->index = 4;
        $routeMiddleware2 = new SingleMiddleware();
        $routeMiddleware2->index = 5;
        $container = new ServiceContainer();
        $container->registerSingleton(CsrfConfig::class, CsrfConfig::class);
        $container->registerSingleton(LoggerInterface::class, new FakeLogger());
        $app = new Application(dirname(__DIR__, 2), 'routes/web.php', $container)
            ->addMiddleware(SingleMiddleware::class)
            ->addMiddleware($middleware)
            ->addRouteMiddleware($routeMiddleware1)
            ->addRouteMiddleware($routeMiddleware2, groups: ['filtered']);

        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/name/jons']));
        $output = ob_get_clean();
        self::assertSame(
            'Middleware 0 begin#Middleware 3 begin#Middleware 4 begin#Hi jons#Middleware 4 end#Middleware 3 end#Middleware 0 end',
            $output,
        );
        SingleMiddleware::clean();
        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/name-filtered/jons']));
        $output = ob_get_clean();
        self::assertSame(
            'Middleware 0 begin#Middleware 3 begin#Middleware 4 begin#Middleware 5 begin#Hi jons#Middleware 5 end#Middleware 4 end#Middleware 3 end#Middleware 0 end',
            $output,
        );
    }

    public function testWrongMiddleware(): void
    {
        $app = new Application(
            dirname(__DIR__, 2),
            'routes/web.php',
            new ServiceContainer(),
        )->addMiddleware(Router::class);
        ob_start();
        $app->handle(new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/name/jons']));
        $output = ob_get_clean();
        self::assertSame(
            'Middleware Vasoft\Joke\Routing\Router must implements MiddlewareInterface.',
            $output,
        );
    }

    public function testKernelBootstrapWrong(): void
    {
        self::writeKernelBootstrap('new \Vasoft\Joke\Tests\Fixtures\Config\SingleConfig();');
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('kernel.php must return a KernelConfig instance.');
        new Application(self::$basePath, '', new ServiceContainer());
    }

    public function testKernelBootstrapSuccess(): void
    {
        self::writeKernelBootstrap('new \Vasoft\Joke\Application\KernelConfig()->setLazyConfigPath("custom_lazy");');
        $container = new ServiceContainer();
        new Application(self::$basePath, '', $container);
        self::assertSame('custom_lazy', $container->get(KernelConfig::class)->getLazyConfigPath());
    }

    #[RunInSeparateProcess]
    public function testExceptionOnBootstrap(): void
    {
        $pathNormalizer = new Path(__DIR__);
        $logger = new FakeLogger();
        $environment = new Environment(new EnvironmentLoader($pathNormalizer->basePath));

        $container = self::createStub(ServiceContainer::class);
        $container
            ->method('get')
            ->willReturnCallback(static function ($name) use ($environment, $pathNormalizer, $logger): mixed {
                return match ($name) {
                    Environment::class, 'env' => $environment,
                    Path::class, 'normalizer.path' => $pathNormalizer,
                    Logger::class, 'logger' => $logger,
                };
            });
        $container
            ->method('registerAlias')
            ->willReturnCallback(static function ($alias, $entity) use ($container): ServiceContainer {
                if ('config' === $alias) {
                    throw new \Exception('Test exception');
                }

                return $container;
            });

        new Application(self::$basePath, '', $container);
        $log = $logger->getRecords();

        self::assertCount(1, $log);
        self::assertArrayHasKey('level', $log[0]);
        self::assertArrayHasKey('message', $log[0]);
        self::assertSame(LogLevel::ERROR, $log[0]['level']);
        self::assertInstanceOf(\Exception::class, $log[0]['message']);
        self::assertSame('Test exception', $log[0]['message']->getMessage());
    }

    public function testNamedMiddleware(): void
    {
        self::writeKernelBootstrap('new \Vasoft\Joke\Application\KernelConfig()->setProviders([]);');
        $pathNormalizer = new Path(self::$basePath);
        $logger = new FakeLogger();
        $environment = new Environment(new EnvironmentLoader(self::$basePath));

        $container = self::createStub(ServiceContainer::class);
        $container
            ->method('getParameterResolver')
            ->willReturn(new ParameterResolver($container));
        $container
            ->method('get')
            ->willReturnCallback(
                static function ($name) use ($container, $environment, $pathNormalizer, $logger): mixed {
                    return match ($name) {
                        ServiceContainer::class => $container,
                        ApplicationConfig::class => new ApplicationConfig(),
                        'middleware.global' => new ApplicationConfig(),
                        Environment::class, 'env' => $environment,
                        Path::class, 'normalizer.path' => $pathNormalizer,
                        Logger::class, 'logger' => $logger,
                    };
                },
            );

        new Application(self::$basePath, '', $container);
        $log = $logger->getRecords();
        self::assertSame(
            'middleware.global is not instance of MiddlewareCollection.',
            $log[0]['message']->getMessage(),
        );
    }

    #[RunInSeparateProcess]
    public function testExceptionAndWrongError(): void
    {
        $expectMessage = 'middleware.global is not instance of MiddlewareCollection.';
        $errorLog = self::getFunctionMock('Vasoft\Joke\Application', 'error_log');
        $errorLog->expects(self::once())->with($expectMessage);

        self::writeKernelBootstrap('new \Vasoft\Joke\Application\KernelConfig()->setProviders([]);');
        $pathNormalizer = new Path(self::$basePath);
        $environment = new Environment(new EnvironmentLoader(self::$basePath));

        $container = self::createStub(ServiceContainer::class);
        $container
            ->method('getParameterResolver')
            ->willReturn(new ParameterResolver($container));
        $container
            ->method('get')
            ->willReturnCallback(
                static function ($name) use ($container, $environment, $pathNormalizer): mixed {
                    return match ($name) {
                        ServiceContainer::class => $container,
                        ApplicationConfig::class => new ApplicationConfig(),
                        'middleware.global' => new ApplicationConfig(),
                        Environment::class, 'env' => $environment,
                        Path::class, 'normalizer.path' => $pathNormalizer,
                        Logger::class, 'logger' => null,
                    };
                },
            );

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessageIs($expectMessage);
        new Application(self::$basePath, '', $container);
    }

    #[RunInSeparateProcess]
    public function testInvalidCsrfException(): void
    {
        $header = self::getFunctionMock('Vasoft\Joke\Http\Response', 'header');

        $statusHeaderExists = false;
        $header->expects(self::atLeastOnce())->willReturnCallback(
            static function ($header) use (&$statusHeaderExists): void {
                if ('HTTP/1.1 403 Forbidden' === $header) {
                    $statusHeaderExists = true;
                }
            },
        );

        $container = new ServiceContainer();
        $container->registerSingleton(CsrfConfig::class, CsrfConfig::class);

        $app = new Application(dirname(__DIR__, 2), '', $container);
        ob_start();
        $request = new HttpRequest(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/queries']);
        $app->handle($request);
        ob_get_clean();
        self::assertTrue($statusHeaderExists);
    }
}
