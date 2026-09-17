<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Application\ApplicationConfig;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Config\Exceptions\ConfigException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Application\ApplicationConfig
 */
final class ApplicationConfigTest extends TestCase
{
    public function testFileRoutes(): void
    {
        $config = new ApplicationConfig();
        $config->setFileRoues('routes.php');
        self::assertSame('routes.php', $config->getFileRoues());
    }

    #[DataProvider('provideFrozenCases')]
    public function testFrozen(string $setter, mixed $value): void
    {
        $config = new ApplicationConfig();
        $config->freeze();
        self::expectException(ConfigException::class);
        $config->{$setter}($value);
    }

    public static function provideFrozenCases(): iterable
    {
        yield ['setFileRoues', 'file.php'];
    }
}
