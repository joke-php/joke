<?php

declare(strict_types=1);

use Vasoft\Joke\Http\RateLimiter\RateLimiterConfig;

return new RateLimiterConfig()
    ->setEnabled(true)
    ->setWithHeaders(true);
