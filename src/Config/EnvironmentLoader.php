<?php

declare(strict_types=1);

namespace Vasoft\Joke\Config;

use Vasoft\Joke\Config\Exceptions\ConfigException;

/**
 * Загрузчик переменных из .env файлов.
 *
 * Поддерживает три уровня конфигурации:
 * 1. Базовый файл .env
 * 2. Файл, специфичный для текущего окружения - .env.{envName}
 * 3. Локальный файл переопределений .env.{localName}
 *
 * .env.{localName} - не загружается дважды и если текущее окружение тестовое
 *
 * Все значения парсятся с автоматическим приведением типов:
 * - строки в кавычках всегда остаются строками
 * - числа - int/float
 * - true/false - boolean
 * - null или пустое значение - null
 * - строки начинающиеся с # - комментарии
 */
readonly class EnvironmentLoader
{
    public function __construct(private string $basePath) {}

    /**
     * Загружает переменные окружения из соответствующих .env-файлов.
     *
     * @param string $envName   имя текущего окружения
     * @param string $localName имя локального окружения. Файл .env.{localName} загружается только один раз, для всех окружений исключая testing
     * @param string $testName  тестовое окружение
     *
     * @return array<string, null|bool|float|int|string>
     */
    public function load(string $envName, string $localName, string $testName): array
    {
        $files = $this->getFileList($envName, $localName, $testName);
        $vars = [];
        foreach ($files as $file) {
            $this->parseFile($file, $vars);
        }

        return $vars;
    }

    /**
     * Считывание переменных и их значений в массив.
     *
     * @param string                                    $fileName Имя загружаемого файла
     * @param array<string, null|bool|float|int|string> $vars     Ссылка на формируемый массив переменных
     *
     * @throws ConfigException При ошибке открытия файла
     */
    private function parseFile(string $fileName, array &$vars): void
    {
        $path = $this->basePath . $fileName;
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (false === $lines) {
            throw  new ConfigException('Unable to load file: ' . $path);
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, null);

            $key = trim(strtoupper($key));
            if (null === $value) {
                $vars[$key] = null;

                continue;
            }
            $vars[$key] = $this->normalizeValue($value);
        }
    }

    private function normalizeValue(string $value): bool|float|int|string|null
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return trim(stripcslashes($value), '"');
        }
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            return trim(stripcslashes($value), "'");
        }
        if (!is_numeric($value)) {
            return $this->normalizeString($value);
        }
        if (false === strpbrk($value, '.eE')) {
            return (int) $value;
        }

        return (float) $value;
    }

    private function normalizeString(string $value): bool|string|null
    {
        return match ($value) {
            'false' => false,
            'true' => true,
            'null' => null,
            default => $value,
        };
    }

    /**
     * Возвращает список файлов, которые необходимо загрузить.
     *
     * @param string $envName   Имя загружаемого окружения
     * @param string $localName Имя локального окружения
     * @param string $testName  Имя тестового окружения
     *
     * @return list<string>
     */
    private function getFileList(string $envName, string $localName, string $testName): array
    {
        $files = ['.env'];
        if ('' !== $envName && $envName !== $localName) {
            $files[] = '.env.' . $envName;
        }
        if ($envName !== $testName) {
            $files[] = '.env.' . $localName;
        }

        return $files;
    }

    /**
     * Возвращает базовый путь к проекту.
     *
     * @deprecated Будет удален в версии 2.0
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
