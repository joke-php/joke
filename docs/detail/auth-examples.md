# Примеры реализации системы аутентификации

Эта статья содержит практические примеры и готовые решения для типичных сценариев использования системы аутентификации.

### Пример 1: Простая аутентификация с сессией

Самый базовый вариант — аутентификация через сессию и конфигурационный провайдер.

**Конфигурация (`config/auth.php`):**

```php
<?php

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Session\SessionAuthenticator;
use Vasoft\Joke\Auth\Provider\ConfigUserProvider;
use Vasoft\Joke\Auth\Rights\ConfigRightsSource;

return new AuthConfig()
    ->addAuthenticator(SessionAuthenticator::class)
    ->addUserProvider(new ConfigUserProvider([
        'admin' => ['name' => 'Administrator', 'email' => 'admin@example.com'],
        'user1' => ['name' => 'Alex', 'email' => 'alex@example.com'],
        'user2' => ['name' => 'Olga', 'email' => 'olga@example.com'],
    ]))
    ->addRightsSource(new ConfigRightsSource([
        'admin' => ['posts:view', 'posts:create', 'posts:edit', 'posts:delete', 'users:manage'],
        'user1' => ['posts:create'],
        'user2' => ['comments:create'],
        // Права для гостей настраиваются через GUEST_KEY.
        // Если ключ не указан, у анонимных пользователей нет никаких прав,
        // и доступ к маршрутам с requiredRights будет запрещён.
        // ConfigRightsSource::GUEST_KEY => ['posts:view'],
    ]));
```

**Контроллер входа:**

```php
class AuthController
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly AuthService $authService,
    ) {}

    public function login():string
    {
        if ('POST' === $this->request->method) {
            $username = $this->request->post->getString('username');
            $password = $this->request->post->getString('password');

            // ВНИМАНИЕ: это заглушка для демонстрации.
            // В реальном приложении здесь должна быть проверка пароля
            // через password_verify() или аналогичный механизм.
            if ($this->verifyCredentials($username, $password)) {
                $this->request->session->set(
                    SessionAuthenticator::VAR_USER_ID,
                    $username
                );

                return 'Вы успешно вошли';
            }

            return 'Неверные учетные данные';
        }

        return 'Форма входа';
    }

    public function logout()
    {
        $this->request->session->delete(SessionAuthenticator::VAR_USER_ID);

        return 'Вы вышли';
    }

    /**
     * ЗАГЛУШКА: всегда возвращает true.
     * Замените на реальную проверку учётных данных.
     */
    private function verifyCredentials(string $username, string $password): bool
    {
        return true;
    }
}
```

**Защита маршрутов:**

```php
use Vasoft\Joke\Auth\AuthMiddleware;
use Vasoft\Joke\Auth\AuthService;
use Vasoft\Joke\Auth\Rights\RightsChecker;
use Vasoft\Joke\Http\Response\ResponseBuilder;

// Маршрут требует аутентификации и права 'posts:view'
$router->get('/dashboard', function () {
    return 'Защищённая панель';
})->addMiddleware(
    static fn(
        AuthService $authService,
        RightsChecker $rightsChecker,
        ResponseBuilder $responseBuilder,
    ) => new AuthMiddleware(
        $authService,
        $rightsChecker,
        $responseBuilder,
        ['posts:view'],
    ),
);

// Маршрут для администраторов только
$router->get('/admin', function () {
    return 'Только для администраторов';
})->addMiddleware(
    static fn(
        AuthService $authService,
        RightsChecker $rightsChecker,
        ResponseBuilder $responseBuilder,
    ) => new AuthMiddleware(
        $authService,
        $rightsChecker,
        $responseBuilder,
        ['users:manage'],
        '/login',  // редирект для неавторизованных
    ),
    'admin-only'
);
```

### Пример 2: JWT аутентификация для API

Типичный случай для REST API — аутентификация через JWT токены.

**Конфигурация:**

```php
<?php

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Jwt\JwtAuthenticator;
use Vasoft\Joke\Auth\Jwt\SimpleJwtHandler;
use Vasoft\Joke\Auth\Provider\ConfigUserProvider;
use Vasoft\Joke\Auth\Rights\ConfigRightsSource;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Http\HttpRequest;

return new AuthConfig()
    ->addAuthenticator(static function (HttpRequest $request, Environment $env) {
        // Если переменная JWT_SECRET не задана или короче 32 символов,
        // конструктор SimpleJwtHandler выбросит ConfigException.
        // Это гарантирует, что приложение не запустится с уязвимым ключом.
        $jwtHandler = new SimpleJwtHandler($env->get('JWT_SECRET', ''));

        return new JwtAuthenticator($request, $jwtHandler);
    })
    ->addUserProvider(new ConfigUserProvider([
        'api_user_1' => ['name' => 'API User 1', 'email' => 'api1@example.com'],
        'api_user_2' => ['name' => 'API User 2', 'email' => 'api2@example.com'],
    ]))
    ->addRightsSource(new ConfigRightsSource([
        'api_user_1' => ['api:read', 'api:write'],
        'api_user_2' => ['api:read'],
    ]));
```

**Контроллер выдачи токена:**

```php
class ApiAuthController
{
    public function __construct(
        private readonly SimpleJwtHandler $jwtHandler,
    ) {}

    public function issueToken()
    {
        $token = $this->jwtHandler->encode([
            'id' => 'api_user_1',
            'scope' => ['api:read', 'api:write'],
        ], 86400); // 24 часа

        return json_encode(['token' => $token]);
    }
}
```

> **Обратите внимание:** поле `iat` добавляется автоматически внутри `SimpleJwtHandler::encode()`. Указывать его вручную
> не нужно.

**API маршруты с защитой:**

```php
use Vasoft\Joke\Auth\AuthMiddleware;
use Vasoft\Joke\Auth\AuthService;
use Vasoft\Joke\Auth\Rights\RightsChecker;
use Vasoft\Joke\Http\Response\ResponseBuilder;

$router->get('/api/data', function () {
    return ['data' => 'sensitive'];
})->addMiddleware(
    static fn(
        AuthService $authService,
        RightsChecker $rightsChecker,
        ResponseBuilder $responseBuilder,
    ) => new AuthMiddleware(
        $authService,
        $rightsChecker,
        $responseBuilder,
        ['api:read'],
        null
    )
);

$router->put('/api/data', function () {
    return ['status' => 'updated'];
})->addMiddleware(
    static fn(
        AuthService $authService,
        RightsChecker $rightsChecker,
        ResponseBuilder $responseBuilder,
    ) => new AuthMiddleware(
        $authService,
        $rightsChecker,
        $responseBuilder,
        ['api:write'],
        null
    )
);
```

## Пример 3: Комбинированная аутентификация

Одновременная поддержка нескольких методов аутентификации (сессия + JWT).

**Конфигурация:**

```php
<?php

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Session\SessionAuthenticator;
use Vasoft\Joke\Auth\Jwt\JwtAuthenticator;
use Vasoft\Joke\Auth\Jwt\SimpleJwtHandler;
use MyApp\Auth\DatabaseUserProvider;
use MyApp\Auth\DatabaseRightsSource;

return new AuthConfig()
    // Порядок важен: сначала проверяем сессию, затем JWT
    ->addAuthenticator(SessionAuthenticator::class)
    ->addAuthenticator(JwtAuthenticator::class)
    
    // Единый провайдер для обоих методов
    ->addUserProvider(DatabaseUserProvider::class)
    
    // Права из БД (работают одинаково для обоих методов)
    ->addRightsSource(DatabaseRightsSource::class);
```

В этом сценарии пользователь может войти либо через веб-форму (сессия), либо использовать API с JWT токеном. Система
автоматически определит метод и загрузит данные пользователя.

## Пример 4: Многоуровневые роли с наследованием

Система ролей, где роли могут наследовать права от других ролей.

**Провайдер ролей:**

```php
use Vasoft\Joke\Contract\Auth\RightsSourceInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

class HierarchicalRoleProvider implements RightsSourceInterface
{
    private array $roleHierarchy = [
        'admin' => ['moderator'],      // admin наследует права moderator
        'moderator' => ['user'],       // moderator наследует права user
        'user' => [],                  // user базовая роль
    ];

    private array $roleRights = [
        'admin' => ['users:manage', 'roles:manage', 'system:configure'],
        'moderator' => ['posts:moderate', 'comments:delete'],
        'user' => ['posts:create', 'comments:create'],
    ];

    public function getRights(UserInterface $user): array
    {
        $role = $user->data->get('role');
        if (null === $role) {
            return [];
        }

        return $this->collectRights($role);
    }

    private function collectRights(string $role): array
    {
        $rights = $this->roleRights[$role] ?? [];
        
        // Рекурсивно собираем права родительских ролей
        foreach ($this->roleHierarchy[$role] ?? [] as $parentRole) {
            $rights = array_merge($rights, $this->collectRights($parentRole));
        }

        return array_unique($rights);
    }
}
```

## Пример 5: LDAP аутентификация

Интеграция с LDAP/Active Directory для корпоративной среды.

**LDAP Аутентификатор:**

```php
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserProviderInterface
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Http\HttpRequest;


class LdapAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly string $ldapServer,
        private readonly string $ldapDomain,
    ) {}

    public function authenticate(): ?UserInterface
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');

        if (null === $username || null === $password) {
            return null;
        }

        if (!$this->verifyLdapCredentials($username, $password)) {
            return null;
        }

        // Возвращаем "черновой" пользователь
        // Полные данные добавит провайдер из LDAP
        return new User($username);
    }

    private function verifyLdapCredentials(string $username, string $password): bool
    {
        try {
            $ldap = ldap_connect($this->ldapServer);
            if (!$ldap) {
                return false;
            }

            $dn = "uid={$username},ou=users,{$this->ldapDomain}";
            return @ldap_bind($ldap, $dn, $password);
        } catch (\Exception) {
            return false;
        }
    }
}

class LdapUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly string $ldapServer,
        private readonly string $ldapDomain,
    ) {}

    public function loadUserById(string $id): ?UserInterface
    {
        // Подключаемся с правами администратора
        // (в реальном коде используйте безопасный способ передачи учетных данных)
        $ldap = ldap_connect($this->ldapServer);
        ldap_bind($ldap, "cn=admin,{$this->ldapDomain}", getenv('LDAP_ADMIN_PASS'));

        $result = ldap_search(
            $ldap,
            "ou=users,{$this->ldapDomain}",
            "uid={$id}"
        );

        $entries = ldap_get_entries($ldap, $result);
        if ($entries['count'] === 0) {
            return null;
        }

        $entry = $entries[0];
        return new User($id, new \Vasoft\Joke\Collections\PropsCollection([
            'email' => $entry['mail'][0] ?? null,
            'name' => $entry['cn'][0] ?? null,
            'department' => $entry['ou'][0] ?? null,
        ]));
    }

    public function enrich(UserInterface $user): ?UserInterface
    {
        $fullUser = $this->loadUserById($user->id);
        if (null === $fullUser) {
            return null;
        }

        // Копируем данные из LDAP
        foreach ($fullUser->data->getAll() as $key => $value) {
            $user->data->set($key, $value);
        }

        return $user;
    }
}
```

### Пример 6: Двухфакторная аутентификация (2FA)

Добавление второго фактора проверки после первичной аутентификации.

**Аутентификатор с проверкой 2FA:**

```php
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Http\HttpRequest;
use PDO;

class TwoFactorAuthenticator implements AuthenticatorInterface
{
    /**
     * @param HttpRequest $request Текущий HTTP-запрос
     * @param PDO         $pdo     Прямое подключение к базе данных.
     *                             В реальном проекте рекомендуется использовать абстракцию
     *                             из пакета joke-php/db-sql для поддержки различных драйверов
     *                             и лучшей тестируемости.
     */
    public function __construct(
        private readonly HttpRequest $request,
        private readonly PDO $pdo,
    ) {}

    public function authenticate(): ?UserInterface
    {
        // Проверяем, прошёл ли пользователь первичную аутентификацию
        $tempUserId = $this->request->session->get('_temp_user_id');
        if (null === $tempUserId) {
            return null;
        }

        // Ищем код 2FA, отправленный пользователю
        $code = $this->request->post->getString('2fa_code');
        if ('' === $code) {
            return null;
        }

        // Проверяем код (временное окно 5 минут)
        // Примечание: NOW() является специфичным для MySQL/MariaDB. 
        // Для PostgreSQL используйте CURRENT_TIMESTAMP, для SQLite — datetime('now').
        $stmt = $this->pdo->prepare(
            'SELECT id FROM user_2fa_codes WHERE user_id = ? AND code = ? AND expires_at > NOW()'
        );
        $stmt->execute([$tempUserId, $code]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        // Удаляем использованный код
        $stmt = $this->pdo->prepare('DELETE FROM user_2fa_codes WHERE code = ?');
        $stmt->execute([$code]);

        // Удаляем временный ID из сессии
        $this->request->session->delete('_temp_user_id');

        // Возвращаем авторизованного пользователя
        return new User($tempUserId);
    }
}
```

> **Примечание по работе с БД:**
> В данном примере используется прямой объект `PDO` для простоты демонстрации логики. Однако в продакшн-среде это создаёт жёсткую связь с конкретной реализацией доступа к данным.
>
> Рекомендуется использовать пакет **[joke-php/db-sql](https://github.com/joke-php/db-sql)**. Он предоставляет интерфейс `ConnectionInterface`, который позволяет:
> 1. Абстрагироваться от конкретного драйвера (MySQL, PostgreSQL, SQLite).
> 2. Легко заменять реализацию при тестировании (mocking).
> 3. Использовать единый стиль работы с запросами через `ConnectionManager`.
>
> Если вы используете `joke-php/db-sql`, конструктор должен принимать `ConnectionManager` или `ConnectionInterface`, а не `PDO`.

## Пример 7: Кэширование прав с Redis

Оптимизация системы прав с использованием Redis для кэширования.

**Кэшированный источник прав:**

```php
use Vasoft\Joke\Contract\Auth\RightsSourceInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

class CachedRightsSource implements RightsSourceInterface
{
    private const CACHE_TTL = 3600; // 1 час

    public function __construct(
        private readonly RightsSourceInterface $source,
        private readonly \Redis $redis,
    ) {}

    public function getRights(UserInterface $user): array
    {
        $cacheKey = "user_rights:{$user->id}";

        // Проверяем кэш
        $cached = $this->redis->get($cacheKey);
        if ($cached !== false) {
            return json_decode($cached, true);
        }

        // Получаем права из источника
        $rights = $this->source->getRights($user);

        // Кэшируем результат
        $this->redis->setex($cacheKey, self::CACHE_TTL, json_encode($rights));

        return $rights;
    }

    public function invalidate(string $userId): void
    {
        $this->redis->del("user_rights:{$userId}");
    }
}
```

## Пример 8: Логирование и аудит

Систематическое логирование событий аутентификации и авторизации.

**Middleware для логирования:**

```php
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Http\HttpRequest;
use Psr\Log\LoggerInterface;

class AuthAuditMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(HttpRequest $request, callable $next): mixed
    {
        $user = $this->authService->getUser();

        $this->logger->info('User action', [
            'user_id' => $user->id,
            'authorized' => $user->authorized,
            'path' => $request->path,
            'method' => $request->method,
            'ip' => $request->ip(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        return $next($request);
    }
}
```

**Логирование провалов аутентификации:**

```php
class LoggingAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly AuthenticatorInterface $inner,
        private readonly LoggerInterface $logger,
        private readonly HttpRequest $request,
    ) {}

    public function authenticate(): ?UserInterface
    {
        $user = $this->inner->authenticate();

        if (null === $user) {
            $this->logger->warning('Authentication failed', [
                'path' => $this->request->path,
                'ip' => $this->request->ip(),
            ]);
        }

        return $user;
    }
}
```

## Проверка конфигурации

При запуске приложения полезно проверить, что система аутентификации настроена правильно:

```php
class AuthBootstrap
{
    public static function verify(AuthConfig $config): void
    {
        if (empty($config->authenticators)) {
            throw new \RuntimeException('No authenticators configured');
        }

        if (empty($config->userProviders)) {
            throw new \RuntimeException('No user providers configured');
        }

        // Убедимся, что есть хотя бы один источник прав для авторизованных пользователей
        if (empty($config->rightSources)) {
            trigger_error('No rights sources configured', E_USER_WARNING);
        }
    }
}

// В bootstrap/app.php
$authConfig = require __DIR__ . '/../config/auth.php';
AuthBootstrap::verify($authConfig);
```

## Дополнительные ресурсы

- [Аутентификация и авторизация: быстрый старт](../base/authentication.md)
- [Система аутентификации: детальное руководство](./auth-system.md)
- [Middleware](../base/middleware.md)
