<?php

/** @var Environment $env */
declare(strict_types=1);

use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Http\Response\JsonResponse;

return new ApplicationConfig()
    ->setFileRoues('routes/web.php')
    ->setGroupResponseClass('json', JsonResponse::class);
