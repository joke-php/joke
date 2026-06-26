<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Contract\Container\ContainerInspectionInterface;
use Vasoft\Joke\Contract\Container\ResolverInterface;
use Vasoft\Joke\Provider\ProviderManager;
use Vasoft\Joke\Provider\ProviderManagerBuilder;
use Vasoft\Joke\Provider\Exceptions\MultipleProvideException;
use Vasoft\Joke\Provider\Exceptions\ServiceNotFoundException;
use Vasoft\Joke\Provider\Exceptions\ProviderException;
use Vasoft\Joke\Tests\Fixtures\Builder\FakeProviderBuilder;
use PHPUnit\Framework\MockObject\Stub;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Provider\ProviderManager
 */
final class ProviderManagerTest extends TestCase
{
    private ContainerInspectionInterface&Stub $container;
    private ResolverInterface&Stub $resolver;

    protected function setUp(): void
    {
        $this->container = self::createStub(ContainerInspectionInterface::class);
        $this->resolver = self::createStub(ResolverInterface::class);

        $this->container->method('getParameterResolver')
            ->willReturn($this->resolver);

        $this->resolver
            ->method('resolveForConstructor')
            ->willReturn([]);
    }

    public function testRegisterAndBootOrder(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $simpleClass = $fakeBuilder->createProviderClass('SimpleProvider');
        $dependencyClass = $fakeBuilder->createProviderClass('DependencyProvider', requires: ['SomeService']);

        $this->container
            ->method('has')
            ->willReturn(true);

        $manager = ProviderManagerBuilder::build($this->container, [$dependencyClass, $simpleClass], []);
        $manager->register();
        $manager->boot();

        self::assertSame(['DependencyProvider', 'SimpleProvider'], ($simpleClass)::getRegistered());
        self::assertSame(['DependencyProvider', 'SimpleProvider'], ($simpleClass)::getBooted());
    }

    public function testRegisterAndBootOrderRelated(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $simpleClass = $fakeBuilder->createProviderClass('SimpleProvider', provides: ['SomeService']);
        $dependencyClass = $fakeBuilder->createProviderClass('DependencyProvider', requires: ['SomeService']);

        $this->container
            ->method('has')
            ->willReturn(true);

        $manager = ProviderManagerBuilder::build($this->container, [$dependencyClass, $simpleClass], []);
        $manager->register();
        $manager->boot();

        self::assertSame(['SimpleProvider', 'DependencyProvider'], ($simpleClass)::getRegistered());
        self::assertSame(['SimpleProvider', 'DependencyProvider'], ($simpleClass)::getBooted());
    }

    public function testDeferredProviderLazyLoading(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $deferredClass = $fakeBuilder->createProviderClass('DeferredProvider', provides: ['DeferredService']);

        $manager = ProviderManagerBuilder::build($this->container, [], [$deferredClass]);

        $manager->register();
        $manager->boot();

        self::assertEmpty(($deferredClass)::getRegistered());
        self::assertEmpty(($deferredClass)::getBooted());

        $result = $manager->bootDeferredFor('DeferredService');

        self::assertTrue($result);
        self::assertCount(1, ($deferredClass)::getRegistered());
        self::assertCount(1, ($deferredClass)::getBooted());
    }

    public function testBootForUnknown(): void
    {
        $fakeBuilder = new FakeProviderBuilder();

        $classA = $fakeBuilder->createProviderClass('ProviderA', provides: ['ServiceA']);

        $manager = ProviderManagerBuilder::build($this->container, [], [$classA]);

        $manager->register();

        $result = $manager->bootDeferredFor('ServiceB');
        self::assertFalse($result);
    }

    public function testDependenciesResolutionOrder(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $classA = $fakeBuilder->createProviderClass('ProviderA', provides: ['ServiceA']);
        $classB = $fakeBuilder->createProviderClass('ProviderB', requires: ['ServiceA'], provides: ['ServiceB']);

        $manager = ProviderManagerBuilder::build($this->container, [], [$classA, $classB]);
        $manager->register();
        $manager->bootDeferredFor('ServiceB');

        self::assertSame(['ProviderA', 'ProviderB'], ($classA)::getBooted());
    }

    public function testCircularDependencyDetection(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $classA = $fakeBuilder->createProviderClass('CircularA', requires: ['ServiceB'], provides: ['ServiceA']);
        $classB = $fakeBuilder->createProviderClass('CircularB', requires: ['ServiceA'], provides: ['ServiceB']);

        $manager = ProviderManagerBuilder::build($this->container, [$classA, $classB], []);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessageIsOrContains('Circular dependency detected');

        $manager->register();
    }

    public function testServiceNotFoundException(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $class = $fakeBuilder->createProviderClass('MissingDepProvider', requires: ['NonExistentService']);

        $manager = ProviderManagerBuilder::build($this->container, [$class], []);

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessageIsOrContains('NonExistentService');

        $manager->register();
    }

    public function testServiceNotFoundExceptionOnBoot(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $class = $fakeBuilder->createProviderClass('MissingDepProvider', requires: ['NonExistentService']);

        $this->container
            ->method('has')
            ->willReturnCallback(static function ($service) {
                static $result = 0;
                ++$result;

                return $result < 2;
            });

        $manager = ProviderManagerBuilder::build($this->container, [$class], []);
        $manager->register();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessageIsOrContains('NonExistentService');

        $manager->boot();
    }

    public function testMultipleProvideException(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $classA = $fakeBuilder->createProviderClass('ProviderOne', provides: ['SharedService']);
        $classB = $fakeBuilder->createProviderClass('ProviderTwo', provides: ['SharedService']);

        $manager = ProviderManagerBuilder::build($this->container, [], [$classA, $classB]);

        $this->expectException(MultipleProvideException::class);
        $this->expectExceptionMessageIsOrContains('SharedService');

        $manager->register();
    }

    public function testIntrospectionMethods(): void
    {
        $fakeBuilder = new FakeProviderBuilder();

        $deferredClass = $fakeBuilder->createProviderClass('IntrospectProvider', provides: ['TestService']);

        $manager = ProviderManagerBuilder::build($this->container, [], [$deferredClass, $deferredClass]);
        $manager->register();

        self::assertContains('TestService', $manager->getProvidedServices());
        self::assertCount(1, $manager->getProvidedServices());
        self::assertEmpty($manager->getRegisteredProviders());
        self::assertEmpty($manager->getLoadedProviders());

        $manager->bootDeferredFor('TestService');

        self::assertNotEmpty($manager->getLoadedProviders());
        self::assertNotEmpty($manager->getRegisteredProviders());
    }

    public function testOnceDeferredBoot(): void
    {
        $fakeBuilder = new FakeProviderBuilder();

        $classA = $fakeBuilder->createProviderClass('IntrospectProvider', provides: ['TestService']);
        $provider = new ($classA);
        $manager = new ProviderManager($this->container, [], [$provider]);
        $manager->register();

        $result1 = $manager->bootDeferredFor('TestService');
        self::assertTrue($result1, 'First time mast be true');
        self::assertCount(1, ($classA)::getBooted(), 'The boot method must be called 1 time after the first startup.');

        $result2 = $manager->bootDeferredFor('TestService');
        self::assertTrue($result2, 'Second time mast be true');
        self::assertCount(1, ($classA)::getBooted(), 'The boot method should NOT be called again');
        self::assertCount(1, ($classA)::getRegistered(), 'The register method should NOT be called again');
    }
}
