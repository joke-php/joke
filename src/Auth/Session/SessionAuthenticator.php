<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth\Session;

use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Http\HttpRequest;

/**
 * Аутентификатор на основе сессии.
 *
 * Проверяет наличие идентификатора пользователя в сессии.
 * Если ID найден, создает объект пользователя; иначе возвращает null,
 *
 * @see AuthenticatorInterface
 */
class SessionAuthenticator implements AuthenticatorInterface
{
    /**
     * Ключ для хранения ID пользователя в сессии.
     */
    public const string VAR_USER_ID = 'jokeUserId';

    public function __construct(private readonly HttpRequest $request) {}

    /**
     * {@inheritDoc}
     *
     * Выполняет поиск идентификатора пользователя в сессии по ключу {@see VAR_USER_ID}.
     *
     * @return null|UserInterface Объект пользователя при наличии ID в сессии или null
     */
    #[\Override]
    public function authenticate(): ?UserInterface
    {
        $userId = $this->request->session->get(static::VAR_USER_ID);

        if (null === $userId) {
            return null;
        }

        return new User($userId);
    }
}
