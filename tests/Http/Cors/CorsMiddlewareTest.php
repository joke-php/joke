<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Cors;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;
use Vasoft\Joke\Support\FileSystem;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Config\EnvironmentLoader;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Cors\CorsConfig;
use Vasoft\Joke\Http\Cors\CorsMiddleware;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Http\Response\ResponseStatus;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Cors\CorsMiddleware
 */
final class CorsMiddlewareTest extends TestCase
{
    use PHPMock;

    private static ResponseBuilder $builder;
    private HtmlResponse $response;

    public static function setUpBeforeClass(): void
    {
        $container = new ServiceContainer();
        $pathNormalizer = new FileSystem(__DIR__);
        $container->registerSingleton(FileSystem::class, $pathNormalizer);
        $container->registerAlias('normalizer.path', FileSystem::class);

        $environment = new Environment(new EnvironmentLoader(''));
        $container->registerSingleton(Environment::class, $environment);
        $container->registerAlias('env', Environment::class);

        $container->registerSingleton(CookieConfig::class, new CookieConfig());
        $container->registerSingleton(PageBuilderConfig::class, new PageBuilderConfig());
        $container->registerSingleton(Request::class, new HttpRequest());

        self::$builder = new ResponseBuilder(new ApplicationConfig(), $container);
    }

    protected function setUp(): void
    {
        $this->response = new HtmlResponse(new CookieCollection(new CookieConfig(), true));
    }

    private function defaultRouteHandler(): HtmlResponse
    {
        $this->response->headers->set('Route-Executed', 'true');

        return $this->response;
    }

    private function defaultRouteHandlerWithVary(): HtmlResponse
    {
        $this->response->headers->set('Route-Executed', 'true');
        $this->response->headers->set('Vary', 'Example1, Example2');

        return $this->response;
    }

    public function testCorsHeadersNotSetByDefault(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $response = new CorsMiddleware(new CorsConfig(), self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayNotHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayNotHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
    }

    public function testCorsAllowOriginDisallow(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setOrigins(['https://my.com']);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayNotHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayNotHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
    }

    public function testCorsAllowOriginAllowPreflightMethodEmpty(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setOrigins(['https://example.com']);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();

        self::assertArrayHasKey('access-control-allow-origin', $headers);
        self::assertArrayNotHasKey('access-control-allow-credentials', $headers);
        self::assertArrayNotHasKey('access-control-allow-methods', $headers);
        self::assertArrayNotHasKey('access-control-expose-headers', $headers);
        self::assertArrayNotHasKey('access-control-max-age', $headers);
        self::assertArrayNotHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
    }

    public function testCorsAllowOriginAllowPreflightMethodInvalid(): void
    {
        $request = new HttpRequest(
            server: [
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'UNKNOWN',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setOrigins(['https://example.com']);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();
        self::assertArrayHasKey('access-control-allow-origin', $headers);
        self::assertArrayNotHasKey('access-control-allow-credentials', $headers);
        self::assertArrayNotHasKey('access-control-allow-methods', $headers);
        self::assertArrayNotHasKey('access-control-expose-headers', $headers);
        self::assertArrayNotHasKey('access-control-max-age', $headers);
        self::assertArrayNotHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
    }

    #[TestDox('Возможность использовать * в списке допустимых заголовков')]
    public function testWildcardForAccessControlRequestHeaders(): void
    {
        $request = new HttpRequest(
            server: [
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'demo-header',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setHeaders(['Example', '*']);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();

        self::assertSame(ResponseStatus::OK, $response->status);
    }

    public function testCorsAllowOriginAllowPreflightHeadersNotSetCredentialsOff(): void
    {
        $request = new HttpRequest(
            server: [
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();
        self::assertArrayHasKey('access-control-allow-origin', $headers);
        self::assertSame('*', $headers['access-control-allow-origin']);
        self::assertArrayNotHasKey('access-control-allow-credentials', $headers);
        self::assertArrayHasKey('access-control-allow-methods', $headers);
        self::assertArrayHasKey('access-control-expose-headers', $headers);
        self::assertArrayHasKey('access-control-max-age', $headers);
        self::assertArrayNotHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    public function testCorsAllowOriginAllowPreflightHeadersNotSetCredentialsOn(): void
    {
        $request = new HttpRequest(
            server: [
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowCredentials(true)
            ->setOrigins(['https://example.com'])
            ->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayHasKey('access-control-allow-origin', $headers);
        self::assertSame('https://example.com', $headers['access-control-allow-origin']);
        self::assertArrayHasKey('access-control-allow-credentials', $headers);
        self::assertArrayHasKey('vary', $headers);
        self::assertArrayHasKey('access-control-allow-methods', $headers);
        self::assertArrayHasKey('access-control-expose-headers', $headers);
        self::assertArrayHasKey('access-control-max-age', $headers);
        self::assertArrayNotHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('При allowCredentials=true и * в допустимых отправляются запрошенные')]
    public function testAllowCredentialsAndWildcard(): void
    {
        $request = new HttpRequest(
            server: [
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'EXAMPLE-HEADER,  example-next',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowCredentials(true)
            ->setOrigins(['https://example.com'])
            ->setHeaders(['*'])
            ->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();
        self::assertSame('example-header, example-next', $response->headers->get('access-control-allow-headers'));
    }

    #[TestDox('При allowCredentials=true и * в допустимых, но нет запрошенных то заголовок не возвращается')]
    public function testAllowCredentialsAndWildcardEmpty(): void
    {
        $request = new HttpRequest(
            server: [
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowCredentials(true)
            ->setOrigins(['https://example.com'])
            ->setHeaders(['*'])
            ->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();
        self::assertArrayNotHasKey('Access-Control-Allow-Headers', $headers);
    }

    #[TestDox('Если заголовок Vary уже был - добавляет значение')]
    public function testUpendVary(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowCredentials(true)
            ->setOrigins(['https://example.com'])
            ->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandlerWithVary(...));

        self::assertSame('Example1, Example2, Origin', $response->headers->get('vary'));
    }

    public function testCorsAllowOriginAllowPreflightHeadersInvalid(): void
    {
        $request = new HttpRequest(
            server: [
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Not-Allowed',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();
        self::assertArrayHasKey('access-control-allow-origin', $headers);
        self::assertArrayNotHasKey('access-control-allow-credentials', $headers);
        self::assertArrayNotHasKey('access-control-allow-methods', $headers);
        self::assertArrayNotHasKey('access-control-expose-headers', $headers);
        self::assertArrayNotHasKey('access-control-max-age', $headers);
        self::assertArrayNotHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
    }

    public function testCorsAllowOriginAllowPreflightHeadersValid(): void
    {
        $request = new HttpRequest(
            server: [
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Not-Allowed,EXAMPLES',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setHeaders(['Not-Allowed', 'Examples']);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();
        self::assertArrayHasKey('access-control-allow-origin', $headers);
    }

    public function testCorsNotAllowedMethod(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()
            ->setMethods([HttpMethod::HEAD])
            ->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();
        self::assertArrayHasKey('access-control-allow-origin', $headers);
        self::assertArrayNotHasKey('access-control-allow-credentials', $headers);
        self::assertArrayNotHasKey('access-control-allow-methods', $headers);
        self::assertArrayNotHasKey('access-control-expose-headers', $headers);
        self::assertArrayNotHasKey('access-control-max-age', $headers);
        self::assertArrayNotHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::METHOD_NOT_ALLOWED, $response->status);
    }

    public function testCorsSuccess(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $config = new CorsConfig()->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();
        self::assertArrayHasKey('access-control-allow-origin', $headers);
        self::assertArrayHasKey('access-control-allow-methods', $headers);
        self::assertArrayHasKey('access-control-expose-headers', $headers);
        self::assertArrayHasKey('access-control-max-age', $headers);
        self::assertArrayHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    public function testCorsSelf(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
            ],
        );
        $config = new CorsConfig()->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));
        $headers = $response->headers->getAll();
        self::assertArrayNotHasKey('access-control-allow-origin', $headers);
        self::assertArrayNotHasKey('access-control-allow-methods', $headers);
        self::assertArrayNotHasKey('access-control-expose-headers', $headers);
        self::assertArrayNotHasKey('access-control-max-age', $headers);
        self::assertArrayHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[RunInSeparateProcess]
    #[TestDox('Некорректный URL запроса равносилен другом origin')]
    public function testIncorrectRequestedUrl(): void
    {
        $parseUrl = self::getFunctionMock('\Vasoft\Joke\Http\Cors', 'parse_url');
        $parseUrl->expects(self::once())->willReturn(false);
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://example.com',
            ],
        );
        $response = new CorsMiddleware(new CorsConfig(), self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayNotHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayNotHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
    }

    #[TestDox('Указан порт: учитывает порт и приводит регистр ')]
    public function testWithPort(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'LOCALHOST:8002',
                'HTTP_ORIGIN' => 'http://localhost:8002',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setAllowCredentials(true)
            ->setOrigins(['http://localhost:8002']);

        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayNotHasKey('access-control-allow-origin', $headers);
        self::assertArrayHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('Если не указан порт подставляет по умолчанию. Не SSL соединение')]
    public function testDefaultPortNoSsl(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'LOCALHOST',
                'HTTP_ORIGIN' => 'http://localhost:80',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setAllowCredentials(true)
            ->setOrigins(['http://localhost:80']);

        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayNotHasKey('access-control-allow-origin', $headers);
        self::assertArrayHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('Если не указан порт подставляет по умолчанию. Не SSL соединение')]
    public function testDefaultPortSsl(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'LOCALHOST',
                'HTTP_ORIGIN' => 'https://localhost:443',
                'HTTPS' => true,
            ],
        );

        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setAllowCredentials(true)
            ->setOrigins(['http://localhost:443']);

        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayNotHasKey('access-control-allow-origin', $headers);
        self::assertArrayHasKey('route-executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }
}
