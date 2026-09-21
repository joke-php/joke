<?php

declare(strict_types=1);

namespace Vasoft\Joke\Application;

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Config\Exceptions\UnknownConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Provider\ConfigurableServiceProviderInterface;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Cors\CorsConfig;
use Vasoft\Joke\Http\Cors\CorsMiddleware;
use Vasoft\Joke\Http\Csrf\CsrfConfig;
use Vasoft\Joke\Http\Csrf\CsrfTokenManager;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Http\Csrf\CsrfMiddleware;
use Vasoft\Joke\Middleware\ExceptionMiddleware;
use Vasoft\Joke\Middleware\MiddlewareCollection;
use Vasoft\Joke\Http\Middleware\SessionMiddleware;
use Vasoft\Joke\Middleware\StdMiddleware;
use Vasoft\Joke\Provider\AbstractProvider;
use Vasoft\Joke\Routing\StdGroup;

class KernelServiceProvider extends AbstractProvider implements ConfigurableServiceProviderInterface
{
    public function __construct(
        private readonly ServiceContainer $serviceContainer,
    ) {}

    public function register(): void
    {
        $this->serviceContainer->registerSingleton('middleware.global', MiddlewareCollection::class);
        $this->serviceContainer->registerSingleton('middleware.route', MiddlewareCollection::class);
        $this->serviceContainer->registerSingleton(ResponseBuilder::class, ResponseBuilder::class);
        $this->serviceContainer->registerSingleton(CsrfTokenManager::class, CsrfTokenManager::class);
    }

    public function boot(): void
    {
        /** @var MiddlewareCollection $middlewares */
        $middlewares = $this->serviceContainer->get('middleware.global');
        $middlewares
            ->addMiddleware(ExceptionMiddleware::class, StdMiddleware::EXCEPTION->value)
            ->addMiddleware(CorsMiddleware::class, StdMiddleware::CORS->value);
        /** @var MiddlewareCollection $middlewares */
        $routeMiddlewares = $this->serviceContainer->get('middleware.route');
        $routeMiddlewares
            ->addMiddleware(SessionMiddleware::class, StdMiddleware::SESSION->value)
            ->addMiddleware(CsrfMiddleware::class, StdMiddleware::CSRF->value, [StdGroup::WEB->value]);
    }

    public function provides(): array
    {
        return [
            'middleware.global',
            'middleware.route',
            ResponseBuilder::class,
        ];
    }

    public static function provideConfigs(): array
    {
        return [
            ApplicationConfig::class,
            CookieConfig::class,
            CsrfConfig::class,
            CorsConfig::class,
            PageBuilderConfig::class,
            AuthConfig::class,
        ];
    }

    public static function buildConfig(string $configClass, ServiceContainer $container): AbstractConfig
    {
        return match ($configClass) {
            ApplicationConfig::class => new ApplicationConfig(),
            CookieConfig::class => new CookieConfig(),
            CsrfConfig::class => new CsrfConfig(),
            CorsConfig::class => new CorsConfig(),
            PageBuilderConfig::class => new PageBuilderConfig(),
            AuthConfig::class => new AuthConfig(),
            default => throw new UnknownConfigException($configClass),
        };
    }
}
