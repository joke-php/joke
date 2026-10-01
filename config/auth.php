<?php

declare(strict_types=1);

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Jwt\SimpleJwtHandler;
use Vasoft\Joke\Auth\Session\SessionAuthenticator;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Demo\Auth\DemoUserProvider;
use Vasoft\Joke\Auth\Rights\ConfigRightsSource;
use Vasoft\Joke\Auth\Jwt\JwtAuthenticator;
use Vasoft\Joke\Http\HttpRequest;

return new AuthConfig()
    ->addAuthenticator(static function (HttpRequest $request, Environment $env) {
        // Если переменная JWT_SECRET не задана или короче 32 символов,
        // конструктор SimpleJwtHandler выбросит ConfigException.
        // Это гарантирует, что приложение не запустится с уязвимым ключом.
        $jwtHandler = new SimpleJwtHandler($env->get('JWT_SECRET', ''));

        return new JwtAuthenticator($request, $jwtHandler);
    })
    ->addAuthenticator(SessionAuthenticator::class)
    ->addUserProvider(DemoUserProvider::class)
    ->addRightsSource(
        new ConfigRightsSource([
            'demo' => ['joke:personal'],
            'api_user_1' => ['api:read', 'api:write'],
            'api_user_2' => ['api:read'],
        ]),
    );
