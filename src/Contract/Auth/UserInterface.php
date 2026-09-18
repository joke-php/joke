<?php

declare(strict_types=1);

namespace Vasoft\Joke\Contract\Auth;

/**
 * Интерфейс пользователя системы аутентификации/авторизации.
 */
interface UserInterface
{
    /**
     * Флаг авторизации пользователя.
     *
     * - true - пользователь прошел аутентификацию
     * - false - анонимный пользователь
     */
    public bool $authorized {
        get;
    }
    /**
     * Уникальный идентификатор пользователя.
     *
     * Для анонимных пользователей может быть null или специальное значение.
     * Рекомендуется использовать int для числовых ID или string для UUID.
     */
    public int|string|null $id {
        get;
    }

    /**
     * Проверяет наличие определенного права у пользователя.
     *
     * Права имеют формат "module:right", например:
     * - "users:view"
     * - "posts:edit"
     * - "admin:delete"
     *
     * @param string $module Модуль системы (например, "users", "posts")
     * @param string $right  Право действия (например, "view", "edit", "delete")
     *
     * @return bool true если право предоставлено, false иначе
     */
    public function can(string $module, string $right): bool;
}
