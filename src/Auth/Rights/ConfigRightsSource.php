<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth\Rights;

use Vasoft\Joke\Contract\Auth\RightsSourceInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

/**
 * Источник прав на основе конфигурационного массива.
 *
 * Хранит статический список прав, распределенных по ID пользователей.
 * Используется преимущественно для тестирования, прототипирования
 * или хранения прав системных пользователей (например, суперадмина).
 */
class ConfigRightsSource implements RightsSourceInterface
{
    /**
     * Ключ прав для гостевых (неавторизованных) пользователей.
     */
    public const string GUEST_KEY = '__guest__';

    /**
     * Карта прав пользователей.
     * Ключ: ID пользователя (или {@see ConfigRightsSource::GUEST_KEY} для гостей), Значение: список прав.
     *
     * @param array<int|string, list<string>> $rights
     */
    public function __construct(private readonly array $rights) {}

    /**
     * {@inheritDoc}
     *
     * Возвращает список прав для указанного пользователя из конфига.
     * Если пользователь не найден в карте прав, возвращает пустой массив.
     * Поддерживает как авторизованных пользователей, так и гостей (id = null).
     */
    #[\Override]
    public function getRights(UserInterface $user): array
    {
        $id = $user->id ?? self::GUEST_KEY;

        return $this->rights[$id] ?? [];
    }
}
