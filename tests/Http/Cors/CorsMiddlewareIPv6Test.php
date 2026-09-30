<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Cors;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Support\FileSystem;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Config\EnvironmentLoader;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Cors\CorsConfig;
use Vasoft\Joke\Http\Cors\CorsMiddleware;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\ResponseStatus;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Cors\CorsMiddleware
 */
#[TestDox('CorsMiddleware и IPv6')]
final class CorsMiddlewareIPv6Test extends TestCase
{
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

    #[TestDox('IPv6 с портом: same-origin запрос проходит без CORS-заголовков')]
    public function testIPv6WithPortSameOrigin(): void
    {
        // Запрос с localhost:8080, Origin тоже localhost:8080
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]:8080',
                'HTTP_ORIGIN' => 'http://[::1]:8080',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        // Same-origin запрос - CORS-заголовки не должны добавляться
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('IPv6 без порта (стандартный 80): same-origin запрос проходит')]
    public function testIPv6WithoutPortSameOrigin(): void
    {
        // Запрос на стандартный порт 80, Origin без явного порта
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]',
                'HTTP_ORIGIN' => 'http://[::1]',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('IPv6 HTTPS с портом 443: same-origin запрос проходит')]
    public function testIPv6HttpsSameOrigin(): void
    {
        // HTTPS запрос на стандартный порт 443
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]:443',
                'HTTP_ORIGIN' => 'https://[::1]',
                'HTTPS' => 'on',
            ],
        );

        $config = new CorsConfig()->setAllowedCors(true);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('IPv6 cross-origin: разные порты блокируются при выключенном CORS')]
    public function testIPv6DifferentPortCrossOriginForbidden(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]:8080',
                'HTTP_ORIGIN' => 'http://[::1]:9000',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()->setAllowedCors(false);
        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
        self::assertArrayNotHasKey('Route-Executed', $headers ?? []);
    }

    #[TestDox('IPv6 cross-origin: разрешенный origin получает CORS-заголовки')]
    public function testIPv6CrossOriginAllowed(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]:8080',
                'HTTP_ORIGIN' => 'http://[::1]:9000',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setOrigins(['http://[::1]:9000']);

        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        self::assertSame('http://[::1]:9000', $headers['Access-Control-Allow-Origin']);
        self::assertArrayHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('IPv6 preflight: корректная обработка OPTIONS запроса')]
    public function testIPv6PreflightRequest(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_HOST' => '[::1]:8080',
                'HTTP_ORIGIN' => 'http://[::1]:9000',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setOrigins(['http://[::1]:9000']);

        $response = (new CorsMiddleware($config, self::$builder))
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        self::assertSame('http://[::1]:9000', $headers['Access-Control-Allow-Origin']);
        self::assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayHasKey('Access-Control-Max-Age', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('IPv6 wildcard origin: все IPv6 origins разрешены')]
    public function testIPv6WildcardOrigin(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]:8080',
                'HTTP_ORIGIN' => 'http://[::1]:9000',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setOrigins(['*']);

        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        self::assertSame('*', $headers['Access-Control-Allow-Origin']);
        self::assertArrayHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    #[TestDox('IPv6 с credentials: корректная установка заголовков')]
    public function testIPv6WithCredentials(): void
    {
        $request = new HttpRequest(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]:8080',
                'HTTP_ORIGIN' => 'http://[::1]:9000',
                'HTTPS' => '',
            ],
        );

        $config = new CorsConfig()
            ->setAllowedCors(true)
            ->setAllowCredentials(true)
            ->setOrigins(['http://[::1]:9000']);

        $response = new CorsMiddleware($config, self::$builder)
            ->handle($request, $this->defaultRouteHandler(...));

        $headers = $response->headers->getAll();

        self::assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        self::assertSame('http://[::1]:9000', $headers['Access-Control-Allow-Origin']);
        self::assertArrayHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertSame('true', $headers['Access-Control-Allow-Credentials']);
        self::assertArrayHasKey('Vary', $headers);
        self::assertStringContainsString('Origin', $headers['Vary']);
        self::assertSame(ResponseStatus::OK, $response->status);
    }
}
