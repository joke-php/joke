<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Collections\HeadersCollection;
use Vasoft\Joke\Mail\Address;
use Vasoft\Joke\Mail\Email;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Mail\Email
 */
#[TestDox('Email класс email письма')]
#[CoversClass(Email::class)]
final class EmailTest extends TestCase
{
    private static Address $from;
    /**
     * @var Address[]
     */
    private static array $to = [];

    public static function setUpBeforeClass(): void
    {
        self::$from = new Address('custom1@example.com');
        self::$to = [new Address('custom2@example.com')];
    }

    #[TestDox('Если не переданы заголовки создаются и тип контента по умолчанию null')]
    public function testConstructorHeadersNull(): void
    {
        $email = new Email(self::$from, self::$to, 'test');
        self::assertInstanceOf(HeadersCollection::class, $email->headers);
        self::assertNull($email->headers->contentType);
    }

    #[TestDox('Заголовки можно передавать в виде массива')]
    public function testConstructorHeadersArray(): void
    {
        $email = new Email(self::$from, self::$to, 'test', headers: ['X-Custom' => 'Value1', 'X-Value' => 'Value2']);
        self::assertInstanceOf(HeadersCollection::class, $email->headers);
        self::assertSame('Value1', $email->headers->get('X-Custom'));
        self::assertSame('Value2', $email->headers->get('X-Value'));
    }

    #[TestDox('Заголовки можно передавать в виде объекта')]
    public function testConstructorHeadersObject(): void
    {
        $headers = new HeadersCollection([]);
        $email = new Email(self::$from, self::$to, 'test', headers: $headers);
        self::assertSame($headers, $email->headers);
    }

    #[TestDox('ensureBaseHeaders по умолчанию тип контента plain')]
    public function testEnsureBaseHeadersDefault(): void
    {
        $email = new Email(self::$from, self::$to, 'test');
        $email->ensureBaseHeaders();
        self::assertSame('text/plain; charset=UTF-8', $email->headers->contentType);
    }

    #[TestDox('ensureBaseHeaders определяет тип контента по типу тела')]
    #[DataProvider('provideEnsureBaseHeadersAutoCases')]
    public function testEnsureBaseHeadersAuto(string $plain, string $text, string $expected): void
    {
        $email = new Email(self::$from, self::$to, 'test', $plain, $text);
        $email->ensureBaseHeaders();
        self::assertSame($expected, $email->headers->contentType);
    }

    public static function provideEnsureBaseHeadersAutoCases(): iterable
    {
        yield ['test', '', 'text/plain; charset=UTF-8'];
        yield ['', 'test', 'text/html; charset=UTF-8'];
    }

    #[TestDox('ensureBaseHeaders при автоматическом определении приоритет у html')]
    public function testEnsureBaseHeadersAutoPriority(): void
    {
        $email = new Email(self::$from, self::$to, 'test', 'plain', 'html');
        $email->ensureBaseHeaders();
        self::assertSame('text/html; charset=UTF-8', $email->headers->contentType);
    }

    #[TestDox('ensureBaseHeaders не переопределяет тип контента если установлен через заголовки')]
    public function testEnsureBaseHeadersContentTypeIfHasHeader(): void
    {
        $customType = 'text/custom; charset=UTF-8';
        $email = new Email(self::$from, self::$to, 'test', html: 'html', headers: ['content-type' => $customType]);
        $email->ensureBaseHeaders();
        self::assertSame($customType, $email->headers->contentType);
    }
}
