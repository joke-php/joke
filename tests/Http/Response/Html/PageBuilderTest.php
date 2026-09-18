<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response\Html;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Response\Html\Asset\AssetFileManager;
use Vasoft\Joke\Http\Response\Html\AttributeCollection;
use Vasoft\Joke\Http\Response\Html\PageBuilder;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\Html\PageBuilder
 */
final class PageBuilderTest extends TestCase
{
    private static PageBuilderConfig $config;
    private static AssetFileManager $manager;

    public static function setUpBeforeClass(): void
    {
        self::$config = new PageBuilderConfig();
        self::$manager = new AssetFileManager(
            __DIR__,
            __DIR__ . '/public',
            '/assets/',
        );
    }

    public function testEmptyPage(): void
    {
        self::$config->setTagSeparator('');
        $builder = new PageBuilder(self::$config, self::$manager);
        self::assertSame('<html lang="ru"><head><meta charset="UTF-8"></head><body></body></html>', $builder->build());
    }

    public function testMeta(): void
    {
        self::$config->setTagSeparator('');
        $builder = new PageBuilder(self::$config, self::$manager);
        $builder->addMeta('test', 'v1');
        self::assertSame(
            '<html lang="ru"><head><meta charset="UTF-8"><meta name="test" content="v1"></head><body></body></html>',
            $builder->build(),
        );
    }

    public function testTitle(): void
    {
        self::$config->setTagSeparator('');
        $builder = new PageBuilder(self::$config, self::$manager);
        $builder->setTitle('test');
        self::assertSame(
            '<html lang="ru"><head><title>test</title><meta charset="UTF-8"></head><body></body></html>',
            $builder->build(),
        );
    }

    #[TestDox('Удаляет теги из заголовка')]
    public function testTitleStripTags(): void
    {
        self::$config->setTagSeparator('');
        $builder = new PageBuilder(self::$config, self::$manager);
        $builder->setTitle('test <small>1</small>');
        self::assertSame(
            '<html lang="ru"><head><title>test 1</title><meta charset="UTF-8"></head><body></body></html>',
            $builder->build(),
        );
    }

    public function testContent(): void
    {
        self::$config->setTagSeparator('');
        $builder = new PageBuilder(self::$config, self::$manager);
        $builder->setContent('<h1>test</h1><p>Hello</p>');
        self::assertSame(
            '<html lang="ru"><head><meta charset="UTF-8"></head><body><h1>test</h1><p>Hello</p></body></html>',
            $builder->build(),
        );
    }

    public function testCharset(): void
    {
        self::$config->setTagSeparator('');
        $builder = new PageBuilder(self::$config, self::$manager);
        $builder->setCharset('windows-1251');
        self::assertSame(
            '<html lang="ru"><head><meta charset="windows-1251"></head><body></body></html>',
            $builder->build(),
        );
    }

    public function testHtmlAttributes(): void
    {
        self::$config->setTagSeparator('');
        $builder = new PageBuilder(self::$config, self::$manager);
        $builder->htmlAttributes->set('lang', 'fr')->append('class', 'mobile');
        self::assertSame(
            '<html lang="fr" class="mobile"><head><meta charset="UTF-8"></head><body></body></html>',
            $builder->build(),
        );
    }

    public function testBodyAttributes(): void
    {
        self::$config->setTagSeparator('');
        $builder = new PageBuilder(self::$config, self::$manager);
        $builder->bodyAttributes->append('class', 'mobile');
        self::assertSame(
            '<html lang="ru"><head><meta charset="UTF-8"></head><body class="mobile"></body></html>',
            $builder->build(),
        );
    }
    #[TestDox('Добавляет js согласно указанному расположению без дублирования')]
    public function testScripts(): void
    {
        self::$config->setTagSeparator("\n");
        $builder = new PageBuilder(self::$config, self::$manager);
        $attributes = new AttributeCollection()->flag('integrity', true);
        $builder->js->addToBody('https://site.ru/s1.js', 50, $attributes);
        $builder->js->addToBody('https://site.ru/s2.js', 1);
        $builder->js->addToBody('https://site.ru/s3.js');
        $builder->js->addToBody('https://site.ru/s3.js');
        $builder->js->addToHead('https://site.ru/s3.js');
        $builder->js->addToHead('https://site.ru/s4.js');
        $builder->js->addToBody('https://site.ru/s4.js');
        self::assertSame(
            <<<'HTML'
                <html lang="ru">
                <head>
                <meta charset="UTF-8">
                <script src="https://site.ru/s3.js"></script>
                <script src="https://site.ru/s4.js"></script>
                </head>
                <body>
                <script src="https://site.ru/s2.js"></script>
                <script integrity src="https://site.ru/s1.js"></script>
                </body>
                </html>
                HTML,
            $builder->build(),
        );
    }
    #[TestDox('Добавляет css согласно указанному расположению без дублирования')]
    public function testCss(): void
    {
        self::$config->setTagSeparator("\n");
        $builder = new PageBuilder(self::$config, self::$manager);
        $attributes = new AttributeCollection()->set('media', 'screen and (max-width: 600px)');
        $builder->css->addToBody('https://site.ru/s1.css', 50, $attributes);
        $builder->css->addToBody('https://site.ru/s2.css', 1);
        $builder->css->addToBody('https://site.ru/s3.css');
        $builder->css->addToHead('https://site.ru/s3.css');
        $builder->css->addToHead('https://site.ru/s3.css');
        $builder->css->addToHead('https://site.ru/s4.css');
        $builder->css->addToBody('https://site.ru/s4.css');
        self::assertSame(
            <<<'HTML'
                <html lang="ru">
                <head>
                <meta charset="UTF-8">
                <link rel="stylesheet" href="https://site.ru/s3.css"/>
                <link rel="stylesheet" href="https://site.ru/s4.css"/>
                </head>
                <body>
                <link rel="stylesheet" href="https://site.ru/s2.css"/>
                <link media="screen and (max-width: 600px)" rel="stylesheet" href="https://site.ru/s1.css"/>
                </body>
                </html>
                HTML,
            $builder->build(),
        );
    }
    #[TestDox('Добавляет строки без дублирования')]
    public function testStringToHead(): void
    {
        self::$config->setTagSeparator("\n");
        $builder = new PageBuilder(self::$config, self::$manager);
        $builder->headString->add('<script>const test=1;</script>');
        $builder->headString->add('<script>const test=1;</script>');
        $builder->headString->add('<style>body{color:red}</style>');
        $builder->bottomString->add('<script>const test2=1;</script>');
        $builder->bottomString->add('<script>const test2=1;</script>');
        $builder->bottomString->add('<style>body{border:1px solid blue;}</style>');
        self::assertSame(
            <<<'HTML'
                <html lang="ru">
                <head>
                <meta charset="UTF-8">
                <script>const test=1;</script><style>body{color:red}</style>
                </head>
                <body>
                <script>const test2=1;</script><style>body{border:1px solid blue;}</style>
                </body>
                </html>
                HTML,
            $builder->build(),
        );
    }
}
