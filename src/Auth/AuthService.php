<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth;

use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Contract\Auth\UserProviderInterface;

/**
 * Сервис аутентификации и управления сессией пользователя.
 *
 * Отвечает за ленивое определение текущего пользователя на основе
 * зарегистрированных в конфигурации аутентификаторов.
 *
 * Процесс аутентификации:
 * 1. Последовательный опрос аутентификаторов до первого успешного результата.
 * 2. Обогащение данных пользователя через зарегистрированные UserProvider.
 * 3. Если пользователь не найден ни одним провайдером, аутентификация считается неудачной.
 * 4. В случае полной неудачи возвращается гостевой объект (User с id = null).
 *
 * Результат кэшируется: повторные вызовы getUser() не приводят к повторной проверке токенов.
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
    /**
     * Кэш созданных экземпляров провайдеров пользователей.
     * Ключ: индекс в массиве определений.
     *
     * @var array<int, UserProviderInterface>
     */
    private array $userProviders = [];
    /**
     * Определения провайдеров из конфигурации.
     *
     * @var list<callable|class-string<UserProviderInterface>|UserProviderInterface>
     */
    private array $userProvidersDefinitions;

    public function __construct(
        protected readonly AuthConfig $config,
        protected readonly ServiceContainer $container,
    ) {
        $this->userProvidersDefinitions = $this->config->userProviders;
    }

    /**
     * Возвращает текущего пользователя.
     *
     * Выполняет ленивую инициализацию: при первом вызове запускает
     * процесс аутентификации и обогащения данных.
     *
     * @return UserInterface Объект пользователя (авторизованный или гость)
     *
     * @throws ConfigException           Если компонент конфигурации имеет неверный тип
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
     * Выполняет процесс аутентификации и поиска пользователя.
     *
     * Последовательно вызывает аутентификаторы. При успехе пытается
     * найти и обогатить данные пользователя через провайдеры.
     * Если провайдеры не нашли пользователя, процесс продолжается
     * со следующим аутентификатором.
     *
     * @throws ConfigException           Если компонент не реализует требуемый интерфейс или зарегистрирован провайдер пользователей некорректного типа
     * @throws ParameterResolveException При ошибках создания зависимостей через контейнер
     */
    private function resolveUser(): void
    {
        foreach ($this->config->authenticators as $authenticator) {
            $authEntity = $this->getAuthenticator($authenticator);
            $user = $authEntity->authenticate();

            if (null !== $user) {
                $foundUser = $this->findUser($user);
                if (null !== $foundUser) {
                    $this->user = $foundUser;

                    return;
                }
            }
        }

        $this->user = new User(null);
    }

    /**
     * Ищет и обогащает пользователя через зарегистрированные провайдеры.
     *
     * @param UserInterface $user "Черновой" объект от аутентификатора
     *
     * @return null|UserInterface Полностью загруженный пользователь или null
     *
     * @throws ConfigException           Если провайдер имеет неверный тип
     * @throws ParameterResolveException При ошибках создания провайдера
     */
    private function findUser(UserInterface $user): ?UserInterface
    {
        foreach ($this->userProvidersDefinitions as $index => $definition) {
            $provider = $this->getProvider($index, $definition);

            $foundedUser = $provider->enrich($user);
            if (null !== $foundedUser) {
                return $foundedUser;
            }
        }

        return null;
    }

    /**
     * Получает экземпляр провайдера, используя кэш инициализации.
     *
     * @param int                                                                $index      Индекс провайдера в конфиге
     * @param callable|class-string<UserProviderInterface>|UserProviderInterface $definition Определение
     *
     * @return UserProviderInterface Экземпляр провайдера
     *
     * @throws ConfigException           Если компонент не реализует UserProviderInterface
     * @throws ParameterResolveException При ошибках создания через контейнер
     */
    private function getProvider(int $index, callable|string|UserProviderInterface $definition): UserProviderInterface
    {
        if (!isset($this->userProviders[$index])) {
            $this->userProviders[$index] = $this->resolveProvider($definition);
        }

        return $this->userProviders[$index];
    }

    /**
     * Возвращает экземпляр провайдера на основе определения.
     *
     * @param callable|class-string<UserProviderInterface>|UserProviderInterface $definition Определение
     *
     * @return UserProviderInterface Готовый к использованию провайдер
     *
     * @throws ConfigException           Если полученный объект не является провайдером
     * @throws ParameterResolveException При ошибках создания через контейнер
     */
    private function resolveProvider(callable|string|UserProviderInterface $definition): UserProviderInterface
    {
        if ($definition instanceof UserProviderInterface) {
            return $definition;
        }

        $source = $this->container->make($definition);

        if (!$source instanceof UserProviderInterface) {
            throw new ConfigException(
                'User provider should implement "Vasoft\Joke\Contract\Auth\UserProviderInterface".',
            );
        }

        return $source;
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
