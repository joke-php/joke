# Реализованные middleware

Joke поставляется с набором встроенных middleware, обеспечивающих базовую функциональность веб-приложения: обработку
ошибок, управление сессией, защиту от CSRF-атак и настройку кросс-доменных запросов (CORS).

## ExceptionMiddleware

- Класс: Vasoft\Joke\Middleware\ExceptionMiddleware
- Уровень: глобальный
- Имя: StdMiddleware::EXCEPTION->value

Перехватывает необработанные исключения и преобразует их в корректные HTTP-ответы (например, 500 Internal Server Error)
и исключает ситуацию, когда пользователь увидит «голые» исключения PHP.

Регистрируется автоматически — дополнительная настройка не требуется.

## SessionMiddleware

- Класс: `Vasoft\Joke\Http\Middleware\SessionMiddleware`
- Уровень: маршрутизатора
- Имя: StdMiddleware::SESSION->value
- Режим: блокирующий (сессия остаётся открытой на всё время обработки запроса)

Запускает сессию, загружает данные и сохраняет их после выполнения цепочки middleware и обработчика. Это поведение по
умолчанию для большинства веб-приложений.

Используется автоматически для всех маршрутов из routes/web.php.

## ReadonlySessionMiddleware

- Класс: `Vasoft\Joke\Http\Middleware\ReadonlySessionMiddleware`
- Режим: неблокирующий (данные считываются, сессия немедленно закрывается)

Предназначен для сценариев, где:

- сессия нужна только для чтения (например, проверка авторизации),
- важна высокая параллельность (несколько AJAX-запросов от одного пользователя).

> Не сохраняет изменения в сессию. Если вам нужно записать данные — используйте SessionMiddleware.

Чтобы заменить стандартный middleware сессии для всех маршрутов:

```php
use Vasoft\Joke\Middleware\StdMiddleware;
use Vasoft\Joke\Http\Middleware\ReadonlySessionMiddleware;

// В bootstrap/app.php 
$app->addRouteMiddleware(ReadonlySessionMiddleware::class, StdMiddleware::SESSION->value);
```

Чтобы заменить стандартный middleware сессии для конкретного маршрута:

```php
use Vasoft\Joke\Middleware\StdMiddleware;
use Vasoft\Joke\Http\Middleware\ReadonlySessionMiddleware;

// В routes/web.php 
$router->get('/informer', Informer::class)
  ->addMiddleware(ReadonlySessionMiddleware::class, StdMiddleware::SESSION->value);
```

## CsrfMiddleware

- **Класс:** `Vasoft\Joke\Http\Csrf\CsrfMiddleware`
- **Уровень:** маршрутизатора
- **Имя:** `StdMiddleware::CSRF->value`
- **Применяется к:** группе `StdGroup::WEB->value` (т.е. всем маршрутам из `web.php`)

Обеспечивает защиту от межсайтовой подделки запроса (CSRF). Вся логика работы с токенами делегирована классу [
`CsrfTokenManager`](#csrftokenmanager).

### Как работает мидлвар

1. **Валидация запроса:** Вызывает `CsrfTokenManager::validate()` для проверки токена.
2. **Обработка запроса:** Передаёт управление следующему мидлвару или контроллеру.
3. **Внедрение токена:** Вызывает `CsrfTokenManager::attach()` для добавления токена в ответ.

> Примечание: Если вы используете режим доставки COOKIE, убедитесь, что флаг Secure в настройках CookieConfig
> соответствует вашему окружению. На продакшене (HTTPS) это критически важно для защиты токена от перехвата.

### Проверка токена

Для небезопасных HTTP-методов (POST, PUT, DELETE, PATCH) мидлвар проверяет совпадение клиентского токена с серверным.
Токен ищется в порядке приоритета:

1. GET/POST параметр: `?csrf_token=...` или `csrf_token=...` (тело запроса)
2. HTTP-заголовок: `X-Csrf-Token: ...`
3. Cookie: `XSRF-TOKEN=...`

При несоответствии или отсутствии токена выбрасывает `CsrfMismatchException` (HTTP 403).

### Доставка токена клиенту

Способ доставки определяется конфигурацией [`CsrfConfig`](#csrfconfig):

| Режим                     | Куда добавляется                        | Рекомендуется для                        |
|:--------------------------|:----------------------------------------|:-----------------------------------------|
| **HEADER** (по умолчанию) | Заголовок `X-Csrf-Token`                | API, SPA-приложений                      |
| **COOKIE**                | Cookie `XSRF-TOKEN` (`httpOnly: false`) | Веб-приложений с паттерном Double Submit |

Токен генерируется автоматически при первом запросе. Вам нужно только передать его в форму или заголовок следующего
запроса.

---

### CsrfTokenManager

Сервис для управления жизненным циклом CSRF-токенов. Доступен через внедрение зависимости в контроллеры.

#### Публичные методы

| Метод                                         | Описание                                             | Когда использовать                     |
|:----------------------------------------------|:-----------------------------------------------------|:---------------------------------------|
| **`validate(HttpRequest $request): string`**  | Проверяет токен запроса, возвращает актуальный токен | В мидлваре (автоматически)             |
| **`reset(HttpRequest, Response): string`**    | Перегенерирует токен и добавляет в ответ             | Логин, смена пароля, эскалация прав    |
| **`invalidate(HttpRequest, Response): void`** | Семантический алиас `reset()` для логаута            | Логаут, блокировка пользователя        |
| **`attach(HttpRequest, Response): void`**     | Добавляет текущий токен из сессии в ответ            | Ручное внедрение в кастомных сценариях |

#### Пример использования в контроллере

```php
public function login(HttpRequest $request, Response $response, CsrfTokenManager $csrf): Response
{
    // ... аутентификация ...
    $csrf->reset($request, $response); // Перегенерировать токен для новой сессии
    return $response;
}

public function logout(HttpRequest $request, Response $response, CsrfTokenManager $csrf): Response
{
    // ... очистка сессии ...
    $csrf->invalidate($request, $response); // Отправить клиенту новый токен
    return $response;
}
```

---

### CsrfConfig

Конфигурация CSRF-защиты.

#### Настройки

| Параметр        | Тип                 | По умолчанию         | Описание                                |
|:----------------|:--------------------|:---------------------|:----------------------------------------|
| `transportMode` | `CsrfTransportMode` | `HEADER`             | Режим доставки токена (HEADER / COOKIE) |
| `cookieConfig`  | `CookieConfig`      | `new CookieConfig()` | Настройки кук (для режима COOKIE)       |

#### Пример настройки

```php
$csrfConfig = (new CsrfConfig())
    ->setTransportMode(CsrfTransportMode::COOKIE)
    ->setCookieConfig(
        (new CookieConfig())
            ->setLifetime(3600)
            ->setSecure($env->get('COOKIE_SECURE', true))
            ->setSameSite('Strict')
    );
```

---

### Константы

| Класс              | Константа           | Значение         |
|:-------------------|:--------------------|:-----------------|
| `CsrfTokenManager` | `CSRF_TOKEN_NAME`   | `'csrf_token'`   |
| `CsrfTokenManager` | `CSRF_TOKEN_HEADER` | `'X-Csrf-Token'` |
| `CsrfTokenManager` | `CSRF_TOKEN_COOKIE` | `'XSRF-TOKEN'`   |

> **Важно:** Константы в `CsrfMiddleware` помечены как `@deprecated`. Используйте константы из `CsrfTokenManager`.

---

### Исключения

| Исключение              | Когда выбрасывается                                     | HTTP-статус |
|:------------------------|:--------------------------------------------------------|:------------|
| `CsrfMismatchException` | Токен клиента не совпадает с серверным                  | 403         |
| `CookieException`       | Ошибка добавления CSRF-куки в ответ                     | 500         |
| `RandomException`       | Не удалось сгенерировать криптографически стойкий токен | 500         |

---

## CorsMiddleware

- **Класс:** `Vasoft\Joke\Http\Cors\CorsMiddleware`
- **Уровень:** глобальный
- **Имя:** `StdMiddleware::CORS->value`

Реализует механизм Cross-Origin Resource Sharing (CORS), позволяющий браузерам выполнять кросс-доменные запросы к вашему
API.
Middleware автоматически обрабатывает как простые запросы, так и предварительные (preflight) запросы методом `OPTIONS`.

Регистрируется автоматически в глобальной цепочке. Для активации необходимо изменить конфигурацию `CorsConfig`.

### Как работает мидлвар

1. **Проверка Origin:** Если запрос содержит заголовок `Origin`, middleware проверяет его наличие в списке разрешенных
   источников.
2. **Preflight (OPTIONS):** Если метод запроса `OPTIONS`, middleware валидирует запрашиваемые методы и заголовки (
   `Access-Control-Request-Method`, `Access-Control-Request-Headers`) и возвращает пустой успешный ответ с
   CORS-заголовками.
3. **Обычный запрос:** Если метод не `OPTIONS`, middleware пропускает запрос дальше по цепочке, а затем добавляет
   необходимые CORS-заголовки в ответ.
4. **Блокировка:** Если источник не разрешен или метод/заголовки не соответствуют конфигурации, возвращается статус
   `403 Forbidden`.

### CorsConfig

Конфигурация правил CORS. По умолчанию CORS отключен (`allowedCors = false`).

#### Настройки

| Параметр           | Тип                | По умолчанию                                            | Описание                                                                       |
|:-------------------|:-------------------|:--------------------------------------------------------|:-------------------------------------------------------------------------------|
| `allowedCors`      | `bool`             | `false`                                                 | Главный переключатель функциональности CORS.                                   |
| `origins`          | `list<string>`     | `['*']`                                                 | Список разрешенных доменов. `'*'` означает все домены.                         |
| `methods`          | `list<HttpMethod>` | `GET, POST, PUT, PATCH, DELETE, OPTIONS`                | Разрешенные HTTP-методы.                                                       |
| `headers`          | `list<string>`     | `['Content-Type', 'Authorization', 'X-Requested-With']` | Разрешенные заголовки в запросе (`Access-Control-Allow-Headers`).              |
| `exposeHeaders`    | `list<string>`     | `[]`                                                    | Заголовки ответа, доступные для чтения в JS (`Access-Control-Expose-Headers`). |
| `maxAge`           | `int`              | `3600`                                                  | Время кэширования preflight-запроса в секундах.                                |
| `allowCredentials` | `bool`             | `false`                                                 | Разрешает отправку куки и заголовков авторизации.                              |

> **Важно:** Использование `allowCredentials = true` совместно с `origins = ['*']` запрещено спецификацией CORS и
> вызовет исключение `ConfigException` при попытке заморозить конфиг. Указывайте конкретные домены.

#### Пример настройки

Разрешить запросы с конкретного фронтенда с передачей куки:

```php
use Vasoft\Joke\Http\Cors\CorsConfig;
use Vasoft\Joke\Http\HttpMethod;

$corsConfig = (new CorsConfig())
    ->setAllowedCors(true)
    ->setOrigins(['https://my-frontend.com'])
    ->setAllowCredentials(true)
    ->setMethods([HttpMethod::GET, HttpMethod::POST])
    ->setExposeHeaders(['X-Csrf-Token']); // Чтобы JS мог прочитать этот заголовок
```

Разрешить все домены (без куки):

```php
$corsConfig = (new CorsConfig())
    ->setAllowedCors(true)
    ->setOrigins(['*'])
    ->setAllowCredentials(false);
```

### Работа с заголовками

Middleware автоматически добавляет заголовок `Vary: Origin` к ответам, если используется не wildcard-источник, чтобы
корректно работать с кэшированием.

Для того чтобы JavaScript мог читать кастомные заголовки ответа (например, CSRF-токен), их необходимо явно указать в
`exposeHeaders`:

```php
$corsConfig->setExposeHeaders(['X-Csrf-Token', 'X-Custom-Header']);
```

Без этого браузер скроет эти заголовки от скрипта, даже если сервер их отправил.