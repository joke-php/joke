<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Collections\HeadersCollection;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Exceptions\JokeException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Collections\HeadersCollection
 */
#[TestDox('HeadersCollection коллекция заголовков')]
#[CoversClass(HeadersCollection::class)]
final class HeadersCollectionTest extends TestCase
{
    #[TestDox('Set приводит имя заголовка к нижнему регистру')]
    public function testSetNormalizeName(): void
    {
        $collection = new HeadersCollection([]);
        $collection->set('X-test', 'value1');
        $collection->set('X-Test', 'value2');
        $all = $collection->getAll();
        self::assertArrayHasKey('x-test', $all);
        self::assertArrayNotHasKey('X-test', $all);
        self::assertCount(1, $all);
    }

    #[TestDox('Get приводит имя заголовка к нижнему регистру')]
    public function testGetNormalizeName(): void
    {
        $collection = new HeadersCollection([]);
        $collection->set('X-test', 'Value');
        self::assertSame('Value', $collection->get('X-TEST'));
    }

    #[TestDox('Unset приводит имя заголовка к нижнему регистру')]
    public function testUnsetNormalizeName(): void
    {
        $collection = new HeadersCollection([]);
        $collection->set('X-test', 'Value');
        $collection->unset('X-TEST');
        self::assertCount(0, $collection->getAll());
    }

    #[TestDox('При Reset имена заголовков приводятся к нижнему регистру')]
    public function testResetNormalizeName(): void
    {
        $collection = new HeadersCollection([]);
        $collection->reset(['X-test' => 'Value']);
        $all = $collection->getAll();
        self::assertArrayHasKey('x-test', $all);
        self::assertArrayNotHasKey('X-test', $all);
        self::assertCount(1, $all);
    }

    #[TestDox('При передаче в конструктор имена заголовков приводятся к нижнему регистру')]
    public function testConstructorNormalizeName(): void
    {
        $collection = new HeadersCollection(['X-test' => 'Value']);
        $all = $collection->getAll();
        self::assertArrayHasKey('x-test', $all);
        self::assertArrayNotHasKey('X-test', $all);
        self::assertCount(1, $all);
    }

    #[DataProvider('provideSetContentTypeCases')]
    #[TestDox('Коллекция содержит выделенные методы для Content-Type')]
    public function testSetContentType(string $name, string $value, string $propertyName): void
    {
        $collection = new HeadersCollection([]);
        $collection->setContentType($value);
        $all = $collection->getAll();

        self::assertSame($value, $collection->{$propertyName});
        self::assertCount(1, $all);
        self::assertSame($value, $all[$name] ?? 'not set');
    }

    public static function provideSetContentTypeCases(): iterable
    {
        return [
            ['content-type', 'application/json', 'contentType'],
        ];
    }

    #[TestDox('Не допускает наличие управляющих символов в значении заголовка')]
    #[DataProvider('provideSanitizeCollectionCases')]
    #[RunInSeparateProcess]
    public function testSanitizeCollection(array $props, string $expected): void
    {
        $collection = new HeadersCollection($props);
        self::expectException(JokeException::class);
        self::expectExceptionMessageIs($expected);
        $collection->sanitize();
    }

    #[RunInSeparateProcess]
    public static function provideSanitizeCollectionCases(): iterable
    {
        yield [
            ["X-Custom: value\r\n" => 'test-value'],
            'Invalid header name "x-custom: value": contains invalid characters.',
        ];
        yield [
            ['X-Custom' => "test-value\r\nTest: check"],
            'Invalid "x-custom" header value: contains control characters.',
        ];
    }

    #[TestDox('Sanitize нормализует значения')]
    public function testSanitizeNormalizeValues(): void
    {
        $collection = new HeadersCollection(['X-test' => " Value\r ", 'x-custom' => 1]);
        $collection->sanitize();
        self::assertSame('Value', $collection->get('x-test'));
        self::assertSame('1', $collection->get('x-custom'));
    }
}
