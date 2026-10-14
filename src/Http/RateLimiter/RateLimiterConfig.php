<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter;

use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Config\Exceptions\ConfigException;

/**
 * Конфигурация ограничителя частоты запросов.
 *
 * Управляет глобальным поведением:
 * - Включение/отключение проверки лимитов
 * - Управление HTTP-заголовками в ответах
 *
 * @see RateLimiterMiddleware
 */
class RateLimiterConfig extends AbstractConfig
{
    /**
     * Включена ли проверка ограничений частоты запросов.
     *
     * При значении false middleware пропускает все запросы без проверки,
     * не обращаясь к хранилищу счётчиков и не добавляя заголовки.
     */
    public bool $enabled = false {
        get => $this->enabled;
    }

    /**
     * Добавлять ли стандартные HTTP-заголовки rate limiting в ответ.
     *
     * Заголовки:
     * - X-Rate-Limit-Limit: максимальное количество разрешённых запросов
     * - X-Rate-Limit-Remaining: оставшееся количество запросов в текущем окне
     * - X-Rate-Limit-Reset: Unix-метка времени сброса окна
     * - Retry-After: время ожидания при превышении лимита (только для 429 ответов)
     *
     * Применяется как к успешным ответам, так и к заблокированным (429).
     */
    public bool $withHeaders = false {
        get => $this->withHeaders;
    }

    /**
     * Устанавливает, включена ли проверка ограничений.
     *
     * @param bool $enabled true для включения, false для отключения
     *
     * @throws ConfigException Если конфигурация заморожена
     */
    public function setEnabled(bool $enabled): static
    {
        $this->guard();
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Устанавливает, добавлять ли HTTP-заголовки rate limiting в ответы.
     *
     * @param bool $withHeaders true для добавления заголовков, false для отключения
     *
     * @throws ConfigException Если конфигурация заморожена
     */
    public function setWithHeaders(bool $withHeaders): static
    {
        $this->guard();
        $this->withHeaders = $withHeaders;

        return $this;
    }
}
