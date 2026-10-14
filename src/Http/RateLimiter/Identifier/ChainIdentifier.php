<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter\Identifier;

use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Http\HttpRequest;

/**
 * Цепочка идентификаторов с семантикой "первый успешный".
 *
 * Последовательно вызывает вложенные идентификаторы и возвращает результат
 * первого, который вернул не-null значение. Если все идентификаторы вернули
 * null, возвращает null.
 *
 * Типичные сценарии:
 * - Приоритетная идентификация: API-ключ → сессия → IP
 * - Fallback-стратегии: основной метод → запасной метод
 *
 * Порядок идентификаторов в массиве определяет приоритет.
 *
 * @see ClientIdentifierInterface
 * @see GroupIdentifier
 */
readonly class ChainIdentifier implements ClientIdentifierInterface
{
    /**
     * Создаёт цепочку идентификаторов.
     *
     * @param list<ClientIdentifierInterface> $identifiers Упорядоченный список идентификаторов
     *                                                     в порядке убывания приоритета
     */
    public function __construct(
        private array $identifiers,
    ) {}

    /**
     * {@inheritDoc}
     *
     * Возвращает результат первого идентификатора, вернувшего не-null значение.
     * Если ни один идентификатор не смог определить клиента, возвращает null.
     */
    public function identify(HttpRequest $request): ?string
    {
        foreach ($this->identifiers as $identifier) {
            $result = $identifier->identify($request);
            if (null !== $result) {
                return $result;
            }
        }

        return null;
    }
}
