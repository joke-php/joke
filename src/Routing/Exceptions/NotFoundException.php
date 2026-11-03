<?php

declare(strict_types=1);

namespace Vasoft\Joke\Routing\Exceptions;

use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\Response\ResponseStatus;

class NotFoundException extends RouterException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            $message,
            $code,
            $previous,
        );
    }

    public function getResponseStatus(): ResponseStatus
    {
        return ResponseStatus::NOT_FOUND;
    }
}
