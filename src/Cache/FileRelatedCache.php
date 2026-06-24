<?php

declare(strict_types=1);

namespace Vasoft\Joke\Cache;

use Vasoft\Joke\Contract\FileRelatedCacheInterface;

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
     */
    public private(set) string $path;

    /**
     * @param string $cacheDir    Базовая директория для хранения кэш-файлов
     * @param string $srcFilePath Путь к исходному файлу (используется как ключ и для проверки актуальности)
     * @param int    $ttl         Время жизни кэша в секундах
     * @param string $extension   Расширение кэш-файла (по умолчанию 'php')
     */
    public function __construct(
        string $cacheDir,
        public readonly string $srcFilePath,
        private readonly int $ttl,
        string $extension = 'php',
    ) {
        $hash = md5($srcFilePath);
        $this->path = sprintf('%s/%s/%s.%s', $cacheDir, mb_substr($hash, 0, 2), $hash, $extension);
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
     */
    public function set(string $value): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $tmp = tempnam($dir, '.tmp.');
        file_put_contents($tmp, $value);
        rename($tmp, $this->path);
    }

    public function clear(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
