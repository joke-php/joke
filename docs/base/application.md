# Приложение

Класс `Vasoft\Joke\Application\Application` является **точкой входа и центральным оркестратором** всего фреймворка. Он
управляет загрузкой маршрутов, выполнением middleware и обработкой HTTP-запросов от начала до конца.

## Жизненный цикл запроса

1. **Создание приложения**  
   В `bootstrap/app.php` создаётся экземпляр `Application` с DI-контейнером и путём к файлу маршрутов:

   ```php
   return new Application(
       dirname(__DIR__),    // базовый путь проекта
       new ServiceContainer()
   );
   ```

2. **Точка входа**  
   В `public/index.php` создаётся HTTP-запрос из глобальных переменных и передаётся в приложение:

   ```php
   $app = require_once __DIR__ . '/../bootstrap/app.php';
   $app->handle(HttpRequest::fromGlobals());
   ```

3. **Обработка запроса**  
   Приложение выполняет следующие шаги:
    - Загружает маршруты из файла маршрутов (по умолчанию `routes/web.php`),
    - Выполняет **глобальные middleware** (до определения маршрута),
    - Находит подходящий маршрут,
    - Выполняет **middleware маршрутизатора** и **middleware маршрута**,
    - Запускает обработчик маршрута,
    - Отправляет ответ клиенту.

## Автоматически регистрируемые компоненты

При создании `Application` автоматически настраиваются:

### Глобальные middleware

- `ExceptionMiddleware` (имя: `exception`) — перехватывает все исключения и преобразует их в HTTP-ответы.

### Middleware маршрутизатора

- `SessionMiddleware` (имя: `session`) — управляет сессией в **блокирующем режиме**,
- `CsrfMiddleware` (имя: `csrf`) — применяется **только к маршрутам из группы `web`**.

> Все маршруты, определённые в файле маршрутов, автоматически получают группу `web`.

## Обработка ответа

Фреймворк использует умную систему формирования ответа через компонент `ResponseBuilder`. Поведение зависит от
конфигурации приложения (`ApplicationConfig::getResponseClass`).

### Режим по умолчанию: Авто-определение

Если в конфигурации не указан конкретный класс ответа (значение пустое `''`), фреймворк автоматически выбирает тип
ответа на основе данных, возвращенных контроллером:

| Тип результата                       | Действие                        | Класс ответа   |
|--------------------------------------|---------------------------------|----------------|
| Массив                               | Автоматически кодируется в JSON | `JsonResponse` |
| Строка, число, объект, `null`        | Оборачивается в HTML            | `HtmlResponse` |
| Экземпляр `Response` (или наследник) | Используется напрямую           | Без изменений  |

**Примеры работы авто-режима:**

```php
// Вернёт JSON (автоматически)
$router->get('/api/data', fn() => ['status' => 'ok', 'id' => 42]);

// Вернёт HTML (автоматически)
$router->get('/page', fn() => '<h1>Hello World</h1>');

// Явный ответ (приоритет над автоматикой)
$router->get('/custom', fn() => (new JsonResponse())->setBody(['force' => true]));
```

### Режим строгого типа (Глобальная настройка)

Вы можете принудительно задать тип ответа для всего приложения в файле конфигурации `config/app.php`. В этом
режиме **авто-определение отключается**. Если тип данных не совпадает с ожидаемым телом ответа, может возникнуть ошибка.

```php
<?php
// config/app.php

declare(strict_types=1);

use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Templator\TemplatedResponse;
use Vasoft\Joke\Http\Response\JsonResponse;

return new ApplicationConfig()
    ->setResponseClass(JsonResponse::class);
```

> **Важно:** При явной установке класса (например, `JsonResponse::class`), попытка вернуть строку `"Hello"` приведет к
> ошибке, так как билдер попытается записать строку в свойство `$body`, ожидающее массив.

Эта архитектура позволяет писать лаконичные контроллеры для смешанных приложений (где есть и API, и страницы) и
переключаться в строгий режим для микросервисов одного типа.

## Регистрация middleware

Вы можете расширять поведение приложения через два метода:

### Глобальные middleware (до определения маршрута)

```php
$app->addMiddleware(CorsMiddleware::class);
```

Полезны для логирования, CORS, обработки ошибок.

### Middleware маршрутизатора (после определения маршрута)

```php
$app->addRouteMiddleware(AuthMiddleware::class, 'auth', ['api']);
```

Можно привязать к конкретным группам маршрутов.

> Именованные middleware можно **переопределять** — новый заменит старый, сохранив позицию в цепочке.

## Интеграция с DI-контейнером

Приложение автоматически регистрирует текущий `HttpRequest` в контейнере:

```php
$this->serviceContainer->registerSingleton(HttpRequest::class, $request);
```

Это позволяет внедрять запрос в любые сервисы или обработчики:

```php
function handle(UserService $users, HttpRequest $request) {
    $id = $request->uri()->pathSegment(2);
    // ...
}
```

## Обработка ошибок

Все необработанные исключения (включая `NotFoundException` при отсутствии маршрута) перехватываются
`ExceptionMiddleware` и преобразуются в корректные HTTP-ответы (обычно 500 или 404).

> Разработчику **не нужно** оборачивать `$app->handle()` в `try/catch` — всё обрабатывается автоматически.

## Пример полной настройки

```php
// bootstrap/app.php
session_set_cookie_params([
    'samesite' => 'Lax',
    'secure' => $_SERVER['HTTPS'] ?? false,
    'httponly' => true,
]);

$container = new ServiceContainer();
$container->registerSingleton(Logger::class, FileLogger::class);

return new Application(dirname(__DIR__), $container)
 ->addMiddleware(CorsMiddleware::class)
 ->addRouteMiddleware(AuthMiddleware::class, 'auth', ['admin']);
```

