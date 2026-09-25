<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Auth\Jwt;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Auth\Jwt\JwtException;
use Vasoft\Joke\Auth\Jwt\SimpleJwtHandler;
use Vasoft\Joke\Config\Exceptions\ConfigException;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Auth\Jwt\SimpleJwtHandler
 */
#[TestDox('SimpleJwtHandler - обработчик токена JWT')]
#[CoversClass(SimpleJwtHandler::class)]
final class SimpleJwtHandlerTest extends TestCase
{
    use PHPMock;

    #[TestDox('Бросает исключение при ключе менее 32 символов')]
    public function testShortSecretKey(): void
    {
        self::expectException(ConfigException::class);
        self::expectExceptionMessageIs('JWT secret key must be at least 32 characters long.');
        new SimpleJwtHandler('secret key');
    }

    #[RunInSeparateProcess]
    #[TestDox('Добавляет время создания')]
    public function testEncodeDecode(): void
    {
        $timeMock = self::getFunctionMock('Vasoft\Joke\Auth\Jwt', 'time');
        $timeMock->expects(self::exactly(1))->willReturnCallback(static function (): int {
            static $count;
            $count ??= 1;
            ++$count;

            return $count;
        });
        $codec = new SimpleJwtHandler('12345678901234567890123456789012');
        $token = $codec->encode(['test' => 'example']);

        self::assertSame(['test' => 'example', 'iat' => 2], $codec->decode($token));
    }

    #[RunInSeparateProcess]
    #[TestDox('Добавляет время действия если передан TTL')]
    public function testEncodeDecodeWithTtl(): void
    {
        $timeMock = self::getFunctionMock('Vasoft\Joke\Auth\Jwt', 'time');
        $timeMock->expects(self::exactly(2))->willReturnCallback(static function (): int {
            static $count;
            $count ??= 1;
            ++$count;

            return $count;
        });
        $codec = new SimpleJwtHandler('12345678901234567890123456789012');
        $token = $codec->encode(['test' => 'example'], 100);
        self::assertSame(['test' => 'example', 'iat' => 2, 'exp' => 102], $codec->decode($token));
    }

    #[RunInSeparateProcess]
    #[TestDox('Бросает исключение если токен протух')]
    public function testEncodeDecodeWithExpired(): void
    {
        $timeMock = self::getFunctionMock('Vasoft\Joke\Auth\Jwt', 'time');
        $timeMock->expects(self::exactly(2))->willReturnCallback(static function (): int {
            static $count = 100;

            $count -= 10;
            if (80 === $count) {
                $count = 1000;
            }

            return $count;
        });
        $codec = new SimpleJwtHandler('12345678901234567890123456789012');
        $token = $codec->encode(['test' => 1], 1);
        self::expectException(JwtException::class);
        self::expectExceptionMessageIs('JWT token has expired.');

        $codec->decode($token);
    }

    #[TestDox('Бросает исключение если токен имеет некорректный формат')]
    public function testInvalidFormat(): void
    {
        $codec = new SimpleJwtHandler('12345678901234567890123456789012');
        self::expectException(JwtException::class);
        self::expectExceptionMessageIs('Invalid JWT format.');
        $codec->decode('invalid.token');
    }

    #[TestDox('Бросает исключение если некорректная сигнатура')]
    public function testInvalidSignature(): void
    {
        $codec = new SimpleJwtHandler('12345678901234567890123456789012');
        self::expectException(JwtException::class);
        self::expectExceptionMessageIs('Invalid JWT signature.');
        $codec->decode(
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0ZXN0IjoiZXhhbXBsZSIsImlhdCI6MiwiZXhwIjoxMDJ9.n1hBWm1YYVWeyKNy8jXUPBY0O4wJeLJR1g0ktrjGfI-Invalid',
        );
    }

    #[TestDox('Бросает исключение если некорректный json')]
    public function testInvalidJson(): void
    {
        $codec = new SimpleJwtHandler('12345678901234567890123456789012');
        self::expectException(JwtException::class);
        self::expectExceptionMessageIsOrContains('Json error: ');
        $codec->decode(
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.bm90.1OoHYIJCiq4lb-LUhT8wrOIJMq_d6ny7UfbZWTeGimQ',
        );
    }

    #[TestDox('Бросает исключение если данные не массив')]
    public function testPayloadNotIsArray(): void
    {
        $codec = new SimpleJwtHandler('12345678901234567890123456789012');
        self::expectException(JwtException::class);
        self::expectExceptionMessageIs('Invalid JWT payload.');
        $codec->decode(
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.Im5vdCI.kMhKd86ci670kBM_KW8Ds-7eR2e1iOP_1FH1-0xvvEU',
        );
    }
}
