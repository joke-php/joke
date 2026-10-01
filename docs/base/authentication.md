# Аутентификация и авторизация

Joke включает встроенную систему аутентификации и авторизации, которая позволяет определять личность пользователя и
проверять его права доступа. Система построена на основе контрактов (интерфейсов), что обеспечивает полную гибкость при
создании собственных реализаций.

## Основные концепции

**Аутентификация** — это процесс определения личности пользователя на основе предоставленных данных (сессия, токен,
cookie и т.д.). **Авторизация** — это проверка прав доступа пользователя на выполнение конкретных действий.

Система разделена на несколько независимых компонентов:

### Аутентификаторы

Аутентификатор — это компонент, который извлекает учетные данные из запроса и определяет, является ли пользователь
авторизованным. Фреймворк поддерживает несколько аутентификаторов одновременно, которые проверяются последовательно до
первого успешного результата.

Встроенные реализации:

- **SessionAuthenticator** — аутентификация на основе сессии
- **JwtAuthenticator** — аутентификация на основе JWT токенов

### Провайдеры пользователей

Провайдер пользователей — это компонент, который подтверждает существование пользователя в системе и загружает его
данные из конкретного источника (база данных, конфигурация, API).

После успешной аутентификации `AuthService` передаёт «черновой» объект пользователя (содержащий только ID)
зарегистрированным провайдерам через метод `enrich()`. Если ни один провайдер не вернул пользователя (все вернули null),
аутентификация считается неудачной — даже если токен или сессия были валидны. Это защищает от доступа удалённых или
заблокированных пользователей, у которых сохранились действующие учётные данные.

Провайдеры предоставляют два метода:

- `enrich(UserInterface $user)` — обогащает существующий объект данными из источника и подтверждает его существование.
  Основной метод, используемый в процессе аутентификации.
- `loadUserById(string $id) `— загружает пользователя по ID независимо от процесса аутентификации. Может использоваться
  для административных задач или фоновых процессов.

Встроенная реализация — **ConfigUserProvider**, которая хранит пользователей в конфигурационном массиве.

### Источники прав

Источник прав — это компонент, который возвращает список разрешений для пользователя в виде плоского массива строк
формата `"модуль:право"` (например, `"posts:edit"`, `"users:delete"`).

Встроенная реализация — **ConfigRightsSource**, которая хранит права в конфигурационном массиве.

### Проверщик прав

Проверщик прав (`RightsChecker`) — это сервис для проверки наличия конкретного права у пользователя. Он агрегирует права
от всех зарегистрированных источников и кэширует результаты для оптимизации производительности.

#### Механизм проверки

При вызове `can($user, $module, $right)` проверка выполняется в следующем порядке:

1. Поиск права в локальном кэше (положительный или отрицательный результат)
2. При отсутствии в кэше — последовательный опрос источников прав до первого совпадения
3. Если ни один источник не предоставил право — фиксация отрицательного результата

### Кэширование

`RightsChecker` использует гибридную систему кэширования:

- *Раздельные кэши* для авторизованных и анонимных пользователей. Права гостей хранятся отдельно от прав авторизованных
  пользователей, что исключает пересечения и упрощает очистку.
- *Отрицательное кэширование*: если право не найдено ни в одном источнике, результат `false` также сохраняется в кэш.
  Это предотвращает повторный опрос источников при повторной проверке того же права.
- *Инкрементальное накопление*: права добавляются в кэш по мере опроса источников, а не единым блоком. При проверке
  нового права для того же пользователя ранее загруженные права берутся из кэша, а опрос продолжается только с
  оставшихся источников.
- *Кэш действует в рамках одного запроса*. Инвалидация не предусмотрена: если права пользователя изменяются во время
  обработки запроса, эти изменения не будут учтены до следующего запроса.

## Быстрый старт

### 1. Конфигурация

Конфигурация системы аутентификации находится в файле `config/auth.php`:

```php
<?php

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Session\SessionAuthenticator;
use Vasoft\Joke\Auth\Rights\ConfigRightsSource;
use Vasoft\Joke\Demo\Auth\DemoUserProvider;

return new AuthConfig()
    ->addAuthenticator(SessionAuthenticator::class)
    ->addUserProvider(DemoUserProvider::class)
    ->addRightsSource(new ConfigRightsSource([
        'demo' => ['joke:personal']  // Пользователь 'demo' имеет право 'joke:personal'
        // Права для гостей настраиваются через GUEST_KEY.
        // Если ключ не указан, у анонимных пользователей нет никаких прав,
        // и доступ к маршрутам с requiredRights будет запрещён.
        // ConfigRightsSource::GUEST_KEY => ['posts:view'],
    ]));
```

Конфигурация определяет:

- **addAuthenticator()** — аутентификаторы, которые будут проверяться при определении текущего пользователя
- **addUserProvider()** — провайдеры для загрузки данных пользователя
- **addRightsSource()** — источники прав доступа

> При первом запросе конфигурации из контейнера зависимостей происходит ее заморозка и дальнейшее конфигурирование
> вызовет исключение.

### 2. Получение текущего пользователя

Сервис `AuthService` предоставляет доступ к текущему пользователю:

```php
use Vasoft\Joke\Auth\AuthService;
use Vasoft\Joke\Http\HttpRequest;

class MyController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function index()
    {
        $user = $this->authService->getUser();
        
        if ($user->authorized) {
            echo "Привет, {$user->data->get('name')}";
        } else {
            echo "Вы не авторизованы";
        }
    }
}
```

Объект пользователя имеет следующие свойства:

- `$user->authorized` — флаг авторизации (true/false)
- `$user->id` — уникальный идентификатор пользователя (или null для гостей)
- `$user->data` — коллекция с дополнительными данными (имя, email, настройки и т.д.)

### 3. Проверка прав доступа

Сервис `RightsChecker` предоставляет метод для проверки наличия права:

```php
use Vasoft\Joke\Auth\Rights\RightsChecker;

class MyController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly RightsChecker $rightsChecker,
    ) {}

    public function editPost(int $postId)
    {
        $user = $this->authService->getUser();
        
        if (!$this->rightsChecker->can($user, 'posts', 'edit')) {
            return "У вас нет права на редактирование постов";
        }
        
        // Редактируем пост...
    }
}
```

### 4. Middleware для защиты маршрутов

Middleware `AuthMiddleware` используется для защиты маршрутов, требующих аутентификации или определённых прав.

Так как AuthMiddleware требует несколько зависимостей (AuthService, RightsChecker, ResponseBuilder), его нужно
регистрировать через замыкание (factory), которое будет разрешено контейнером зависимостей:

```php
use Vasoft\Joke\Auth\AuthMiddleware;
use Vasoft\Joke\Auth\AuthService;
use Vasoft\Joke\Auth\Rights\RightsChecker;
use Vasoft\Joke\Http\Response\ResponseBuilder;

$router->get('/profile', function() {
    return 'Ваш профиль';
})->addMiddleware(
    static fn(
        AuthService $authService,
        RightsChecker $rightsChecker,
        ResponseBuilder $responseBuilder,
    ) => new AuthMiddleware(
        $authService,
        $rightsChecker,
        $responseBuilder,
        ['posts:view', 'posts:edit'],  // требуемые права
        null  // или URL редиректа для неавторизованных
    ),
);
```

**Параметры AuthMiddleware:**

- **authService** — сервис для получения текущего пользователя
- **rightsChecker** — сервис для проверки прав доступа
- **responseBuilder** — фабрика для создания HTTP ответов
- **requiredRights** — массив требуемых прав в формате `['модуль:право', ...]`
- **redirectUrl** — опциональный URL редиректа для неавторизованных пользователей (если null, вернётся статус 401)

**Поведение middleware:**

Middleware проверяет права из списка `requiredRights` последовательно. Для каждого права вызывается RightsChecker::
can(). Если проверка не прошла:

- Неавторизованный пользователь + задан `redirectUrl` — редирект на указанный URL
- Неавторизованный пользователь + `redirectUrl` не задан — статус 401 (Unauthorized)
- Авторизованный пользователь без требуемого права — статус 403 (Forbidden)

Если список `requiredRights` пуст, доступ разрешён всем пользователям (включая гостей) без каких-либо проверок.

## Типичные сценарии использования

### Аутентификация с сессией

Встроенный `SessionAuthenticator` ищет ID пользователя в сессии по ключу `jokeUserId`:

```php
use Vasoft\Joke\Auth\Session\SessionAuthenticator;

// Вход пользователя
$request->session->set(SessionAuthenticator::VAR_USER_ID, 'user123');

// При следующем запросе SessionAuthenticator найдёт пользователя в сессии
```

### Аутентификация с JWT

Встроенный `JwtAuthenticator` извлекает токен из:

1. Заголовка `Authorization: Bearer <token>`
2. Cookie с именем `jwt`

Для работы с JWT требуется реализация `JwtHandlerInterface`. Встроенная реализация — `SimpleJwtHandler`:

```php
use Vasoft\Joke\Auth\Jwt\SimpleJwtHandler;

$jwtHandler = new SimpleJwtHandler('your-secret-key-min-32-chars-long!');

// Кодирование токена
$token = $jwtHandler->encode(
    ['id' => 'user123', 'email' => 'user@example.com', 'role' => 'editor'],
    3600  // TTL в секундах
);
```

#### Как payload отображается на объект пользователя

При успешной аутентификации `JwtAuthenticator` разбирает payload следующим образом:

- Поле `id` становится идентификатором пользователя (`$user->id`)
- Все остальные поля сохраняются в коллекции данных под ключом `jwt`

```php
// После аутентификации по токену выше:
$user->id;                    // 'user123'
$user->data->get('jwt');      // ['email' => 'user@example.com', 'role' => 'editor', 'iat' => ..., 'exp' => ...]
$user->data->get('jwt')['email']; // 'user@example.com'
```

> **Обратите внимание:** стандартные поля JWT (`iat`, `exp`) также сохраняются в `$user->data['jwt']` и доступны для
> чтения. Если вам нужны дополнительные данные пользователя (например, полное имя или настройки), которые не входят в
> токен, используйте `UserProvider` для обогащения через метод `enrich()`.

### Комбинированная аутентификация

Можно использовать несколько аутентификаторов одновременно:

```php
// config/auth.php
declare(strict_types=1);

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Auth\Jwt\SimpleJwtHandler;
use Vasoft\Joke\Auth\Session\SessionAuthenticator;
use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Auth\Jwt\JwtAuthenticator;
use Vasoft\Joke\Http\HttpRequest;
use App\MyUserProvider;
use App\MyRightsSource;

return new AuthConfig()
    ->addAuthenticator(SessionAuthenticator::class)
    ->addAuthenticator(static function (HttpRequest $request, Environment $env) {
        // Ключ по умолчанию — только для краткости примера.
        // В продакшене отсутствие JWT_SECRET должно выбрасывать исключение,
        // иначе токен можно будет подделать с помощью известного ключа.
        $jwtHandler = new SimpleJwtHandler($env->get('JWT_SECRET', 'your-secret-key-min-32-chars'));

        return new JwtAuthenticator($request, $jwtHandler);
    })
    ->addUserProvider(MyUserProvider::class)
    ->addRightsSource(MyRightsSource::class);
```

При обработке запроса система сначала проверит сессию, затем JWT. Первый найденный пользователь будет считаться текущим.

## Встроенные компоненты

### User — объект пользователя

```php
use Vasoft\Joke\Auth\User;
use Vasoft\Joke\Collections\PropsCollection;

// Авторизованный пользователь с данными
$user = new User('user123', new PropsCollection([
    'name' => 'Alex',
    'email' => 'alex@example.com',
]));

echo $user->id;         // 'user123'
echo $user->authorized; // true
echo $user->data->get('name'); // 'Alex'

// Авторизованный пользователь без дополнительных данных
// (типичный случай при создании внутри аутентификатора)
$user = new User('user123');
echo $user->authorized; // true
echo $user->data->getAll(); // []

// Анонимный (гостевой) пользователь
$guest = new User(null);
echo $guest->authorized; // false
echo $guest->id;         // null
```

> **Обратите внимание:** второй параметр `$data` опционален. При отсутствии `PropsCollection` коллекция создаётся
> автоматически пустой. Свойство `$authorized` вычисляется автоматически: `true`, если `$id` не равен `null`.

### ConfigUserProvider — провайдер на основе конфигурации

```php
use Vasoft\Joke\Auth\Provider\ConfigUserProvider;

$provider = new ConfigUserProvider([
    'admin' => ['name' => 'Administrator', 'email' => 'admin@example.com'],
    'demo' => ['name' => 'Demo User', 'email' => 'demo@example.com'],
]);

$user = $provider->loadUserById('admin');
// Вернёт User с данными администратора
```

### ConfigRightsSource — источник прав на основе конфигурации

```php
use Vasoft\Joke\Auth\Rights\ConfigRightsSource;

$source = new ConfigRightsSource([
    'admin' => ['posts:view', 'posts:edit', 'users:manage'],
    'demo' => ['posts:view'],
    ConfigRightsSource::GUEST_KEY => ['posts:view'], // права для гостей
]);

$user = $authService->getUser();
$rights = $source->getRights($user);
// Вернёт права для текущего пользователя
```

## Следующие шаги

- [Примеры реализации системы аутентификации](../detail/auth-examples.md) — готовые решения для типичных сценариев
- [Детальное руководство по системе аутентификации](../detail/auth-system.md) — создание собственных реализаций
  контрактов
- [ClosureMiddleware — Адаптер для Middleware-замыканий](../detail/closure-middleware.md) — регистрация middleware с
  автоматическим внедрением зависимостей
- [Middleware](./middleware.md) — подробнее о системе middleware
- [DI-контейнер](./container.md) — как работает внедрение зависимостей
