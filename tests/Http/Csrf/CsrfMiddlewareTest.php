<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Csrf;

use phpmock\phpunit\PHPMock;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Csrf\CsrfTokenManager;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Http\Csrf\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Middleware\Exceptions\MiddlewareException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Csrf\CsrfMiddleware
 */
final class CsrfMiddlewareTest extends TestCase
{
    use PHPMock;

    private static ServiceContainer $container;

    public static function setUpBeforeClass(): void
    {
        self::$container = new ServiceContainer();
        self::$container->registerSingleton(CookieConfig::class, CookieConfig::class);
        self::$container->registerSingleton(CsrfTokenManager::class, CsrfTokenManager::class);
    }

    public function testRequiredTokenManager(): void
    {
        self::expectException(MiddlewareException::class);
        self::expectExceptionMessageIs('CsrfMiddleware requires a valid csrf token manager.');
        new CsrfMiddleware(new ResponseBuilder(new ApplicationConfig(), self::$container));
    }

    public function testHandle(): void
    {
        $log = [];
        /** @var CsrfTokenManager $manager */
        $manager = self::getMockBuilder(CsrfTokenManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects(self::once())->method('validate')
            ->willReturnCallback(static function (HttpRequest $request) use (&$log): string {
                $log[] = 'validate';

                return 'token';
            });
        $manager->expects(self::once())->method('attach')
            ->willReturnCallback(static function (HttpRequest $request, Response $response) use (&$log): void {
                $log[] = 'attach';
            });


        $request = new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/csrf']);
        $middleware = new CsrfMiddleware(
            new ResponseBuilder(new ApplicationConfig(), self::$container),
            manager: $manager,
        );
        $middleware->handle($request, static function () use (&$log): array {
            $log[] = 'route handler';

            return $log;
        });
        self::assertSame(['validate', 'route handler', 'attach'], $log);
    }
}
