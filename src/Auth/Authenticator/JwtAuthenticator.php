<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth\Authenticator;

use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

class JwtAuthenticator implements AuthenticatorInterface
{
    public function authenticate(): UserInterface
    {
        return new User();
    }
}
