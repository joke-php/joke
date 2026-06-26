<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Config;

use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Tests\Fixtures\Config\SingleConfig;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Config\AbstractConfig
 */
final class AbstractConfigTest extends TestCase
{
    public function testMutability(): void
    {
        $example = new SingleConfig();
        $example->setValue(10);
        self::assertSame(10, $example->getValue());
        $example->setValue(15);
        self::assertSame(15, $example->getValue());
    }

    public function testFrozen(): void
    {
        $example = new SingleConfig();
        $example->freeze();
        self::assertTrue($example->isFrozen());
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs(
            'Cannot modify frozen configuration of [Vasoft\Joke\Tests\Fixtures\Config\SingleConfig].',
        );
        $example->setValue(15);
    }
}
