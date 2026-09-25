<?php

declare(strict_types=1);

namespace Vasoft\Joke\Contract\Auth;

use Vasoft\Joke\Auth\Jwt\JwtException;

/**
 * Интерфейс для работы с JWT (кодирование и декодирование).
 */
interface JwtHandlerInterface
{
    /**
     * Кодирует массив данных в JWT-строку.
     *
     * @param array<string, mixed> $payload Данные для включения в токен
     * @param null|int             $ttl     Время жизни токена в секундах (null = без ограничений)
     *
     * @return string Готовая JWT-строка
     */
    public function encode(array $payload, ?int $ttl = null): string;

    /**
     * Декодирует JWT-строку и проверяет её подпись.
     *
     * @param string $token JWT-строка
     *
     * @return array<string, mixed> Расшифрованный payload
     *
     * @throws JwtException Если токен невалиден или подпись неверна
     */
    public function decode(string $token): array;
}
