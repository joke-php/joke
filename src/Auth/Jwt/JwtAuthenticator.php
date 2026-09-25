<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth\Jwt;

use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Collections\PropsCollection;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\JwtHandlerInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Http\HttpRequest;

/**
 * Аутентификатор на основе JWT токенов.
 *
 * Извлекает токен из заголовка Authorization (Bearer) или из cookies,
 * декодирует его и создает объект пользователя.
 *
 * @see AuthenticatorInterface
 * @see JwtHandlerInterface
 */
class JwtAuthenticator implements AuthenticatorInterface
{
    /** Имя HTTP-заголовка для передачи токена. */
    public const string HEADER_NAME = 'Authorization';
    /** Префикс схемы Bearer в заголовке Authorization. */
    public const string BEARER_PREFIX = 'Bearer ';
    /** Имя cookie для хранения токена. */
    public const string COOKIE_NAME = 'jwt';
    /** Ключ для хранения всего payload токена в данных пользователя. */
    public const string KEY_JWT = 'jwt';
    /** Ключ идентификатора пользователя внутри payload токена. */
    public const string KEY_USER_ID = 'id';

    public function __construct(
        private readonly HttpRequest $request,
        private readonly JwtHandlerInterface $codec,
    ) {}

    /**
     * {@inheritDoc}
     *
     * Пытается извлечь и расшифровать JWT токен.
     * В случае успеха возвращает авторизованного пользователя, иначе возвращает null.
     */
    #[\Override]
    public function authenticate(): ?UserInterface
    {
        $token = $this->extractToken();
        if (null === $token) {
            return null;
        }

        try {
            $payload = $this->codec->decode($token);
            if (!empty($payload[self::KEY_USER_ID])) {
                $userId = $payload[self::KEY_USER_ID];
                unset($payload[self::KEY_USER_ID]);

                return new User($userId, new PropsCollection([self::KEY_JWT => $payload]));
            }

            return null;
        } catch (JwtException) {
            return null;
        }
    }

    /**
     * Извлекает токен из запроса.
     *
     * Приоритет:
     * 1. Заголовок Authorization: Bearer <token>
     * 2. Cookie с именем jwt
     *
     * @return null|string Токен или null, если не найден
     */
    private function extractToken(): ?string
    {
        $headerValue = $this->request->headers->get(self::HEADER_NAME);
        if (null !== $headerValue && str_starts_with($headerValue, self::BEARER_PREFIX)) {
            $token = substr($headerValue, strlen(self::BEARER_PREFIX));

            if ('' !== $token) {
                return $token;
            }
        }

        return $this->request->cookies->get(self::COOKIE_NAME);
    }
}
