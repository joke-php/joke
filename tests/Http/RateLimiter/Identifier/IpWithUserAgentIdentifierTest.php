<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\RateLimiter\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpWithUserAgentIdentifier;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\RateLimiter\Identifier\IpWithUserAgentIdentifier
 */
#[TestDox('IpWithUserAgentIdentifier идентификация по IP и UserAgent')]
#[CoversClass(IpWithUserAgentIdentifier::class)]
final class IpWithUserAgentIdentifierTest extends TestCase
{
    #[TestDox('Идентификатор на основе IP и UserAgent')]
    public function testDirectIp(): void
    {
        $identifier = new IpWithUserAgentIdentifier();
        $request = new HttpRequest(server: [
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_USER_AGENT' => 'My browser',
        ]);

        $id = $identifier->identify($request);

        self::assertSame('ip:192.168.1.1:ua:b2a6a99249a519615b9646b555fbde48', $id);
    }

    #[TestDox('Если есть только IP - null')]
    public function testIpOnly(): void
    {
        $identifier = new IpWithUserAgentIdentifier();
        $request = new HttpRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

        $id = $identifier->identify($request);

        self::assertNull($id);
    }

    #[TestDox('Если есть только UserAgent - null')]
    public function testUserAgentOnly(): void
    {
        $identifier = new IpWithUserAgentIdentifier();
        $request = new HttpRequest(server: ['HTTP_USER_AGENT' => 'My browser']);

        $id = $identifier->identify($request);

        self::assertNull($id);
    }

    #[TestDox('Возвращает null если нет данных')]
    public function testUnknown(): void
    {
        $identifier = new IpIdentifier();
        $request = new HttpRequest();

        $id = $identifier->identify($request);

        self::assertNull($id);
    }
}
