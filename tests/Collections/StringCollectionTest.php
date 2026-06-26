<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Collections;

use Vasoft\Joke\Collections\StringCollection;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Exceptions\JokeException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Collections\StringCollection
 */
final class StringCollectionTest extends TestCase
{
    public function testGetStringOrDefault(): void
    {
        $collection = new StringCollection([
            'varString' => 'Example',
            'varInt' => 123,
            'varFloat' => 12.3,
            'varBoolTrue' => true,
            'varBoolFalse' => false,
        ]);
        self::assertSame('Example', $collection->getStringOrDefault('varString'));
        self::assertSame('123', $collection->getStringOrDefault('varInt'));
        self::assertSame('12.3', $collection->getStringOrDefault('varFloat'));
        self::assertSame('1', $collection->getStringOrDefault('varBoolTrue'));
        self::assertSame('', $collection->getStringOrDefault('varBoolFalse'));
    }

    public function testGetDefault(): void
    {
        $collection = new StringCollection([
            'varArray' => [123, 'test'],
        ]);
        self::assertSame('not-set', $collection->getStringOrDefault('varString', 'not-set'));
        self::assertSame('def', $collection->getStringOrDefault('varArray', 'def'));
    }

    public function testGetStringOrFailSuccess(): void
    {
        $collection = new StringCollection([
            'varString' => 'Example',
            'varInt' => 123,
            'varFloat' => 12.3,
            'varBoolTrue' => true,
            'varBoolFalse' => false,
        ]);
        self::assertSame('Example', $collection->getStringOrFail('varString'));
        self::assertSame('123', $collection->getStringOrFail('varInt'));
        self::assertSame('12.3', $collection->getStringOrFail('varFloat'));
        self::assertSame('1', $collection->getStringOrFail('varBoolTrue'));
        self::assertSame('', $collection->getStringOrFail('varBoolFalse'));
    }

    public function testGetStringOrFailType(): void
    {
        $collection = new StringCollection([
            'varArray' => [123, 'test'],
        ]);
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('Property "varArray" must be scalar or null to be used as string, got array.');
        $collection->getStringOrFail('varArray');
    }

    public function testGetStringOrFailTypeCustom(): void
    {
        $collection = new StringCollection([
            'varArray' => [123, 'test'],
        ]);
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('varArray array');
        $collection->getStringOrFail(
            'varArray',
            invalidTypeFactory: static fn(string $key, string $type) => throw new JokeException($key . ' ' . $type),
        );
    }

    public function testGetStringOrFailNotDefined(): void
    {
        $collection = new StringCollection([]);
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('Property "varNotDefined" does not exist.');
        $collection->getStringOrFail('varNotDefined');
    }

    public function testGetStringOrFailNotDefinedCustom(): void
    {
        $collection = new StringCollection([]);
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs('varNotDefined not found');
        $collection->getStringOrFail(
            'varNotDefined',
            static fn(string $key) => throw new JokeException($key . ' not found'),
        );
    }

    // Добавить тест для null
    public function testGetStringWithNull(): void
    {
        $collection = new StringCollection(['nullable' => null]);
        self::assertSame('', $collection->getStringOrDefault('nullable'));
        self::assertSame('', $collection->getStringOrFail('nullable'));
    }
}
