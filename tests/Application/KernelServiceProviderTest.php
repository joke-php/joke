<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Application\KernelServiceProvider;
use Vasoft\Joke\Config\Exceptions\UnknownConfigException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Cors\CorsConfig;
use Vasoft\Joke\Http\Csrf\CsrfConfig;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Application\KernelServiceProvider
 */
final class KernelServiceProviderTest extends TestCase
{
    public function testUnknownConfig(): void
    {
        $this->expectException(UnknownConfigException::class);
        $this->expectExceptionMessageIs('Unknown config class: unknown');
        KernelServiceProvider::buildConfig('unknown', new ServiceContainer());
    }

    #[DataProvider('provideBuildConfigCases')]
    public function testBuildConfig(string $className): void
    {
        $config = KernelServiceProvider::buildConfig($className, new ServiceContainer());
        self::assertInstanceOf($className, $config);
    }

    public static function provideBuildConfigCases(): iterable
    {
        yield [ApplicationConfig::class];
        yield [CookieConfig::class];
        yield [CsrfConfig::class];
        yield [PageBuilderConfig::class];
        yield [CorsConfig::class];
    }
}
