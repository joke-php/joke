<?php

declare(strict_types=1);

namespace Vasoft\Joke\Middleware;

/**
 * Наименования стандартных middleware для обеспечения единственного экземпляра.
 */
enum StdMiddleware: string
{
    case SESSION = 'session';
    case EXCEPTION = 'exception';
    case CSRF = 'csrf';
    case CORS = 'cors';
    case RATE_LIMITER = 'rate_limiter';
}
