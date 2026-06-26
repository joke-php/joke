<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Provider;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Contract\Container\ContainerInspectionInterface;
use Vasoft\Joke\Contract\Container\ResolverInterface;
use Vasoft\Joke\Provider\Exceptions\ProviderException;
use Vasoft\Joke\Provider\ProviderManagerBuilder;
use Vasoft\Joke\Tests\Fixtures\Builder\FakeProviderBuilder;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Provider\ProviderManagerBuilder
 */
final class ProviderManagerBuilderTest extends TestCase
{
    private ContainerInspectionInterface&Stub $container;
    private ResolverInterface&Stub $resolver;

    protected function setUp(): void
    {
        $this->container = self::createStub(ContainerInspectionInterface::class);
        $this->resolver = self::createStub(ResolverInterface::class);

        $this->container->method('getParameterResolver')
            ->willReturn($this->resolver);

        //        $this->resolver
        //            ->method('resolveForConstructor')
        //            ->willReturn([]);
    }

    public function testParameterResolverException(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $simpleClass = $fakeBuilder->createProviderClass('SimpleProvider');

        $this->resolver->method('resolveForConstructor')
            ->willThrowException(new \Exception('Test Exception'));

        self::expectException(ProviderException::class);
        self::expectExceptionMessageIsOrContains('Maybe some constructor arguments are not resolvable');
        ProviderManagerBuilder::build($this->container, [$simpleClass], []);
    }

    public function testParameterResolverExceptionSafeOriginalMessage(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $simpleClass = $fakeBuilder->createProviderClass('SimpleProvider');

        $this->resolver->method('resolveForConstructor')
            ->willThrowException(new \Exception('Test Exception'));

        try {
            ProviderManagerBuilder::build($this->container, [$simpleClass], []);
        } catch (ProviderException $e) {
            self::assertSame('Test Exception', $e->getPrevious()->getMessage());
        }
    }

    public function testImplementsServiceProviderInterface(): void
    {
        $this->resolver->method('resolveForConstructor')
            ->willReturn([]);
        self::expectException(ProviderException::class);
        self::expectExceptionMessageIs(
            'Provider class "stdClass" must implement Vasoft\Joke\Contract\Provider\ServiceProviderInterface',
        );
        ProviderManagerBuilder::build($this->container, [\stdClass::class], []);
    }

    public function testDuble(): void
    {
        $fakeBuilder = new FakeProviderBuilder();
        $simpleClass = $fakeBuilder->createProviderClass('SimpleProvider');

        $this->resolver->method('resolveForConstructor')
            ->willThrowException(new \Exception('Test Exception'));

        self::expectException(ProviderException::class);
        self::expectExceptionMessageIsOrContains('Providers cannot be both regular and deferred');
        ProviderManagerBuilder::build($this->container, [$simpleClass], [$simpleClass]);
    }
}
