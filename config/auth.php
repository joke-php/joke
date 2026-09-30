<?php

declare(strict_types=1);

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Session\SessionAuthenticator;
use Vasoft\Joke\Demo\Auth\DemoUserProvider;
use Vasoft\Joke\Auth\Rights\ConfigRightsSource;

return new AuthConfig()
    ->addAuthenticator(SessionAuthenticator::class)
    ->addUserProvider(DemoUserProvider::class)
    ->addRightsSource(new ConfigRightsSource(['demo' => ['joke:personal']]));
