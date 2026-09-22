<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Auth\User;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Collections\PropsCollection;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\User
 */
#[TestDox('Объект пользователя')]
#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    #[TestDox('Флаг авторизации зависит от id пользователя')]
    #[DataProvider('provideAuthorizedCases')]
    public function testAuthorized(int|string|null $id, bool $expected): void
    {
        self::assertSame($expected, new User($id)->authorized);
    }

    public static function provideAuthorizedCases(): iterable
    {
        yield [null, false];
        yield [123, true];
        yield ['some-GUID', true];
    }

    #[TestDox('Создает коллекцию свойств даже если не передана')]
    public function testInitPropsCollection(): void
    {
        $user = new User(123);
        self::assertInstanceOf(PropsCollection::class, $user->data);
    }

    #[TestDox('Не переопределяет коллекцию свойств из конструктора')]
    public function testPropsCollectionFrmConstructor(): void
    {
        $props = new PropsCollection([]);
        $user = new User(123, $props);
        self::assertSame($props, $user->data);
    }
}
