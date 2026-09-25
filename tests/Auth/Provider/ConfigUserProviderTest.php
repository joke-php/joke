<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Auth\Provider\ConfigUserProvider;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Collections\PropsCollection;
use Vasoft\Joke\Contract\Auth\UserInterface;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\Provider\ConfigUserProvider
 */
#[TestDox('ConfigUserProvider - источник пользователй на основе конфига')]
#[CoversClass(ConfigUserProvider::class)]
final class ConfigUserProviderTest extends TestCase
{
    public function testLoadByIdExists(): void
    {
        $provider = new ConfigUserProvider(['userId' => ['name' => 'Alex'], 123 => ['name' => 'Olga']]);
        $user = $provider->loadUserById('userId');
        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame('Alex', $user->data->getString('name', ''));
    }

    public function testLoadByIdNotExists(): void
    {
        $provider = new ConfigUserProvider(['userId' => ['name' => 'Alex'], 123 => ['name' => 'Olga']]);
        $user = $provider->loadUserById('other');
        self::assertNull($user);
    }

    public function testEnrichExists(): void
    {
        $provider = new ConfigUserProvider(['userId' => ['name' => 'Alex'], 123 => ['name' => 'Olga']]);
        $userSrc = new User('userId', new PropsCollection(['name' => 'no name']));
        $userDst = $provider->enrich($userSrc);
        self::assertSame($userSrc, $userDst);
        self::assertSame('Alex', $userDst->data->getString('name', ''));
    }

    public function testEnrichNotExists(): void
    {
        $provider = new ConfigUserProvider(['userId' => ['name' => 'Alex'], 123 => ['name' => 'Olga']]);
        $userSrc = new User('other', new PropsCollection(['name' => 'no name']));
        $userDst = $provider->enrich($userSrc);
        self::assertNull($userDst);
    }
}
