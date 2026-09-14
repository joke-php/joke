<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Collections;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Collections\PropsCollection;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Exceptions\Property\EmptyPropertyException;
use Vasoft\Joke\Exceptions\Property\MissingPropertyException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Collections\ReadonlyPropsCollection
 */
final class ReadonlyPropsCollectionTest extends TestCase
{
    private static array $data = [
        'string' => 'string',
        'int' => 1,
        'float' => 1.1,
        'bool' => true,
        'array' => [1, 2, 3],
    ];
    private static ?PropsCollection $collection = null;

    public static function setUpBeforeClass(): void
    {
        self::$collection = new PropsCollection(self::$data);
        parent::setUpBeforeClass();
    }

    public function testDefaultValue(): void
    {
        self::assertNull(self::$collection->get('notExists'));
        self::assertSame('default Value', self::$collection->get('notExists', 'default Value'));
    }

    public function testGetTypes(): void
    {
        self::assertSame(self::$data['string'], self::$collection->get('string'));
        self::assertSame(self::$data['int'], self::$collection->get('int'));
        self::assertSame(self::$data['float'], self::$collection->get('float'));
        self::assertSame(self::$data['bool'], self::$collection->get('bool'));
        self::assertSame(self::$data['array'], self::$collection->get('array'));
    }

    public function testGetAll(): void
    {
        self::assertSame(self::$data, self::$collection->getAll());
    }

    public function testHas(): void
    {
        self::assertTrue(self::$collection->has('string'));
        self::assertFalse(self::$collection->has('integer'));
    }

    public function testGetOrFailDefault(): void
    {
        self::expectException(MissingPropertyException::class);
        self::expectExceptionMessageIs('Property "unknown" does not exist.');
        self::$collection->getOrFail('unknown');
    }

    public function testGetOrFailSuccess(): void
    {
        self::assertSame(self::$data['string'], self::$collection->getOrFail('string'));
        self::assertSame(self::$data['int'], self::$collection->getOrFail('int'));
        self::assertSame(self::$data['float'], self::$collection->getOrFail('float'));
        self::assertSame(self::$data['bool'], self::$collection->getOrFail('bool'));
        self::assertSame(self::$data['array'], self::$collection->getOrFail('array'));
    }

    public function testGetArray(): void
    {
        $collection = new PropsCollection([
            'empty' => '',
            'null' => null,
            'array' => ['values', 'keys'],
            'singleString' => 'test1',
            'separated' => 'values|keys',
            'commaSeparated' => 'test1 , test2',
            'integer' => 123,
            'float' => 12.2,
            'bool' => true,
        ]);
        $default = ['def'];
        self::assertSame($default, $collection->getArray('empty', $default));
        self::assertSame($default, $collection->getArray('null', $default));
        self::assertSame($default, $collection->getArray('notExists', $default));
        self::assertSame(['values', 'keys'], $collection->getArray('array'));
        self::assertSame(['test1'], $collection->getArray('singleString'));
        self::assertSame(['test1', 'test2'], $collection->getArray('commaSeparated'));
        self::assertSame(['values', 'keys'], $collection->getArray('separated', separator: '|'));
        self::assertSame([123], $collection->getArray('integer'));
        self::assertSame([12.2], $collection->getArray('float'));
        self::assertSame([true], $collection->getArray('bool'));
    }

    public function testGetInt(): void
    {
        $collection = new PropsCollection([
            'empty' => '',
            'null' => null,
            'int' => 1,
            'intNegative' => '-1',
            'float' => 12.0,
            'boolTrue' => true,
            'boolFalse' => false,
        ]);
        self::assertSame(1, $collection->getInt('empty', 1));
        self::assertSame(2, $collection->getInt('null', 2));
        self::assertSame(1, $collection->getInt('int', 100));
        self::assertSame(100, $collection->getInt('unknown', 100));
        self::assertSame(-1, $collection->getInt('intNegative', 100));
        self::assertSame(12, $collection->getInt('float', 1));
        self::assertSame(1, $collection->getInt('boolTrue', 199));
        self::assertSame(0, $collection->getInt('boolFalse', 199));
    }

    #[DataProvider('provideGetIntExceptionCases')]
    public function testGetIntException(mixed $value, string $type): void
    {
        $collection = new PropsCollection(['value' => $value]);
        self::expectException(ConversionException::class);
        self::expectExceptionMessageIs("Property \"value\" cannot be converted to int, got {$type}.");
        $collection->getInt('value', 1);
    }

    public static function provideGetIntExceptionCases(): iterable
    {
        return [
            'float' => [123.13444, 'float'],
            'string' => ['Hello world', 'string'],
            'array' => [[1, 2, 3], 'array'],
        ];
    }

    public function testGetString(): void
    {
        $collection = new PropsCollection([
            'empty' => '',
            'null' => null,
            'int' => -1,
            'str' => 'Joke',
            'float' => 12.23,
            'boolTrue' => true,
            'boolFalse' => false,
        ]);

        self::assertSame('def', $collection->getString('null', 'def'));
        self::assertSame('', $collection->getString('empty', 'def'));
        self::assertSame('-1', $collection->getString('int', 'def'));
        self::assertSame('Joke', $collection->getString('str', 'def'));
        self::assertSame('12.23', $collection->GetString('float', 'def'));
        self::assertSame('1', $collection->getString('boolTrue', 'def'));
        self::assertSame('0', $collection->getString('boolFalse', 'def'));
    }

    public function testGetStringException(): void
    {
        $collection = new PropsCollection([
            'value' => [['Hello', 1], 'array'],
        ]);

        self::expectException(ConversionException::class);
        self::expectExceptionMessageIs('Property "value" cannot be converted to string, got array.');
        $collection->getString('value', 'def');
    }

    public function testGetBool(): void
    {
        $collection = new PropsCollection([
            'empty' => '',
            'null' => null,
            'negative' => -11,
            'positive' => 10,
            'zero' => 0,
            'boolTrue' => true,
            'boolFalse' => false,
        ]);
        self::assertTrue($collection->getBool('empty', true));
        self::assertTrue($collection->getBool('null', true));
        self::assertFalse($collection->getBool('empty', false));
        self::assertFalse($collection->getBool('null', false));
        self::assertTrue($collection->getBool('boolTrue', false));
        self::assertFalse($collection->getBool('boolFalse', true));
        self::assertFalse($collection->getBool('zero', true));
        self::assertTrue($collection->getBool('positive', false));
        self::assertTrue($collection->getBool('negative', false));
    }

    #[DataProvider('provideGetBoolFromStringCases')]
    public function testGetBoolFromString(string $value, bool $expected): void
    {
        $collection = new PropsCollection(['value' => $value]);
        self::assertSame($expected, $collection->getBool('value', !$expected));
    }

    public static function provideGetBoolFromStringCases(): iterable
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

    #[DataProvider('provideGetBoolExceptionCases')]
    public function testGetBoolException(mixed $value, string $type): void
    {
        $collection = new PropsCollection(['value' => $value]);
        self::expectException(ConversionException::class);
        self::expectExceptionMessageIs("Property \"value\" cannot be converted to bool, got {$type}.");
        $collection->getBool('value', true);
    }

    public static function provideGetBoolExceptionCases(): iterable
    {
        return [
            'array' => [['Hello', 1], 'array'],
            'string' => ['Joke', 'string'],
            'float' => [10.2, 'float'],
        ];
    }

    public function testGetFloat(): void
    {
        $collection = new PropsCollection([
            'empty' => '',
            'null' => null,
            'float' => -12.1,
            'int' => 11,
            'intString' => ' -11 ',
            'floatString' => ' 11.32 ',
            'floatEString' => '1E2',
            'positive' => 10,
            'zero' => 0,
            'boolTrue' => true,
            'boolFalse' => false,
        ]);
        self::assertEqualsWithDelta(3.1, $collection->getFloat('empty', 3.1), 0.0001);
        self::assertEqualsWithDelta(3.1, $collection->getFloat('null', 3.1), 0.0001);
        self::assertEqualsWithDelta(-12.1, $collection->getFloat('float', 3.1), 0.0001);
        self::assertEqualsWithDelta(11.0, $collection->getFloat('int', 3.1), 0.0001);
        self::assertEqualsWithDelta(-11, $collection->getFloat('intString', 3.1), 0.0001);
        self::assertEqualsWithDelta(11.32, $collection->getFloat('floatString', 3.1), 0.0001);
        self::assertEqualsWithDelta(100.0, $collection->getFloat('floatEString', 3.1), 0.0001);
        self::assertEqualsWithDelta(1.0, $collection->getFloat('boolTrue', 3.1), 0.0001);
        self::assertEqualsWithDelta(0.0, $collection->getFloat('boolFalse', 3.1), 0.0001);
    }

    #[DataProvider('provideGetFloatExceptionCases')]
    public function testGetFloatException(mixed $value, string $type): void
    {
        $collection = new PropsCollection(['test' => $value]);
        self::expectException(ConversionException::class);
        self::expectExceptionMessageIs("Property \"test\" cannot be converted to float, got {$type}.");
        $collection->getFloat('test', 1);
    }

    public static function provideGetFloatExceptionCases(): iterable
    {
        return [
            'string' => ['Hello world', 'string'],
            'array' => [[1, 2, 3], 'array'],
        ];
    }

    #[DataProvider('provideGetTypedOrFailSuccessCases')]
    #[TestDox('get*OrFail должно возвращать приведенное к заданному типу значение')]
    public function testGetTypedOrFailSuccess(string $function, mixed $value, mixed $expected): void
    {
        $collection = new PropsCollection(['property' => $value]);

        self::assertSame($expected, $collection->{$function}('property'));
    }

    public static function provideGetTypedOrFailSuccessCases(): iterable
    {
        yield 'array' => ['getArrayOrFail', 'example,apple', ['example', 'apple']];
        yield 'int' => ['getIntOrFail', '123', 123];
        yield 'string' => ['getStringOrFail', 123, '123'];
        yield 'bool' => ['getBoolOrFail', '0', false];
        yield 'float' => ['getFloatOrFail', '43.1', 43.1];
    }

    #[DataProvider('provideGetTypedOrFailExceptionOnEmptyCases')]
    #[TestDox('get*OrFail должно бросать исключение если пустое значение')]
    public function testGetTypedOrFailExceptionOnEmpty(string $function, mixed $value): void
    {
        $collection = new PropsCollection(['property' => $value]);
        self::expectException(EmptyPropertyException::class);
        self::expectExceptionMessageIs('Property "property" cannot be empty.');

        $collection->{$function}('property');
    }

    public static function provideGetTypedOrFailExceptionOnEmptyCases(): iterable
    {
        yield 'array empty string' => ['getArrayOrFail', ''];
        yield 'array null' => ['getArrayOrFail', null];
        yield 'int empty string' => ['getIntOrFail', ''];
        yield 'int null' => ['getIntOrFail', null];
        yield 'string null' => ['getStringOrFail', null];
        yield 'bool empty string' => ['getBoolOrFail', ''];
        yield 'bool null' => ['getBoolOrFail', null];
        yield 'float empty string' => ['getFloatOrFail', ''];
        yield 'float null' => ['getFloatOrFail', null];
    }

    #[DataProvider('provideGetTypedOrFailExceptionOnWrongTypeCases')]
    #[TestDox('get*OrFail должно бросать исключение значение нельзя привести')]
    public function testGetTypedOrFailExceptionOnWrongType(
        string $function,
        mixed $value,
        string $expectedType,
        string $type,
    ): void {
        $collection = new PropsCollection(['property' => $value]);
        self::expectException(ConversionException::class);
        self::expectExceptionMessageIs("Property \"property\" cannot be converted to {$expectedType}, got {$type}.");

        $collection->{$function}('property');
    }

    public static function provideGetTypedOrFailExceptionOnWrongTypeCases(): iterable
    {
        yield 'int' => ['getIntOrFail', 'tes 12', 'int', 'string'];
        yield 'string' => ['getStringOrFail', ['test'], 'string', 'array'];
        yield 'bool' => ['getBoolOrFail', 'example', 'bool', 'string'];
        yield 'float' => ['getFloatOrFail', '123,11', 'float', 'string'];
    }
}
