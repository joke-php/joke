<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\RateLimiter\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\RateLimiter\Identifier\HeaderIdentifier;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\RateLimiter\Identifier\HeaderIdentifier
 */
#[TestDox('HeaderIdentifier идентификация по заголовку')]
#[CoversClass(HeaderIdentifier::class)]
final class HeaderIdentifierTest extends TestCase
{
    #[TestDox('Идентификатор по заданному заголовку')]
    public function testByHeader(): void
    {
        $identifier = new HeaderIdentifier('X-Custom-Param');
        $request = new HttpRequest(server: ['HTTP_X_CUSTOM_PARAM' => 'Test']);

        $id = $identifier->identify($request);

        self::assertSame('header-x-custom-param:Test', $id);
    }

    #[TestDox('Заданный заголовок отсутствует')]
    public function testUnknown(): void
    {
        $identifier = new HeaderIdentifier('X-Custom-Param');
        $request = new HttpRequest(server: ['HTTP_X_CUSTOM_PARAM_OTHER' => 'Test']);

        $id = $identifier->identify($request);

        self::assertNull($id);
    }
}
