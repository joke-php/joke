<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Cors;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Http\Cors\CorsConfig;
use Vasoft\Joke\Http\HttpMethod;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Cors\CorsConfig
 */
final class CorsConfigTest extends TestCase
{
    #[DataProvider('provideFrozenCases')]
    public function testFrozen(string $setter, mixed $value): void
    {
        $config = new CorsConfig();
        $config->freeze();
        self::expectException(ConfigException::class);
        $config->{$setter}($value);
    }

    public static function provideFrozenCases(): iterable
    {
        yield ['setOrigins', []];
        yield ['setMethods', []];
        yield ['setHeaders', []];
        yield ['setExposeHeaders', []];
        yield ['setMaxAge', 0];
        yield ['setAllowCredentials', true];
        yield ['setAllowedCors', true];
    }

    #[DataProvider('provideHeadersAsStringCases')]
    public function testHeadersAsString(string $setter, string $getter): void
    {
        $config = new CorsConfig();
        $config->{$setter}(['Header-First', 'Header-Second']);
        self::assertSame('Header-First,Header-Second', $config->{$getter}());
    }

    public static function provideHeadersAsStringCases(): iterable
    {
        yield ['setHeaders', 'getHeadersAsString'];
        yield ['setExposeHeaders', 'getExposeHeadersAsString'];
    }

    public function testMethodsAsString(): void
    {
        $config = new CorsConfig();
        $config->setMethods([HttpMethod::POST, HttpMethod::PUT, HttpMethod::DELETE]);
        self::assertSame('POST,PUT,DELETE,OPTIONS', $config->getMethodsAsString());
    }

    public function testWrongCombinationsNotFrozen(): void
    {
        $config = new CorsConfig()
            ->setAllowCredentials(true)
            ->setAllowedCors(true)
            ->setOrigins(['*']);

        self::assertSame(['*'], $config->origins);
        self::assertTrue($config->allowCredentials);
    }

    #[DataProvider('provideWrongCombinationsFrozenCases')]
    public function testWrongCombinationsFrozen(string $name): void
    {
        $config = new CorsConfig()
            ->setAllowCredentials(true)
            ->setAllowedCors(true)
            ->setOrigins(['*']);
        $config->freeze();
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs(
            'Cannot use wildcard origin when allowCredentials is enabled. Please specify explicit domains.',
        );
        $test = $config->{$name};
    }

    public static function provideWrongCombinationsFrozenCases(): iterable
    {
        yield ['origins'];
        yield ['allowCredentials'];
    }

    #[DataProvider('provideSetAndGetCases')]
    public function testSetAndGet(string $setName, string $getName, mixed $value, mixed $expected): void
    {
        $config = new CorsConfig();
        $config->{$setName}($value);
        self::assertSame($expected, $config->{$getName});
    }

    public static function provideSetAndGetCases(): iterable
    {
        yield ['setAllowedCors', 'allowedCors', true, true];
        yield ['setAllowCredentials', 'allowCredentials', true, true];
        yield [
            'setOrigins',
            'origins',
            ['https://test.ru', 'http://127.0.0.1:8000'],
            ['https://test.ru', 'http://127.0.0.1:8000'],
        ];
        yield [
            'setMethods',
            'methods',
            [HttpMethod::POST, HttpMethod::GET],
            [HttpMethod::POST, HttpMethod::GET, HttpMethod::OPTIONS],
        ];
        yield ['setHeaders', 'headers', ['Content-Type', 'My-Header'], ['Content-Type', 'My-Header']];
        yield ['setExposeHeaders', 'exposeHeaders', ['Content-Type', 'My-Header'], ['Content-Type', 'My-Header']];
        yield ['setMaxAge', 'maxAge', 10, 10];
    }
}
