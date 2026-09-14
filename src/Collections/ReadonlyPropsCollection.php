<?php

declare(strict_types=1);

namespace Vasoft\Joke\Collections;

use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Exceptions\Property\EmptyPropertyException;
use Vasoft\Joke\Exceptions\Property\MissingPropertyException;
use Vasoft\Joke\Support\Types\TypeConverter;

/**
 * Коллекция для хранения данными в виде ключ-значение. Только для чтения.
 *
 * Поддерживает значения любого скалярного типа, массивы и null.
 */
class ReadonlyPropsCollection
{
    /** @var array<string,mixed> Набор свойств */
    protected array $props = [];

    /**
     * @param array<int|string,mixed> $props Начальный набор свойств
     */
    public function __construct(array $props)
    {
        $this->props = $this->normalizeArrayKeys($props);
    }

    /**
     * Возвращает значение свойства по ключу.
     *
     * @param string                                                         $key     Имя свойства
     * @param null|array<int|string,mixed>|bool|float|int|list<mixed>|string $default Значение по умолчанию, если ключ не существует
     *
     * @return null|array<int|string,mixed>|bool|float|int|list<mixed>|string Значение свойства или значение по умолчанию
     */
    public function get(string $key, array|bool|float|int|string|null $default = null): array|bool|float|int|string|null
    {
        return $this->props[$key] ?? $default;
    }

    /**
     * Возвращает значение как массив.
     *
     * Поддерживает следующие преобразования:
     * - массив → возвращается как есть
     * - непустая строка → разбивается по запятым на элементы массива (с trim)
     * - пустая строка или null → возвращается значение по умолчанию
     *
     * @param string                  $key       Имя параметра
     * @param array<int|string,mixed> $default   Значение по умолчанию, если ключ не существует или значение равно пустой строке или null
     * @param non-empty-string        $separator Разделитель строки
     *
     * @return array<int|string, mixed> Преобразованное значение или значение по умолчанию
     *
     * @throws ConversionException если значение не может быть преобразовано в массив
     */
    public function getArray(
        string $key,
        array $default = [],
        string $separator = ',',
    ): array {
        $value = $this->get($key);

        return TypeConverter::toArray($value, $key, $default, $separator);
    }

    /**
     * Возвращает значение как массив. Выбрасывает исключение если параметр не зарегистрирован в коллекции.
     *
     * Поддерживает следующие преобразования:
     * - массив → возвращается как есть
     * - непустая строка → разбивается по запятым на элементы массива (с trim)
     * - пустая строка или null → выбрасывает исключение
     *
     * @param string           $key       Имя параметра
     * @param non-empty-string $separator Разделитель строки
     *
     * @return array<int|string, mixed> Преобразованное значение
     *
     * @throws ConversionException      если значение не может быть преобразовано в массив
     * @throws EmptyPropertyException   если параметр имеет пустое значение
     * @throws MissingPropertyException если параметр не существует в коллекции
     */
    public function getArrayOrFail(
        string $key,
        string $separator = ',',
    ): array {
        $value = $this->getOrFail($key);
        if ('' === $value || null === $value) {
            throw new EmptyPropertyException($key);
        }

        return TypeConverter::toArray($value, $key, separator: $separator);
    }

    /**
     * Возвращает значение как целое число.
     *
     * Поддерживает следующие преобразования:
     * - int → возвращается как есть
     * - строка с целым числом (включая отрицательные) → преобразуется в int
     * - float, представляющий целое число (например, 3.0) → преобразуется в int
     * - bool → true=1, false=0
     * - null или пустая строка → возвращается значение по умолчанию
     *
     * @param string $key     Имя параметра
     * @param int    $default Значение по умолчанию, если ключ не существует, значение равно null или пустая строка
     *
     * @throws ConversionException если значение не может быть преобразовано в целое число
     */
    public function getInt(string $key, int $default): int
    {
        $value = $this->get($key);

        return TypeConverter::toInt($value, $key, $default);
    }

    /**
     * Возвращает значение как целое число. Выбрасывает исключение если параметр не зарегистрирован в коллекции.
     *
     * Поддерживает следующие преобразования:
     * - int → возвращается как есть
     * - строка с целым числом (включая отрицательные) → преобразуется в int
     * - float, представляющий целое число (например, 3.0) → преобразуется в int
     * - bool → true=1, false=0
     * - null или пустая строка → выбрасывает исключение
     *
     * @param string $key Имя параметра
     *
     * @throws ConversionException      если значение не может быть преобразовано в int
     * @throws EmptyPropertyException   если параметр имеет пустое значение
     * @throws MissingPropertyException если параметр не существует в коллекции
     */
    public function getIntOrFail(string $key): int
    {
        $value = $this->getOrFail($key);
        if (null === $value || '' === $value) {
            throw new EmptyPropertyException($key);
        }

        return TypeConverter::toInt($value, $key, 0);
    }

    /**
     * Возвращает значение как строку.
     *
     * Поддерживает следующие преобразования:
     * - string → возвращается как есть
     * - int/float → преобразуются в строку
     * - bool → true='1', false='0'
     * - null → возвращается значение по умолчанию
     * - пустая строка → возвращается как есть
     *
     * @param string $key     Имя параметра
     * @param string $default Значение по умолчанию, если значение равно null или ключ не существует
     *
     * @return string Преобразованное значение или значение по умолчанию
     *
     * @throws ConversionException если значение не может быть преобразовано в строку
     */
    public function getString(string $key, string $default): string
    {
        $value = $this->get($key);

        return TypeConverter::toString($value, $key, $default);
    }

    /**
     * Возвращает значение как строку. Выбрасывает исключение если параметр не зарегистрирован в коллекции.
     *
     * Поддерживает следующие преобразования:
     * - string → возвращается как есть
     * - int/float → преобразуются в строку
     * - bool → true='1', false='0'
     * - null → бросает исключение
     * - пустая строка → возвращается как есть
     *
     * @param string $key Имя параметра
     *
     * @return string Преобразованное значение
     *
     * @throws ConversionException      если значение не может быть преобразовано в string
     * @throws EmptyPropertyException   если параметр имеет пустое значение
     * @throws MissingPropertyException если параметр не существует в коллекции
     */
    public function getStringOrFail(string $key): string
    {
        $value = $this->getOrFail($key);
        if (null === $value) {
            throw new EmptyPropertyException($key);
        }

        return TypeConverter::toString($value, $key, '');
    }

    /**
     * Возвращает значение как логическое (boolean).
     *
     * Поддерживает следующие преобразования:
     * - bool → возвращается как есть
     * - строка: '1', 'true', 'yes', 'on', 'y' (регистронезависимо) → true;
     *           '0', 'false', 'no', 'off', 'n', '' → false
     * - int: 0 → false, любое другое число → true
     *
     * @param string $key     Имя параметра
     * @param bool   $default Значение по умолчанию, если ключ не существует или значение равно null или пустая строка
     *
     * @return bool Преобразованное значение или значение по умолчанию
     *
     * @throws ConversionException если строковое значение не распознано как булево
     *                             или значение не может быть преобразовано в boolean
     */
    public function getBool(string $key, bool $default): bool
    {
        $value = $this->get($key);

        return TypeConverter::toBool($value, $key, $default);
    }

    /**
     * Возвращает значение как логическое (boolean). Выбрасывает исключение если параметр не зарегистрирован в коллекции.
     *
     * Поддерживает следующие преобразования:
     * - bool → возвращается как есть
     * - строка: '1', 'true', 'yes', 'on', 'y' (регистронезависимо) → true;
     *           '0', 'false', 'no', 'off', 'n' → false
     * - int: 0 → false, любое другое число → true
     * - пустая строки или null: бросает исключение
     *
     * @param string $key Имя параметра
     *
     * @return bool Преобразованное значение
     *
     * @throws ConversionException      если значение не может быть преобразовано в boolean
     * @throws EmptyPropertyException   если параметр имеет пустое значение
     * @throws MissingPropertyException если параметр не существует в коллекции
     */
    public function getBoolOrFail(string $key): bool
    {
        $value = $this->getOrFail($key);
        if (null === $value || '' === $value) {
            throw new EmptyPropertyException($key);
        }
        // true передаётся лишь для удовлетворения сигнатуры toBool().
        return TypeConverter::toBool($value, $key, true);
    }

    /**
     * Возвращает значение как число с плавающей точкой.
     *
     * Поддерживает следующие преобразования:
     * - float → возвращается как есть
     * - int → преобразуется в float
     * - числовая строка → преобразуется в float
     * - bool → true=1.0, false=0.0
     * - null или пустая строка → возвращается значение по умолчанию
     *
     * @param string $key     Имя параметра
     * @param float  $default Значение по умолчанию, если ключ не найден, пустая строка или null
     *
     * @return float Преобразованное значение или значение по умолчанию
     *
     * @throws ConversionException если строка не является числовой
     *                             или значение не может быть преобразовано в float
     */
    public function getFloat(string $key, float $default): float
    {
        $value = $this->get($key);

        return TypeConverter::toFloat($value, $key, $default);
    }

    /**
     * Возвращает значение как число с плавающей точкой. Выбрасывает исключение если параметр не зарегистрирован в коллекции.
     *
     * Поддерживает следующие преобразования:
     * - float → возвращается как есть
     * - int → преобразуется в float
     * - числовая строка → преобразуется в float
     * - bool → true=1.0, false=0.0
     * - null или пустая строка → бросает исключение
     *
     * @param string $key Имя параметра
     *
     * @return float Преобразованное значение
     *
     * @throws ConversionException      если значение не может быть преобразовано в float
     * @throws EmptyPropertyException   если параметр имеет пустое значение
     * @throws MissingPropertyException если параметр не существует в коллекции
     */
    public function getFloatOrFail(string $key): float
    {
        $value = $this->getOrFail($key);
        if (null === $value || '' === $value) {
            throw new EmptyPropertyException($key);
        }

        return TypeConverter::toFloat($value, $key, 0);
    }

    /**
     * Возвращает все свойства в виде ассоциативного массива.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->props;
    }

    /**
     * Проверяет, существует ли заданный параметр в коллекции.
     *
     * @param string $key Имя параметра
     *
     * @return bool true, если существует
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->props);
    }

    /**
     * Возвращает значение свойства по ключу, если не существует - выбрасывает исключение.
     *
     * @param string $key Имя параметра
     *
     * @return null|array<int|string,mixed>|bool|float|int|list<mixed>|string
     *
     * @throws MissingPropertyException
     */
    public function getOrFail(string $key): array|bool|float|int|string|null
    {
        if (!$this->has($key)) {
            throw new MissingPropertyException($key);
        }

        return $this->props[$key];
    }

    /**
     * @param array<int|string,mixed> $array
     *
     * @return array<string,mixed>
     */
    protected static function normalizeArrayKeys(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
