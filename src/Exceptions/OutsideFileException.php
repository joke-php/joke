<?php

declare(strict_types=1);

namespace Vasoft\Joke\Exceptions;

class OutsideFileException extends FileSystemException
{
    public function __construct(string $path, string $basePath, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Resolved \"{$path}\" is outside of the {$basePath}.",
            $code,
            $previous,
        );
    }
}
