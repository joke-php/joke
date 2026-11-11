<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Response\JsonResponse;
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

    #[TestDox('Set body принимает различные типы данных и преобразует их')]
    #[DataProvider('provideSetBodyCases')]
    public function testSetBody(mixed $body, array $expected): void
    {
        $response = new JsonResponse(self::$cookies);
        $response->setBody($body);
        self::assertSame($expected, $response->getBody());
    }

    public static function provideSetBodyCases(): iterable
    {
        yield [1, ['message' => '1']];
        yield [null, []];
        yield ['', []];
        yield [new Example(['test' => '1']), ['test' => '1']];
    }
}
class Example implements \JsonSerializable
{
    public function __construct(private readonly array $data) {}

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }
}
