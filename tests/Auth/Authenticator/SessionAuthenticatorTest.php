<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth\Authenticator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\Authenticator\SessionAuthenticator;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Http\HttpRequest;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\Authenticator\SessionAuthenticator
 */
#[TestDox('SessionAuthenticator аутентификатор на основе сессий')]
#[CoversClass(SessionAuthenticator::class)]
final class SessionAuthenticatorTest extends TestCase
{
    public function testAuthenticateAnonymous(): void
    {
        $request = new HttpRequest();
        $authenticator = new SessionAuthenticator($request);
        self::assertNull($authenticator->authenticate());
    }

    public function testAuthenticateIntegerId(): void
    {
        $request = new HttpRequest();
        $request->session->set(SessionAuthenticator::VAR_USER_ID, 12);
        $authenticator = new SessionAuthenticator($request);
        $user = $authenticator->authenticate();
        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame(12, $user->id);
    }

    public function testAuthenticateStringId(): void
    {
        $expected = 'SOME-GUID';
        $request = new HttpRequest();
        $request->session->set(SessionAuthenticator::VAR_USER_ID, $expected);
        $authenticator = new SessionAuthenticator($request);
        $user = $authenticator->authenticate();
        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame($expected, $user->id);
    }
}
