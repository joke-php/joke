<?php

declare(strict_types=1);

namespace Auth\Rights;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Auth\Rights\ConfigRightsSource;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Contract\Auth\UserInterface;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\Rights\ConfigRightsSource
 */
#[TestDox('ConfigRightsSource - источник прав на основе конфига')]
#[CoversClass(ConfigRightsSource::class)]
final class ConfigRightsSourceTest extends TestCase
{
    private static array $rightsRoot = [
        'main:example1',
        'main:example2',
        'main:example3',
    ];
    private static array $rightsAdmin = [
        'main:example1',
        'main:example3',
    ];
    private static array $rightsGuest = [
        'main:example1',
        'main:example3',
    ];

    private function getSource(): ConfigRightsSource
    {
        return new ConfigRightsSource([
            'root' => self::$rightsRoot,
            'admin' => self::$rightsAdmin,
            ConfigRightsSource::GUEST_KEY => self::$rightsGuest,
        ]);
    }

    #[TestDox('Возвращает права для заданного пользователя')]
    #[DataProvider('provideGetRightsCases')]
    public function testGetRights(UserInterface $user, array $expected): void
    {
        $source = $this->getSource();
        $rights = $source->getRights($user);
        self::assertSame($expected, $rights);
    }

    public static function provideGetRightsCases(): iterable
    {
        yield 'Authorized with rights' => [new User('root'), self::$rightsRoot];
        yield 'Authorized without rights' => [new User('alex'), []];
        yield 'Anonymous with rights' => [new User(null), self::$rightsGuest];
    }
}
