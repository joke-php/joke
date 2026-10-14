<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter\Identifier;

use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Exceptions\ConversionException;

/**
 * Идентификатор клиента по значению HTTP-заголовка.
 *
 * Извлекает значение указанного заголовка из запроса и формирует
 * уникальный идентификатор вида "header-{имя_заголовка}:{значение}".
 *
 * Типичные сценарии использования:
 * - API-ключи (X-API-Key, Authorization)
 * - Токены доступа
 * - Кастомные заголовки идентификации
 *
 * Если заголовок отсутствует или пуст, возвращает null, что позволяет
 * использовать этот идентификатор в составе {@see ChainIdentifier}
 * как один из fallback-вариантов.
 *
 * @see ClientIdentifierInterface
 * @see ChainIdentifier
 */
readonly class HeaderIdentifier implements ClientIdentifierInterface
{
    /**
     * Создаёт идентификатор по HTTP-заголовку.
     *
     * @param string $headerKey Имя HTTP-заголовка для извлечения значения регистронезависимое
     */
    public function __construct(private string $headerKey) {}

    /**
     * {@inheritDoc}
     *
     * Формирует идентификатор в формате "header-{lowercase_header_key}:{value}".
     * Имя заголовка приводится к нижнему регистру для консистентности ключей,
     * значение заголовка сохраняется как есть.
     *
     * @throws ConversionException При ошибке приведения значения заголовка к строке
     */
    public function identify(HttpRequest $request): ?string
    {
        $apiKey = $request->headers->getString($this->headerKey, '');

        return ('' !== $apiKey) ? sprintf('header-%s:%s', strtolower($this->headerKey), $apiKey) : null;
    }
}
