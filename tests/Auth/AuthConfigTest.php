<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Jwt\JwtAuthenticator;
use Vasoft\Joke\Auth\Provider\ConfigUserProvider;
use Vasoft\Joke\Auth\Session\SessionAuthenticator;
use Vasoft\Joke\Auth\Rights\ConfigRightsSource;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Http\HttpRequest;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\AuthConfig
 */
#[TestDox('Конфигурация системы аутентификации')]
#[CoversClass(AuthConfig::class)]
final class AuthConfigTest extends TestCase
{
    #[TestDox('Конфигурация принимает разные типы описания аутентификатора')]
    public function testConfigureAuthenticator(): void
    {
        $request = new HttpRequest();
        $object = new SessionAuthenticator($request);
        $function = static fn(): SessionAuthenticator => new SessionAuthenticator($request);
        $config = new AuthConfig();
        $config
            ->addAuthenticator($object)
            ->addAuthenticator($function)
            ->addAuthenticator(JwtAuthenticator::class);

        self::assertSame($object, $config->authenticators[0]);
        self::assertSame($function, $config->authenticators[1]);
        self::assertSame(JwtAuthenticator::class, $config->authenticators[2]);
    }

    #[TestDox('Конфигурация принимает разные типы источников прав')]
    public function testRightsSource(): void
    {
        $object = new ConfigRightsSource();
        $function = static fn(): ConfigRightsSource => new ConfigRightsSource();
        $config = new AuthConfig();
        $config
            ->addRightsSource($object)
            ->addRightsSource($function)
            ->addRightsSource(ConfigRightsSource::class);

        self::assertSame($object, $config->rightSources[0]);
        self::assertSame($function, $config->rightSources[1]);
        self::assertSame(ConfigRightsSource::class, $config->rightSources[2]);
    }

    #[TestDox('Конфигурация принимает разные типы провайдеров пользователей')]
    public function testUserProvider(): void
    {
        $object = new ConfigUserProvider([]);
        $function = static fn(): ConfigUserProvider => new ConfigUserProvider([]);
        $config = new AuthConfig();
        $config
            ->addUserProvider($object)
            ->addUserProvider($function)
            ->addUserProvider(ConfigUserProvider::class);

        self::assertSame($object, $config->userProviders[0]);
        self::assertSame($function, $config->userProviders[1]);
        self::assertSame(ConfigUserProvider::class, $config->userProviders[2]);
    }

    #[DataProvider('provideFrozenCases')]
    #[TestDox('Конфигурация не допускает изменений после заморозки')]
    public function testFrozen(string $setter, mixed $value): void
    {
        $config = new AuthConfig();
        $config->freeze();
        self::expectException(ConfigException::class);
        $config->{$setter}($value);
    }

    public static function provideFrozenCases(): iterable
    {
        yield ['addRightsSource', ConfigRightsSource::class];
        yield ['addAuthenticator', JwtAuthenticator::class];
        yield ['addUserProvider', ConfigUserProvider::class];
    }
}
