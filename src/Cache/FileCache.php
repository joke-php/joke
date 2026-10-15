<?php

declare(strict_types=1);

namespace Vasoft\Joke\Cache;

use Vasoft\Joke\Cache\Exceptions\CacheException;
use Vasoft\Joke\Contract\Cache\CacheInterface;
use Vasoft\Joke\Support\FileSystem;
use Vasoft\Joke\Exceptions\FileSystemException;

/**
 * Реализация файлового кэша с поддержкой префиксов и контроля десериализации.
 *
 * Хранит данные в виде сериализованных файлов, организуя их по подкаталогам
 * на основе первых двух символов MD5-хэша ключа для оптимизации работы ФС.
 */
class FileCache implements CacheInterface
{
    /**
     * @var ?string Префикс (подкаталог) для изоляции групп кэша
     */
    private ?string $prefix = null;

    /**
     * Установка $allowedClasses в значение true - небезопасна может приводить к уязвимости системы в результате десериализации классов.
     *
     * @param FileSystem              $fs             Сервис работы с файловой системой
     * @param bool|list<class-string> $allowedClasses Разрешенные классы для десериализации.
     *                                                false — запретить все объекты, true — разрешить все,
     *                                                массив — белый список конкретных классов
     */
    public function __construct(
        private readonly FileSystem $fs,
        private readonly bool|array $allowedClasses = false,
    ) {}

    public function setPrefix(string $prefix): static
    {
        if (null === $this->prefix) {
            $this->prefix = $prefix;
        }

        return $this;
    }

    /**
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->extractData($key);

        return false === $data ? $default : $data['value'];
    }

    /**
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        try {
            $file = $this->getFileName($key);
            $expires = $this->calculateExpiration($ttl);
            if (null !== $expires && $expires < time()) {
                return true;
            }
            $data = serialize(['value' => $value, 'expires' => $expires]);

            return $this->fs->writeFileSafe($file, $data) > 0;
        } catch (FileSystemException $e) {
            throw new CacheException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
    public function delete(string $key): bool
    {
        try {
            $file = $this->getFileName($key);
            if (!file_exists($file)) {
                return true;
            }

            return unlink($file);
        } catch (FileSystemException $e) {
            throw new CacheException($e->getMessage(), previous: $e);
        }
    }

    /**
     * Очистка кеша
     * Не использую FileSystem::clearDir, т.к. необходима логика при которой все доступные к удалению
     * файлы будут удалены - в первую очередь запботимся о пользователях.
     *
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
    public function clear(): bool
    {
        try {
            $prefix = null === $this->prefix ? '' : $this->prefix;
            $path = $this->fs->atCache($prefix);
            if (!is_dir($path)) {
                return true;
            }
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            $result = true;
            foreach ($items as $item) {
                if (!($item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()))) {
                    $result = false;
                }
            }
            if ($result && '' !== $prefix && !@rmdir($path)) {
                $result = false;
            }

            return $result;
        } catch (FileSystemException $e) {
            throw new CacheException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
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

    /**
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
    public function has(string $key): bool
    {
        return false !== $this->extractData($key);
    }

    /**
     * Извлекает и валидирует данные из файла кэша.
     *
     * @return array{value: mixed, expires: null|int}|false
     *
     * @throws CacheException При некорректном ключе кеша и ошибках взаимодействия с файлами
     */
    private function extractData(string $key): false|array
    {
        try {
            $file = $this->getFileName($key);
            if (!file_exists($file)) {
                return false;
            }
            $context = $this->fs->readFile($file);
        } catch (FileSystemException $e) {
            throw new CacheException($e->getMessage(), previous: $e);
        }

        $data = $this->parseData($context);
        if (false === $data) {
            $this->delete($key);
        }

        return $data;
    }

    /**
     * Парсит и валидирует данные из сериализованной строки кэша.
     *
     * Проверяет структуру данных, наличие разрешенных классов и срок действия.
     * Если данные некорректны или истекли, возвращает false.
     *
     * @param string $context Сериализованная строка из файла кэша
     *
     * @return array{value: mixed, expires: null|int}|false Массив с данными кэша или false при ошибке/истечении срока
     */
    private function parseData(string $context): false|array
    {
        try {
            $data = @unserialize($context, ['allowed_classes' => $this->allowedClasses]);
        } catch (\Throwable) {
            $data = false;
        }
        if (
            !is_array($data)
            || !array_key_exists('value', $data)
            || !array_key_exists('expires', $data)
            || $this->containsIncompleteClass($data)
            || (null !== $data['expires'] && time() > $data['expires'])
        ) {
            return false;
        }

        return $data;
    }

    /**
     * Проверяет, не содержит ли значение объекты __PHP_Incomplete_Class.
     *
     * Возникают, когда unserialize() встречает класс, запрещённый allowed_classes.
     * Такие записи считаются испорченными и не должны попадать наружу.
     */
    private function containsIncompleteClass(mixed $value): bool
    {
        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }
        if (is_array($value)) {
            if (array_any($value, fn($item) => $this->containsIncompleteClass($item))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Формирует путь к файлу кэша на основе ключа.
     *
     * Использует MD5-хэш для создания безопасного имени файла и распределяет
     * файлы по подкаталогам для предотвращения переполнения одной директории.
     *
     * @param string $key Ключ кэша
     *
     * @return string Абсолютный путь к файлу .cache
     *
     * @throws FileSystemException При невозможности создать каталог
     * @throws CacheException      При некорректном ключе кеша
     */
    private function getFileName(string $key): string
    {
        if ('' === trim($key)) {
            throw new CacheException('Key must not be empty.');
        }

        $preparedKey = md5($key);
        $prefix = null === $this->prefix ? '' : $this->prefix . \DIRECTORY_SEPARATOR;

        $path = $this->fs->atCache($prefix . substr($preparedKey, 0, 2));
        $this->fs->ensureDirectory($path);

        return $path . \DIRECTORY_SEPARATOR . $preparedKey . '.cache';
    }

    /**
     * Вычисляет временную метку истечения срока действия кэша.
     *
     * @param null|\DateInterval|int $ttl Время жизни (секунды или интервал)
     *
     * @return null|int Временная метка Unix или null для бессрочного хранения
     */
    private function calculateExpiration(\DateInterval|int|null $ttl): ?int
    {
        if (null === $ttl) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            $timestamp = new \DateTimeImmutable()->add($ttl)->getTimestamp();
        } else {
            $timestamp = time() + $ttl;
        }

        return $timestamp;
    }

    /**
     * Атомарно модифицирует значение в кэше.
     *
     * Если ключ не существует, модификатор получит null.
     * TTL перезапускается при каждой модификации.
     *
     * @param string                 $key      Ключ кэша
     * @param callable(mixed): mixed $modifier Принимает текущее значение (или null), возвращает новое
     * @param null|\DateInterval|int $ttl      Новый TTL (null = бессрочно)
     *
     * @return mixed Новое значение после модификации
     *
     * @throws CacheException При ошибках ФС или некорректном ключе
     */
    public function modify(string $key, callable $modifier, \DateInterval|int|null $ttl = null): mixed
    {
        try {
            $file = $this->getFileName($key);
            $expires = $this->calculateExpiration($ttl);
            $serialized = $this->fs->modifyFileSafe(
                $file,
                function (string $context) use ($modifier, $expires): string {
                    $data = $this->parseData($context);
                    $currentValue = false === $data ? null : $data['value'];

                    $newValue = $modifier($currentValue);

                    if (is_resource($newValue) || $newValue instanceof \Closure) {
                        throw new CacheException(
                            sprintf(
                                'Modifier returned non-serializable type: "%s".',
                                get_debug_type($newValue),
                            ),
                        );
                    }

                    return serialize(['value' => $newValue, 'expires' => $expires]);
                },
            );

            $result = unserialize($serialized, ['allowed_classes' => $this->allowedClasses]);

            return is_array($result) && array_key_exists('value', $result)
                ? $result['value']
                : null;
        } catch (\Throwable $e) {
            throw new CacheException($e->getMessage(), previous: $e);
        }
    }
}
