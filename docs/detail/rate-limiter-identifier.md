# Реализация собственного идентификатора клиента для ограничителя частоты запросов

Встроенные идентификаторы (`IpIdentifier`, `HeaderIdentifier`, `IpWithUserAgentIdentifier`, `ChainIdentifier`,
`GroupIdentifier`) покрывают базовые сценарии, но в реальных приложениях часто требуется кастомная логика: идентификация
по JWT-токену, по сессии, по комбинации признаков или с учётом бизнес-правил. Вы можете написать собственную реализацию,
сохранив совместимость с `RateLimiterMiddleware`.

## Контракт ClientIdentifierInterface

Все идентификаторы должны реализовывать интерфейс `Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface`:

```php
interface ClientIdentifierInterface
{
    /**
     * Определяет уникальный идентификатор клиента из запроса.
     *
     * Возвращаемая строка используется как ключ в хранилище счётчиков.
     * Должна быть уникальной и детерминированной для одного клиента.
     *
     * @param HttpRequest $request HTTP запрос
     *
     * @return null|non-empty-string Идентификатор клиента или null
     */
    public function identify(HttpRequest $request): ?string;
}
```

### Семантика возврата null

Возврат `null` означает **«я не могу идентифицировать этого клиента»**, а не «клиент не существует». Это сигнал для
цепочки идентификаторов (`ChainIdentifier`) попробовать следующую стратегию, или для middleware — использовать fallback
на IP-адрес.

Никогда не возвращайте пустую строку. Если идентификация невозможна — возвращайте `null`. Пустая строка нарушает
контракт `non-empty-string` и приведёт к ошибке в хранилище.

### Требования к ключу

**Уникальность.** Разные клиенты должны получать разные ключи. Один и тот же клиент при повторных запросах должен
получать одинаковый ключ (детерминированность).

**Стабильность.** Ключ не должен меняться в течение сессии или срока действия токена. Если пользователь перелогинился и
получил новый токен — это нормально, что ключ изменится. Но если ключ меняется при каждом запросе без объективной
причины — лимиты не будут работать.

**Префиксы.** Используйте префиксы для разделения пространств имён разных стратегий. Это предотвращает коллизии и
упрощает отладку:

```php
// Хорошо
return 'jwt:' . $token->getUserId();
return 'session:' . $sessionId;
return 'api-key:' . $apiKey;

// Плохо — возможны коллизии между стратегиями
return $userId;
return $sessionId;
```

## Регистрация

Идентификаторы не регистрируются в контейнере глобально — они передаются напрямую в конструктор `RateLimiterMiddleware`
при регистрации маршрута:

```php
use App\RateLimiter\Identifier\JwtIdentifier;

$router->post('/api/data', ApiDataController::class)
    ->addMiddleware(
        static fn(
            StorageInterface $storage,
            RateLimiterConfig $config,
            ResponseBuilder $builder,
        ) => new RateLimiterMiddleware(
            $storage,
            new JwtIdentifier(), // ← ваш идентификатор
            $config,
            $builder,
            limit: 30,
            window: 60,
        ),
        StdMiddleware::RATE_LIMITER->value,
    );
```

Если идентификатор требует зависимостей из контейнера, используйте замыкание:

```php
static fn(
    StorageInterface $storage,
    RateLimiterConfig $config,
    ResponseBuilder $builder,
    AuthService $authService,
) => new RateLimiterMiddleware(
    $storage,
    new JwtIdentifier($authService),
    $config,
    $builder,
    limit: 30,
    window: 60,
),
```

## Пример: SessionIdentifier

Идентификация по ID сессии. Подходит для веб-приложений, где пользователи авторизованы и лимиты должны применяться
индивидуально.

```php
<?php

declare(strict_types=1);

namespace App\RateLimiter\Identifier;

use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Http\HttpRequest;

final class SessionIdentifier implements ClientIdentifierInterface
{
    public function identify(HttpRequest $request): ?string
    {
        $sessionId = $request->cookies->getString('PHPSESSID', '');

        if ('' === $sessionId) {
            return null;
        }

        return 'session:' . $sessionId;
    }
}
```

Обратите внимание: если кука сессии отсутствует, возвращается `null`, а не выбрасывается исключение. Это позволяет
использовать `SessionIdentifier` в цепочке как приоритетную стратегию с fallback на IP.

## Пример: JwtIdentifier

Идентификация по claim из JWT-токена. Подходит для stateless API.

```php
<?php

declare(strict_types=1);

namespace App\RateLimiter\Identifier;

use Vasoft\Joke\Auth\AuthService;
use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Http\HttpRequest;

final class JwtIdentifier implements ClientIdentifierInterface
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function identify(HttpRequest $request): ?string
    {
        $user = $this->authService->getUser();

        if (null === $user) {
            return null;
        }

        return 'user:' . $user->id;
    }
}
```

Здесь используется `AuthService` из контейнера фреймворка. Если пользователь не авторизован, возвращается `null` —
middleware перейдёт к fallback-стратегии.

## Комбинирование стратегий

Собственные идентификаторы полностью совместимы с `ChainIdentifier` и `GroupIdentifier`:

```php
new ChainIdentifier([
    new JwtIdentifier($authService),       // 1. Пробуем JWT
    new HeaderIdentifier('X-API-Key'),     // 2. Пробуем API-ключ
    new SessionIdentifier(),               // 3. Пробуем сессию
    // 4. Fallback на IP выполнит сам middleware
])
```

Порядок в массиве определяет приоритет. Первая успешная идентификация побеждает.