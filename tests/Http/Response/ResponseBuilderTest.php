<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;
use Vasoft\Joke\Http\Response\HtmlPageResponse;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\JsonResponse;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Support\FileSystem;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\ResponseBuilder
 */
final class ResponseBuilderTest extends TestCase
{
    private static ServiceContainer $container;
    private static CookieCollection $cookies;

    public static function setUpBeforeClass(): void
    {
        self::$container = new ServiceContainer();
        self::$container->registerSingleton(CookieConfig::class, CookieConfig::class);
        self::$container->registerSingleton(Request::class, new HttpRequest());
        self::$container->registerSingleton(PageBuilderConfig::class, PageBuilderConfig::class);
        $basePath = sys_get_temp_dir() . '/joke_test' . bin2hex(random_bytes(8));
        mkdir($basePath);
        self::$container->registerSingleton(FileSystem::class, new FileSystem($basePath));
        self::$cookies = new CookieCollection(new CookieConfig(), true);
    }

    #[DataProvider('provideTypesCases')]
    public function testTypes(mixed $response, mixed $defaultResponse, mixed $expectedResponse): void
    {
        if (is_string($response) && str_starts_with($response, 'Vasoft\Joke\\')) {
            $response = new $response(self::$cookies);
        }
        $builder = new ResponseBuilder(new ApplicationConfig()->setResponseClass($defaultResponse), self::$container);
        $response = $builder->make($response);
        self::assertInstanceOf($expectedResponse, $response);
    }

    public static function provideTypesCases(): iterable
    {
        yield ['Test', '', HtmlResponse::class];
        yield [['Test' => 'test'], '', JsonResponse::class];
        yield [HtmlResponse::class, '', HtmlResponse::class];
        yield [JsonResponse::class, '', JsonResponse::class];
        yield [HtmlResponse::class, JsonResponse::class, HtmlResponse::class];
        yield [JsonResponse::class, HtmlResponse::class, JsonResponse::class];
        yield ['test', HtmlResponse::class, HtmlResponse::class];
        yield [['Test' => 'test'], JsonResponse::class, JsonResponse::class];
    }

    public function testApplicationDefault(): void
    {
        $config = new ApplicationConfig()->setResponseClass(JsonResponse::class);
        $builder = new ResponseBuilder($config, self::$container);
        self::expectExceptionMessageIs(
            'Cannot assign string to property Vasoft\Joke\Http\Response\JsonResponse::$body of type array',
        );
        $builder->make('Test');
    }

    #[TestDox('Текущий тип ответа имеет самый высокий приоритет')]
    public function testCurrentResponseClassOverrideApplicationLevel(): void
    {
        $config = new ApplicationConfig()->setResponseClass(JsonResponse::class);
        $builder = new ResponseBuilder($config, self::$container);
        $builder->setCurrentResponseClass(HtmlResponse::class);
        self::assertInstanceOf(HtmlResponse::class, $builder->make('Test'));
    }

    #[TestDox('Пустые строки в качестве класса по умолчанию приводят к автоопределению')]
    public function testEmptyStringEqualAutoDetermineClass(): void
    {
        $config = new ApplicationConfig()->setResponseClass(' ');
        $builder = new ResponseBuilder($config, self::$container);
        $builder->setCurrentResponseClass(' ');
        self::assertInstanceOf(HtmlPageResponse::class, $builder->make('Test'));
        self::assertInstanceOf(JsonResponse::class, $builder->make(['Test' => 'test']));
    }
}
