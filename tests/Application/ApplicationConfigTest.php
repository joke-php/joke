<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Application\ApplicationConfig;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Http\Response\HtmlPageResponse;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\JsonResponse;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Application\ApplicationConfig
 */
final class ApplicationConfigTest extends TestCase
{
    #[DataProvider('provideFrozenCases')]
    public function testFrozen(string $setter, mixed $value): void
    {
        $config = new ApplicationConfig();
        $config->freeze();
        self::expectException(ConfigException::class);
        $config->{$setter}($value);
    }

    public static function provideFrozenCases(): iterable
    {
        yield ['setFileRoues', 'file.php'];
        yield ['setResponseClass', null];
    }

    #[DataProvider('provideSetAndGetCases')]
    public function testSetAndGet(string $setName, string $getName, mixed $value): void
    {
        $config = new ApplicationConfig();
        $config->{$setName}($value);
        self::assertSame($value, $config->{$getName}());
    }

    public static function provideSetAndGetCases(): iterable
    {
        yield ['setFileRoues', 'getFileRoues', 'file.php'];
        yield ['setResponseClass', 'getResponseClass', HtmlResponse::class];
    }

    #[TestDox('Хранит заданные типы ответов по умолчанию по имени группы')]
    public function testSetGroupResponseClass(): void
    {
        $config = new ApplicationConfig();
        $config
            ->setGroupResponseClass('web', HtmlPageResponse::class)
            ->setGroupResponseClass('api', JsonResponse::class);
        self::assertSame(HtmlPageResponse::class, $config->getGroupResponseClass('web'));
        self::assertSame(JsonResponse::class, $config->getGroupResponseClass('api'));
        self::assertNull($config->getGroupResponseClass('unknown'));
    }

    #[TestDox('Пустую строку преобразует в null')]
    public function testSetGroupResponseClassEmptyStringAsNull(): void
    {
        $config = new ApplicationConfig();
        $config->setGroupResponseClass('api', ' ');
        self::assertNull($config->getGroupResponseClass('api'));
    }

    #[TestDox('setGroupResponseClass бросает исключение если конфиг заморожен')]
    public function testSetGroupResponseClassIfFrozen(): void
    {
        $config = new ApplicationConfig();
        $config->freeze();
        self::expectException(ConfigException::class);
        $config->setGroupResponseClass('api', ' ');
    }
}
