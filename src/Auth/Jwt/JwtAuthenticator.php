<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth\Jwt;

use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

class JwtAuthenticator implements AuthenticatorInterface
{
    public const string HEADER_NAME = 'Authorization';
    public const string BEARER_PREFIX = 'Bearer ';
    public const string COOKIE_NAME = 'jwt';
    public function authenticate(): UserInterface
    {
        return new User();
    }
}
