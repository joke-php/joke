<?php

declare(strict_types=1);

namespace Vasoft\Joke\Collections;

use Vasoft\Joke\Exceptions\Property\MissingPropertyException;

/**
 * Коллекция для хранения и управления HTTP или MIME-заголовками.
 *
 * Расширяет PropsCollection, добавляя регистронезависимость ключей
 * и специализированные методы для работы с Content-Type.
 * Все имена заголовков внутри коллекции автоматически приводятся к нижнему регистру.
 */
class HeadersCollection extends PropsCollection
{
    /**
     * Возвращает значение заголовка Content-Type.
     */
    public ?string $contentType {
        get => $this->props['content-type'] ?? null;
    }

    /**
     * Устанавливает значение заголовка Content-Type.
     *
     * @param string $value MIME-тип, например: 'text/html', 'application/json'
     */
    public function setContentType(string $value): static
    {
        $this->props['content-type'] = $value;

        return $this;
    }

    /**
     * Устанавливает значение заголовка.
     *
     * Имя заголовка автоматически приводится к нижнему регистру для обеспечения
     * регистронезависимости согласно стандартам RFC.
     *
     * @param string $key   Имя заголовка (регистр не имеет значения)
     * @param mixed  $value Значение заголовка
     */
    public function set(string $key, mixed $value): static
    {
        return parent::set(strtolower($key), $value);
    }

    /**
     * Возвращает значение заголовка по имени.
     *
     * @param string                                                         $key     Имя заголовка (регистр не имеет значения)
     * @param null|array<int|string,mixed>|bool|float|int|list<mixed>|string $default Значение по умолчанию
     *
     * @return null|string Значение заголовка или default
     */
    public function get(string $key, float|array|bool|int|string|null $default = null): ?string
    {
        if (null !== $default) {
            $default = (string) $default;
        }

        return parent::get(strtolower($key), $default);
    }

    /**
     * Удаляет заголовок по имени.
     *
     * @param string $key Имя удаляемого заголовка (регистр не имеет значения)
     */
    public function unset(string $key): static
    {
        return parent::unset(strtolower($key));
    }

    /**
     * Нормализует ключи массива, приводя их к нижнему регистру.
     *
     * @param array<int|string,mixed> $array Исходный массив заголовков
     *
     * @return array<string,mixed> Массив с ключами в нижнем регистре
     */
    protected function normalizeArrayKeys(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[strtolower((string) $key)] = $value;
        }

        return $result;
    }

    /**
     * Возвращает значение заголовка или выбрасывает исключение, если он отсутствует.
     *
     * @param string $key Имя заголовка (регистр не имеет значения)
     *
     * @return null|array<int|string,mixed>|bool|float|int|string Значение заголовка
     *
     * @throws MissingPropertyException если заголовок не найден
     */
    public function getOrFail(string $key): array|bool|float|int|string|null
    {
        return parent::getOrFail(strtolower($key));
    }
}
