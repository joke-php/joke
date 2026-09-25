<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\AuthService;
use Vasoft\Joke\Auth\Provider\ConfigUserProvider;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\AuthService
 */
#[TestDox('Сервис аутентификации')]
#[CoversClass(AuthService::class)]
final class AuthServiceTest extends TestCase
{
    private AuthConfig $config;
    private ServiceContainer $container;
    public array $initialized = [];

    protected function setUp(): void
    {
        $this->config = new AuthConfig();
        $this->container = new ServiceContainer();
    }

    #[TestDox('Если нет аутентификаторов - возвращается неавторизованный пользователь')]
    public function testNoAuthenticators(): void
    {
        $authService = new AuthService($this->config, $this->container);
        self::assertFalse($authService->getUser()->authorized);
    }

    #[TestDox('обходит все аутентификаторы последовательно')]
    public function testAnonymous(): void
    {
        $this->config->addAuthenticator($this->createMockAuthenticator('first '));
        $this->config->addAuthenticator($this->createMockAuthenticator('second '));
        $authService = new AuthService($this->config, $this->container);
        ob_start();
        $user = $authService->getUser();
        self::assertSame('first second ', ob_get_clean());
        self::assertFalse($user->authorized);
    }

    #[TestDox('Аутентификаторы инициализируются только при необходимости + можно использовать callback')]
    public function testInitializeIfNeed(): void
    {
        $this->config->addUserProvider(new ConfigUserProvider([2 => []]));
        $this->initialized = [];
        $this->container->registerSingleton(UserInterface::class, new User(2));

        $this->config->addAuthenticator(fn() => $this->createMockAuthenticator('provider1'));
        $this->config->addAuthenticator(fn(UserInterface $user) => $this->createMockAuthenticator('provider2', $user));
        $this->config->addAuthenticator(fn() => $this->createMockAuthenticator('provider3'));
        $authService = new AuthService($this->config, $this->container);
        ob_start();
        $authService->getUser();
        ob_get_clean();
        self::assertSame(['provider1', 'provider2'], $this->initialized);
    }

    #[TestDox('Аутентификаторы возможно добавлять по имени класса')]
    public function testAuthenticatorByClassName(): void
    {
        $this->config->addUserProvider(new ConfigUserProvider([3 => []]));
        $user = new User(3);
        $this->initialized = [];
        $this->container->registerSingleton(UserInterface::class, $user);

        $this->config->addAuthenticator(SimpleTestAuthenticator::class);
        $authService = new AuthService($this->config, $this->container);
        ob_start();
        $userFromService = $authService->getUser();
        ob_get_clean();
        self::assertSame($user, $userFromService);
    }

    #[TestDox('Аутентификатор вызывается только один раз')]
    public function testAuthOnce(): void
    {
        $this->config->addUserProvider(new ConfigUserProvider([1 => []]));
        $this->config->addAuthenticator($this->createMockAuthenticator('first ', new User(1)));
        $authService = new AuthService($this->config, $this->container);
        ob_start();
        $user1 = $authService->getUser();
        $user2 = $authService->getUser();
        self::assertSame('first ', ob_get_clean());
        self::assertTrue($user1->authorized);
        self::assertSame($user1, $user2);
    }

    #[TestDox('Если в провайдере не найде - возвращает гостя')]
    public function testAuthExistsAndProviderNotfound(): void
    {
        $this->config->addUserProvider(new ConfigUserProvider([1 => []]));
        $this->config->addAuthenticator($this->createMockAuthenticator('first ', new User(3)));
        $authService = new AuthService($this->config, $this->container);
        ob_start();
        $user = $authService->getUser();
        ob_get_clean();
        self::assertFalse($user->authorized);
    }

    #[TestDox('Кеширует провайдеры')]
    public function testCacheProvider(): void
    {
        $log = [];
        $this->config->addUserProvider(static function () use (&$log) {
            $log[] = 'init';

            return new ConfigUserProvider([1 => []]);
        });
        $this->config->addAuthenticator($this->createMockAuthenticator('first ', new User(3)));
        $authService = new AuthService($this->config, $this->container);
        ob_start();
        $authService->getUser();
        ob_get_clean();
        self::assertSame(['init'], $log);
    }

    #[TestDox('Бросает исключение если провайдер некорректного типа')]
    public function testExceptionIfProviderWrongType(): void
    {
        $this->config->addUserProvider(static fn() => new \stdClass());
        $this->config->addAuthenticator($this->createMockAuthenticator('first ', new User(4)));
        $authService = new AuthService($this->config, $this->container);

        try {
            ob_start();
            self::expectException(ConfigException::class);
            self::expectExceptionMessageIs(
                'User provider should implement "Vasoft\Joke\Contract\Auth\UserProviderInterface".',
            );
            $authService->getUser();
        } finally {
            ob_get_clean();
        }
    }

    #[TestDox('Выбрасывается исключение если не правильный тип аутентификатора')]
    #[DataProvider('provideExceptionOnWrongTypeCases')]
    public function testExceptionOnWrongType(string|callable $authenticator): void
    {
        $this->config->addAuthenticator($authenticator);
        $authService = new AuthService($this->config, $this->container);
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('Authenticator must implement AuthenticatorInterface');
        $authService->getUser();
    }

    public static function provideExceptionOnWrongTypeCases(): iterable
    {
        yield [static fn() => new \stdClass()];
        yield [\stdClass::class];
    }

    private function createMockAuthenticator(string $message, ?UserInterface $user = null): AuthenticatorInterface
    {
        return new readonly class ($message, $this, $user) implements AuthenticatorInterface {
            public function __construct(
                private string $message,
                AuthServiceTest $test,
                private ?UserInterface $user = null,
            ) {
                $test->initialized[] = $message;
            }

            public function authenticate(): ?UserInterface
            {
                echo $this->message;

                return $this->user;
            }
        };
    }
}

readonly class SimpleTestAuthenticator implements AuthenticatorInterface
{
    public function __construct(private ?UserInterface $user) {}

    public function authenticate(): ?UserInterface
    {
        echo 'SimpleTestAuthenticator';

        return $this->user;
    }
}
