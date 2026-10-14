<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter\Identifier;

use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\ServerCollection;

/**
 * Идентификатор клиента по IP-адресу.
 *
 * Формирует уникальный идентификатор вида "ip:{address}" на основе
 * IP-адреса клиента, полученного через {@see ServerCollection::getClientIp()}.
 *
 * Поддерживает определение реального IP за прокси-серверами (при корректной
 * настройке trusted proxies в конфигурации приложения).
 *
 * Возвращает null, если IP-адрес не удалось определить (пустая строка),
 * что позволяет использовать этот идентификатор в составе {@see ChainIdentifier}
 * как fallback-вариант.
 *
 * @see ClientIdentifierInterface
 * @see ChainIdentifier
 */
class IpIdentifier implements ClientIdentifierInterface
{
    /**
     * {@inheritDoc}
     *
     * Формирует идентификатор в формате "ip:{address}".
     * Возвращает null, если IP-адрес пуст или не определён.
     */
    public function identify(HttpRequest $request): ?string
    {
        $ip = $request->server->getClientIp();

        return '' === $ip ? null : 'ip:' . $request->server->getClientIp();
    }
}
