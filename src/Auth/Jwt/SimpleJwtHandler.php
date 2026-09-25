<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth\Jwt;

use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Contract\Auth\JwtHandlerInterface;

/**
 * Простая реализация JWT кодека на основе алгоритма HS256.
 *
 * Использует только нативные функции PHP для кодирования и проверки подписи.
 * Соответствует спецификации RFC 7519.
 *
 * @see JwtHandlerInterface
 */
class SimpleJwtHandler implements JwtHandlerInterface
{
    /**
     * Ключ для времени выдачи токена (Issued At) в payload.
     * Стандартное поле JWT, содержащее timestamp создания токена.
     */
    public const string KEY_ISSUED_AT = 'iat';
    /**
     * Ключ для времени истечения токена (Expiration Time) в payload.
     * Стандартное поле JWT, содержащее timestamp, после которого токен считается невалидным.
     */
    public const string KEY_EXPIRED_AT = 'exp';

    /**
     * Создает экземпляр кодека.
     *
     * @param string $secretKey Секретный ключ для подписи токенов.
     *                          Рекомендуется использовать строку длиной не менее 32 символов.
     *
     * @throws ConfigException Если слишком короткий ключ
     */
    public function __construct(
        private readonly string $secretKey,
    ) {
        if (strlen($secretKey) < 32) {
            throw new ConfigException('JWT secret key must be at least 32 characters long.');
        }
    }

    /**
     * {@inheritDoc}
     *
     * Формирует JWT-токен с заголовком HS256. Автоматически добавляет
     * время создания (iat) и, при указании TTL, время истечения (exp).
     */
    #[\Override]
    public function encode(array $payload, ?int $ttl = null): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $now = time();
        $payload[self::KEY_ISSUED_AT] = $now;
        if (null !== $ttl) {
            $payload[self::KEY_EXPIRED_AT] = $now + $ttl;
        }

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $this->secretKey, true);

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * {@inheritDoc}
     *
     * Выполняет декодирование токена, проверку целостности подписи (HMAC)
     * и валидацию срока действия (exp).
     *
     * @return array<string, mixed> Расшифрованный payload токена
     *
     * @throws JwtException Если токен имеет неверный формат, подпись не совпадает или срок действия истек
     */
    #[\Override]
    public function decode(string $token): array
    {
        $segments = explode('.', $token);
        if (3 !== count($segments)) {
            throw new JwtException('Invalid JWT format.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $segments;

        $signingInput = "{$headerB64}.{$payloadB64}";
        $expectedSignature = hash_hmac('sha256', $signingInput, $this->secretKey, true);

        if (!hash_equals($expectedSignature, $this->base64UrlDecode($signatureB64))) {
            throw new JwtException('Invalid JWT signature.');
        }

        try {
            $payload = json_decode($this->base64UrlDecode($payloadB64), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new JwtException('Json error: ' . $exception->getMessage(), previous: $exception);
        }
        if (!is_array($payload)) {
            throw new JwtException('Invalid JWT payload.');
        }

        if (isset($payload[self::KEY_EXPIRED_AT]) && $payload[self::KEY_EXPIRED_AT] < time()) {
            throw new JwtException('JWT token has expired.');
        }

        return $payload;
    }

    /**
     * Кодирует данные в формат Base64URL.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Декодирует данные из формата Base64URL.
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
