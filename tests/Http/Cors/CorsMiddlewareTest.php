<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Cors;

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
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayNotHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayNotHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
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
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayNotHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayNotHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
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
        self::assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        self::assertSame('*', $headers['Access-Control-Allow-Origin']);
        self::assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
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
        self::assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        self::assertSame('https://example.com', $headers['Access-Control-Allow-Origin']);
        self::assertArrayHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayHasKey('Vary', $headers);
        self::assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
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
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayNotHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayNotHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
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
        self::assertArrayHasKey('Access-Control-Allow-Origin', $headers);
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
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayNotHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayNotHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayNotHasKey('Route-Executed', $headers);
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
        self::assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayHasKey('Route-Executed', $headers);
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
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        self::assertArrayNotHasKey('Access-Control-Expose-Headers', $headers);
        self::assertArrayNotHasKey('Access-Control-Max-Age', $headers);
        self::assertArrayHasKey('Route-Executed', $headers);
        self::assertSame(ResponseStatus::OK, $response->status);
    }
}
