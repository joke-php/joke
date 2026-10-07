<?php

declare(strict_types=1);

namespace Vasoft\Joke\Contract\Cache;

use Vasoft\Joke\Cache\Exceptions\CacheException;

/**
 * Определяет контракт для реализации механизма кеширования.
 *
 * Интерфейс предоставляет базовые методы для сохранения, извлечения и удаления данных
 * с поддержкой времени жизни (TTL). Реализации могут использовать различные хранилища
 * (файловая система, память, базы данных), сохраняя единый способ взаимодействия.
 *
 * Ключи должны быть уникальными строками в рамках экземпляра кэша.
 */
interface CacheInterface
{
    /**
     * Устанавливает префикс (пространство имен) для изоляции группы кэша.
     *
     * Префикс определяет логическую группу ключей. Реализация ДОЛЖНА обеспечивать:
     * 1. Полную изоляцию ключей разных префиксов.
     * 2. Поддержку иерархических префиксов через разделитель `/`.
     *    При вызове clear() для родительского префикса (например, 'pages')
     *    должны удаляться все элементы вложенных префиксов ('pages/list', 'pages/detail').
     *
     * Метод должен вызываться один раз после создания экземпляра.
     * Повторный вызов игнорируется или выбрасывает исключение (зависит от реализации).
     *
     * @param non-empty-string $prefix Имя префикса. Допускаются буквы, цифры, `_`, `-`, `/`.
     *                                 Не должен начинаться или заканчиваться на `/`.
     *
     * @return $this
     *
     * @throws CacheException При некорректном формате префикса
     */
    public function setPrefix(string $prefix): static;

    /**
     * Извлекает элемент из кэша по ключу.
     *
     * @param string $key     Уникальный идентификатор элемента
     * @param mixed  $default Значение по умолчанию, если элемент не найден
     *
     * @return mixed Значение элемента или $default
     *
     * @throws CacheException При ошибках кеширования или некорректном ключе
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Сохраняет элемент в кэше.
     *
     * При отрицательном ttl кеш не сохраняется, но возвращается true.
     *
     * @param string                 $key   Уникальный идентификатор элемента
     * @param mixed                  $value Сохраняемое значение
     * @param null|\DateInterval|int $ttl   Время жизни в секундах или DateInterval. Null = бессрочно
     *
     * @return bool True при успешном сохранении
     *
     * @throws CacheException При ошибках кеширования или некорректном ключе
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool;

    /**
     * Удаляет элемент из кэша.
     *
     * @param string $key Уникальный идентификатор элемента
     *
     * @return bool True, если элемент существовал и был удален, или если его не было
     *
     * @throws CacheException При ошибках кеширования или некорректном ключе
     */
    public function delete(string $key): bool;

    /**
     * Очищает все элементы в пространстве имен (префиксе) кэша.
     *
     * @return bool True при успешной очистке
     */
    public function clear(): bool;

    /**
     * Извлекает несколько элементов из кэша.
     *
     * @param iterable<string> $keys    Список ключей
     * @param mixed            $default Значение по умолчанию для отсутствующих ключей
     *
     * @return iterable<string, mixed> Ассоциативный массив пар ключ-значение
     *
     * @throws CacheException При ошибках кеширования
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * Сохраняет несколько элементов в кэше.
     *
     * @param iterable<string, mixed> $values Ассоциативный массив пар ключ-значение
     * @param null|\DateInterval|int  $ttl    Время жизни для всех элементов
     *
     * @return bool True, если все элементы были успешно сохранены
     *
     * @throws CacheException При ошибках кеширования
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool;

    /**
     * Удаляет несколько элементов из кэша.
     *
     * @param iterable<string> $keys Список ключей
     *
     * @return bool True, если все указанные элементы были успешно удалены
     *
     * @throws CacheException При ошибках кеширования
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * Проверяет наличие элемента в кэше.
     *
     * @param string $key Уникальный идентификатор элемента
     *
     * @return bool True, если элемент существует и не истек срок его действия
     *
     * @throws CacheException При ошибках кеширования или некорректном ключе
     */
    public function has(string $key): bool;
}
