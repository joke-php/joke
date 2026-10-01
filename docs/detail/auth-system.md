# Система аутентификации: детальное руководство

Это руководство предназначено для разработчиков, которые хотят создавать собственные реализации компонентов системы
аутентификации и авторизации. Здесь рассматриваются все контракты (интерфейсы) и показаны примеры их реализации.

## Архитектура системы

Система аутентификации состоит из нескольких взаимодействующих компонентов:

```
HTTP Запрос
    |
[AuthService]
    ├ [Authenticator 1] ─ UserInterface (или null)
    ├ [Authenticator 2] ─ UserInterface (или null)
    └ [UserProvider] ─ обогащённый UserInterface
    |
[User] с данными
    |
[RightsChecker]
    ├ [RightsSource 1] ─ права
    ├ [RightsSource 2] ─ права
    └ результат: true/false
```

## Контракты системы

### UserInterface — объект пользователя

Контракт определяет минимальную структуру любого пользователя в системе:

```php
namespace Vasoft\Joke\Contract\Auth;

use Vasoft\Joke\Collections\PropsCollection;

interface UserInterface
{
    /**
     * Флаг авторизации пользователя
     */
    public bool $authorized { get; }
    
    /**
     * Уникальный идентификатор
     */
    public int|string|null $id { get; }
    
    /**
     * Дополнительные данные пользователя
     */
    public PropsCollection $data { get; }
}
```

**Реализация:**

```php
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Collections\PropsCollection;

$user = new User(
    'user123',
    new PropsCollection(['name' => 'Alex', 'role' => 'admin'])
);

echo $user->id;         // 'user123'
echo $user->authorized; // true (так как id не null)
echo $user->data->get('name'); // 'Alex'
```

Встроенный класс `User` реализует этот контракт. Свойство `authorized` автоматически вычисляется как `id !== null`.

> **Важно:** Объект `UserInterface` является иммутабельным после создания. Свойства `$id`, `$authorized` и ссылка на
> коллекцию `$data` имеют только геттеры (`private(set)` или аналогичные механизмы PHP 8.5 Property Hooks). Это
> означает,
> что если вам нужно изменить ID пользователя (например, при маппинге внешнего OAuth-ID на внутренний ID БД), метод
`enrich()` провайдера должен вернуть **новый** экземпляр объекта пользователя, а не модифицировать переданный.

### AuthenticatorInterface — аутентификатор

Аутентификатор отвечает за определение личности пользователя из данных запроса:

```php
namespace Vasoft\Joke\Contract\Auth;

interface AuthenticatorInterface
{
    /**
     * Пытается определить пользователя.
     * 
     * Возвращает UserInterface при успехе или null, если данные отсутствуют
     * или пользователь не найден.
     */
    public function authenticate(): ?UserInterface;
}
```

**Правила реализации:**

1. Метод **должен** вернуть `null`, если учетные данные отсутствуют или неверны
2. Метод **должен** вернуть `null`, если пользователь не найден (не выбрасывать исключение)
3. Исключения выбрасываются **только** при критических ошибках (ошибка БД, неверный формат токена)
4. Возвращаемый пользователь — это "черновой" объект, который будет обогащен провайдером

**Пример: аутентификатор на основе API ключа**

```php
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Http\HttpRequest;

class ApiKeyAuthenticator implements AuthenticatorInterface
{
    public function __construct(private readonly HttpRequest $request) {}

    public function authenticate(): ?UserInterface
    {
        // Извлекаем API ключ из заголовка
        $apiKey = $this->request->headers->get('X-API-Key');
        
        if (null === $apiKey) {
            return null; // ключ не передан
        }

        // Проверяем ключ (в реальном коде — обращение к БД)
        $userId = $this->validateApiKey($apiKey);
        if (null === $userId) {
            return null; // ключ неверный
        }

        // Возвращаем "черновой" пользователь с минимумом данных
        // Полные данные добавит провайдер через enrich()
        return new User($userId);
    }

    private function validateApiKey(string $key): ?string
    {
        // Ваша логика валидации
        return str_starts_with($key, 'valid-') ? 'user-' . substr($key, 6) : null;
    }
}
```

**Пример: аутентификатор на основе Basic Auth**

```php
class BasicAuthAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly HttpRequest $request,
    ) {}

    public function authenticate(): ?UserInterface
    {
        $auth = $this->request->headers->get('Authorization');
        
        if (null === $auth || !str_starts_with($auth, 'Basic ')) {
            return null;
        }

        try {
            $credentials = base64_decode(substr($auth, 6), true);
            if ($credentials === false) {
                return null;
            }

            [$login, $password] = explode(':', $credentials, 2);
            
            // Проверяем пароль
            if (!$this->verifyPassword($login, $password)) {
                return null;
            }

            return new User($login);
        } catch (\Exception) {
            return null;
        }
    }

    private function verifyPassword(string $login, string $password): bool
    {
        // Ваша логика проверки пароля
        return true;
    }
}
```

### UserProviderInterface — провайдер пользователей

Провайдер загружает полную информацию о пользователе после успешной аутентификации:

```php
namespace Vasoft\Joke\Contract\Auth;

interface UserProviderInterface
{
    /**
     * Загружает пользователя по ID
     */
    public function loadUserById(string $id): ?UserInterface;

    /**
     * Обогащает данные пользователя (добавляет роли, профиль и т.д.)
     */
    public function enrich(UserInterface $user): ?UserInterface;
}
```

**Правила реализации:**

1. Оба метода **должны** вернуть `null`, если пользователь не найден (не выбрасывать исключение)
2. `enrich()` может быть вызван с "чёрновым" пользователем от аутентификатора
3. После обогащения пользователь может быть отклонен провайдером (возврат `null`)
4. Система опрашивает провайдеров последовательно до первого успешного результата
5. **Иммутабельность:** Если требуется изменить `$id` пользователя (например, заменить внешний ID на внутренний ID базы
   данных), метод `enrich()` обязан вернуть новый экземпляр `UserInterface`, так как свойства существующего объекта
   неизменяемы.

**Пример: провайдер на основе базы данных (с использованием `joke-php/db-sql`)**

```php
use Vasoft\Joke\Contract\Auth\UserProviderInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Collections\PropsCollection;
use Vasoft\Joke\Db\Sql\ConnectionManager;

class DatabaseUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly ConnectionManager $connections,
    ) {}

    public function loadUserById(string $id): ?UserInterface
    {
        // Получаем подключение по умолчанию (или можно указать имя)
        $connection = $this->connections->connection();

        // query() возвращает ResultInterface. 
        // fetch() вернет первую строку как ассоциативный массив или false.
        $row = $connection->query(
            'SELECT id, name, email, role FROM users WHERE id = :id',
            ['id' => $id]
        )->fetch();

        if (false === $row) {
            return null;
        }

        return new User($row['id'], new PropsCollection($row));
    }

    public function enrich(UserInterface $user): ?UserInterface
    {
        if (null === $user->id) {
            return null;
        }

        $fullUser = $this->loadUserById((string)$user->id);
        
        if (null === $fullUser) {
            return null; // Пользователь удален или заблокирован в БД
        }

        // Переносим данные из БД в объект пользователя
        foreach ($fullUser->data->getAll() as $key => $value) {
            $user->data->set($key, $value);
        }

        return $user;
    }
}
```

> **Примечание:** В данном примере используется `ConnectionManager` из
> пакета [joke-php/db-sql](https://github.com/joke-php/db-sql). Этот подход обеспечивает:
> 1. **Управление соединениями:** Менеджер кэширует экземпляры подключений и управляет их жизненным циклом.
> 2. **Безопасность:** Методы `query()` и `execute()` принимают параметры отдельно от SQL-строки, что гарантирует
     использование prepared statements под капотом PDO и предотвращает SQL-инъекции.
> 3. **Гибкость результатов:** Интерфейс `ResultInterface` позволяет эффективно читать данные (`fetch()` для одной
     записи, `all()` для списка), скрывая специфику драйвера БД.

**Пример: многоуровневый провайдер**

```php
class CachedUserProvider implements UserProviderInterface
{
    private array $userCache = [];

    public function __construct(private readonly DatabaseUserProvider $dbProvider) {}

    public function loadUserById(string $id): ?UserInterface
    {
        // Проверяем кэш
        if (isset($this->userCache[$id])) {
            return $this->userCache[$id];
        }

        // Загружаем из БД
        $user = $this->dbProvider->loadUserById($id);
        if (null !== $user) {
            $this->userCache[$id] = $user;
        }

        return $user;
    }

    public function enrich(UserInterface $user): ?UserInterface
    {
        return $this->dbProvider->enrich($user);
    }
}
```

### RightsSourceInterface — источник прав

Источник прав возвращает список разрешений для пользователя:

```php
namespace Vasoft\Joke\Contract\Auth;

interface RightsSourceInterface
{
    /**
     * Получает права для пользователя в виде массива строк
     * Формат: ["модуль:право", "модуль:право", ...]
     * Пример: ["posts:view", "posts:edit", "admin:delete"]
     */
    public function getRights(UserInterface $user): array;
}
```

**Правила реализации:**

1. Метод должен корректно обрабатывать как авторизованных, так и анонимных пользователей (`$user->id === null`). Для
   гостей часто используется специальный ключ, например `ConfigRightsSource::GUEST_KEY`.
2. Возвращает плоский массив прав в формате `"модуль:право"`
3. Система может работать с несколькими источниками одновременно (права объединяются)

**Пример: источник прав на основе ролей (RBAC)**

```php
use Vasoft\Joke\Contract\Auth\RightsSourceInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

class RoleBasedRightsSource implements RightsSourceInterface
{
    private array $roleRights = [
        'admin' => ['posts:view', 'posts:edit', 'posts:delete', 'users:manage'],
        'moderator' => ['posts:view', 'posts:edit', 'comments:delete'],
        'user' => ['posts:view', 'comments:create'],
        '__guest__' => [], // Права для гостей, если нужно
    ];

    public function __construct(private readonly \PDO $pdo) {}

    public function getRights(UserInterface $user): array
    {
        // Получаем роль пользователя из данных
        $role = $user->data->get('role');
        
        if (null === $role) {
            // Для гостей можно использовать GUEST_KEY или возвращать пустой массив
            return $this->roleRights['__guest__'] ?? [];
        }

        return $this->roleRights[$role] ?? [];
    }
}
```

**Пример: источник прав на основе разрешений в БД**

```php
class DatabaseRightsSource implements RightsSourceInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function getRights(UserInterface $user): array
    {
        if (null === $user->id) {
            return []; // Гости не имеют прав в БД
        }

        $stmt = $this->pdo->prepare(
            'SELECT CONCAT(module, ":", right_name) as right 
             FROM user_rights 
             WHERE user_id = ? 
             UNION 
             SELECT CONCAT(module, ":", right_name) as right 
             FROM role_rights 
             JOIN user_roles ON role_rights.role_id = user_roles.role_id 
             WHERE user_roles.user_id = ?'
        );
        
        $stmt->execute([$user->id, $user->id]);
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
```

**Пример: комбинированный источник прав**

```php
class CompositeRightsSource implements RightsSourceInterface
{
    public function __construct(
        private readonly array $sources, // массив RightsSourceInterface
    ) {}

    public function getRights(UserInterface $user): array
    {
        $rights = [];
        
        foreach ($this->sources as $source) {
            $rights = array_merge($rights, $source->getRights($user));
        }

        // Удаляем дубликаты
        return array_unique($rights);
    }
}
```

### RightsCheckerInterface — проверщик прав

Интерфейс для проверки наличия права у пользователя:

```php
namespace Vasoft\Joke\Contract\Auth;

interface RightsCheckerInterface
{
    /**
     * Проверяет наличие права
     * 
     * @return bool true если право есть, false в противном случае
     */
    public function can(UserInterface $user, string $module, string $right): bool;
}
```

Встроенная реализация `RightsChecker` использует все зарегистрированные источники и кэширует результаты для оптимизации.

**Пример: кастомный проверщик с логированием**

```php
use Vasoft\Joke\Contract\Auth\RightsCheckerInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Psr\Log\LoggerInterface;

class LoggingRightsChecker implements RightsCheckerInterface
{
    public function __construct(
        private readonly RightsChecker $inner,
        private readonly LoggerInterface $logger,
    ) {}

    public function can(UserInterface $user, string $module, string $right): bool
    {
        $result = $this->inner->can($user, $module, $right);
        
        $this->logger->info(
            'Rights check',
            [
                'user_id' => $user->id,
                'module' => $module,
                'right' => $right,
                'granted' => $result,
            ]
        );

        return $result;
    }
}
```

### JwtHandlerInterface — кодер JWT токенов

Интерфейс для кодирования и декодирования JWT токенов:

```php
namespace Vasoft\Joke\Contract\Auth;

use Vasoft\Joke\Auth\Jwt\JwtException;

interface JwtHandlerInterface
{
    /**
     * Кодирует данные в JWT токен
     */
    public function encode(array $payload, ?int $ttl = null): string;

    /**
     * Декодирует и проверяет JWT токен
     * 
     * @throws JwtException если токен невалиден
     */
    public function decode(string $token): array;
}
```

**Встроенная реализация:**

Встроенный класс `SimpleJwtHandler` использует алгоритм HS256 (HMAC-SHA256). Он автоматически добавляет временные
метки (`iat` и `exp`).

```php
use Vasoft\Joke\Auth\Jwt\SimpleJwtHandler;

$handler = new SimpleJwtHandler('your-secret-key-minimum-32-chars');

// Кодирование
$token = $handler->encode([
    'id' => 'user123',
    'email' => 'user@example.com',
], 3600); // 1 час

// Декодирование
try {
    $payload = $handler->decode($token);
    echo $payload['id']; // 'user123'
} catch (\Vasoft\Joke\Auth\Jwt\JwtException $e) {
    echo "Токен невалиден: " . $e->getMessage();
}
```

## AuthService — сервис аутентификации

`AuthService` — это главный класс, который управляет процессом определения текущего пользователя:

```php
use Vasoft\Joke\Auth\AuthService;

class MyController
{
    public function __construct(private readonly AuthService $authService) {}

    public function index()
    {
        $user = $this->authService->getUser();
        // Первый вызов выполнит полный процесс аутентификации
        // Последующие вызовы вернут кэшированный результат
    }
}
```

**Процесс аутентификации:**

1. Опрашиваются все аутентификаторы последовательно до первого успешного результата
2. Найденный "черновой" пользователь передаётся провайдерам для обогащения
3. Первый провайдер, вернувший ненулевой результат, определяет пользователя
4. Если ни один провайдер не найдёт пользователя, возвращается гостевой объект (`User(null)`)
5. Результат кэшируется

## Полный пример: Аутентификация OAuth2

Вот полный пример создания кастомной системы аутентификации с использованием OAuth2. Обратите внимание на обработку
смены ID пользователя.

```php
// OAuth2Authenticator
use Vasoft\Joke\Contract\Auth\AuthenticatorInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Collections\PropsCollection;

class OAuth2Authenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly OAuth2Provider $provider, // гипотетический провайдер
    ) {}

    public function authenticate(): ?UserInterface
    {
        $code = $this->request->query->get('code');
        $state = $this->request->query->get('state');

        if (null === $code || !$this->validateState($state)) {
            return null;
        }

        try {
            $token = $this->provider->exchangeCodeForToken($code);
            $remoteUser = $this->provider->getUserInfo($token);
            
            // Возвращаем "черновой" пользователь с внешним ID провайдера
            return new User($remoteUser['id'], new PropsCollection([
                'oauth_email' => $remoteUser['email'],
                'oauth_name' => $remoteUser['name'],
            ]));
        } catch (\Exception) {
            return null;
        }
    }

    private function validateState(?string $state): bool
    {
        if (null === $state) {
            return false;
        }
        $sessionState = $this->request->session->get('oauth_state');
        return null !== $sessionState && hash_equals($sessionState, $state);
    }
}

// OAuth2UserProvider
use Vasoft\Joke\Contract\Auth\UserProviderInterface;

class OAuth2UserProvider implements UserProviderInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function loadUserById(string $id): ?UserInterface
    {
        // Ищем по внутреннему ID
        $stmt = $this->pdo->prepare('SELECT id, oauth_id, email, name FROM oauth_users WHERE id = ?');
        $stmt->execute([$id]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new User($row['id'], new PropsCollection($row));
    }

    public function enrich(UserInterface $user): ?UserInterface
    {
        // Ищем пользователя по внешнему ID из токена
        $stmt = $this->pdo->prepare('SELECT id, email, name FROM oauth_users WHERE oauth_id = ?');
        $stmt->execute([(string)$user->id]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($row) {
            // Пользователь существует. Создаем НОВЫЙ объект с внутренним ID.
            // Старый объект $user (с внешним ID) отбрасывается, так как $id immutable.
            return new User(
                $row['id'], // Внутренний ID из БД
                new PropsCollection([
                    ...$user->data->getAll(), // Сохраняем данные из токена
                    'db_email' => $row['email'],
                    'db_name' => $row['name'],
                ])
            );
        }

        // Новый пользователь — создаём запись в БД
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO oauth_users (oauth_id, email, name) VALUES (?, ?, ?)'
        );
        
        try {
            $insertStmt->execute([
                $user->id,
                $user->data->get('oauth_email'),
                $user->data->get('oauth_name'),
            ]);
            
            $newLocalId = $this->pdo->lastInsertId();
            
            // Возвращаем нового пользователя с внутренним ID
            return new User(
                $newLocalId,
                new PropsCollection($user->data->getAll())
            );
        } catch (\PDOException) {
            return null;
        }
    }
}

// Конфигурация
use Vasoft\Joke\Auth\AuthConfig;

return new AuthConfig()
    ->addAuthenticator(OAuth2Authenticator::class)
    ->addUserProvider(OAuth2UserProvider::class)
    ->addRightsSource(new \Vasoft\Joke\Auth\Rights\ConfigRightsSource([
        // Права назначаются по ВНУТРЕННЕМУ ID, который вернет enrich()
        // Например, если первый созданный пользователь получил local_id = 1
        '1' => ['posts:view', 'profile:view'],
    ]));
```

## Кэширование и производительность

### RightsChecker использует многоуровневое кэширование:

1. **Кэш вычислений** — запоминает результаты для каждой проверки (положительные и отрицательные)
2. **Ленивая инициализация источников** — источники создаются только при первом обращении
3. **Раздельные кэши** — права авторизованных и анонимных пользователей хранятся отдельно

```php
// Все эти проверки используют кэш (не обращаются к источникам повторно)
$checker->can($user, 'posts', 'view');
$checker->can($user, 'posts', 'view'); // Hit cache
$checker->can($user, 'posts', 'edit'); // Miss cache -> query sources -> store result
```

### AuthService кэширует пользователя:

```php
$user1 = $authService->getUser(); // выполняет полный процесс аутентификации
$user2 = $authService->getUser(); // возвращает кэшированный объект (тот же)
```

## Интеграция с контейнером зависимостей

Все аутентификаторы и провайдеры разрешаются через контейнер, поэтому они могут содержать свои зависимости:

```php
class CustomAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly MyDatabaseConnection $db,
        private readonly LoggerInterface $logger,
    ) {}

    public function authenticate(): ?UserInterface
    {
        // Все зависимости будут автоматически разрешены
    }
}

$config->addAuthenticator(CustomAuthenticator::class); // класс, не экземпляр
```

## Безопасность

При реализации собственных компонентов обратите внимание на следующие моменты:

1. **Не выбрасывайте исключения при неудачной аутентификации** — возвращайте `null`
2. **Используйте `hash_equals()` для сравнения чувствительных данных** — защита от timing-атак
3. **Валидируйте входные данные** — особенно в аутентификаторах и провайдерах
4. **Используйте HTTPS** — при передаче токенов и sensitive данных
5. **Не логируйте пароли** — логируйте только значимые события
6. **Соблюдайте принцип наименьших привилегий** — давайте ровно столько прав, сколько нужно

## Дополнительные ресурсы

- [Аутентификация и авторизация: быстрый старт](../base/authentication.md)
- [Middleware: системы защиты](../base/middleware.md)
- [DI-контейнер: внедрение зависимостей](../base/container.md)