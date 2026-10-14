<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\RateLimiter\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier
 */
#[TestDox('IpIdentifier идентификация по IP')]
#[CoversClass(IpIdentifier::class)]
final class IpIdentifierTest extends TestCase
{
    #[TestDox('Получает IP из стандартной переменной')]
    public function testDirectIp(): void
    {
        $identifier = new IpIdentifier();
        $request = new HttpRequest(server: ['REMOTE_ADDR' => '192.168.1.1']);

        $id = $identifier->identify($request);

        self::assertSame('ip:192.168.1.1', $id);
    }

    #[TestDox('Получает IP из X-Forwarded-For')]
    public function testIpFromForwarded(): void
    {
        $identifier = new IpIdentifier();
        $request = new HttpRequest(
            server: [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_REAL_IP' => '192.168.1.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.42, 203.0.113.41',
            ],
        );

        $id = $identifier->identify($request);

        self::assertSame('ip:203.0.113.42', $id);
    }

    #[TestDox('Получает IP из X-Real-IP')]
    public function testIpFromRealIp(): void
    {
        $identifier = new IpIdentifier();
        $request = new HttpRequest(
            server: [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_REAL_IP' => '192.168.1.1',
            ],
        );

        $id = $identifier->identify($request);

        self::assertSame('ip:192.168.1.1', $id);
    }

    #[TestDox('Возвращает null если нет данных об IP')]
    public function testUnknownRemoteAddr(): void
    {
        $identifier = new IpIdentifier();
        $request = new HttpRequest();

        $id = $identifier->identify($request);

        self::assertNull($id);
    }
}
