<?php

declare(strict_types=1);

namespace Http\Response\Html\Asset;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Response\Html\Asset\AssetFileManager;
use Vasoft\Joke\Http\Response\Html\Asset\CssCollection;
use Vasoft\Joke\Http\Response\Html\Asset\ScriptCollection;
use Vasoft\Joke\Http\Response\Html\AttributeCollection;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\Html\Asset\AssetCollection
 */
#[RunTestsInSeparateProcesses]
final class AssetCollectionTest extends TestCase
{
    use PHPMock;

    private static string $projectPath = '';
    private static string $documentRoot = '';
    private static string $cssFile = '';
    private static string $jsFile = '';
    private static string $assetUri = 'assets';

    public static function setUpBeforeClass(): void
    {
        $name = 'AssetCollection' . random_int(1, 100);
        $base = sys_get_temp_dir() . '/joke_asset_collection_test_' . uniqid();
        self::$projectPath = $base . $name . \DIRECTORY_SEPARATOR;
        self::$documentRoot = self::$projectPath . 'www' . \DIRECTORY_SEPARATOR;
        mkdir(self::$documentRoot . self::$assetUri . \DIRECTORY_SEPARATOR, recursive: true);
        mkdir(self::$projectPath . 'modules' . \DIRECTORY_SEPARATOR, recursive: true);
        self::$cssFile = self::$projectPath . 'modules/outside.css';
        self::$jsFile = self::$projectPath . 'modules/inside.js';
        file_put_contents(self::$cssFile, 'b{color:blue;}');
        file_put_contents(self::$jsFile, "console.log('test');");
    }

    protected function setUp(): void
    {
        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'md5')
            ->expects(self::atLeastOnce())
            ->willReturn('path-hash');
        self::getFunctionMock('Vasoft\Joke\Http\Response\Html\Asset', 'filemtime')
            ->expects(self::atLeastOnce())
            ->willReturn(100);
    }

    public static function tearDownAfterClass(): void
    {
        self::cleanDir(self::$projectPath);
    }

    private static function cleanDir(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = scandir($dir);
        if (is_array($files)) {
            $items = array_diff($files, ['.', '..']);
            foreach ($items as $item) {
                $path = $dir . \DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    self::cleanDir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }

    #[TestDox('Добавление в заголовок страницы')]
    public function testAssetCollectionForHead(): void
    {
        $expect = <<<'HTML'
            <script src="/assets/pa/path-hash_inside.js?v=100"></script>
            <script src="/assets/pa/path-hash_inside.js?example=1&amp;v=100"></script>
            <script src="https://vik.devv/public/Some/Test/Strucure/script.js"></script>
            <script src="https://vik.devv/public/Some/Test/Strucure/script.js?example=1"></script>
            HTML;

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $collection = new ScriptCollection($manager, '/assets/', "\n");
        $collection->addToHead(self::$jsFile);
        $collection->addToHead(self::$jsFile . '?example=1');
        $collection->addToHead('https://vik.devv/public/Some/Test/Strucure/script.js');
        $collection->addToHead('https://vik.devv/public/Some/Test/Strucure/script.js?example=1');
        self::assertSame($expect, $collection->buildForHead());
        self::assertSame('', $collection->buildForBody());
    }

    #[TestDox('Подключаемые файлы размещаются и в теле и в заголовке согласно заданному размещению')]
    public function testAssetCollection(): void
    {
        $expectHead = <<<'HTML'
            <script src="/assets/pa/path-hash_inside.js?v=100"></script>
            <script src="https://vik.devv/public/Some/Test/Strucure/script.js"></script>
            HTML;
        $expectBody = <<<'HTML'
            <script src="/assets/pa/path-hash_inside.js?example=1&amp;v=100"></script>
            <script src="https://vik.devv/public/Some/Test/Strucure/script.js?example=1"></script>
            HTML;

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $collection = new ScriptCollection($manager, 'assets', "\n");
        $collection->addToHead(self::$jsFile);
        $collection->addToBody(self::$jsFile . '?example=1');
        $collection->addToHead('https://vik.devv/public/Some/Test/Strucure/script.js');
        $collection->addToBody('https://vik.devv/public/Some/Test/Strucure/script.js?example=1');
        self::assertSame($expectHead, $collection->buildForHead());
        self::assertSame($expectBody, $collection->buildForBody());
    }

    #[TestDox('Добавление в тело страницы')]
    public function testAssetCollectionForBody(): void
    {
        $expect
            = '<link rel="stylesheet" href="/assets/pa/path-hash_outside.css?v=100"/>'
            . '<link rel="stylesheet" href="/assets/pa/path-hash_outside.css?example=1&amp;v=100"/>'
            . '<link rel="stylesheet" href="https://vik.devv/public/Some/Test/Strucure/script.css"/>'
            . '<link rel="stylesheet" href="https://vik.devv/public/Some/Test/Strucure/script.css?example=1"/>';

        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $collection = new CssCollection($manager, '/assets/');
        $collection->addToBody(self::$cssFile);
        $collection->addToBody(self::$cssFile . '?example=1');
        $collection->addToBody('https://vik.devv/public/Some/Test/Strucure/script.css');
        $collection->addToBody('https://vik.devv/public/Some/Test/Strucure/script.css?example=1');
        self::assertSame($expect, $collection->buildForBody());
        self::assertSame('', $collection->buildForHead());
    }

    #[TestDox('Добавление в head имеет более высокий приоритет')]
    public function testAssetPriority(): void
    {
        $expectHead = <<<'HTML'
            <link rel="stylesheet" href="https://vik.devv/public/Some/Test/Strucure/style1.css"/>
            <link rel="stylesheet" href="/css/pa/path-hash_outside.css?v=100"/>
            HTML;
        $expectBody = '';
        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $collection = new CssCollection($manager, 'css', "\n");
        $collection->addToHead('https://vik.devv/public/Some/Test/Strucure/style1.css');
        $collection->addToBody('https://vik.devv/public/Some/Test/Strucure/style1.css');

        $collection->addToHead(self::$cssFile);
        $collection->addToBody(self::$cssFile);
        self::assertSame($expectHead, $collection->buildForHead());
        self::assertSame($expectBody, $collection->buildForBody());
    }

    #[TestDox('Подключаемый файл подключается единожды')]
    public function testAssetOnce(): void
    {
        $expectHead = '<link rel="stylesheet" href="/css/pa/path-hash_outside.css?v=100"/>';
        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $collection = new CssCollection($manager, 'css', "\n");
        $collection->addToHead(self::$cssFile);
        $collection->addToHead(self::$cssFile);
        self::assertSame($expectHead, $collection->buildForHead());
    }

    #[TestDox('Строки выводятся в соответствии с сортировкой')]
    public function testAssetOrder(): void
    {
        $expectBody = <<<'HTML'
            <script src="https://vik.devv/public/Some/Test/Strucure/test1.js"></script>
            <script src="/assets/pa/path-hash_inside.js?v=100"></script>
            <script src="https://vik.devv/public/Some/Test/Strucure/test3.js"></script>
            HTML;
        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $collection = new ScriptCollection($manager, self::$assetUri, "\n");
        $collection->addToBody(self::$jsFile);
        $collection->addToBody('https://vik.devv/public/Some/Test/Strucure/test3.js', order: 600);
        $collection->addToBody('https://vik.devv/public/Some/Test/Strucure/test1.js', order: 200);
        self::assertSame($expectBody, $collection->buildForBody());
    }

    #[RunInSeparateProcess]
    #[TestDox('Добавляются аттрибуты разного типа')]
    public function testAssetAttributes(): void
    {
        $expectBody = <<<'HTML'
            <script defer data-id="1" src="/assets/pa/path-hash_inside.js?v=100"></script>
            HTML;
        $manager = new AssetFileManager(self::$projectPath, self::$documentRoot);
        $collection = new ScriptCollection($manager, self::$assetUri, "\n");
        $collection->addToBody(self::$jsFile, attributes: new AttributeCollection(['defer' => true, 'data-id' => '1']));
        self::assertSame($expectBody, $collection->buildForBody());
    }
}
