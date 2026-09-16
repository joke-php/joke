<?php

declare(strict_types=1);

use Vasoft\Joke\Http\Response\ResponseStatus;
use Vasoft\Joke\Tests\Fixtures\Controllers\InvokeController;
use Vasoft\Joke\Routing\Router;
use Vasoft\Joke\Tests\Fixtures\Controllers\SingleController;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\ResponseBuilder;

/**
 * @var Router $router
 */
$router->get(
    '/',
    static fn(ResponseBuilder $builder) => $builder->makeDefault()
        ->setBody(
            <<<'HTML'
                <ul>
                    <li><a href="/name/Alex">Hi Alex</a> Текстовый ответ. Имя можно менять</li>
                    <li><a href="/json/Alex">Hi Alex</a> Json ответ. Имя можно менять</li>
                    <li><a href="/invoke/property">__Invoke</a></li>
                    <li><a href="/shop">Список товаров</a></li>
                    <li><a href="/shop/an">Товары имеющие "an" в названии</a></li>
                    <li><a href="/shop/info">Вызов статического метода как замыкания</a></li>
                    <li><a href="/shop/infoNew">Вызов статического метода переданного строкой</a></li>
                </ul>
                HTML,
        ),
);
$router->get('/name/{name:slug}', static fn(string $name) => 'Hi ' . $name, 'hiName');
$router->get('/json/{name:slug}', static fn(string $name) => ['fio' => $name]);
$route = $router->get('/name-filtered/{name:slug}', static fn(string $name) => 'Hi ' . $name)->addGroup('filtered');
$router->get('/invoke/{prop}', InvokeController::class);
$router->get('/shop', [SingleController::class, 'index']);
$router->get('/shop/info', SingleController::info(...));
$router->get('/shop/infoNew', SingleController::class . '::info');
$router->get('/shop/{filter}', [SingleController::class, 'find']);

$routeHandler = static fn(HttpRequest $request) => [
    'id' => spl_object_id($request),
    'get' => $request->get->getAll(),
    'post' => $request->post->getAll(),
    'files' => $request->files->getAll(),
    'json' => $request->json,
];

$router->post('/queries', $routeHandler);
$router->put('/queries', $routeHandler);
$router->patch('/queries', $routeHandler);
$router->head('/queries', $routeHandler);
$router->get('/{*}', static fn(string $path, ResponseBuilder $builder) => $builder->makeDefault()
    ->setStatus(ResponseStatus::NOT_FOUND)
    ->setBody("Запрошен несуществующий путь: {$path}"));
// @todo Интерфейс, публичный/статический метод
