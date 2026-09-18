<?php

declare(strict_types=1);

namespace Vasoft\Joke\Contract\Auth;

/**
 * Интерфейс провайдера пользователей.
 *
 * Провайдер отвечает за поиск и загрузку данных пользователя
 * из конкретного источника (база данных, конфигурация, LDAP и т.д.).
 *
 * В отличие от Authenticator, который определяет "как аутентифицировать",
 * UserProvider знает "где хранятся данные пользователей".
 *
 * Один провайдер может использоваться несколькими аутентификаторами.
 * Например, SessionAuthenticator и JwtAuthenticator могут использовать
 * один и тот же DatabaseUserProvider.
 *
 * @see AuthenticatorInterface
 */
interface UserProviderInterface
{
    /**
     * Загружает пользователя по уникальному идентификатору.
     *
     * Идентификатором может быть:
     * - числовой ID пользователя
     * - UUID
     * - email или логин (зависит от реализации)
     *
     * Метод ДОЛЖЕН вернуть null, если пользователь не найден,
     * а НЕ выбрасывать исключение. Это позволяет аутентификаторам
     * корректно обрабатывать случай "пользователь не существует".
     *
     * @param string $identifier Уникальный идентификатор пользователя
     *
     * @return null|UserInterface Объект пользователя или null если не найден
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface;
}
