<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;
use Vasoft\Joke\Http\Response\HtmlPageResponse;
use Vasoft\Joke\Support\FileSystem;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\HtmlPageResponse
 */
final class HtmlPageResponseTest extends TestCase
{
    private static PageBuilderConfig $builderConfig;
    private static FileSystem $fs;
    private static CookieCollection $cookies;

    public static function setUpBeforeClass(): void
    {
        self::$cookies = new CookieCollection(new CookieConfig(), true);
        self::$builderConfig = new PageBuilderConfig()->setTagSeparator('');
        self::$fs = new FileSystem(dirname(__DIR__, 2));
    }

    #[TestDox('getBody возвращает только контент страницы')]
    public function testGetBody(): void
    {
        $response = new HtmlPageResponse(self::$cookies, self::$builderConfig, self::$fs);
        $response->setBody(
            '<html lang="ru"><head><meta charset="UTF-8"></head><body><h1>Hello World</h1></body></html>',
        );
        self::assertSame(
            '<h1>Hello World</h1>',
            $response->getBody(),
        );
    }

    #[TestDox('getPage возвращает полную страницу при передаче только контента')]
    public function testGetPage(): void
    {
        $response = new HtmlPageResponse(self::$cookies, self::$builderConfig, self::$fs);
        $response->setBody('<h1>Hello World</h1>');
        self::assertSame(
            '<html lang="ru"><head><meta charset="UTF-8"></head><body><h1>Hello World</h1></body></html>',
            $response->getPage(),
        );
    }

    #[TestDox('getBodyAsString возвращает полную страницу при передаче полной страницы')]
    public function testFullHtml(): void
    {
        $response = new HtmlPageResponse(self::$cookies, self::$builderConfig, self::$fs);
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
