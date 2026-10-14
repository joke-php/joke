<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter\Exceptions;

use Vasoft\Joke\Exceptions\JokeException;

/**
 * Исключение, выбрасываемое при ошибках в работе ограничителя числа запросов.
 */
class RateLimiterException extends JokeException {}
