<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth\Jwt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\Jwt\JwtAuthenticator;
use Vasoft\Joke\Auth\Jwt\JwtException;
use Vasoft\Joke\Contract\Auth\JwtHandlerInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Http\HttpRequest;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\Jwt\JwtAuthenticator
 */
#[TestDox('JwtAuthenticator Аутентификация на основе JWT токена')]
#[CoversClass(JwtAuthenticator::class)]
final class JwtAuthenticatorTest extends TestCase
{
    #[TestDox('Возвращает null если нет токена')]
    public function testAuthenticateTokenNotPresent(): void
    {
        $auth = new JwtAuthenticator(new HttpRequest(), new TestingJwtHandler());
        self::assertNull($auth->authenticate());
    }

    #[TestDox('Возвращает null если токена в заголовке пустой и нет в куках')]
    public function testAuthenticateEmptyInHeaderAndNotPresentCookie(): void
    {
        $request = new HttpRequest(server: ['HTTP_AUTHORIZATION' => '']);
        $auth = new JwtAuthenticator($request, new TestingJwtHandler());
        self::assertNull($auth->authenticate());
    }

    #[TestDox('Возвращает null если токена в заголовке не имеет префикса и нет в куках')]
    public function testAuthenticatePrefixNotExistInHeaderAndNotPresentCookie(): void
    {
        $request = new HttpRequest(server: ['HTTP_AUTHORIZATION' => 'valid.token']);
        $auth = new JwtAuthenticator($request, new TestingJwtHandler());
        self::assertNull($auth->authenticate());
    }

    #[TestDox('Токен в заголовке имеет больший приоритет')]
    public function testTokenSourcePriority(): void
    {
        $request = new HttpRequest(
            cookies: ['jwt' => 'token_in_cookie'],
            server: ['HTTP_AUTHORIZATION' => 'Bearer valid.token'],
        );
        $auth = new JwtAuthenticator($request, new TestingJwtHandler());
        $user = $auth->authenticate();
        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame(1, $user->id);
        self::assertSame(['name' => 'Alex'], $user->data->getArray('jwt'));
    }

    #[TestDox('Токен и кук если нет в заголовке')]
    public function testFromCookiesIfHeaderNotPresent(): void
    {
        $request = new HttpRequest(
            cookies: ['jwt' => 'token_in_cookie'],
        );
        $auth = new JwtAuthenticator($request, new TestingJwtHandler());
        $user = $auth->authenticate();
        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame(2, $user->id);
        self::assertSame(['name' => 'Olga'], $user->data->getArray('jwt'));
    }

    #[TestDox('Токен и кук если в заголовке нет префикса')]
    public function testFromCookiesIfHeaderPrefixNotExists(): void
    {
        $request = new HttpRequest(
            cookies: ['jwt' => 'token_in_cookie'],
            server: ['HTTP_AUTHORIZATION' => 'valid.token'],
        );
        $auth = new JwtAuthenticator($request, new TestingJwtHandler());
        $user = $auth->authenticate();
        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame(2, $user->id);
    }

    #[TestDox('Токен и кук если в заголовке пустой')]
    public function testFromCookiesIfHeaderEmpty(): void
    {
        $request = new HttpRequest(
            cookies: ['jwt' => 'token_in_cookie'],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '],
        );
        $auth = new JwtAuthenticator($request, new TestingJwtHandler());
        $user = $auth->authenticate();
        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame(2, $user->id);
    }

    #[TestDox('возвращает null если в данных нет идентификатора пользователя')]
    public function testUserIdNotPresent(): void
    {
        $request = new HttpRequest(
            cookies: ['jwt' => 'token_in_cookie'],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '],
        );
        $auth = new JwtAuthenticator(
            $request,
            new TestingJwtHandler([], []),
        );
        self::assertNull($auth->authenticate());
    }

    #[TestDox('возвращает null при ошибке декодирования токена')]
    public function testNullOnJwtException(): void
    {
        $request = new HttpRequest(
            server: ['HTTP_AUTHORIZATION' => 'Bearer wrong.token'],
        );
        $auth = new JwtAuthenticator(
            $request,
            new TestingJwtHandler([], []),
        );
        self::assertNull($auth->authenticate());
    }
}

final class TestingJwtHandler implements JwtHandlerInterface
{
    public function __construct(
        private readonly array $dataHeader = ['id' => 1, 'name' => 'Alex'],
        private readonly array $dataCookie = ['id' => 2, 'name' => 'Olga'],
    ) {}

    public function encode(array $payload, ?int $ttl = null): string
    {
        return 'token';
    }

    public function decode(string $token): array
    {
        if ('wrong.token' === $token) {
            throw new JwtException('Wrong token');
        }

        return 'token_in_cookie' === $token ? $this->dataCookie : $this->dataHeader;
    }
}
