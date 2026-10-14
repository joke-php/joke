<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter\Identifier;

use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Http\HttpRequest;

/**
 * Идентификатор клиента по комбинации IP-адреса и User-Agent.
 *
 * Формирует уникальный идентификатор вида "ip:{address}:ua:{md5_hash}",
 * где хэш вычисляется от значения заголовка User-Agent.
 *
 * Используется, когда необходимо различать клиентов за одним NAT/IP,
 * но с разными браузерами/приложениями.
 *
 * Возвращает null, если IP-адрес или User-Agent пусты, что позволяет
 * использовать этот идентификатор в составе {@see ChainIdentifier}.
 *
 * @see ClientIdentifierInterface
 * @see ChainIdentifier
 */
class IpWithUserAgentIdentifier implements ClientIdentifierInterface
{
    /**
     * {@inheritDoc}
     *
     * Формирует идентификатор в формате "ip:{address}:ua:{md5(user_agent)}".
     * Возвращает null, если IP-адрес или User-Agent пусты.
     *
     * @throws ConversionException При ошибках приведения типов
     */
    public function identify(HttpRequest $request): ?string
    {
        $ip = $request->server->getClientIp();
        $ua = $request->headers->getString('User-Agent', '');

        return '' === $ip || '' === $ua ? null : sprintf('ip:%s:ua:%s', $ip, md5($ua));
    }
}
