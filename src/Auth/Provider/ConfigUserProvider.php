<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth\Provider;

use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Collections\PropsCollection;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Contract\Auth\UserProviderInterface;

/**
 * Провайдер пользователей на основе конфигурационного массива.
 *
 * Используется преимущественно для тестирования, прототипирования
 * или хранения статических системных пользователей (например, суперадмина).
 *
 * @see UserProviderInterface
 */
readonly class ConfigUserProvider implements UserProviderInterface
{
    /**
     * Карта пользователей.
     * Ключ: ID пользователя, Значение: ассоциативный массив свойств.
     *
     * @param array<int|string, array<string, mixed>> $users
     */
    public function __construct(
        private array $users,
    ) {}

    /**
     * {@inheritDoc}
     *
     * Ищет пользователя по ID в конфигурационном массиве.
     * Если пользователь найден, создает новый объект User с его данными.
     */
    #[\Override]
    public function loadUserById(string $id): ?UserInterface
    {
        if (array_key_exists($id, $this->users)) {
            return new User($id, new PropsCollection($this->users[$id]));
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * Обогащает существующий объект пользователя данными из конфига.
     * Полезно, когда пользователь уже создан аутентификатором (например, JWT),
     * но требует дополнительных свойств (ролей, имени и т.д.).
     *
     * Значения из конфига имеют приоритет: если свойство уже существовало
     * в объекте пользователя, оно будет перезаписано.
     *
     * Если ID пользователя отсутствует в конфиге, возвращает null.
     */
    #[\Override]
    public function enrich(UserInterface $user): ?UserInterface
    {
        if (!array_key_exists($user->id, $this->users)) {
            return null;
        }

        foreach ($this->users[$user->id] as $propName => $propValue) {
            $user->data->set($propName, $propValue);
        }

        return $user;
    }
}
