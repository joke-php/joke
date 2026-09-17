<?php

declare(strict_types=1);

namespace Vasoft\Joke\Support;

use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Exceptions\FileSystemException;

/**
 * Утилитарный класс для нормализации и валидации файловых путей.
 *
 * Гарантирует, что все операции с путями выполняются относительно корректного
 * абсолютного базового пути. Класс является неизменяемым (immutable).
 *
 * Особенности:
 * - Автоматическая проверка существования и абсолютности базового пути при создании.
 * - Кроссплатформенная поддержка (Windows/Linux/macOS) через определение стиля путей.
 * - Нормализация завершающих разделителей для директорий и файлов.
 * - Изолированное подключение файлов с передачей переменных через Closure.
 * - Атомарная запись файлов через временные файлы.
 *
 * @see IncludeType
 */
class FileSystem
{
    /**
     * Абсолютный базовый путь к корневой директории.
     * Всегда заканчивается разделителем директории.
     *
     * @var non-empty-string
     */
    public readonly string $basePath;

    /**
     * Права доступа по умолчанию для создаваемых директорий.
     */
    private const int DEFAULT_DIR_PERMISSIONS = 0o775;

    /**
     * Путь к директории временных и служебных данных (var).
     *
     * Используется для хранения кэша, логов и других генерируемых файлов.
     *
     * @var non-empty-string
     */
    public string $varPath {
        get => $this->basePath . 'var' . \DIRECTORY_SEPARATOR;
    }
    /**
     * Путь к директории bootstrap.
     *
     * Содержит скрипты инициализации приложения (например, kernel.php).
     *
     * @var non-empty-string
     */
    public string $bootstrapPath {
        get => $this->basePath . 'bootstrap' . \DIRECTORY_SEPARATOR;
    }
    /**
     * Путь к директории кэша.
     *
     * Используется для хранения скомпилированных шаблонов, кэша конфигурации
     * и других временных данных для ускорения работы приложения.
     *
     * @var non-empty-string
     */
    public string $cachePath {
        get => $this->varPath . 'cache' . \DIRECTORY_SEPARATOR;
    }
    /**
     * Путь к директории логов.
     *
     * Предназначена для хранения файлов журналов событий и ошибок приложения.
     *
     * @var non-empty-string
     */
    public string $logPath {
        get => $this->varPath . 'log' . \DIRECTORY_SEPARATOR;
    }
    /**
     * Путь к публичной директории.
     *
     * Точка входа для веб-сервера. Содержит фронт-контроллер (index.php),
     * а также публичные ресурсы: CSS, JavaScript, изображения и другие файлы,
     * доступные напрямую из браузера.
     *
     * @var non-empty-string
     */
    public string $publicPath {
        get => $this->basePath . 'public' . \DIRECTORY_SEPARATOR;
    }
    /**
     * Флаг операционной системы Windows.
     */
    private bool $isWindows;

    /**
     * Создаёт экземпляр сервиса файловой системы.
     *
     * @param string $basePath базовый путь к директории
     *
     * @throws ConfigException если путь не является абсолютным или не существует на диске
     */
    public function __construct(string $basePath)
    {
        $realBase = realpath($basePath);
        if (false === $realBase || !is_dir($realBase)) {
            throw new ConfigException("Path must be an existing directory: '{$basePath}'.");
        }
        $this->isWindows = 'WIN' === strtoupper(substr(PHP_OS, 0, 3));
        $this->basePath = rtrim($realBase, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;
    }

    /**
     * Нормализует путь к директории.
     *
     * Если передан относительный путь, он преобразуется в абсолютный путем добавления basePath.
     * Гарантирует, что результирующий путь всегда заканчивается ровно одним разделителем директории.
     *
     * @param non-empty-string $path путь к директории (относительный или абсолютный)
     *
     * @return non-empty-string абсолютный нормализованный путь к директории с завершающим разделителем
     */
    public function normalizeDir(string $path): string
    {
        if (!self::isAbsolute($path)) {
            $path = $this->basePath . $path;
        }

        return rtrim($path, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;
    }

    /**
     * Нормализует путь к файлу.
     *
     * Если передан относительный путь, он преобразуется в абсолютный путем добавления basePath.
     * В отличие от директорий, путь к файлу не гарантированно должен заканчиваться разделителем.
     *
     * @param non-empty-string $path путь к файлу (относительный или абсолютный)
     *
     * @return non-empty-string абсолютный нормализованный путь к файлу
     */
    public function normalizeFile(string $path): string
    {
        if (self::isAbsolute($path)) {
            return $path;
        }

        return $this->basePath . $path;
    }

    /**
     * Проверяет, является ли путь абсолютным.
     *
     * Учитывает особенности операционной системы:
     * - Для Windows: проверяет наличие буквы диска с разделителем (например, "C:\" или "C:/").
     * - Для Unix-систем: проверяет наличие начального слэша ("/").
     *
     * @param non-empty-string $path путь для проверки
     *
     * @return bool true, если путь абсолютный, иначе false
     */
    public function isAbsolute(string $path): bool
    {
        if ($this->isWindows) {
            return (bool) preg_match('~^[A-Z]:(\\\|/)~i', $path);
        }

        return str_starts_with($path, \DIRECTORY_SEPARATOR);
    }

    /**
     * Формирует абсолютный путь относительно базовой директории.
     *
     * @param non-empty-string $path относительный путь
     *
     * @return non-empty-string абсолютный путь
     */
    public function atBase(string $path): string
    {
        return $this->basePath . ltrim($path, \DIRECTORY_SEPARATOR);
    }

    /**
     * Формирует абсолютный путь относительно директории кэша.
     *
     * @param non-empty-string $path относительный путь внутри var/cache/
     *
     * @return non-empty-string абсолютный путь
     */
    public function atCache(string $path): string
    {
        return $this->cachePath . ltrim($path, \DIRECTORY_SEPARATOR);
    }

    /**
     * Формирует абсолютный путь относительно директории bootstrap.
     *
     * @param non-empty-string $path относительный путь внутри bootstrap/
     *
     * @return non-empty-string абсолютный путь
     */
    public function atBootstrap(string $path): string
    {
        return $this->bootstrapPath . ltrim($path, \DIRECTORY_SEPARATOR);
    }

    /**
     * Формирует абсолютный путь относительно директории логов.
     *
     * @param non-empty-string $path относительный путь внутри var/log/
     *
     * @return non-empty-string абсолютный путь
     */
    public function atLog(string $path): string
    {
        return $this->logPath . ltrim($path, \DIRECTORY_SEPARATOR);
    }

    /**
     * Формирует абсолютный путь относительно директории var.
     *
     * @param non-empty-string $path относительный путь внутри var/
     *
     * @return non-empty-string абсолютный путь
     */
    public function atVar(string $path): string
    {
        return $this->varPath . ltrim($path, \DIRECTORY_SEPARATOR);
    }

    /**
     * Формирует абсолютный путь относительно произвольной директории.
     *
     * @param non-empty-string $directory базовая директория (будет нормализована)
     * @param non-empty-string $path      относительный путь внутри указанной директории
     *
     * @return non-empty-string абсолютный путь
     */
    public function at(string $directory, string $path): string
    {
        return $this->normalizeDir($directory) . ltrim($path, \DIRECTORY_SEPARATOR);
    }

    /**
     * Создаёт директорию рекурсивно, если она не существует.
     *
     * Перед созданием проверяет, что путь находится внутри basePath.
     *
     * @param non-empty-string $directory   абсолютный путь к директории
     * @param int              $permissions права доступа (по умолчанию 0775)
     *
     * @throws FileSystemException если путь вне basePath или не удалось создать директорию
     */
    public function ensureDirectory(string $directory, int $permissions = self::DEFAULT_DIR_PERMISSIONS): void
    {
        $this->validatePath($directory);
        if (!is_dir($directory) && !mkdir($directory, $permissions, true) && !is_dir($directory)) {
            throw new FileSystemException("Unable to create directory: '{$directory}'.");
        }
    }

    /**
     * Проверяет, что путь находится внутри базовой директории.
     *
     * Защита от directory traversal атак:
     * 1. Логическая проверка через нормализацию пути.
     * 2. Если файл существует — дополнительная проверка через realpath()
     *    для защиты от symlink-атак.
     *
     * @param non-empty-string $path путь для проверки
     *
     * @throws FileSystemException если путь выходит за пределы basePath
     */
    public function validatePath(string $path): void
    {
        $path = trim($path);
        if ('' === trim($path)) {
            throw new FileSystemException('Path cannot be empty.');
        }

        if (!$this->isAbsolute($path)) {
            $path = $this->basePath . $path;
        }
        $normalized = $this->cleanPath($path);
        $base = rtrim($this->basePath, \DIRECTORY_SEPARATOR);
        if (!str_starts_with($normalized, $base)) {
            throw new FileSystemException("Path must be in base path: '{$path}'.");
        }
        $realPath = realpath($path);
        if (false !== $realPath && !str_starts_with($realPath, $base)) {
            throw new FileSystemException("Resolved '{$path}' is outside of the base directory.");
        }
    }

    /**
     * Подключает файл с изоляцией области видимости.
     *
     * @param non-empty-string    $file         абсолютный путь к файлу
     * @param array<string,mixed> $vars         переменные для передачи в контекст файла
     * @param IncludeType         $type         тип подключения
     * @param null|string         $errorMessage кастомное сообщение об ошибке (для require*)
     *
     * @return mixed значение из return подключаемого файла, или false если include-файл не найден
     *
     * @throws FileSystemException если путь вне basePath или require-файл не найден
     */
    private function doInclude(string $file, array $vars, IncludeType $type, ?string $errorMessage = null): mixed
    {
        $this->validatePath($file);
        if (!file_exists($file)) {
            if (IncludeType::REQUIRE === $type || IncludeType::REQUIRE_ONCE === $type) {
                throw new FileSystemException($errorMessage ?? "Unable to include file: '{$file}'.");
            }

            return false;
        }
        $includer = static function (string $__path, array $__vars, string $__type): mixed {
            extract($__vars, EXTR_SKIP);
            unset($__vars);

            return match ($__type) {
                'include' => include $__path,
                'include_once' => include_once $__path,
                'require' => require $__path,
                'require_once' => require_once $__path,
                default => '',
            };
        };

        return $includer($file, $vars, $type->value);
    }

    /**
     * Подключает файл через include с передачей переменных.
     *
     * Не вызывает ошибку, если файл не существует.
     *
     * @param non-empty-string    $file путь к файлу (должен быть внутри basePath)
     * @param array<string,mixed> $vars переменные для передачи в контекст файла
     *
     * @throws FileSystemException если путь вне basePath
     */
    public function includeFile(string $file, array $vars = []): mixed
    {
        return $this->doInclude($file, $vars, IncludeType::INCLUDE);
    }

    /**
     * Подключает файл через require с передачей переменных.
     *
     * Выбрасывает исключение, если файл не существует.
     *
     * @param non-empty-string    $file         путь к файлу (должен быть внутри basePath)
     * @param array<string,mixed> $vars         переменные для передачи в контекст файла
     * @param null|string         $errorMessage кастомное сообщение об ошибке
     *
     * @throws FileSystemException если файл не найден или путь вне basePath
     */
    public function requireFile(string $file, array $vars = [], ?string $errorMessage = null): mixed
    {
        return $this->doInclude($file, $vars, IncludeType::REQUIRE, $errorMessage);
    }

    /**
     * Подключает файл через include_once с передачей переменных.
     *
     * Файл будет подключён только один раз за время выполнения скрипта.
     * Не вызывает ошибку, если файл не существует.
     *
     * @param non-empty-string    $file путь к файлу (должен быть внутри basePath)
     * @param array<string,mixed> $vars переменные для передачи в контекст файла
     *
     * @throws FileSystemException если путь вне basePath
     */
    public function includeFileOnce(string $file, array $vars = []): mixed
    {
        return $this->doInclude($file, $vars, IncludeType::INCLUDE_ONCE);
    }

    /**
     * Подключает файл через require_once с передачей переменных.
     *
     * Файл будет подключён только один раз за время выполнения скрипта.
     * Выбрасывает исключение, если файл не существует.
     *
     * @param non-empty-string    $file         путь к файлу (должен быть внутри basePath)
     * @param array<string,mixed> $vars         переменные для передачи в контекст файла
     * @param null|string         $errorMessage кастомное сообщение об ошибке
     *
     * @throws FileSystemException если файл не найден или путь вне basePath
     */
    public function requireFileOnce(string $file, array $vars = [], ?string $errorMessage = null): mixed
    {
        return $this->doInclude($file, $vars, IncludeType::REQUIRE_ONCE, $errorMessage);
    }

    /**
     * Записывает данные в файл.
     *
     * Перед записью проверяет, что путь находится внутри basePath.
     *
     * @param non-empty-string $fileName абсолютный путь к файлу
     * @param mixed            $data     данные для записи
     * @param int              $flags    флаги для file_put_contents()
     * @param null|resource    $context  контекст потока
     *
     * @return int количество записанных байт
     *
     * @throws FileSystemException если путь вне basePath или запись не удалась
     */
    public function writeFile(string $fileName, mixed $data, int $flags = 0, $context = null): int
    {
        $this->validatePath($fileName);
        $result = file_put_contents($fileName, $data, $flags, $context);
        if (false === $result) {
            throw new FileSystemException("Failed to write file: '{$fileName}'.");
        }

        return $result;
    }

    /**
     * Атомарно записывает данные в файл через временный файл.
     *
     * Гарантирует, что целевой файл никогда не окажется в частично записанном состоянии.
     * Подходит для записи кэша, конфигураций и других критичных файлов.
     *
     * **Важно**: метод не поддерживает флаг FILE_APPEND. Для последовательного добавления
     * данных в файл используйте {@see writeFileAppendSafe()}.
     *
     * @param non-empty-string $fileName абсолютный путь к файлу
     * @param mixed            $data     данные для записи
     * @param int              $flags    флаги для file_put_contents() (FILE_APPEND игнорируется)
     * @param null|resource    $context  контекст потока
     *
     * @return int количество записанных байт
     *
     * @throws FileSystemException если путь вне basePath или запись/переименование не удались
     */
    public function writeFileSafe(string $fileName, mixed $data, int $flags = 0, $context = null): int
    {
        if ($flags & FILE_APPEND) {
            throw new FileSystemException('FILE_APPEND flag is not supported by writeFileSafe().');
        }
        $this->validatePath($fileName);
        $path = dirname($fileName);
        $tmp = tempnam($path, '.tmp.');
        $result = file_put_contents($tmp, $data, $flags, $context);
        if (false === $result || false === rename($tmp, $fileName)) {
            @unlink($tmp);

            throw new FileSystemException("Failed to write file: '{$fileName}'.");
        }

        return $result;
    }

    /**
     * Безопасно добавляет данные в конец файла с использованием блокировки.
     *
     * Защищает от конкурентных записей через эксклюзивную блокировку (flock).
     * Подходит для логирования и другого последовательного добавления данных.
     *
     * @param non-empty-string $fileName абсолютный путь к файлу
     * @param mixed            $data     данные для добавления
     * @param null|resource    $context  контекст потока
     *
     * @return int количество записанных байт
     *
     * @throws FileSystemException если путь вне basePath, не удалось открыть/заблокировать/записать файл
     */
    public function writeFileAppendSafe(string $fileName, mixed $data, $context = null): int
    {
        $this->validatePath($fileName);

        $data = (string) $data;
        $mode = 'ab';

        $handle = @fopen($fileName, $mode, false, $context);
        if (false === $handle) {
            throw new FileSystemException("Failed to open file for appending: '{$fileName}'.");
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new FileSystemException("Failed to acquire lock on file: '{$fileName}'.");
        }

        $result = fwrite($handle, $data);
        flock($handle, LOCK_UN);
        fclose($handle);

        if (false === $result) {
            throw new FileSystemException("Failed to write file: '{$fileName}'.");
        }

        return $result;
    }

    /**
     * Читает содержимое файла.
     *
     * Перед чтением проверяет, что путь находится внутри basePath.
     *
     * @param non-empty-string $fileName абсолютный путь к файлу
     * @param null|resource    $context  контекст потока
     * @param int              $offset   позиция начала чтения
     * @param null|int<0, max> $length   максимальное количество байт для чтения
     *
     * @return string содержимое файла
     *
     * @throws FileSystemException если путь вне basePath или чтение не удалось
     */
    public function readFile(string $fileName, $context = null, int $offset = 0, ?int $length = null): string
    {
        $this->validatePath($fileName);
        error_clear_last();
        $result = @file_get_contents($fileName, false, $context, $offset, $length);
        if (false === $result) {
            $error = error_get_last();
            $message = $error['message'] ?? "Failed to read file: \"{$fileName}\".";

            throw new FileSystemException($message);
        }

        return $result;
    }

    /**
     * Очищает и нормализует путь без обращения к файловой системе.
     *
     * Разрешает конструкции "." и "..", унифицирует разделители.
     * Используется для логической валидации путей в {@see validatePath()}.
     *
     * @param non-empty-string $path абсолюный путь для очистки
     *
     * @return non-empty-string нормализованный путь
     */
    private function cleanPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $parts = explode('/', $path);
        $absolutes = [];

        foreach ($parts as $part) {
            if ('' === $part || '.' === $part) {
                continue;
            }

            if ('..' === $part) {
                if (!empty($absolutes)) {
                    array_pop($absolutes);
                }
            } else {
                $absolutes[] = $part;
            }
        }

        $result = implode(\DIRECTORY_SEPARATOR, $absolutes);

        return \DIRECTORY_SEPARATOR . $result;
    }
}
