<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

/**
 * HTTP-статусы ответов.
 *
 * Представляет наиболее часто используемые коды состояния HTTP
 * в виде backed enum с целочисленным значением (код статуса).
 * Включает метод для получения текстового описания статуса.
 */
enum ResponseStatus: int
{
    case OK = 200;
    case MOVED_PERMANENTLY = 301;
    case FOUND = 302;
    case SEE_OTHER = 303;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case INTERNAL_SERVER_ERROR = 500;

    /**
     * Возвращает текстовое описание HTTP-статуса.
     *
     * Соответствует стандартным фразам из RFC 7231 и других спецификаций.
     *
     * @return string Текстовое описание, например: 'OK', 'Not Found', 'Internal Server Error'
     */
    public function http(): string
    {
        return match ($this) {
            self::OK => 'OK',
            self::MOVED_PERMANENTLY => 'Moved Permanently',
            self::FOUND => 'Found',
            self::SEE_OTHER => 'See Other',
            self::BAD_REQUEST => 'Bad Request',
            self::UNAUTHORIZED => 'Unauthorized',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
            self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
            self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
        };
    }
}
