<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Fixtures\Http\RateLimiter;

use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\HttpRequest;

class TestingIdentifier implements ClientIdentifierInterface
{
    public function __construct(
        private readonly string $id = 'default',
        private readonly ?JokeException $exception = null,
    ) {}

    public int $count = 0;

    public function identify(HttpRequest $request): string
    {
        ++$this->count;
        if (null !== $this->exception) {
            throw $this->exception;
        }

        return $this->id;
    }
}
