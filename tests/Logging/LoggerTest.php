<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Logging;

use Vasoft\Joke\Contract\Logging\LogHandlerInterface;
use Vasoft\Joke\Logging\Exception\LogException;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Logging\Logger;
use Vasoft\Joke\Logging\LogLevel;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Logging\Logger
 */
final class LoggerTest extends TestCase
{
    public function testEmptyHandlers(): void
    {
        self::expectException(LogException::class);
        self::expectExceptionMessageIs('At least one log handler must be provided.');
        new Logger([]);
    }

    public function testRawMessage(): void
    {
        $valueContext = [];

        $handler = self::createMock(LogHandlerInterface::class);
        $handler->expects(self::once())->method('write')
            ->willReturnCallback(
                static function (LogLevel $level, object|string $message, array $context = []) use (
                    &$valueContext
                ): void {
                    $valueContext = $context;
                },
            );
        $logger = new Logger([$handler]);
        $logger->info('Some {value} message', ['value' => 10]);
        self::assertArrayHasKey('rawMessage', $valueContext);
        self::assertSame('Some {value} message', $valueContext['rawMessage']);
        self::assertSame(['value' => 10, 'rawMessage' => 'Some {value} message'], $valueContext);
    }
}
