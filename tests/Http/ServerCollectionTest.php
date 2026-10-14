<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Http\ServerCollection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\ServerCollection
 */
final class ServerCollectionTest extends TestCase
{
    use PHPMock;

    public function testParseHeaderFromServer(): void
    {
        $agent = 'Mozilla/5.0 ' . date('Y-m-d H:i:s');
        $serverCollection = new ServerCollection([
            'HTTP_USER_AGENT' => $agent,
            'HTTP_CUSTOM_HEADER' => 'My Custom Header',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '100',
            'CONTENT_ENCODING' => 'gzip',
            'CONTENT_LANGUAGE' => 'en-US',
            'CONTENT_MD5' => 'QWxhZGRpbWVudA==',
        ]);
        $headers = $serverCollection->getHeaders();
        self::assertSame($agent, $headers['User-Agent'] ?? null);
        self::assertSame('My Custom Header', $headers['Custom-Header'] ?? null);
        self::assertSame('application/json', $headers['Content-Type'] ?? null);
        self::assertSame('100', $headers['Content-Length'] ?? null);
        self::assertSame('gzip', $headers['Content-Encoding'] ?? null);
        self::assertSame('en-US', $headers['Content-Language'] ?? null);
        self::assertSame('QWxhZGRpbWVudA==', $headers['Content-MD5'] ?? null);
    }

    public function testParseHeaderFromServerDefaultValues(): void
    {
        $serverCollection = new ServerCollection([]);
        $headers = $serverCollection->getHeaders();
        self::assertSame('text/html', $headers['Content-Type'] ?? null);
        self::assertSame('0', $headers['Content-Length'] ?? null);
        self::assertSame('', $headers['Content-Encoding'] ?? null);
        self::assertSame('', $headers['Content-Language'] ?? null);
        self::assertSame('', $headers['Content-MD5'] ?? null);
    }

    #[DataProvider('provideGetHostCases')]
    public function testGetHost(array $serverArray, string $host): void
    {
        $serverCollection = new ServerCollection($serverArray);
        self::assertSame($host, $serverCollection->getHost());
    }

    public static function provideGetHostCases(): iterable
    {
        yield 'empty data' => [[], ''];
        yield 'HTTP HOST priority' => [['HTTP_HOST' => 'example.com', 'SERVER_NAME' => 'example2.com'], 'example.com'];
        yield 'From Server Name' => [['SERVER_NAME' => 'example3.com'], 'example3.com'];
        yield 'Remove port' => [['SERVER_NAME' => 'example3.com:80'], 'example3.com'];
        yield 'Ipv6' => [['SERVER_NAME' => '[::1]'], '[::1]'];
        yield 'Ipv6 with port' => [['SERVER_NAME' => '[::1]:80'], '[::1]'];
    }

    public function testGetPortServerPortInvalid(): void
    {
        $serverCollection = new ServerCollection([
            'SERVER_PORT' => 'InvalidValue',
            'HTTPS' => '1',
        ]);
        self::assertSame(443, $serverCollection->getPort());
    }

    public function testGetPortForwardedInvalid(): void
    {
        $serverCollection = new ServerCollection([
            'HTTP_X_FORWARDED_PORT' => 'InvalidValue',
            'HTTP_HOST' => 'te.com:4001',
            'SERVER_PORT' => '4002',
        ]);
        self::assertSame(4001, $serverCollection->getPort());
    }

    #[DataProvider('provideGetPortCases')]
    public function testGetPort(array $serverArray, int $host): void
    {
        $serverCollection = new ServerCollection($serverArray);
        self::assertSame($host, $serverCollection->getPort());
    }

    public static function provideGetPortCases(): iterable
    {
        yield 'empty data' => [[], 80];
        yield 'Priority forward' => [
            [
                'HTTP_X_FORWARDED_PORT' => '4000',
                'HTTP_HOST' => 'te.com:4001',
                'SERVER_PORT' => '4002',
                'HTTPS' => '1',
            ],
            4000,
        ];
        yield 'Priority forward less' => [
            [
                'HTTP_X_FORWARDED_PORT' => '-1',
                'HTTP_HOST' => 'te.com:4001',
                'SERVER_PORT' => '4002',
                'HTTPS' => '1',
            ],
            4001,
        ];
        yield 'Priority forward more' => [
            [
                'HTTP_X_FORWARDED_PORT' => '4444444444444',
                'HTTP_HOST' => 'te.com:4001',
                'SERVER_PORT' => '4002',
                'HTTPS' => '1',
            ],
            4001,
        ];
        yield 'Priority host' => [
            [
                'HTTP_HOST' => 'te.com:4001',
                'SERVER_PORT' => '4002',
                'HTTPS' => '1',
            ],
            4001,
        ];
        yield 'Priority host less' => [
            [
                'HTTP_HOST' => 'te.com:-1',
                'SERVER_PORT' => '4002',
                'HTTPS' => '1',
            ],
            4002,
        ];
        yield 'Priority host more' => [
            [
                'HTTP_HOST' => 'te.com:444444444',
                'SERVER_PORT' => '4002',
                'HTTPS' => '1',
            ],
            4002,
        ];
        yield 'Priority Server Port' => [['SERVER_PORT' => '4002', 'HTTPS' => '1'], 4002];
        yield 'Priority Server Port less' => [['SERVER_PORT' => '-1', 'HTTPS' => '1'], 443];
        yield 'Priority Server Port more' => [['SERVER_PORT' => '99999999', 'HTTPS' => '1'], 443];
        yield 'By Security 1' => [['HTTPS' => '1'], 443];
        yield 'By Security On' => [['HTTPS' => 'On'], 443];
        yield 'By Security Yes' => [['HTTPS' => 'Yes'], 443];
        yield 'By Security Off' => [['HTTPS' => 'Off'], 80];
        yield 'By Security NotStandardString' => [['HTTPS' => 'NotStandardStringInHeader'], 80];
    }

    #[DataProvider('provideGetSchemeCases')]
    public function testGetScheme(array $serverArray, string $host): void
    {
        $serverCollection = new ServerCollection($serverArray);
        self::assertSame($host, $serverCollection->getScheme());
    }

    public static function provideGetSchemeCases(): iterable
    {
        yield 'empty data' => [[], 'http'];
        yield 'Priority Forwarded https' => [
            [
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTPS' => 'Off',
                'HTTP_X_FORWARDED_PORT' => 80,
            ],
            'https',
        ];
        yield 'Priority Forwarded http' => [
            [
                'HTTP_X_FORWARDED_PROTO' => 'http',
                'HTTPS' => '1',
                'HTTP_X_FORWARDED_PORT' => 443,
            ],
            'https',
        ];
        yield 'Secured by header' => [['HTTPS' => '1', 'HTTP_X_FORWARDED_PORT' => 80], 'https'];
        yield 'Secured by port' => [['HTTPS' => 'Off', 'HTTP_X_FORWARDED_PORT' => 443], 'https'];
        yield 'Not Secured by port' => [['HTTP_X_FORWARDED_PORT' => 441], 'http'];
    }

    #[DataProvider('provideGetBaseUrlCases')]
    public function testGetBaseUrl(array $serverArray, string $host): void
    {
        $serverCollection = new ServerCollection($serverArray);
        self::assertSame($host, $serverCollection->getBaseUrl());
    }

    /** @noinspection HttpUrlsUsage */
    public static function provideGetBaseUrlCases(): iterable
    {
        yield 'empty data' => [[], ''];
        yield 'Https not std port' => [['HTTPS' => 'On', 'HTTP_HOST' => 'te.com:4001'], 'https://te.com:4001'];
        yield 'Http not std port' => [['HTTPS' => 'Off', 'HTTP_HOST' => 'te.com:4001'], 'http://te.com:4001'];
        yield 'Http std port' => [['HTTPS' => 'Off', 'HTTP_HOST' => 'te.com:80'], 'http://te.com'];
        yield 'Https std port' => [['HTTPS' => 'Yes', 'HTTP_HOST' => 'te.com:443'], 'https://te.com'];
        yield 'Https port and not secure port' => [['HTTPS' => 'On', 'HTTP_HOST' => 'te.com:80'], 'https://te.com:80'];
    }

    #[TestDox('Получает IP из стандартной переменной')]
    public function testGetIpDirect(): void
    {
        $collection = new ServerCollection([
            'REMOTE_ADDR' => '192.168.1.1',
        ]);
        self::assertSame('192.168.1.1', $collection->getClientIp());
    }

    #[TestDox('Получает IP из X-Forwarded-For')]
    public function testGetIpForwarded(): void
    {
        $collection = new ServerCollection([
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_X_REAL_IP' => '192.168.1.2',
            'HTTP_X_FORWARDED_FOR' => '192.168.1.3, 192.168.1.4',
        ]);
        self::assertSame('192.168.1.3', $collection->getClientIp());
    }

    #[TestDox('Кеширует полученный IP')]
    #[RunInSeparateProcess]
    public function testGetIpCached(): void
    {
        $foo = self::getFunctionMock('Vasoft\Joke\Http', 'array_map');
        $foo->expects(self::once())->willReturn(['192.168.1.3']);
        $collection = new ServerCollection(['HTTP_X_FORWARDED_FOR' => '192.168.1.3']);
        $collection->getClientIp();

        self::assertSame('192.168.1.3', $collection->getClientIp());
    }

    #[TestDox('Получает IP из X-Real-IP')]
    public function testGetIpReal(): void
    {
        $collection = new ServerCollection([
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_X_REAL_IP' => '192.168.1.2',
        ]);
        self::assertSame('192.168.1.2', $collection->getClientIp());
    }

    #[TestDox('Не использует невалидные IP')]
    public function testGetIpWrong(): void
    {
        $collection = new ServerCollection([
            'REMOTE_ADDR' => '992.168.1.1',
            'HTTP_X_REAL_IP' => '992.168.1.2',
            'HTTP_X_FORWARDED_FOR' => '992.168.1.3',
        ]);
        self::assertSame('', $collection->getClientIp());
    }
}
