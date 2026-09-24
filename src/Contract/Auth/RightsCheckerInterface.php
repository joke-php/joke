<?php

declare(strict_types=1);

namespace Vasoft\Joke\Contract\Auth;

/**
 * Интерфейс проверщика прав доступа (Авторизация).
 *
 * Отвечает за определение того, имеет ли пользователь разрешение
 * на выполнение определенного действия в конкретном модуле.
 *
 * В отличие от AuthService, который занимается аутентификацией (определением личности),
 * RightsChecker фокусируется исключительно на политике доступа (Authorization).
 *
 * @see UserInterface
 * @see AuthService
 */
interface RightsCheckerInterface
{
    /**
     * Проверяет наличие права у пользователя.
     *
     * @param UserInterface $user   Пользователь (может быть гостем)
     * @param string        $module Модуль
     * @param string        $right  Право
     */
    public function can(UserInterface $user, string $module, string $right): bool;
}
