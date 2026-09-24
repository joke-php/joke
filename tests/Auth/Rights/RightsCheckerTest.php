<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth\Rights;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Rights\RightsChecker;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Auth\RightsSourceInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\Rights\RightsChecker
 */
#[TestDox('RightsChecker сервис проверки прав')]
#[CoversClass(RightsChecker::class)]
final class RightsCheckerTest extends TestCase
{
    public array $initializedSources = [];
    public array $queryRights = [];
    private AuthConfig $config;
    private ServiceContainer $container;

    protected function setUp(): void
    {
        $this->config = new AuthConfig();
        $this->container = new ServiceContainer();
    }

    #[DataProvider('provideUserHasNoRightsIdNotSourcesCases')]
    #[TestDox('По умолчанию возвращает false')]
    public function testUserHasNoRightsIdNotSources(UserInterface $user): void
    {
        $checker = new RightsChecker($this->config, $this->container);
        self::assertFalse($checker->can($user, 'main', 'read'));
    }

    #[DataProvider('provideUserHasNoRightsIdNotSourcesCases')]
    #[TestDox('Формат права <модуль>:<право>')]
    public function testUserHasRights(UserInterface $user): void
    {
        $this->config->addRightsSource(new SimpleTestRightsSource($this)->setRights(['main:write', 'main:read']));
        $checker = new RightsChecker($this->config, $this->container);
        self::assertTrue($checker->can($user, 'main', 'read'));
    }

    #[DataProvider('provideUserHasNoRightsIdNotSourcesCases')]
    #[TestDox('По порядку обходит все источники')]
    public function testCheckAllSources(UserInterface $user): void
    {
        $this->config->addRightsSource(new SimpleTestRightsSource($this)->setRights(['main:read']));
        $this->config->addRightsSource(new SimpleTestRightsSource($this)->setRights(['mail:read']));
        $this->config->addRightsSource(new SimpleTestRightsSource($this)->setRights(['forum:read']));
        $this->queryRights = [];
        $this->initializedSources = [];
        SimpleTestRightsSource::$index = 0;
        $checker = new RightsChecker($this->config, $this->container);
        $checker->can($user, 'main', 'write');
        $id = $user->id ?? 'anonymous';
        self::assertSame(['1 ' . $id, '2 ' . $id, '3 ' . $id], $this->queryRights);
    }

    #[DataProvider('provideUserHasNoRightsIdNotSourcesCases')]
    #[TestDox('Источники инициализируются один раз и запрос кешируется')]
    public function testLazyInitAndCachedResult(UserInterface $user): void
    {
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['main:read']));
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['mail:read']));
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['forum:read']));
        $this->queryRights = [];
        $this->initializedSources = [];
        SimpleTestRightsSource::$index = 0;
        $checker = new RightsChecker($this->config, $this->container);
        $checker->can($user, 'main', 'write');
        $checker->can($user, 'main', 'write');
        $id = $user->id ?? 'anonymous';
        self::assertSame(['1 ' . $id, '2 ' . $id, '3 ' . $id], $this->queryRights);
        self::assertSame([1, 2, 3], $this->initializedSources);
    }

    #[DataProvider('provideUserHasNoRightsIdNotSourcesCases')]
    #[TestDox('Источники могут быть разных типов')]
    public function testSourceDefinitionTypes(UserInterface $user): void
    {
        $this->container->registerSingleton(self::class, $this);
        SimpleTestRightsSource::$index = 0;
        $this->config->addRightsSource(new SimpleTestRightsSource($this)->setRights(['main:read']));
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['mail:read']));
        $this->config->addRightsSource(SimpleTestRightsSource::class);
        $this->queryRights = [];
        $this->initializedSources = [];
        $checker = new RightsChecker($this->config, $this->container);
        $checker->can($user, 'main', 'write');
        $id = $user->id ?? 'anonymous';
        self::assertSame(['1 ' . $id, '2 ' . $id, '3 ' . $id], $this->queryRights);
        self::assertSame([2, 3], $this->initializedSources);
    }

    #[TestDox('Для разных пользователй источники инициализируются один раз и запрос для каждого свой')]
    public function testAnonymousAdnAuthorized(): void
    {
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['main:read']));
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['mail:read']));
        $this->queryRights = [];
        $this->initializedSources = [];
        SimpleTestRightsSource::$index = 0;
        $checker = new RightsChecker($this->config, $this->container);
        $checker->can(new User(1), 'main', 'write');
        $checker->can(new User(2), 'main', 'write');
        $checker->can(new User(null), 'main', 'write');
        self::assertSame(['1 1', '2 1', '1 2', '2 2', '1 anonymous', '2 anonymous'], $this->queryRights);
        self::assertSame([1, 2], $this->initializedSources);
    }

    #[DataProvider('provideUserHasNoRightsIdNotSourcesCases')]
    #[TestDox('Проверка останавливается если найдено необходимое право')]
    public function testCheckStopIfFound(UserInterface $user): void
    {
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['main:read']));
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['mail:read']));
        $this->config->addRightsSource(fn() => new SimpleTestRightsSource($this)->setRights(['forum:read']));
        $this->queryRights = [];
        $this->initializedSources = [];
        SimpleTestRightsSource::$index = 0;
        $checker = new RightsChecker($this->config, $this->container);
        $checker->can($user, 'mail', 'read');
        $id = $user->id ?? 'anonymous';
        self::assertSame(['1 ' . $id, '2 ' . $id], $this->queryRights);
        self::assertSame([1, 2], $this->initializedSources);
    }

    public static function provideUserHasNoRightsIdNotSourcesCases(): iterable
    {
        yield [new User(123)];
        yield [new User(null)];
    }

    #[DataProvider('provideExceptionIfWrongSourceTypeCases')]
    #[TestDox('Выбрасывает исключение если источник неправильного типа')]
    public function testExceptionIfWrongSourceType(UserInterface $user, string|callable $definition): void
    {
        $this->container->registerSingleton(self::class, $this);
        SimpleTestRightsSource::$index = 0;
        $this->config->addRightsSource($definition);
        $this->queryRights = [];
        $this->initializedSources = [];
        $checker = new RightsChecker($this->config, $this->container);
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs(
            'Right source should implement "Vasoft\Joke\Contract\Auth\RightsSourceInterface".',
        );
        $checker->can($user, 'main', 'write');
    }

    public static function provideExceptionIfWrongSourceTypeCases(): iterable
    {
        yield [new User(123), \stdClass::class];
        yield [new User(null), \stdClass::class];
        yield [new User(123), static fn() => new \stdClass()];
        yield [new User(null), static fn() => new \stdClass()];
    }
}

class SimpleTestRightsSource implements RightsSourceInterface
{
    public static int $index = 0;
    private array $rights = ['default:test'];
    private int $id;

    public function __construct(private readonly RightsCheckerTest $test)
    {
        $this->id = ++self::$index;
        $this->test->initializedSources[] = $this->id;
    }

    public function setRights(array $rights): self
    {
        $this->rights = $rights;

        return $this;
    }

    public function getRights(UserInterface $user): array
    {
        $this->test->queryRights[] = $this->id . ' ' . ($user->id ?? 'anonymous');

        return $this->rights;
    }
}
