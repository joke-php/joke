<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Response\JsonResponse;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Response\ResponseStatus;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\JsonResponse
 */
final class JsonResponseTest extends TestCase
{
    private static CookieCollection $cookies;

    public static function setUpBeforeClass(): void
    {
        self::$cookies = new CookieCollection(new CookieConfig(), true);
    }

    public function testDefaultStatusIsOk(): void
    {
        $response = new JsonResponse(self::$cookies);
        self::assertSame(ResponseStatus::OK, $response->status);
    }

    public function testDefaultContentType(): void
    {
        $response = new JsonResponse(self::$cookies);
        self::assertSame('application/json', $response->headers->contentType);
    }

    public function testGetBody(): void
    {
        $response = new JsonResponse(self::$cookies);
        $body = [
            'example' => 'test',
            'value' => 1,
        ];
        $response->setBody($body);
        self::assertSame($body, $response->getBody());
        self::assertSame(json_encode($body), $response->getBodyAsString());
    }
}
