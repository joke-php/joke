<?php

declare(strict_types=1);

namespace Vasoft\Joke\Exceptions;

/**
 * Исключение, возникающее при ошибке преобразования типа свойства.
 */
class ConversionException extends JokeException
{
    /**
     * @param string          $propertyName Имя свойства или параметра
     * @param mixed           $value        Фактическое полученное значение
     * @param string          $targetType   Целевой тип, к которому выполнялось преобразование
     * @param int             $code         Код ошибки
     * @param null|\Throwable $previous     Предыдущее исключение
     */
    public function __construct(
        string $propertyName,
        mixed $value,
        string $targetType,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $actualType = get_debug_type($value);

        parent::__construct(
            sprintf(
                'Property "%s" cannot be converted to %s, got %s.',
                $propertyName,
                $targetType,
                $actualType,
            ),
            $code,
            $previous,
        );
    }
}
