<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Container;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Application\KernelServiceProvider;
use Vasoft\Joke\Config\ConfigManager;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Config\EnvironmentLoader;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\Exceptions\AutowiredException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\ParameterResolver;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Support\Normalizers\Path;
use Vasoft\Joke\Tests\Fixtures\FakeExample;
use Vasoft\Joke\Tests\Fixtures\Service\SingleService;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Container\ParameterResolver
 */
final class ParameterResolverTest extends TestCase
{
    use PHPMock;

    protected static ServiceContainer $serviceContainer;

    public static function setUpBeforeClass(): void
    {
        self::$serviceContainer = new ServiceContainer();
        parent::setUpBeforeClass();
    }

    #[DataProvider('provideClosureCases')]
    public function testClosure($closure): void
    {
        $resolver = new ParameterResolver(self::$serviceContainer);
        self::assertSame([2, 1], $resolver->resolveForCallable($closure, ['page' => 1, 'num' => 2]));
    }

    public static function provideClosureCases(): iterable
    {
        $testObject = new FakeExample(0);

        return [
            [
                static fn($num, $page) => $num + $page,
            ],
            [FakeExample::exampleClosureStatic(...)],
            [$testObject->exampleClosure(...)],
            ['\Vasoft\Joke\Tests\Fixtures\FakeExample::exampleClosureStatic'],
            ['exampleClosureFunction'],
            [[FakeExample::class, 'exampleClosureStatic']],
            [[$testObject, 'exampleClosure']],
        ];
    }

    public function testResolveObject(): void
    {
        $callback = static fn(int $page, FakeExample $num) => $page + $num->num;
        $resolver = new ParameterResolver(self::$serviceContainer);
        $args = $resolver->resolveForCallable($callback, ['page' => 1, 'num' => 2]);
        self::assertInstanceOf(FakeExample::class, $args[1]);
    }

    public function testResolveObjectException(): void
    {
        $callback = static fn(int $a, \stdClass $b) => $a + $b->value;
        $resolver = new ParameterResolver(self::$serviceContainer);
        self::expectException(AutowiredException::class);
        self::expectExceptionMessageIs(
            'Failed to autowire parameter "$b": expected type "stdClass" cannot be resolved or is incompatible with the provided value.',
        );
        $resolver->resolveForCallable($callback, ['b' => 1, 'a' => 2]);
    }

    public function testAutowiredService(): void
    {
        $callback = static fn(int $a, SingleService $b) => $a + $b->getValue();
        self::$serviceContainer->registerSingleton(SingleService::class, SingleService::class);
        $resolver = new ParameterResolver(self::$serviceContainer);
        $args = $resolver->resolveForCallable($callback, ['a' => 12]);
        self::assertCount(2, $args);
    }

    public function testAutowiredUnknown(): void
    {
        $callback = static fn(int $a, \SingleServiceUnknown $b) => $a + $b->getValue();
        $resolver = new ParameterResolver(self::$serviceContainer);
        self::expectException(AutowiredException::class);
        self::expectExceptionMessageIs(
            'Failed to autowire parameter "$b": expected type "SingleServiceUnknown" cannot be resolved or is incompatible with the provided value.',
        );
        $args = $resolver->resolveForCallable($callback, ['a' => 12]);
    }

    public function testAutowiredServiceNotRegistered(): void
    {
        $callback = static fn(int $a, FakeExample $b) => $a + $b->value;
        $resolver = new ParameterResolver(self::$serviceContainer);
        self::expectException(AutowiredException::class);
        self::expectExceptionMessageIs(
            'Failed to autowire parameter "$b": expected type "Vasoft\Joke\Tests\Fixtures\FakeExample" cannot be resolved or is incompatible with the provided value.',
        );
        $resolver->resolveForCallable($callback, ['a' => 12]);
    }

    public function testAutowiredScalar(): void
    {
        $callback = static fn(int $a, $b) => $a + $b->getValue();
        $resolver = new ParameterResolver(self::$serviceContainer);
        self::expectException(AutowiredException::class);
        self::expectExceptionMessageIs(
            'Failed to autowire parameter "$b": expected type "scalar" cannot be resolved or is incompatible with the provided value.',
        );
        $resolver->resolveForCallable($callback, ['a' => 12]);
    }

    public function testResolveForConstructor(): void
    {
        $resolver = new ParameterResolver(self::$serviceContainer);
        $args = $resolver->resolveForConstructor(FakeExample::class, ['num' => 12, 'z' => 14]);
        self::assertCount(1, $args);
        self::assertSame(12, $args[0]);
    }

    public function testResolveFloat(): void
    {
        $fake = new FakeExample(1);
        $resolver = new ParameterResolver(self::$serviceContainer);
        $args = $resolver->resolveForCallable([$fake, 'setFloat'], ['value' => 12.3]);
        self::assertCount(1, $args);
        self::assertSame(12.3, $args[0]);
    }

    public function testResolveMultipleTypes(): void
    {
        $fake = new FakeExample(1);
        $resolver = new ParameterResolver(self::$serviceContainer);
        $provider = new KernelServiceProvider(new ServiceContainer());
        $args = $resolver->resolveForCallable(
            [$fake, 'props'],
            ['value' => 12.3, 'provider' => $provider],
        );
        self::assertCount(2, $args);
        self::assertSame(12.3, $args[0]);
        self::assertSame($provider, $args[1]);
    }

    public function testResolveForCallableThrowsOnInvalidCallable(): void
    {
        $container = self::createStub(ServiceContainer::class);
        $resolver = new ParameterResolver($container);

        $this->expectException(ParameterResolveException::class);
        $this->expectExceptionMessageIs('Not a valid callback');
        $resolver->resolveForCallable(new \stdClass());
    }

    public function testResolveForConstructorThrowsOnNonExistentClass(): void
    {
        $container = self::createStub(ServiceContainer::class);
        $resolver = new ParameterResolver($container);

        $this->expectException(ParameterResolveException::class);
        $resolver->resolveForConstructor('Totally\NonExistent\ClassName');
    }

    #[RunInSeparateProcess]
    public function testReflectionExceptionWrap(): void
    {
        $code = random_int(1000, 99999);
        $mockIsString = self::getFunctionMock('Vasoft\Joke\Container', 'is_string');
        $mockIsString->expects(self::once())->willThrowException(new \ReflectionException('Test Exception', $code));
        $container = self::createStub(ServiceContainer::class);
        $resolver = new ParameterResolver($container);

        $this->expectException(ParameterResolveException::class);
        $this->expectExceptionMessageIs('Test Exception');
        $this->expectExceptionCode($code);
        $resolver->resolveForCallable('Totally\NonExistent\ClassName');
    }

    public function testWrongCallable(): void
    {
        $container = self::createStub(ServiceContainer::class);
        $resolver = new ParameterResolver($container);

        $this->expectException(ParameterResolveException::class);
        $resolver->resolveForConstructor('Totally\NonExistent\ClassName');
    }

    public function testConfigManagerException(): void
    {
        $serviceContainer = new ServiceContainer();

        $pathNormalizer = new Path(__DIR__);
        $serviceContainer->registerSingleton(Path::class, $pathNormalizer);
        $serviceContainer->registerAlias('normalizer.path', Path::class);

        $environment = new Environment(new EnvironmentLoader(''));
        $serviceContainer->registerSingleton(Environment::class, $environment);
        $serviceContainer->registerAlias('env', Environment::class);

        $configManager = self::createStub(ConfigManager::class);
        $configManager
            ->method('get')
            ->willThrowException(new ConfigException('Test message'));
        $serviceContainer->registerSingleton(ConfigManager::class, $configManager);

        $fake = new FakeExample(1);
        $resolver = new ParameterResolver($serviceContainer);

        try {
            $resolver->resolveForCallable([$fake, 'setConfig']);
            self::fail('Expected AutowiredException to be thrown');
        } catch (AutowiredException $e) {
            self::assertSame(
                'Failed to autowire parameter "$config": expected type "Vasoft\Joke\Tests\Fixtures\Config\SingleConfig" cannot be resolved or is incompatible with the provided value.',
                $e->getMessage(),
            );

            return;
        }
    }
}
