<?php

declare(strict_types=1);

namespace Vasoft\Joke\Config;

use Vasoft\Joke\Config\Exceptions\ConfigException;

/**
 * Клас для определения текущего окружения и доступа к переменным окружения.
 *
 * Определять текущее окружение из источников (в порядке приоритета)
 * - $_ENV['JK_ENV']
 * - $_SERVER['JK_ENV']
 * - getenv('JK_ENV')
 *
 * Если не задано, то окружение считается local
 * Загружает переменные окружения:
 * - .env
 * - .env.{env}
 * - .env.local (загружается один раз и имеет самый высокий приоритет)
 */
class Environment
{
    /**
     * Имя переменной окружения, используется для определения текущего режима.
     */
    public const string ENV_VAR_NAME = 'JK_ENV';
    /**
     * Стандартное имя для production окружения.
     */
    public const string ENV_PRODUCTION = 'production';
    /**
     * Стандартное имя для окружения разработки.
     */
    public const string ENV_DEVELOPMENT = 'development';
    /**
     * Стандартное имя для тестового окружения.
     */
    public const string ENV_TESTING = 'testing';
    /**
     * Стандартное имя для локального окружения.
     */
    public const string ENV_LOCAL = 'local';
    /**
     * Ассоциативный массив переменных окружения.
     *
     * @var array<string,null|bool|float|int|string>
     */
    private array $vars = [];

    public private(set) string $name {
        get => $this->name;
    }

    private string $basePath = '';

    public function __construct(EnvironmentLoader $loader)
    {
        $environmentName = $_ENV[self::ENV_VAR_NAME]
            ?? $_SERVER[self::ENV_VAR_NAME]
            ?? getenv(self::ENV_VAR_NAME)
            ?: null;
        $this->name = is_string($environmentName) ? $environmentName : self::ENV_LOCAL;
        $this->vars = $loader->load($this->name, self::ENV_LOCAL, self::ENV_TESTING);
        $this->basePath = $loader->getBasePath();
    }

    /**
     * Проверяет, совпадает ли запрошенное окружение с текущим
     *
     * @param string $name Запрошенное окружение
     *
     * @return bool true, если совпадает
     */
    public function is(string $name): bool
    {
        return $name === $this->name;
    }

    /**
     * Проверяет, является ли текущее окружение production.
     */
    public function isProduction(): bool
    {
        return self::ENV_PRODUCTION === $this->name;
    }

    /**
     * Проверяет, является ли текущее окружение development.
     */
    public function isDevelopment(): bool
    {
        return self::ENV_DEVELOPMENT === $this->name;
    }

    /**
     * Проверяет, является ли текущее окружение testing.
     */
    public function isTesting(): bool
    {
        return self::ENV_TESTING === $this->name;
    }

    /**
     * Возвращает значение переменной окружения или значение по умолчанию.
     *
     * Имя переменной нечувствительно к регистру
     *
     * @param string                     $name         Имя переменной
     * @param null|bool|float|int|string $defaultValue Значение по умолчанию
     */
    public function get(string $name, bool|float|int|string|null $defaultValue = null): bool|float|int|string|null
    {
        return $this->vars[strtoupper($name)] ?? $defaultValue;
    }

    /**
     * Проверяет, существует ли переменная окружения.
     *
     * Имя переменной нечувствительно к регистру
     *
     * @param string $name Имя переменной
     */
    public function has(string $name): bool
    {
        return array_key_exists(strtoupper($name), $this->vars);
    }

    /**
     * Возвращает значение переменной или выбрасывает исключение, если ее нет
     *
     * @param string      $name    Имя переменной
     * @param null|string $message Сообщение об ошибке или null - для сообщения по умолчанию
     *
     * @throws ConfigException если переменная не существует в окружении
     */
    public function getOrFail(string $name, ?string $message = null): bool|float|int|string|null
    {
        if (!$this->has($name)) {
            $message ??= ('The environment "' . strtoupper($name) . '" does not exist.');

            throw new ConfigException($message);
        }

        return $this->get($name);
    }

    /**
     * Возвращает базовый путь к проекту.
     *
     * @deprecated В версии 2.0 будет удален
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
