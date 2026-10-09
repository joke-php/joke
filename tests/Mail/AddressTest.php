<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Mail;

use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Mail\Address;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Mail\Address
 */
final class AddressTest extends TestCase
{
    #[TestDox('При конвертации в строку без имени возвращается адрес')]
    public function testToStringWithoutName(): void
    {
        $email = 'alex@example.com';
        self::assertSame($email, (string) new Address($email));
    }

    #[TestDox('При конвертации в строку с именем форматируется')]
    public function testToStringWithName(): void
    {
        $email = 'alex@example.com';
        $name = 'Alex Tester';
        $expected = sprintf('"%s" <%s>', $name, $email);
        self::assertSame($expected, (string) new Address($email, $name));
    }

    #[TestDox('При конвертации в строку экранируются кавычки в имени')]
    public function testToStringWithNameSlashes(): void
    {
        $email = 'alex@example.com';
        $name = 'Alex "Tester"';
        $expected = sprintf('"%s" <%s>', addslashes($name), $email);
        self::assertSame($expected, (string) new Address($email, $name));
    }
}
