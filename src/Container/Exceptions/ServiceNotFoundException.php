<?php

declare(strict_types=1);

namespace Vasoft\Joke\Container\Exceptions;

class ServiceNotFoundException extends ContainerException
{
    public function __construct(string $ServiceName, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Service '{$ServiceName}' not found.", $code, $previous);
    }
}
