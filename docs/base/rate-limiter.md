# Ограничитель частоты запросов (Rate Limiter)

Ограничитель частоты запросов защищает приложение от злоупотреблений и перегрузок, ограничивая количество запросов от
одного клиента за
определённый промежуток времени. При превышении лимита клиент получает ответ `429 Too Many Requests`.

## Подключение

Ограничитель частоты запросов поставляется как встроенный middleware. Для активации достаточно включить его в
конфигурации и
зарегистрировать на нужных маршрутах.

### 1. Включите в конфигурации

По умолчанию ограничитель частоты запросов отключен. Активируйте его через `RateLimiterConfig`:

```php
// config/rate-limiter.php
use Vasoft\Joke\Http\RateLimiter\RateLimiterConfig;

return new RateLimiterConfig()
    ->setEnabled(true)
    ->setWithHeaders(true);
```

| Параметр      | Тип  | По умолчанию | Описание                                                                   |
|:--------------|:-----|:-------------|:---------------------------------------------------------------------------|
| `enabled`     | bool | `false`      | Главный переключатель. При `false` middleware полностью пропускает запросы |
| `withHeaders` | bool | `false`      | Добавлять заголовки `X-Rate-Limit-*` к ответам                             |

### 2. Зарегистрируйте middleware на маршруте

Используйте замыкание для передачи параметров лимита:

```php
use Vasoft\Joke\Http\RateLimiter\RateLimiterMiddleware;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier;
use Vasoft\Joke\Contract\Http\RateLimiter\StorageInterface;
use Vasoft\Joke\Http\RateLimiter\RateLimiterConfig;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Middleware\StdMiddleware;

$router->get('/api/data', ApiDataController::class)
    ->addMiddleware(
        static fn(
            StorageInterface $storage,
            RateLimiterConfig $config,
            ResponseBuilder $builder,
        ) => new RateLimiterMiddleware(
            $storage,
            new IpIdentifier(),
            $config,
            $builder,
            limit: 60,   // максимум 60 запросов
            window: 3600 // за 1 час
        ),
        StdMiddleware::RATE_LIMITER->value,
    );
```

Параметры конструктора `RateLimiterMiddleware`:

| Параметр            | Тип                         | По умолчанию | Описание                                       |
|:--------------------|:----------------------------|:-------------|:-----------------------------------------------|
| `$storage`          | `StorageInterface`          | —            | Хранилище счётчиков (внедряется из контейнера) |
| `$clientIdentifier` | `ClientIdentifierInterface` | —            | Стратегия идентификации клиента                |
| `$config`           | `RateLimiterConfig`         | —            | Конфигурация (внедряется из контейнера)        |
| `$builder`          | `ResponseBuilder`           | —            | Фабрика ответов (внедряется из контейнера)     |
| `$limit`            | int                         | `100`        | Максимум запросов в окне (должен быть > 0)     |
| `$window`           | int                         | `3600`       | Длительность окна в секундах (должна быть > 0) |

> Middleware автоматически внедряет `StorageInterface`, `RateLimiterConfig` и `ResponseBuilder` из DI-контейнера. Вам
> нужно явно указать только стратегию идентификации и параметры лимита.

## Стратегии идентификации клиента

Стратегия определяет, по какому признаку группировать запросы. Выбор стратегии зависит от типа приложения.

### IpIdentifier

Группирует по IP-адресу. Подходит для публичных API и защиты от DDoS.

```php
use Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier;

new IpIdentifier()
// Ключ: "ip:192.168.1.1"
```

### HeaderIdentifier

Группирует по значению HTTP-заголовка. Подходит для API с ключами доступа.

```php
use Vasoft\Joke\Http\RateLimiter\Identifier\HeaderIdentifier;

new HeaderIdentifier('X-API-Key')
// Ключ: "header-x-api-key:abc123"
```

Если заголовок отсутствует, возвращает `null` — используйте в цепочке как приоритетный вариант.

### IpWithUserAgentIdentifier

Группирует по комбинации IP и User-Agent. Различает разных клиентов за одним NAT.

```php
use Vasoft\Joke\Http\RateLimiter\Identifier\IpWithUserAgentIdentifier;

new IpWithUserAgentIdentifier()
// Ключ: "ip:192.168.1.1:ua:a1b2c3d4..."
```

### ChainIdentifier

Пробует несколько стратегий по очереди и использует первую успешную. Рекомендуется как стратегия по умолчанию.

```php
use Vasoft\Joke\Http\RateLimiter\Identifier\ChainIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\HeaderIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier;

new ChainIdentifier([
    new HeaderIdentifier('X-API-Key'),  // Сначала пробуем API-ключ
    new IpIdentifier(),                  // Fallback на IP
])
```

### GroupIdentifier

Объединяет несколько идентификаторов в один составной ключ. Все части обязательны.

```php
use Vasoft\Joke\Http\RateLimiter\Identifier\GroupIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\IpIdentifier;
use Vasoft\Joke\Http\RateLimiter\Identifier\HeaderIdentifier;

new GroupIdentifier([
    new IpIdentifier(),
    new HeaderIdentifier('X-API-Key'),
])
// Ключ: "group:ip:192.168.1.1#header-x-api-key:abc123"
```

## HTTP-заголовки ответа

При включённой опции `withHeaders` middleware добавляет к каждому ответу:

| Заголовок                | Описание                                             |
|:-------------------------|:-----------------------------------------------------|
| `X-Rate-Limit-Limit`     | Максимальное количество разрешённых запросов         |
| `X-Rate-Limit-Remaining` | Оставшееся количество запросов в текущем окне        |
| `X-Rate-Limit-Reset`     | Unix-метка времени сброса окна                       |
| `Retry-After`            | Секунды до повторной попытки (только для ответа 429) |

Пример заголовков в ответе:

```
X-Rate-Limit-Limit: 60
X-Rate-Limit-Remaining: 42
X-Rate-Limit-Reset: 1729000000
```

## Поведение при ошибках

Rate Limiter спроектирован по принципу **fail-open**: если хранилище счётчиков недоступно (ошибка диска, права доступа),
запрос пропускается без проверки лимитов. Это гарантирует, что сбой rate limiter'а не положит всё приложение.

Ошибки конфигурации (некорректные значения `limit` или `window`) обнаруживаются сразу при создании middleware и
выбрасывают `RateLimiterException`.

## Алгоритм работы

Rate Limiter использует алгоритм **Sliding Window**: каждый запрос обновляет время жизни счётчика. Окно «скользит» вслед
за активностью клиента.

Это означает:

- Блокировка действует до тех пор, пока клиент не прекратит активность на длительность `$window`
- Активный клиент, продолжающий слать запросы после превышения лимита, останется заблокированным
- Счётчик сбрасывается автоматически после периода неактивности

Вот исправленный финальный абзац:

> Для большинства задач защиты API и веб-приложений sliding window является оптимальным выбором. Если вам требуется
> строгое фиксированное окно или иное хранилище счётчиков, см.
> раздел [Реализация собственного хранилища](../detail/rate-limiter-storage.md). Если встроенные стратегии
> идентификации не подходят для вашего сценария (например, идентификация по JWT, сессии или пользовательская
> бизнес-логика), см.
> раздел [Реализация собственного идентификатора клиента](../detail/rate-limiter-identifier.md).