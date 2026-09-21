<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth;

use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\RightsSourceInterface;

/**
 * Конфигурация сервиса аутентификации.
 *
 * Содержит список зарегистрированных аутентификаторов и источников прав.
 *
 * Конфигурация поддерживает добавление компонентов до момента заморозки.
 * После заморозки (freeze) добавление новых компонентов запрещено.
 *
 * @see AuthService
 * @see AbstractConfig
 *
 * @todo Заменить AuthService
 */
class AuthConfig extends AbstractConfig
{
    /**
     * Список аутентификаторов для определения личности пользователя.
     *
     * Аутентификаторы проверяются последовательно в порядке добавления.
     * Первый успешный аутентификатор определяет пользователя.
     *
     * @var list<AuthenticatorInterface|callable|class-string<AuthenticatorInterface>>
     */
    public private(set) array $authenticators = [];
    /**
     * Список источников прав доступа.
     *
     * Права от всех источников агрегируются и объединяются.
     * Источники следует вызывать только для авторизованных пользователей.
     *
     * @var list<callable|class-string<RightsSourceInterface>|RightsSourceInterface>
     */
    public private(set) array $rightSources = [];

    /**
     * Добавляет аутентификатор в конфигурацию.
     *
     * Аутентификатор будет использоваться AuthService для попытки
     * аутентификации пользователя. Порядок добавления важен:
     * аутентификаторы проверяются в порядке FIFO.
     *
     * Поддерживаемые форматы:
     * - Экземпляр класса, реализующего AuthenticatorInterface
     * - Callable, возвращающий экземпляр аутентификатора
     * - Строка с полным именем класса аутентификатора
     *
     * @param AuthenticatorInterface|callable|class-string<AuthenticatorInterface> $authenticator Экземпляр аутентификатора
     *
     * @return static Для цепочки вызовов
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
     * Источник прав будет использоваться для получения списка разрешений
     * для авторизованных пользователей. Все источники работают независимо,
     * их права объединяются.
     *
     * Поддерживаемые форматы:
     * - Экземпляр класса, реализующего AuthenticatorInterface
     * - Callable, возвращающий экземпляр источника прав
     * - Строка с полным именем класса аутентификатора
     *
     * @param callable|class-string<RightsSourceInterface>|RightsSourceInterface $rightsSource Экземпляр источника прав
     *
     * @return static Для цепочки вызовов
     *
     * @throws ConfigException Если конфигурация уже заморожена
     */
    public function addRightsSource(string|callable|RightsSourceInterface $rightsSource): static
    {
        $this->guard();
        $this->rightSources[] = $rightsSource;

        return $this;
    }
}
