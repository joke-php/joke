<?php

declare(strict_types=1);

namespace Vasoft\Joke\Demo\Auth;

use Vasoft\Joke\Auth\Jwt\JwtAuthenticator;
use Vasoft\Joke\Auth\Jwt\SimpleJwtHandler;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Http\Response\ResponseBuilder;

class ApiAuthController
{
    private readonly SimpleJwtHandler $jwtHandler;

    public function __construct(
        Environment $env,
    ) {
        $this->jwtHandler = new SimpleJwtHandler($env->get('JWT_SECRET', ''));
    }

    public function issueToken(ResponseBuilder $builder): Response
    {
        $token = $this->jwtHandler->encode([
            'id' => 'api_user_1',
            'scope' => ['api:read', 'api:write'],
        ], 86400); // 24 часа
        $response = $builder->make(['token' => $token]);
        $response->cookies->add(JwtAuthenticator::COOKIE_NAME, $token);

        return $response;
    }
}
