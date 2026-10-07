# Система кеширования (для разработчиков)

Документ предназначен для тех, кто хочет заменить встроенный файловый кеш собственной реализацией (Redis, Memcached,
APCu, база данных и т. п.). Пользовательское описание API находится в [base/cache.md](../base/cache.md).

## Архитектура

Система состоит из четырёх частей:

| Класс                                         | Назначение                                                         |
|-----------------------------------------------|--------------------------------------------------------------------|
| `Vasoft\Joke\Contract\Cache\CacheInterface`   | Контракт, который реализует любой драйвер кеша                     |
| `Vasoft\Joke\Cache\CacheManager`              | Фабрика: создаёт драйвер для префикса через DI-контейнер           |
| `Vasoft\Joke\Cache\FileCache`                 | Встроенная реализация (файлы в `var/cache`)                        |
| `Vasoft\Joke\Cache\Exceptions\CacheException` | Базовое исключение системы кеширования (наследует `JokeException`) |

Тип драйвера хранится в `ApplicationConfig::$cacheClass` (по умолчанию `FileCache::class`), менять его можно через
`ApplicationConfig::setCacheClass()`. `KernelServiceProvider` регистрирует `CacheManager` в контейнере как singleton.

### CacheInterface

```php
namespace Vasoft\Joke\Contract\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function getMultiple(iterable $keys, mixed $default = null): iterable;
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool;
    public function deleteMultiple(iterable $keys): bool;
    public function has(string $key): bool;
    public function setPrefix(string $prefix): static;
}
```

Семантика, которую должен обеспечить драйвер:

- `get()` возвращает `$default`, если элемента нет или срок его жизни истёк.
- `set()` с `$ttl === null` хранит элемент бессрочно. `$ttl` — число секунд или `\DateInterval`.
- `delete()` возвращает `true`, если элемент удалён или его не было.
- `clear()` очищает пространство имён текущего префикса и всех его вложенных префиксов (например, очистка `pages` удалит
  данные из `pages/list`).
- `has()` возвращает `true` только для существующего и не просроченного элемента.
- Методы `*Multiple` работают с набором ключей; атомарность не гарантируется.
- Ошибки хранилища и некорректный ключ должны приводить к `CacheException`.
- `setPrefix` устанавливает префикс. Поддерживаются иерархические имена через `/`. Метод должен вызываться ровно один
  раз после создания экземпляра.

### CacheManager

```php
public function build(string $prefix, bool|array $allowedClasses = false): CacheInterface
```

Менеджер вызывает `ServiceContainer::make($cacheClass, ['allowedClasses' => $allowedClasses])`.
Из этого следуют, что конструктор вашего драйвера:

1. Может иметь параметр `bool|array $allowedClasses` (значение по умолчанию `false`).
2. Остальные параметры разрешаются контейнером автоматически (как у `FileCache` параметр `FileSystem $fs`).

Если результат `make()` не реализует `CacheInterface`, менеджер выбрасывает `ConfigException`.
После создание экземпляра происходит вызов метода `setPrefix`.
Менеджер каждый раз создаёт новый экземпляр драйвера, повторное использование в рамках запроса не реализовано.

### FileCache как образец

```php
public function __construct(
    private readonly FileSystem $fs,
    private readonly bool|array $allowedClasses = false,
) {}
```

Особенности `FileCache`, которые стоит учитывать при написании собственного драйвера:

- Запись в файл — `serialize(['value' => ..., 'expires' => ?int])`, путь `var/cache/{prefix}/{md5[0..1]}/{md5}.cache`.
- Чтение — `unserialize(..., ['allowed_classes' => $allowedClasses])`. Повреждённая, просроченная запись или запись с
  неразрешённым классом считается промахом и удаляется.
- Пустой ключ (`'' === trim($key)`) приводит к `CacheException('Key must not be empty.')`.

## Создание собственного драйвера

### Шаг 1. Реализуйте CacheInterface

Пример ниже использует расширение `ext-redis`. Он демонстрирует рекомендуемые приёмы: проверку префикса, очистку
префикса без `KEYS`, оборачивание ошибок в `CacheException` и корректную обработку TTL.

```php
<?php

declare(strict_types=1);

namespace App\Cache;

use Vasoft\Joke\Cache\Exceptions\CacheException;
use Vasoft\Joke\Contract\Cache\CacheInterface;

class RedisCache implements CacheInterface
{
    public const string DEFAULT_PREFIX = '__DEFAULT_PREFIX__';  
    private string $prefix = self::DEFAULT_PREFIX;
    
    /**
     * @param \Redis                  $redis          Подключение к Redis
     * @param bool|list<class-string> $allowedClasses Разрешённые классы при десериализации
     *
     * @throws CacheException При недопустимом префиксе
     */
    public function __construct(
        private readonly \Redis $redis,
        private readonly bool|array $allowedClasses = false,
    ) {
    }
    /**
    * @param string $prefix Пространство имён (только буквы, цифры, '_', '.', '-', '/')
    * @return $this
    */    
    public function setPrefix(string $prefix): static
    {
        if (self::DEFAULT_PREFIX === $this->prefix) {
            if (1 !== preg_match('/^[A-Za-z0-9_.-]+$/', $prefix)) {
               throw new CacheException('Invalid cache prefix.');
            }
            $this->prefix = str_replace('/', ':', $prefix);
        }

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->call(fn() => $this->redis->get($this->makeKey($key)));
        if (!is_string($raw)) {
            return $default;
        }
        $data = @unserialize($raw, ['allowed_classes' => $this->allowedClasses]);
        if (!is_array($data) || !array_key_exists('value', $data)) {
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $fullKey = $this->makeKey($key);
        $seconds = $this->toSeconds($ttl);
        if (null !== $seconds && $seconds <= 0) {
            return $this->delete($key);
        }

        try {
            $raw = serialize(['value' => $value]);
        } catch (\Throwable $e) {
            throw new CacheException('Value cannot be serialized: ' . $e->getMessage(), 0, $e);
        }

        return $this->call(
            fn() => null === $seconds
                ? (bool) $this->redis->set($fullKey, $raw)
                : (bool) $this->redis->setex($fullKey, $seconds, $raw),
        );
    }

    public function delete(string $key): bool
    {
        $fullKey = $this->makeKey($key);
        $this->call(fn() => $this->redis->del($fullKey));

        return true;
    }

    public function clear(): bool
    {
        $pattern = 'joke:cache:' . $this->prefix . ':*';

        $iterator = null;
        do {
            $keys = $this->call(fn() => $this->redis->scan($iterator, $pattern, 100));
            if (false !== $keys && !empty($keys)) {
                $this->call(fn() => $this->redis->del(...$keys));
            }
        } while ($iterator > 0);

        return true;
    }
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    public function has(string $key): bool
    {
        $fullKey = $this->makeKey($key);

        return 0 < (int) $this->call(fn() => $this->redis->exists($fullKey));
    }

    private function makeKey(string $key): string
    {
        if ('' === trim($key)) {
            throw new CacheException('Key must not be empty.');
        }
        $version = (int) $this->call(fn() => $this->redis->get('joke:cache:version:' . $this->prefix));

        return 'joke:cache:' . $this->prefix . ':' . $version . ':' . hash('sha256', $key);
    }

    private function toSeconds(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl instanceof \DateInterval) {
            $now = new \DateTimeImmutable();

            return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        return $ttl;
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     *
     * @throws CacheException
     */
    private function call(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (\Throwable $e) {
            throw new CacheException('Cache storage error: ' . $e->getMessage(), 0, $e);
        }
    }
}
```

Замечания к примеру:

- `CacheException` наследует `\Exception` и принимает стандартные аргументы (`message`, `code`, `previous`).
- Ключ хешируется (`sha256`), поэтому длина и символы исходного ключа не влияют на ключ хранилища.
- Для драйверов, хранящих данные в разделяемом хранилище, выбирайте изоляцию префиксов, не требующую полного
  перебора ключей.

### Шаг 2. Сделайте зависимости доступными контейнеру

Контейнер создаёт драйвер через `make()` и сам подставляет типизированные зависимости. Для `\Redis` нужна регистрация
в провайдере (`registerSingleton` принимает имя и класс, объект либо callable):

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Provider\AbstractProvider;

class RedisServiceProvider extends AbstractProvider
{
    public function __construct(
        private readonly ServiceContainer $serviceContainer,
    ) {}

    public function register(): void
    {
        $this->serviceContainer->registerSingleton(\Redis::class, static function (): \Redis {
            $redis = new \Redis();
            $redis->connect('127.0.0.1', 6379);

            return $redis;
        });
    }

    public function provides(): array
    {
        return [\Redis::class];
    }
}
```

Провайдер подключается в `bootstrap/kernel.php` (
подробнее: [providers.md](providers.md), [start/config.md](../start/config.md)):

```php
<?php
// bootstrap/kernel.php

declare(strict_types=1);

use App\Providers\RedisServiceProvider;
use Vasoft\Joke\Application\KernelConfig;

return new KernelConfig()
    ->addProvider(RedisServiceProvider::class);
```

### Шаг 3. Укажите драйвер в конфигурации приложения

Тип кеша задаётся в `config/app.php` через `ApplicationConfig::setCacheClass()`:

```php
<?php
// config/app.php

declare(strict_types=1);

use App\Cache\RedisCache;
use Vasoft\Joke\Application\ApplicationConfig;

return new ApplicationConfig()
    ->setCacheClass(RedisCache::class);
```

После этого `CacheManager::build()` во всём приложении будет возвращать `RedisCache`. Выбрать разные драйверы для разных
префиксов штатно нельзя: класс один на приложение.

## Рекомендации по реализации

1. **Ключ.** Пустой ключ (`'' === trim($key)`) — `CacheException`. Не используйте ключ как есть в путях и именах:
   хешируйте или строго фильтруйте.
2. **Префикс.** Проверяйте в конструкторе по белому списку символов. Недопустимы `..`, разделители пути и пустая строка:
   префикс участвует в операции `clear()`.
3. **TTL.** `null` — бессрочно, число — секунды, `\DateInterval` — переводите в абсолютное время. Значение `<= 0`
   трактуйте как «элемент уже просрочен» (удалите и верните `true`).
4. **Сериализация.** Применяйте `serialize()`/`unserialize()` с `['allowed_classes' => $allowedClasses]`. Значение
   `true` разрешает любые классы и небезопасно, если хранилище доступно на запись посторонним. Ошибки `serialize()`
   оборачивайте в `CacheException`.
5. **allowedClasses при чтении.** Если запись содержит запрещённый класс, возвращайте `$default`, а не удаляйте запись:
   другой экземпляр драйвера с другими настройками может быть вправе её прочитать.
6. **Исключения.** Любую ошибку хранилища оборачивайте в `CacheException` с `previous`, чтобы вызывающий код ловил один
   тип исключения.
7. **Групповые операции.** Допустимо реализовать циклом по одиночным (как в `FileCache`), либо использовать пакетные
   команды хранилища. Приводите ключи к `string`, иначе при `declare(strict_types=1)` целочисленные ключи массива
   приведут к `TypeError`.
8. **Чистка просроченных данных.** Если хранилище не удаляет их само (как файловая система), предусмотрите способ
   их очистки.

## Тестирование

Реальный пример тестов см. в `tests/Cache/FileCacheTest.php` и `tests/Cache/CacheManagerTest.php`. Для проверки
собственного драйвера полезно покрыть:

- `get` для отсутствующего ключа возвращает `$default`;
- `set`/`get`/`has`/`delete` для скаляров, массивов и разрешённых объектов;
- истечение TTL (в том числе `0` и `\DateInterval`);
- пустой ключ и недопустимый префикс — `CacheException`;
- изоляцию двух префиксов и поведение `clear()` одного из них;
- объект неразрешённого класса при `allowedClasses = false`.

```php
<?php

declare(strict_types=1);

namespace App\Tests\Cache;

use App\Cache\RedisCache;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Cache\Exceptions\CacheException;

final class RedisCacheTest extends TestCase
{
    public function testEmptyKeyThrowsException(): void
    {
        $cache = new RedisCache('test', self::createStub(\Redis::class));

        $this->expectException(CacheException::class);
        $cache->get('');
    }

    public function testInvalidPrefixThrowsException(): void
    {
        $this->expectException(CacheException::class);
        new RedisCache('../etc', self::createStub(\Redis::class));
    }
}
```
