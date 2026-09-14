<?php

declare(strict_types=1);

namespace Vasoft\Joke\Exceptions\Property;

class MissingPropertyException extends PropertyException
{
    public function __construct(string $propertyName, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Property "' . $propertyName . '" does not exist.',
            $code,
            $previous,
        );
    }
}
