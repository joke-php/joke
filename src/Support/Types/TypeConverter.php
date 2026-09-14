<?php

declare(strict_types=1);

namespace Vasoft\Joke\Support\Types;

use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Exceptions\JokeException;

/**
 * Утилитарный класс для безопасного преобразования значений к ожидаемым скалярным типам или массивам.
 *
 * Все методы статические, не хранят состояние и выбрасывают исключения при невозможности безопасного преобразования.
 * Поддерживается кастомизация исключений через фабрику.
 */
final class TypeConverter
{
    /**
     * Преобразует значение в массив.
     *
     * Поддерживает следующие преобразования:
     * - массив - возвращается как есть
     * - непустая строка - разбивается по запятым на элементы массива (с trim)
     * - пустая строка или null - возвращается значение по умолчанию
     * - скалярное значение - массив с одним элементом
     *
     * @param mixed                   $value     Значение параметра
     * @param string                  $key       Имя параметра
     * @param array<int|string,mixed> $default   Значение по умолчанию значение равно пустой строке или null
     * @param non-empty-string        $separator Разделитель строки
     *
     * @return array<int|string, mixed> Преобразованное значение или значение по умолчанию
     *
     * @throws JokeException если значение не может быть преобразовано в массив
     */
    public static function toArray(
        mixed $value,
        string $key,
        array $default = [],
        string $separator = ',',
    ): array {
        if ('' === $value || null === $value) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return array_map('trim', explode($separator, $value));
        }
        if (is_scalar($value)) {
            return [$value];
        }

        throw new ConversionException($key, $value, 'array');
    }

    /**
     * Приводит значение к типу целое число.
     *
     * Поддерживает следующие преобразования:
     * - int - возвращается как есть
     * - строка с целым числом (включая отрицательные) - преобразуется в int
     * - float, представляющий целое число (например, 3.0) - преобразуется в int
     * - bool - true=1, false=0
     * - null или пустая строка - возвращается значение по умолчанию
     *
     * @param mixed  $value   Значение параметра
     * @param string $key     Имя параметра
     * @param int    $default Значение по умолчанию если значение равно null или пустая строка
     *
     * @return int Преобразованное значение или значение по умолчанию
     *
     * @throws JokeException если значение не может быть преобразовано в целое число
     */
    public static function toInt(mixed $value, string $key, int $default): int
    {
        if (null === $value || '' === $value) {
            return $default;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && false !== filter_var($value, FILTER_VALIDATE_INT)) {
            return (int) $value;
        }

        if (is_float($value) && 0.0 === fmod($value, 1.0)) {
            return (int) $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        throw new ConversionException($key, $value, 'int');
    }

    /**
     * Приводит значение к типу строка.
     *
     * Поддерживает следующие преобразования:
     * - string - возвращается как есть
     * - int/float - преобразуются в строку
     * - bool - true='1', false='0'
     * - null - возвращается значение по умолчанию
     * - пустая строка - возвращается как есть
     *
     * @param mixed  $value   Значение параметра
     * @param string $key     Имя параметра
     * @param string $default Значение по умолчанию, если значение равно null
     *
     * @return string Преобразованное значение или значение по умолчанию
     *
     * @throws JokeException если значение не может быть преобразовано в строку
     */
    public static function toString(
        mixed $value,
        string $key,
        string $default,
    ): string {
        if (null === $value) {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        throw new ConversionException($key, $value, 'string');
    }

    /**
     * Возвращает значение как логическое (boolean).
     *
     * Поддерживает следующие преобразования:
     * - bool - возвращается как есть
     * - строка: '1', 'true', 'yes', 'on', 'y' (регистронезависимо) - true;
     *           '0', 'false', 'no', 'off', 'n', '' - false
     * - int: 0 - false, любое другое число - true
     *
     * @param mixed  $value   Значение параметра
     * @param string $key     Имя параметра
     * @param bool   $default Значение по умолчанию, если значение равно null или пустая строка
     *
     * @return bool Преобразованное значение или значение по умолчанию
     *
     * @throws JokeException если строковое значение не распознано как булево
     *                       или значение не может быть преобразовано в boolean
     */
    public static function toBool(
        mixed $value,
        string $key,
        bool $default,
    ): bool {
        if (null === $value || '' === $value) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, ['1', 'true', 'yes', 'on', 'y'], true)) {
                return true;
            }
            if (in_array($lower, ['0', 'false', 'no', 'off', 'n'], true)) {
                return false;
            }

            throw new ConversionException($key, $value, 'bool');
        }

        if (is_int($value)) {
            return 0 !== $value;
        }

        throw new ConversionException($key, $value, 'bool');
    }

    /**
     * Приводит значение к типу число с плавающей точкой.
     *
     * Поддерживает следующие преобразования:
     * - float - возвращается как есть
     * - int - преобразуется в float
     * - числовая строка - преобразуется в float
     * - bool - true=1.0, false=0.0
     * - null или пустая строка - возвращается значение по умолчанию
     *
     * @param mixed  $value   Значение параметра
     * @param string $key     Имя параметра
     * @param float  $default Значение по умолчанию, если пустая строка или null
     *
     * @return float Преобразованное значение или значение по умолчанию
     *
     * @throws JokeException если строка не является числовой
     *                       или значение не может быть преобразовано в float
     */
    public static function toFloat(
        mixed $value,
        string $key,
        float $default,
    ): float {
        if (null === $value || '' === $value) {
            return $default;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        throw new ConversionException($key, $value, 'float');
    }
}
