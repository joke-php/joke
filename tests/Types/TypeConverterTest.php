<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Types;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Support\Types\TypeConverter;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Support\Types\TypeConverter
 */
final class TypeConverterTest extends TestCase
{
    public function testToArray(): void
    {
        self::assertSame(['default'], TypeConverter::toArray('', 'example', ['default']));
        self::assertSame([], TypeConverter::toArray(null, 'example'));
        self::assertSame(['values', 'keys'], TypeConverter::toArray(['values', 'keys'], 'example'));
        self::assertSame(['values'], TypeConverter::toArray('values', 'example'));
        self::assertSame(['values', 'keys'], TypeConverter::toArray('values,keys', 'example'));
        self::assertSame([123], TypeConverter::toArray(123, 'example'));
        self::assertSame([-14.4], TypeConverter::toArray(-14.4, 'example', separator: '|'));
    }

    public function testToArrayException(): void
    {
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('Property "example" cannot be converted to array, got stdClass.');
        TypeConverter::toArray(new \stdClass(), 'example');
    }

    public function testToArrayExceptionCustom(): void
    {
        $object = new \stdClass();
        ob_start();
        echo 'test ';
        var_dump($object);
        $expectedMessage = ob_get_clean();

        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs($expectedMessage);
        TypeConverter::toArray($object, 'test', exceptionFactory: static function (
            $key,
            $value,
        ) {
            ob_start();
            echo $key, ' ';
            var_dump($value);
            $message = ob_get_clean();

            return new ConfigException($message);
        });
    }

    public function testToInt(): void
    {
        self::assertSame(1, TypeConverter::toInt('', 'example', 1));
        self::assertSame(2, TypeConverter::toInt(null, 'example', 2));
        self::assertSame(3, TypeConverter::toInt(3, 'example', 100));
        self::assertSame(-3, TypeConverter::toInt(-3, 'example', 100));
        self::assertSame(4, TypeConverter::toInt(' 4 ', 'example', 1));
        self::assertSame(-4, TypeConverter::toInt(' -4 ', 'example', 1));
        self::assertSame(1, TypeConverter::toInt(true, 'example', 199));
        self::assertSame(0, TypeConverter::toInt(false, 'example', 199));
    }

    /**
     * @throws ConfigException
     */
    #[DataProvider('provideToIntExceptionCases')]
    public function testToIntException(mixed $value, string $type): void
    {
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs("Property \"test\" cannot be converted to int, got {$type}.");
        TypeConverter::toInt($value, 'test', 1);
    }

    public static function provideToIntExceptionCases(): iterable
    {
        return [
            'object' => [new \stdClass(), 'stdClass'],
            'float' => [123.13444, 'float'],
            'string' => ['Hello world', 'string'],
            'array' => [[1, 2, 3], 'array'],
        ];
    }

    public function testToIntExceptionCustom(): void
    {
        $value = 123.125;

        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs(sprintf('test: %0.2f', $value));
        TypeConverter::toInt($value, 'test', 1, exceptionFactory: static fn(
            $key,
            $value,
        ) => new ConfigException(sprintf('%s: %0.2f', $key, $value)));
    }

    public function testToString(): void
    {
        self::assertSame('empty', TypeConverter::toString(null, 'str', 'empty'));
        self::assertSame('', TypeConverter::toString('', 'str', 'empty'));
        self::assertSame('-1', TypeConverter::toString('-1', 'str', 'empty'));
        self::assertSame('Joke', TypeConverter::toString('Joke', 'str', 'empty'));
        self::assertSame('-3', TypeConverter::toString(-3, 'str', 'def'));
        self::assertSame('3.442', TypeConverter::toString(3.442, 'str', 'def'));
        self::assertSame('1', TypeConverter::toString(true, 'str', 'def'));
        self::assertSame('0', TypeConverter::toString(false, 'str', 'def'));
    }

    /**
     * @throws ConfigException
     */
    #[DataProvider('provideToStringExceptionCases')]
    public function testToStringException(mixed $value, string $type): void
    {
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs("Property \"my\" cannot be converted to string, got {$type}.");
        TypeConverter::toString($value, 'my', 'def');
    }

    public static function provideToStringExceptionCases(): iterable
    {
        return [
            'object' => [new \stdClass(), 'stdClass'],
            'array' => [['Hello', 1], 'array'],
        ];
    }

    public function testToStringExceptionCustom(): void
    {
        $value = [4, 5, 6];

        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('str: 4,5,6');
        TypeConverter::toString($value, 'str', 'def', exceptionFactory: static fn(
            $key,
            $value,
        ) => new ConfigException(sprintf('%s: %s', $key, implode(',', $value))));
    }

    public function testToBool(): void
    {
        self::assertTrue(TypeConverter::toBool(null, 'str', true));
        self::assertTrue(TypeConverter::toBool('', 'str', true));
        self::assertTrue(TypeConverter::toBool(true, 'str', false));
        self::assertFalse(TypeConverter::toBool(false, 'str', true));
        self::assertFalse(TypeConverter::toBool(0, 'str', true));
        self::assertTrue(TypeConverter::toBool(-10, 'str', false));
        self::assertTrue(TypeConverter::toBool(10, 'str', false));
    }

    #[DataProvider('provideToBoolFromStringCases')]
    public function testToBoolFromString(string $value, bool $expected): void
    {
        self::assertSame($expected, TypeConverter::toBool($value, 'str', !$expected));
    }

    public static function provideToBoolFromStringCases(): iterable
    {
        return [
            ['1', true],
            ['true', true],
            ['yes', true],
            ['on', true],
            ['y', true],
            ['0', false],
            ['false', false],
            ['no', false],
            ['off', false],
            ['n', false],
        ];
    }

    #[DataProvider('provideToBoolExceptionCases')]
    public function testToBoolException(mixed $value, string $type): void
    {
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs("Property \"my\" cannot be converted to bool, got {$type}.");
        TypeConverter::toBool($value, 'my', true);
    }

    public static function provideToBoolExceptionCases(): iterable
    {
        return [
            'object' => [new \stdClass(), 'stdClass'],
            'array' => [['Hello', 1], 'array'],
            'string' => ['Joke', 'string'],
            'float' => [10.2, 'float'],
        ];
    }

    public function testToBoolExceptionCustom(): void
    {
        $value = [4, 5, 6];

        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('str: 4,5,6');
        TypeConverter::toBool($value, 'str', false, exceptionFactory: static fn(
            $key,
            $value,
        ) => new ConfigException(sprintf('%s: %s', $key, implode(',', $value))));
    }

    public function testToBoolStringExceptionCustom(): void
    {
        $value = 'example';

        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('str: example');
        TypeConverter::toBool($value, 'str', false, exceptionFactory: static fn(
            $key,
            $value,
        ) => new ConfigException(sprintf('%s: %s', $key, $value)));
    }

    public function testToFloat(): void
    {
        self::assertEqualsWithDelta(3.1, TypeConverter::toFloat('', 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(3.1, TypeConverter::toFloat(null, 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(5.2, TypeConverter::toFloat(5.2, 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(-7.1, TypeConverter::toFloat(-7.1, 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(8.0, TypeConverter::toFloat(8, 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(4.0, TypeConverter::toFloat(' 4 ', 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(-4.0, TypeConverter::toFloat(' -4 ', 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(9.1, TypeConverter::toFloat('9.1', 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(100.0, TypeConverter::toFloat('1e2', 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(1.0, TypeConverter::toFloat(true, 'example', 3.1), 0.0001);
        self::assertEqualsWithDelta(0.0, TypeConverter::toFloat(false, 'example', 3.1), 0.0001);
    }

    /**
     * @throws ConfigException
     */
    #[DataProvider('provideToFloatExceptionCases')]
    public function testToFloatException(mixed $value, string $type): void
    {
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs("Property \"test\" cannot be converted to float, got {$type}.");
        TypeConverter::toFloat($value, 'test', 1);
    }

    public static function provideToFloatExceptionCases(): iterable
    {
        return [
            'object' => [new \stdClass(), 'stdClass'],
            'string' => ['Hello world', 'string'],
            'array' => [[1, 2, 3], 'array'],
        ];
    }

    public function testToFloatExceptionCustom(): void
    {
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('test: apple');
        TypeConverter::toFloat('apple', 'test', 1, exceptionFactory: static fn(
            $key,
            $value,
        ) => new ConfigException(sprintf('%s: %s', $key, $value)));
    }
}
