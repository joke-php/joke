<?php

declare(strict_types=1);

namespace Vasoft\Joke\Contract\Http\RateLimiter;

use Vasoft\Joke\Http\HttpRequest;

/**
 * Контракт для определения идентификатора клиента из запроса.
 */
interface ClientIdentifierInterface
{
    /**
     * Определяет уникальный идентификатор клиента из запроса.
     *
     * Возвращаемая строка используется как ключ в хранилище счётчиков.
     * Должна быть уникальной и детерминированной для одного клиента.
     *
     * @param HttpRequest $request HTTP запрос
     *
     * @return null|non-empty-string Идентификатор клиента или null в случае отсутствия данных для идентификации
     */
    public function identify(HttpRequest $request): ?string;
}
