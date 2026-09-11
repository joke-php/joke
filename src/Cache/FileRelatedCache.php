<?php

declare(strict_types=1);

namespace Vasoft\Joke\Cache;

use Vasoft\Joke\Contract\FileRelatedCacheInterface;
use Vasoft\Joke\Exceptions\FileSystemException;
use Vasoft\Joke\Support\FileSystem;

/**
 * Файловый кэш, привязанный к исходному файлу.
 *
 * Хранит скомпилированные данные (например PHP-код шаблонов) в файловой системе.
 * Автоматически инвалидирует кэш при изменении исходного файла или истечении TTL.
 *
 * Пример использования:
 * ```php
 * $cache = new FileRelatedCache('/tmp/cache', '/app/views/index.php', 3600);
 *
 * if (!$cache->exists()) {
 *     $compiled = $compiler->compile(file_get_contents($cache->srcFilePath));
 *     $cache->set($compiled);
 * }
 *
 * include $cache->path;
 * ```
 */
class FileRelatedCache implements FileRelatedCacheInterface
{
    /**
     * Путь к кэш-файлу.
     *
     * Формируется на основе MD5-хеша исходного файла.
     * Структура: {cacheDir}/{2 символа хеша}/{полный хеш}.{extension}
     *
     *  ВНИМАНИЕ: в многопроцессной среде (FPM, ReactPHP) между проверкой exists()
     *  и использованием path возможна гонка — другой процесс может удалить кэш-файл.
     *  Рекомендуется использовать try/catch вокруг include или проверять
     *  существование файла непосредственно перед include.
     *
     * @var non-empty-string
     */
    public private(set) string $path;

    /**
     * @param FileSystem $fs          Сервис файловой системы
     * @param string     $cacheDir    Базовая директория для хранения кэш-файлов относительно каталога кеша проекта
     * @param string     $srcFilePath Путь к исходному файлу (используется как ключ и для проверки актуальности)
     * @param int        $ttl         Время жизни кэша в секундах
     * @param string     $extension   Расширение кэш-файла (по умолчанию 'php')
     *
     * @throws FileSystemException При ошибках файловой системы
     */
    public function __construct(
        private readonly FileSystem $fs,
        string $cacheDir,
        public readonly string $srcFilePath,
        private readonly int $ttl,
        string $extension = 'php',
    ) {
        $cacheDir = trim($cacheDir, '\/');
        $hash = md5($srcFilePath);
        $path = $fs->atCache(sprintf('%s/%s', $cacheDir, mb_substr($hash, 0, 2)));
        $fs->ensureDirectory($path);
        $this->path = $path . $hash . '.' . $extension;
    }

    /**
     * {@inheritDoc}
     *
     * Проверка включает:
     * 1. Существование кэш-файла
     * 2. Соответствие TTL
     * 3. Актуальность относительно mtime исходного файла
     *
     * Ошибки чтения метаданных файлов подавляются и трактуются как отсутствие кэша.
     */
    public function exists(): bool
    {
        if (!file_exists($this->path)) {
            return false;
        }
        $cacheTime = @filemtime($this->path);
        $maxTime = time() - $this->ttl;
        if (false === $cacheTime || $cacheTime <= $maxTime) {
            return false;
        }
        $srcTime = @filemtime($this->srcFilePath);
        if (false !== $srcTime && $srcTime > $cacheTime) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * Реализует атомарную запись: данные сначала пишутся во временный файл, затем переименовываются в целевой путь.
     *
     * @throws FileSystemException При ошибках файловой системы
     */
    public function set(string $value): void
    {
        $this->fs->writeFileSafe($this->path, $value);
    }

    public function clear(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
