<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth;

use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Collections\PropsCollection;

/**
 * Пользователь системы.
 *
 * Представляет собой объект с данными пользователя и его правами доступа.
 *
 * Объект является иммутабельным после создания: ID, данные и права
 * не могут быть изменены извне.
 *
 * @see UserInterface
 * @see AuthService
 */
class User implements UserInterface
{
    public bool $authorized {
        get => null !== $this->id;
    }
    /**
     * {@inheritDoc}
     *
     * Уникальный идентификатор, полученный от провайдера пользователей.
     */
    public int|string|null $id {
        get {
            return $this->id;
        }
    }
    /**
     * {@inheritDoc}
     *
     * Коллекция дополнительных свойств пользователя (профиль, настройки и т.д.).
     */
    public PropsCollection $data {
        get {
            return $this->data ??= new PropsCollection([]);
        }
    }

    /**
     * Инициализирует авторизованного пользователя.
     *
     * @param null|int|string  $id   Уникальный идентификатор пользователя или null для анонимного
     * @param ?PropsCollection $data Данные пользователя (имя, email, настройки)
     */
    public function __construct(
        int|string|null $id,
        ?PropsCollection $data = null,
    ) {
        $this->id = $id;
        if (null !== $data) {
            $this->data = $data;
        }
    }
}
