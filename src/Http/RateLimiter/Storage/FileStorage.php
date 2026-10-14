<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter\Storage;

use Vasoft\Joke\Cache\Exceptions\CacheException;
use Vasoft\Joke\Cache\FileCache;
use Vasoft\Joke\Contract\Http\RateLimiter\StorageInterface;
use Vasoft\Joke\Http\RateLimiter\Exceptions\RateLimiterException;
use Vasoft\Joke\Support\FileSystem;

/**
 * Файловое хранилище счётчиков для ограничителя частоты запросов.
 *
 * Использует {@see FileCache} в качестве бэкенда с изолированным префиксом
 * для хранения данных rate limiter'а отдельно от основного кэша приложения.
 *
 * Особенности:
 * - Атомарность инкремента через файловые блокировки ({@see FileCache::modify()})
 * - Автоматическая сериализация/десериализация значений
 * - Изоляция данных в подкаталоге "rate_limiter"
 *
 * @see StorageInterface
 * @see FileCache
 */
class FileStorage implements StorageInterface
{
    /**
     * Префикс (подкаталог) для изоляции данных rate limiter'а в файловом кэше.
     */
    public const string PREFIX = 'rate_limiter';
    private FileCache $storage;

    /**
     * Создаёт файловое хранилище счётчиков.
     *
     * Инициализирует {@see FileCache} с префиксом {@see PREFIX} для изоляции
     * данных rate limiter'а от остальных записей кэша.
     *
     * @param FileSystem $fs Сервис работы с файловой системой
     *
     * @throws RateLimiterException При некорректном формате префикса
     */
    public function __construct(
        FileSystem $fs,
    ) {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->storage = new FileCache($fs)
            ->setPrefix(static::PREFIX);
    }

    /**
     * {@inheritDoc}
     *
     * Реализует атомарный инкремент через {@see FileCache::modify()}, что обеспечивает
     * безопасность при конкурентном доступе из нескольких процессов благодаря
     * файловым блокировкам.
     *
     * @throws RateLimiterException При ошибках чтения/записи файла или повреждении данных кэша
     */
    public function increment(string $key, int $ttl = 3600): int
    {
        try {
            return $this->storage->modify(
                $key,
                static function (string|int|null $value) {
                    $value = (int) ($value ?? 0);

                    return $value + 1;
                },
                $ttl,
            );
        } catch (CacheException $e) {
            throw new RateLimiterException($e->getMessage(), previous: $e);
        }
    }
}
