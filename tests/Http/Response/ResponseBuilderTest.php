<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use PHPUnit\Framework\Attributes\DataProvider;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\JsonResponse;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\ResponseBuilder
 */
final class ResponseBuilderTest extends TestCase
{
    private static ServiceContainer $container;

    public static function setUpBeforeClass(): void
    {
        self::$container = new ServiceContainer();
        self::$container->registerSingleton(CookieConfig::class, CookieConfig::class);
    }

    #[DataProvider('provideTypesCases')]
    public function testTypes(mixed $response, mixed $defaultResponse, mixed $expectedResponse): void
    {
        $builder = new ResponseBuilder(new ApplicationConfig(), self::$container)
            ->setDefaultResponseClass($defaultResponse);
        $response = $builder->make($response);
        self::assertInstanceOf($expectedResponse, $response);
    }

    public static function provideTypesCases(): iterable
    {
        yield ['Test', '', HtmlResponse::class];
        yield [['Test' => 'test'], '', JsonResponse::class];
        yield [new HtmlResponse(), '', HtmlResponse::class];
        yield [new JsonResponse(), '', JsonResponse::class];
        yield [new HtmlResponse(), JsonResponse::class, HtmlResponse::class];
        yield [new JsonResponse(), HtmlResponse::class, JsonResponse::class];
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
}
