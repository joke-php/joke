# Кеширование

Joke предоставляет встроенную систему кеширования с поддержкой времени жизни (TTL), групповых операций и различных хранилищ данных. По умолчанию используется файловый кэш, но система разработана с возможностью подключения собственных реализаций.

## Основные концепции

Система кеширования построена вокруг интерфейса `CacheInterface` и менеджера `CacheManager`:

- **Префиксы** — изолируют группы кэша в отдельные пространства имен. Разные части приложения могут использовать разные префиксы для независимого кеширования.
- **TTL (Time To Live)** — время жизни элемента кэша в секундах или через `DateInterval`. Если не указан, кэш хранится бессрочно.
- **CacheManager** — фабрика для создания экземпляров кэша с нужным префиксом.

## Получение экземпляра кэша

Кэш предоставляется через внедрение зависимостей. Получите экземпляр `CacheManager` и создайте кэш для нужного префикса:

```php
use Vasoft\Joke\Cache\CacheManager;

$router->get('/', static function (CacheManager $cacheManager) {
    // Создаём кэш с префиксом 'index'
    $cache = $cacheManager->build('index');
    
    return 'Success';
});
```

## Основные операции

### Сохранение значения

```php
// Сохранить на 2 минуты (120 секунд)
$cache->set('user:123', ['name' => 'Alice', 'email' => 'alice@example.com'], 120);

// Сохранить без ограничения срока
$cache->set('config:app', $appConfig);

// Сохранить с использованием DateInterval
$interval = new \DateInterval('PT1H'); // 1 час
$cache->set('session:xyz', $sessionData, $interval);
```

### Получение значения

```php
// Получить значение или null, если его нет
$user = $cache->get('user:123');

// Получить значение или вернуть значение по умолчанию
$user = $cache->get('user:123', ['default' => true]);
```

### Проверка наличия

```php
if ($cache->has('user:123')) {
    $user = $cache->get('user:123');
}
```

### Удаление значения

```php
// Удалить конкретный ключ
$cache->delete('user:123');

// Удалить несколько ключей
$cache->deleteMultiple(['user:123', 'user:456', 'user:789']);
```

### Очистка всего префикса

```php
// Удалить все ключи в текущем префиксе
$cache->clear();
```

## Групповые операции

Для удобства работы с несколькими ключами используйте групповые методы:

```php
// Сохранить несколько значений
$cache->setMultiple([
    'user:1' => ['id' => 1, 'name' => 'Alice'],
    'user:2' => ['id' => 2, 'name' => 'Bob'],
    'user:3' => ['id' => 3, 'name' => 'Charlie'],
], 300); // TTL 5 минут для всех

// Получить несколько значений
$users = $cache->getMultiple(['user:1', 'user:2', 'user:999'], []);
// Результат: ['user:1' => [...], 'user:2' => [...], 'user:999' => []]
```

## Практический пример

Кеширование фрагмента страницы на 2 минуты:

```php
use Vasoft\Joke\Cache\CacheManager;
use Vasoft\Joke\Http\Response\ResponseBuilder;

$router->get('/', static function (ResponseBuilder $builder, CacheManager $cacheManager) {
    $cache = $cacheManager->build('index');
    if ($cache->has('example1')) {
        $cached = $cache->get('example1');
    } else {
        $cached = '<p>Этот блок закеширован на 2 минуты в ' . date('H:i:s') . '</p>';
        $cache->set('example1', $cached, 120);
    }

    return $builder->makeDefault()->setBody($cached);
});
```

> Между `has()` и `get()` запись может истечь или быть удалена. Если важно получить значение гарантированно,
> вызывайте `get()` с собственным значением по умолчанию и проверяйте результат.

## Конфигурация

По умолчанию фреймворк использует файловый кэш. Чтобы изменить реализацию, используйте `ApplicationConfig`:

```php
<?php
// config/app.php

declare(strict_types=1);

use App\Cache\RedisCache; // Ваша реализация CacheInterface
use Vasoft\Joke\Application\ApplicationConfig;

return new ApplicationConfig()
    ->setCacheClass(RedisCache::class);
```

Класс должен реализовывать `CacheInterface`. Как написать собственную реализацию, описано в
[detail/cache-system.md](../detail/cache-system.md). Тип кеша один на всё приложение.

## Обработка ошибок

Методы кэша выбрасывают исключение `CacheException` при некорректном ключе (например, пустая строка):

```php
use Vasoft\Joke\Cache\Exceptions\CacheException;

try {
    $cache->set('', 'value'); // Ошибка: пустой ключ
} catch (CacheException $e) {
    // Ошибка: "Key must not be empty."
}
```

Если `CacheManager` настроен на класс, который не реализует `CacheInterface`, `build()` выбросит `ConfigException`.

## Инвалидация кэша

Если вы изменили данные в базе, необходимо инвалидировать кэш:

```php
// После обновления пользователя
updateUserInDatabase($userId, $newData);

// Удаляем устаревший кэш
$cache->delete('user:' . $userId);
```

Или для полного сброса кэша по префиксу:

```php
// Очищаем весь кэш страниц
$cache->clear();
```

## Префиксы и изоляция

Префикс — имя подкаталога внутри `var/cache`. Используйте короткие статические имена из букв, цифр, `_`, `-`
(`pages`, `users`). Не формируйте префикс из пользовательского ввода и не передавайте пустую строку или пути с `..`:
`clear()` удаляет всё содержимое каталога префикса. 

Разные префиксы изолируют кэш-данные. Это полезно для организации:

```php
$pageCache = $cacheManager->build('pages');
$userCache = $cacheManager->build('users');
$configCache = $cacheManager->build('config');

// Очистка только пользовательского кэша не затронет остальное
$userCache->clear();
```

Вложенные префиксы (`pages/list`) удобно использовать для группового управления кешем:

```php
$cacheManager->build('pages/list')->set('data','test1');
$cacheManager->build('pages/detail')->set('data','test2');
// ....
// Очистка всей группы кеша
$cacheManager->build('pages')->clear();
```

## Сериализация объектов

Значения сериализуются при сохранении и десериализуются при получении. Скаляры и массивы работают без настройки.
Объекты по умолчанию **запрещены**: `build()` создаёт кэш с `allowedClasses = false`. Запись с объектом будет
сохранена, но при чтении расценена как испорченная: `get()` вернёт значение по умолчанию, а запись удалится.

Чтобы кешировать объекты, передайте список разрешённых классов вторым аргументом `build()`:

```php
class User
{
    public function __construct(public int $id, public string $name) {}
}

$cache = $cacheManager->build('users', [User::class]);

$cache->set('user', new User(123, 'Alice'));

$cachedUser = $cache->get('user');
echo $cachedUser->name; // Alice
```

Значения `allowedClasses`:

- `false` (по умолчанию) — объекты запрещены;
- `[User::class, ...]` — разрешены только перечисленные классы (рекомендуется);
- `true` — разрешены любые классы. Это небезопасно: при возможности записи в каталог кеша злоумышленник может
  подсунуть произвольные объекты. Используйте только осознанно.

Для одного префикса используйте один и тот же список классов во всех местах кода: чтение с более строгим списком
считает записи испорченными и удалит их.

> Объекты, которые нельзя сериализовать (например, замыкания), сохранить нельзя.
