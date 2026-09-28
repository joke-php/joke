<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\AuthService;
use Vasoft\Joke\Auth\Rights\RightsChecker;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;
use Vasoft\Joke\Http\Response\JsonResponse;
use Vasoft\Joke\Http\Response\RedirectResponse;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Http\Response\ResponseStatus;
use Vasoft\Joke\Support\FileSystem;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\AuthMiddleware
 */
#[TestDox('AuthMiddleware - проверка аутентификации и прав доступа')]
#[CoversClass(AuthMiddleware::class)]
final class AuthMiddlewareTest extends TestCase
{
    private ServiceContainer $container;
    public array $initialized = [];
    private RightsChecker $rightsChecker;
    private ResponseBuilder $responseBuilder;

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();

        $this->rightsChecker = new RightsChecker(new AuthConfig(), $this->container);
        $this->responseBuilder = new ResponseBuilder(new ApplicationConfig(), $this->container);

        $this->container->registerSingleton(CookieConfig::class, CookieConfig::class);
        $this->container->registerSingleton(PageBuilderConfig::class, PageBuilderConfig::class);
        $basePath = sys_get_temp_dir() . '/joke_auth_test' . bin2hex(random_bytes(8));
        mkdir($basePath);
        $this->container->registerSingleton(FileSystem::class, new FileSystem($basePath));
    }

    public function testExceptionIfInvalidFormatRight(): void
    {
        $authService = self::createStub(AuthService::class);
        $authService->method('getUser')->willReturn(new User(1));
        $middleware = new AuthMiddleware($authService, $this->rightsChecker, $this->responseBuilder, ['root'], null);

        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('Invalid right format: "root". Expected "module:right".');
        $middleware->handle(new HttpRequest(), static fn() => '');
    }

    #[TestDox('Анонимный, нет права, редирект не указан: должен вернуть 401')]
    public function testAnonymousNoRightNonRedirect(): void
    {
        $authService = self::createStub(AuthService::class);
        $request = new HttpRequest();
        $this->container->registerSingleton(Request::class, $request);
        $authService->method('getUser')->willReturn(new User(null));
        $middleware = new AuthMiddleware(
            $authService,
            $this->rightsChecker,
            $this->responseBuilder,
            ['main:read'],
            null,
        );
        /** @var Response $response */
        $response = $middleware->handle($request, static fn() => '');
        self::assertSame(ResponseStatus::UNAUTHORIZED, $response->status);
    }

    #[TestDox('Анонимный, нет права, редирект указан: должен выполнить редирект с 302')]
    public function testAnonymousNoRightRedirect(): void
    {
        $authService = self::createStub(AuthService::class);
        $request = new HttpRequest();
        $this->container->registerSingleton(Request::class, $request);
        $authService->method('getUser')->willReturn(new User(null));
        $middleware = new AuthMiddleware(
            $authService,
            $this->rightsChecker,
            $this->responseBuilder,
            ['main:read'],
            '/login',
        );
        /** @var Response $response */
        $response = $middleware->handle($request, static fn() => '');
        self::assertSame(ResponseStatus::FOUND, $response->status);
        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    #[TestDox('Анонимный, есть права: выполняется следующий обработчик')]
    public function testAnonymousHaveRight(): void
    {
        $request = new HttpRequest();
        $this->container->registerSingleton(Request::class, $request);
        $authService = self::createStub(AuthService::class);
        $authService->method('getUser')->willReturn(new User(null));
        $rightsChecker = self::createStub(RightsChecker::class);
        $rightsChecker->method('can')->willReturn(true);

        $middleware = new AuthMiddleware(
            $authService,
            $rightsChecker,
            $this->responseBuilder,
            ['main:read'],
            '/login',
        );
        $expected = new JsonResponse(new CookieCollection(new CookieConfig(), true));
        /** @var Response $response */
        $response = $middleware->handle($request, static fn() => $expected);
        self::assertSame($expected, $response);
    }

    #[TestDox('Авторизованный, нет права: должен вернуть 403')]
    public function testUserNoRightNonRedirect(): void
    {
        $authService = self::createStub(AuthService::class);
        $request = new HttpRequest();
        $this->container->registerSingleton(Request::class, $request);
        $authService->method('getUser')->willReturn(new User(1));
        $middleware = new AuthMiddleware(
            $authService,
            $this->rightsChecker,
            $this->responseBuilder,
            ['main:read'],
            null,
        );
        /** @var Response $response */
        $response = $middleware->handle($request, static fn() => '');
        self::assertSame(ResponseStatus::FORBIDDEN, $response->status);
    }

    #[TestDox('Авторизованный, есть права: выполняется следующий обработчик')]
    public function testUserHaveRight(): void
    {
        $request = new HttpRequest();
        $this->container->registerSingleton(Request::class, $request);
        $authService = self::createStub(AuthService::class);
        $authService->method('getUser')->willReturn(new User(1));
        $rightsChecker = self::createStub(RightsChecker::class);
        $rightsChecker->method('can')->willReturn(true);

        $middleware = new AuthMiddleware(
            $authService,
            $rightsChecker,
            $this->responseBuilder,
            ['main:read'],
            '/login',
        );
        $expected = new JsonResponse(new CookieCollection(new CookieConfig(), true));
        /** @var Response $response */
        $response = $middleware->handle($request, static fn() => $expected);
        self::assertSame($expected, $response);
    }
}
