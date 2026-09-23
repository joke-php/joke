<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth;

use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

/**
 * Сервис аутентификации.
 *
 * Отвечает за ленивое определение текущего пользователя на основе
 * зарегистрированных в конфигурации аутентификаторов.
 *
 * При первом обращении к getUser() последовательно опрашивает все
 * аутентификаторы до первого успешного результата. Если ни один
 * аутентификатор не смог определить пользователя, возвращается
 * гостевой объект (User с id = null).
 *
 * @see AuthConfig
 * @see ServiceContainer
 */
class AuthService
{
    /**
     * Кэшированный экземпляр текущего пользователя.
     * Инициализируется лениво при первом вызове getUser().
     */
    private ?UserInterface $user = null;

    public function __construct(
        protected readonly AuthConfig $config,
        protected readonly ServiceContainer $container,
    ) {}

    /**
     * Возвращает текущего пользователя.
     *
     * Выполняет ленивую инициализацию: при первом вызове запускает
     * процесс аутентификации через зарегистрированные драйверы.
     * Результат кэшируется для последующих обращений.
     *
     * @return UserInterface Объект пользователя (авторизованный или гость)
     *
     * @throws ConfigException           Если компонент не реализует требуемый интерфейс
     * @throws ParameterResolveException При ошибках создания зависимостей через контейнер
     */
    public function getUser(): UserInterface
    {
        if (null === $this->user) {
            $this->resolveUser();
        }

        return $this->user;
    }

    /**
     * Выполняет процесс аутентификации.
     *
     * Последовательно вызывает все зарегистрированные аутентификаторы.
     * Останавливается при первом успешном результате.
     * В случае неудачи всех попыток создает гостевого пользователя.
     *
     * @throws ConfigException           Если компонент не реализует требуемый интерфейс
     * @throws ParameterResolveException При ошибках создания зависимостей через контейнер
     */
    private function resolveUser(): void
    {
        foreach ($this->config->authenticators as $authenticator) {
            $authEntity = $this->getAuthenticator($authenticator);

            $this->user = $authEntity->authenticate();

            if (null !== $this->user) {
                return;
            }
        }

        $this->user = new User(null);
    }

    /**
     * Резолвит компонент аутентификатора из определения конфигурации.
     *
     * Поддерживает три формата:
     * - Готовый экземпляр AuthenticatorInterface
     * - Callable-фабрика (вызывается через контейнер)
     * - Имя класса (создается через контейнер)
     *
     * @param AuthenticatorInterface|callable|class-string<AuthenticatorInterface> $authenticator Определение компонента
     *
     * @return AuthenticatorInterface Экземпляр аутентификатора
     *
     * @throws ConfigException           Если полученный объект не реализует AuthenticatorInterface
     * @throws ParameterResolveException При ошибках связывания параметров в контейнере
     */
    private function getAuthenticator(string|callable|AuthenticatorInterface $authenticator): AuthenticatorInterface
    {
        if (is_string($authenticator) || is_callable($authenticator)) {
            $authenticator = $this->container->make($authenticator);
        }
        if (!$authenticator instanceof AuthenticatorInterface) {
            throw new ConfigException('Authenticator must implement AuthenticatorInterface');
        }

        return $authenticator;
    }
}
