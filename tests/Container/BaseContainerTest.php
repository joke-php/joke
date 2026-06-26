<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Container;

use phpmock\phpunit\PHPMock;
use Vasoft\Joke\Container\Exceptions\ContainerException;
use Vasoft\Joke\Contract\Container\ResolverInterface;
use Vasoft\Joke\Container\ParameterResolver;
use Vasoft\Joke\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Tests\Fixtures\Service\SingleService;
use Vasoft\Joke\Tests\Fixtures\Service\TestableParameterResolver;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Container\BaseContainer
 */
final class BaseContainerTest extends TestCase
{
    use PHPMock;

    public function testDefaults(): void
    {
        $container = new ServiceContainer();
        $resolver = $container->get(ResolverInterface::class);
        self::assertInstanceOf(ParameterResolver::class, $resolver);
        $container = $container->get(ServiceContainer::class);
        self::assertInstanceOf(ServiceContainer::class, $container);
    }

    public function testRegisterSingletonInstance(): void
    {
        $container = new ServiceContainer();
        $resolver = new TestableParameterResolver($container);
        TestableParameterResolver::$constructorCallCount = 0;

        $container->registerSingleton(ResolverInterface::class, $resolver);
        $container->getParameterResolver();
        $container->getParameterResolver();
        self::assertSame(0, TestableParameterResolver::$constructorCallCount);
    }

    public function testRegisterInstance(): void
    {
        $container = new ServiceContainer();
        $resolver = new TestableParameterResolver($container);
        TestableParameterResolver::$constructorCallCount = 0;

        $container->register(ParameterResolver::class, $resolver);
        $resolver1 = $container->getParameterResolver();
        $resolver2 = $container->getParameterResolver();
        self::assertSame(0, TestableParameterResolver::$constructorCallCount);
        self::assertSame($resolver1, $resolver2);
    }

    public function testMultipleObject(): void
    {
        $container = new ServiceContainer();
        $container->register(SingleService::class, SingleService::class);
        $service1 = $container->get(SingleService::class);
        $service2 = $container->get(SingleService::class);
        self::assertNotSame($service1, $service2);
    }

    public function testGetNotRegistered(): void
    {
        $container = new ServiceContainer();
        $service1 = $container->get(SingleService::class);
        self::assertNull($service1);
    }

    public function testRegisteredCallback(): void
    {
        $callbackCount = 0;
        $container = new ServiceContainer();
        $container->register(SingleService::class, static function () use (&$callbackCount) {
            ++$callbackCount;

            return new SingleService();
        });
        $service1 = $container->get(SingleService::class);
        $service2 = $container->get(SingleService::class);
        self::assertSame(2, $callbackCount);
        self::assertNotSame($service1, $service2);
    }

    public function testRegisteredSingletonCallback(): void
    {
        $callbackCount = 0;
        $container = new ServiceContainer();
        $container->registerSingleton(SingleService::class, static function () use (&$callbackCount) {
            ++$callbackCount;

            return new SingleService();
        });
        $service1 = $container->get(SingleService::class);
        $service2 = $container->get(SingleService::class);
        self::assertSame(1, $callbackCount);
        self::assertSame($service1, $service2);
    }

    public function testAlias(): void
    {
        $container = new ServiceContainer();
        $container->registerAlias('a', 'b');
        $container->registerAlias('b', SingleService::class);
        $container->registerSingleton(SingleService::class, SingleService::class);

        $s1 = $container->get('a');
        $s2 = $container->get('b');
        $s3 = $container->get(SingleService::class);

        self::assertSame($s1, $s2);
        self::assertSame($s2, $s3);
    }

    public function testRegisterAliasWarning(): void
    {
        $triggerError = self::getFunctionMock('Vasoft\Joke\Container', 'trigger_error');
        $triggerError->expects(self::once())->with(
            "Service name 'Custom\\Example' is not a valid class or interface.",
            E_USER_WARNING,
        );
        $container = new ServiceContainer();
        $container->registerAlias('a', 'Custom\Example');
    }

    public function testCircularAlias(): void
    {
        $container = new ServiceContainer();
        $container->registerAlias('a', 'b');
        $container->registerAlias('b', 'a');

        self::expectException(ContainerException::class);
        self::expectExceptionMessageIs('Circular alias detected: a-b.');
        $container->get('a');
    }

    public function testRegisterWarning(): void
    {
        $triggerError = self::getFunctionMock('Vasoft\Joke\Container', 'trigger_error');
        $triggerError->expects(self::once())->with(
            "Service name 'Custom\\Example' is not a valid class or interface.",
            E_USER_WARNING,
        );
        $container = new ServiceContainer();
        $container->register('Custom\Example', 'new Custom\Example()');
    }
}
