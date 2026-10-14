<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\RateLimiter\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\RateLimiter\Identifier\GroupIdentifier;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\RateLimiter\Identifier\HeaderIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpWithUserAgentIdentifier;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\RateLimiter\Identifier\GroupIdentifier
 */
#[TestDox('GroupIdentifier определение по списку идентификаторов, комбинация из всех')]
#[CoversClass(GroupIdentifier::class)]
final class GroupIdentifierTest extends TestCase
{
    #[TestDox('Идентификатор найден')]
    public function testIdentified(): void
    {
        $identifier = new GroupIdentifier([
            new HeaderIdentifier('X-Custom-Param'),
            new IpIdentifier(),
            new IpWithUserAgentIdentifier(),
        ]);

        $request = new HttpRequest(server: [
            'REMOTE_ADDR' => '123.12.12.11',
            'HTTP_USER_AGENT' => 'My browser',
            'HTTP_X_CUSTOM_PARAM' => 'Test',
        ]);

        $id = $identifier->identify($request);

        self::assertSame(
            'group:header-x-custom-param:Test#ip:123.12.12.11#ip:123.12.12.11:ua:b2a6a99249a519615b9646b555fbde48',
            $id,
        );
    }

    #[TestDox('Разделитель между частями можно менять')]
    public function testCustomSeparator(): void
    {
        $identifier = new GroupIdentifier([
            new HeaderIdentifier('X-Custom-Param'),
            new IpIdentifier(),
            new IpWithUserAgentIdentifier(),
        ], '???');

        $request = new HttpRequest(server: [
            'REMOTE_ADDR' => '123.12.12.11',
            'HTTP_USER_AGENT' => 'My browser',
            'HTTP_X_CUSTOM_PARAM' => 'Test',
        ]);

        $id = $identifier->identify($request);

        self::assertSame(
            'group:header-x-custom-param:Test???ip:123.12.12.11???ip:123.12.12.11:ua:b2a6a99249a519615b9646b555fbde48',
            $id,
        );
    }

    #[TestDox('Идентификация невозможна - null')]
    public function testUnknown(): void
    {
        $identifier = new GroupIdentifier([
            new HeaderIdentifier('X-Custom-Param'),
            new IpIdentifier(),
            new IpWithUserAgentIdentifier(),
        ]);
        $request = new HttpRequest();

        $id = $identifier->identify($request);

        self::assertNull($id);
    }
}
