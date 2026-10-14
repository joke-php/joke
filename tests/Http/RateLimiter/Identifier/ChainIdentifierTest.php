<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\RateLimiter\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\RateLimiter\Identifier\ChainIdentifier;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\RateLimiter\Identifier\HeaderIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpWithUserAgentIdentifier;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\RateLimiter\Identifier\ChainIdentifier
 */
#[TestDox('ChainIdentifier определение по списку идентификаторов, первый подходящий')]
#[CoversClass(ChainIdentifier::class)]
final class ChainIdentifierTest extends TestCase
{
    #[TestDox('Идентификатор найден')]
    public function testIdentified(): void
    {
        $identifier = new ChainIdentifier([
            new HeaderIdentifier('X-Custom-Param'),
            new IpIdentifier(),
            new IpWithUserAgentIdentifier(),
        ]);

        $request = new HttpRequest(server: ['REMOTE_ADDR' => '123.12.12.11', 'HTTP_USER_AGENT' => 'My browser']);

        $id = $identifier->identify($request);

        self::assertSame('ip:123.12.12.11', $id);
    }

    #[TestDox('Идентификация невозможна - null')]
    public function testUnknown(): void
    {
        $identifier = new ChainIdentifier([
            new HeaderIdentifier('X-Custom-Param'),
            new IpIdentifier(),
            new IpWithUserAgentIdentifier(),
        ]);
        $request = new HttpRequest();

        $id = $identifier->identify($request);

        self::assertNull($id);
    }
}
