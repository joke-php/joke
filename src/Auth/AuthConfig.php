<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth;

use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\RightsSourceInterface;
use Vasoft\Joke\Contract\Auth\UserProviderInterface;

/**
 * Конфигурация сервиса аутентификации.
 *
 * Содержит списки зарегистрированных аутентификаторов, источников прав
 * и провайдеров пользователей.
 *
 * Конфигурация поддерживает добавление компонентов до момента заморозки.
 * После вызова freeze() добавление новых компонентов запрещено.
 *
 * @see AuthService
 * @see AbstractConfig
 */
class AuthConfig extends AbstractConfig
{
    /**
     * Список аутентификаторов для определения личности пользователя.
     *
     * Аутентификаторы проверяются последовательно в порядке добавления (FIFO).
     * Первый успешный аутентификатор определяет пользователя.
     *
     * @var list<AuthenticatorInterface|callable|class-string<AuthenticatorInterface>>
     */
    public private(set) array $authenticators = [];
    /**
     * Список источников прав доступа.
     *
     * Права от всех источников агрегируются и объединяются.
     * Источники опрашиваются только после успешной аутентификации.
     *
     * @var list<callable|class-string<RightsSourceInterface>|RightsSourceInterface>
     */
    public private(set) array $rightSources = [];
    /**
     * Список провайдеров данных пользователей.
     *
     * Используются для загрузки данных пользователя по его ID
     * после успешной аутентификации. Провайдеры опрашиваются последовательно
     * до первого успешного результата.
     *
     * @var list<callable|class-string<UserProviderInterface>|UserProviderInterface>
     */
    public private(set) array $userProviders = [];

    /**
     * Добавляет аутентификатор в конфигурацию.
     *
     * Порядок добавления важен: аутентификаторы проверяются в порядке FIFO.
     *
     * Поддерживаемые форматы:
     * - Экземпляр класса, реализующего AuthenticatorInterface
     * - Callable, возвращающий экземпляр аутентификатора
     * - Строка с полным именем класса аутентификатора
     *
     * @param AuthenticatorInterface|callable|class-string<AuthenticatorInterface> $authenticator Определение аутентификатора
     *
     * @throws ConfigException Если конфигурация уже заморожена
     */
    public function addAuthenticator(string|callable|AuthenticatorInterface $authenticator): static
    {
        $this->guard();
        $this->authenticators[] = $authenticator;

        return $this;
    }

    /**
     * Добавляет источник прав в конфигурацию.
     *
     * Все источники работают независимо, их права объединяются.
     *
     * Поддерживаемые форматы:
     * - Экземпляр класса, реализующего RightsSourceInterface
     * - Callable, возвращающий экземпляр источника прав
     * - Строка с полным именем класса источника прав
     *
     * @param callable|class-string<RightsSourceInterface>|RightsSourceInterface $rightsSource Определение источника прав
     *
     * @throws ConfigException Если конфигурация уже заморожена
     */
    public function addRightsSource(string|callable|RightsSourceInterface $rightsSource): static
    {
        $this->guard();
        $this->rightSources[] = $rightsSource;

        return $this;
    }

    /**
     * Добавляет провайдер пользователей в конфигурацию.
     *
     * Провайдеры отвечают за поиск и загрузку данных пользователя
     * из конкретного источника (БД, API, конфиг и т.д.).
     * Опрашиваются последовательно до первого найденного пользователя.
     *
     * Поддерживаемые форматы:
     * - Экземпляр класса, реализующего UserProviderInterface
     * - Callable, возвращающий экземпляр провайдера
     * - Строка с полным именем класса провайдера
     *
     * @param callable|class-string<UserProviderInterface>|UserProviderInterface $provider Определение провайдера
     *
     * @return static Для цепочки вызовов
     *
     * @throws ConfigException Если конфигурация уже заморожена
     */
    public function addUserProvider(string|callable|UserProviderInterface $provider): static
    {
        $this->guard();
        $this->userProviders[] = $provider;

        return $this;
    }
}
