<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Application\Application;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;
use Vasoft\Joke\Http\Response\HtmlPageResponse;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\HtmlPageResponse
 */
final class HtmlPageResponseTest extends TestCase
{
    private static ServiceContainer $container;

    public static function setUpBeforeClass(): void
    {
        self::$container = new ServiceContainer();
        $builderConfig = new PageBuilderConfig()->setTagSeparator('');
        self::$container->registerSingleton(PageBuilderConfig::class, $builderConfig);
        self::$container->registerSingleton(CookieConfig::class, CookieConfig::class);
        new Application(dirname(__DIR__, 2), self::$container);
    }

    public function testOnlyBody(): void
    {
        $response = new HtmlPageResponse(self::$container);
        $response->setBody('<h1>Hello World</h1>');
        self::assertSame(
            '<html lang="ru"><head><meta charset="UTF-8"></head><body><h1>Hello World</h1></body></html>',
            $response->getBody(),
        );
    }

    public function testFullHtml(): void
    {
        $response = new HtmlPageResponse(self::$container);
        $response->setBody(
            <<<'HTML'
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                    <meta charset="windows-1251">
                    <title>Title</title>
                    </head>
                    <body><h1>Hello World</h1></body></html>
                HTML,
        );
        self::assertSame(
            '<html lang="en"><head><title>Title</title><meta charset="windows-1251"></head><body><h1>Hello World</h1></body></html>',
            $response->getBodyAsString(),
        );
    }
}
