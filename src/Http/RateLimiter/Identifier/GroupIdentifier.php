<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter\Identifier;

use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Http\HttpRequest;

/**
 * Составной идентификатор, объединяющий несколько идентификаторов в один ключ.
 *
 * Формирует идентификатор вида "group:{part1}{separator}{part2}{separator}...",
 * где каждая часть — результат работы отдельного {@see ClientIdentifierInterface}.
 *
 * Если любой из вложенных идентификаторов возвращает null, весь составной
 * идентификатор также возвращает null (строгая семантика: все части обязательны).
 *
 * Пример использования: группировка по IP + API-ключу для раздельного
 * учёта лимитов разных пользователей за одним IP.
 *
 * @see ClientIdentifierInterface
 * @see ChainIdentifier
 */
readonly class GroupIdentifier implements ClientIdentifierInterface
{
    /**
     * Создаёт составной идентификатор.
     *
     * @param list<ClientIdentifierInterface> $identifiers Упорядоченный список идентификаторов,
     *                                                     результаты которых объединяются
     * @param string                          $separator   Разделитель между частями в итоговом ключе
     */
    public function __construct(
        private array $identifiers,
        private string $separator = '#',
    ) {}

    /**
     * {@inheritDoc}
     *
     * Последовательно вызывает каждый вложенный идентификатор и объединяет
     * результаты через разделитель. Возвращает null, если хотя бы один
     * идентификатор вернул null.
     */
    public function identify(HttpRequest $request): ?string
    {
        $id = [];
        foreach ($this->identifiers as $identifier) {
            $part = $identifier->identify($request);
            if (null === $part) {
                return null;
            }

            $id[] = $part;
        }

        return 'group:' . implode($this->separator, $id);
    }
}
