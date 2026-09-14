<?php

declare(strict_types=1);

namespace Vasoft\Joke\Collections;

use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Exceptions\JokeException;

/**
 * Коллекция, предназначенная для хранения данных, которые могут быть безопасно приведены к строке.
 *
 * Все значения рассматриваются как скаляры или null. Нескалярные значения (массивы, объекты и т.д.)
 * считаются недопустимыми при строгом доступе.
 */
class StringCollection extends ReadonlyPropsCollection
{
    /**
     * Возвращает значение по ключу как строку. Если ключ отсутствует или значение не является
     * скаляром/null, возвращается заданное значение по умолчанию.
     *
     * @param string $key     Имя параметра
     * @param string $default Значение по умолчанию, если ключ не существует или тип недопустим
     *
     * @return string Строковое представление значения или значение по умолчанию
     */
    public function getStringOrDefault(string $key, string $default = ''): string
    {
        $value = parent::get($key, $default);

        return is_scalar($value) || null === $value ? (string) $value : $default;
    }

    /**
     * Возвращает значение по ключу как строку. Выбрасывает исключение, если:
     * - ключ не существует
     * - значение не является скаляром или null
     *
     * Для ошибки отсутствия ключа используется $missingFactory,
     * для ошибки неверного типа — $invalidTypeFactory.
     *
     * @param string                                        $key                Имя параметра
     * @param null|(callable(string,string): JokeException) $invalidTypeFactory Фабрика исключения при неверном типе;
     *                                                                          принимает ($key, $actualType)
     *
     * @return string Строковое представление значения
     *
     * @throws JokeException При отсутствии ключа или недопустимом типе значения
     */
    public function getStringOrFail(
        string $key,
        ?callable $invalidTypeFactory = null,
    ): string {
        $value = parent::getOrFail($key);

        if (!is_scalar($value) && null !== $value) {
            $factory = $invalidTypeFactory ?? static fn(
                string $k,
                string $type,
            ): JokeException => new ConfigException(
                'Property "' . $k
                . '" must be scalar or null to be used as string, got '
                . $type . '.',
            );

            throw $factory($key, get_debug_type($value));
        }

        return (string) $value;
    }
}
