<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response\Html\Asset;

use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Logging\Exception\LogException;

/**
 * Менеджер для обработки адресов статических файлов (CSS, JS, изображения).
 *
 * Формирует публичные URI с версионированием для кэширования в браузере.
 * Файлы вне documentRoot копируются с хэшированием имён для скрытия структуры проекта.
 * Файлы внутри documentRoot используются напрямую с добавлением параметра версии.
 * URI нормализуются: приводятся к нижнему регистру для кросс-платформенной консистентности.
 */
class AssetFileManager
{
    /**
     * @var string Базовый путь проекта (корневая директория)
     */
    protected readonly string $projectBasePath;
    /**
     * @var string Публичная директория (documentRoot веб-сервера)
     */
    protected readonly string $documentRoot;

    /**
     * @param string $projectBasePath Базовый путь проекта
     * @param string $documentRoot    Публичная директория (documentRoot)
     * @param string $getProp         Имя GET-параметра для версионирования (по умолчанию `v`)
     */
    public function __construct(
        string $projectBasePath,
        string $documentRoot,
        private readonly string $getProp = 'v',
    ) {
        $this->projectBasePath = rtrim($projectBasePath, '/');
        $this->documentRoot = rtrim($documentRoot, '/');
    }

    /**
     * Обрабатывает путь к файлу и возвращает публичный URI с версионированием.
     *
     * - Абсолютные URL возвращаются без изменений
     * - Файлы вне documentRoot копируются с хэшированием имени
     * - Файлы внутри documentRoot используются напрямую с версионированием
     *
     * @param string $filePath      Путь к файлу или URL
     * @param string $directoryName Подкаталог внтури базовый URI для публичных статических файлов, может быть пустой строкой
     *
     * @return string Публичный URI с параметром версии
     *
     * @throws JokeException Если файл не найден или путь вне разрешённой директории
     */
    public function process(string $filePath, string $directoryName): string
    {
        if (str_contains($filePath, ':/')) {
            return $filePath;
        }

        return $this->processFile($filePath, !str_starts_with($filePath, $this->documentRoot), $directoryName);
    }

    /**
     * Обрабатывает файл: копирует (если нужно) и формирует URI с версионированием.
     *
     * @param string $source        Путь к исходному файлу
     * @param bool   $withCopy      Копировать файл в documentRoot (true) или использовать напрямую (false)
     * @param string $directoryName Подкаталог внутри базовый URI для публичных статических файлов, может быть пустой строкой
     *
     * @return string URI с параметром версии
     *
     * @throws JokeException Если файл не найден
     * @throws LogException  Если не удалось создать директорию или скопировать файл
     */
    private function processFile(string $source, bool $withCopy, string $directoryName): string
    {
        $parts = explode('?', $source, 2);
        $path = $parts[0];
        $realPath = realpath($path);
        $query = $parts[1] ?? '';
        if (false === $realPath) {
            throw new JokeException('Asset file not found: ' . $path);
        }
        if (!str_starts_with($realPath, $this->projectBasePath)) {
            throw new JokeException("Asset path outside allowed {$source} directory.");
        }
        clearstatcache(true, $realPath);
        $timestamp = filemtime($realPath);
        if ($withCopy) {
            $uri = $this->compileUri($realPath, $directoryName);
            $cachedCopy = $this->documentRoot . $uri;
            $this->checkFile($realPath, $cachedCopy, $timestamp);
        } else {
            $uri = str_replace($this->documentRoot, '', $realPath);
        }
        if ('' !== $query) {
            $uri .= '?' . $query . '&';
        } else {
            $uri .= '?';
        }
        $uri .= $this->getProp . '=' . $timestamp;

        return $uri;
    }

    /**
     * Копирует файл с блокировкой для защиты от race condition.
     *
     * Использует flock() для безопасной одновременной записи.
     * Копирование выполняется только если файл устарел или отсутствует.
     *
     * @param string $source      Путь к исходному файлу
     * @param string $destination Путь к целевому файлу
     *
     * @throws LogException Если не удалось открыть файл или скопировать
     */
    private function checkFile(string $source, string $destination, int $timestamp): void
    {
        if (file_exists($destination) && filemtime($destination) + 60 >= $timestamp) {
            return;
        }

        $handle = fopen($destination, 'c+');

        if (false === $handle) {
            throw new LogException("Unable to open file for locking: {$destination}.");
        }
        if (flock($handle, LOCK_EX)) {
            clearstatcache(true, $destination);
            if (!copy($source, $destination)) {
                flock($handle, LOCK_UN);
                fclose($handle);

                throw new LogException("Unable to copy asset from {$source} to {$destination}");
            }
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }

    /**
     * Создаёт директорию рекурсивно, если она не существует.
     *
     * @param string $dir Путь к директории
     *
     * @throws LogException Если не удалось создать директорию
     */
    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0o775, true)) {
            throw new LogException("Unable to create directory '{$dir}'.");
        }
    }

    /**
     * Компилирует URI для файла с хэшированием и заменой путей.
     *
     * - Проверяет что файл внутри projectBasePath (безопасность)
     * - Генерирует хэш от realpath для консистентности
     * - Создаёт целевую директорию если нужно
     *
     * @param string $src           Путь к исходному файлу
     * @param string $directoryName Подкаталог внутри базовый URI для публичных статических файлов, может быть пустой строкой
     *
     * @return string Относительный URI от documentRoot
     *
     * @throws JokeException Если путь вне разрешённой директории
     * @throws LogException  Если не удалось создать директорию
     */
    private function compileUri(string $src, string $directoryName): string
    {
        $hash = md5($src);
        $info = pathinfo($src);
        $baseUri = trim($directoryName, " \n\r\t\v\0/");
        $dir = $baseUri . '/' . substr($hash, 0, 2) . '/';
        $this->ensureDir($this->documentRoot . '/' . ltrim($dir, '/'));

        return '/' . $dir . $hash . '_' . $info['basename'];
    }
}
