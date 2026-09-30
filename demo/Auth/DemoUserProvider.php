<?php

declare(strict_types=1);

namespace Vasoft\Joke\Demo\Auth;

use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Collections\PropsCollection;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Contract\Auth\UserProviderInterface;

/**
 * Демонстрационный провайдер пользователей.
 *
 * Содержит захардкоженный набор тестовых пользователей.
 * Предназначен исключительно для демонстрации работы системы аутентификации
 * и не должен использоваться в продакшн-среде.
 *
 * @see UserProviderInterface
 */
class DemoUserProvider implements UserProviderInterface
{
    /**
     * Хранилище демо-пользователей.
     * Ключ: ID пользователя, Значение: отображаемое имя.
     *
     * @var array<string, string>
     */
    private array $userRepository = [
        'demo' => 'Demo User',
    ];

    /**
     * {@inheritDoc}
     *
     * Ищет пользователя по ID в демо-хранилище.
     * Возвращает объект User с именем или null, если пользователь не найден.
     */
    #[\Override]
    public function loadUserById(string $id): ?UserInterface
    {
        if (!isset($this->userRepository[$id])) {
            return null;
        }

        return new User($id, new PropsCollection(['name' => $this->userRepository[$id]]));
    }

    /**
     * {@inheritDoc}
     *
     * Обогащает существующий объект пользователя данными из демо-хранилища.
     * Добавляет свойство 'name' к коллекции данных пользователя.
     *
     * Возвращает null, если пользователь отсутствует в демо-хранилище.
     */
    #[\Override]
    public function enrich(UserInterface $user): ?UserInterface
    {
        if (!isset($this->userRepository[$user->id])) {
            return null;
        }

        $user->data->set('name', $this->userRepository[$user->id]);

        return $user;
    }
}
