<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http;

use Vasoft\Joke\Collections\ReadonlyPropsCollection;
use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Exceptions\JokeException;

/**
 * Коллекция серверных переменных с преобразованием в HTTP-заголовки.
 *
 * Расширяет PropsCollection, добавляя метод для извлечения и нормализации
 * HTTP-заголовков из серверных переменных (в формате $_SERVER).
 * Обрабатывает как стандартные заголовки (HTTP_*), так и специальные
 * переменные контента (CONTENT_TYPE, CONTENT_LENGTH и др.).
 */
class ServerCollection extends ReadonlyPropsCollection
{
    /**
     * Извлекает и нормализует HTTP-заголовки из серверных переменных.
     *
     * Преобразует ключи вида HTTP_ACCEPT_LANGUAGE → Accept-Language
     * и добавляет специальные заголовки контента (Content-Type, Content-Length и др.)
     * из соответствующих серверных переменных.
     *
     * @return array<string, string> Ассоциативный массив заголовков в стандартном HTTP-формате
     *
     * @throws ConversionException При невозможности преобразовать параметр к строке
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->props as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
            }
        }
        $headers['Content-Type'] = $this->getString('CONTENT_TYPE', 'text/html');
        $headers['Content-Length'] = $this->getString('CONTENT_LENGTH', '0');
        $headers['Content-Encoding'] = $this->getString('CONTENT_ENCODING', '');
        $headers['Content-Language'] = $this->getString('CONTENT_LANGUAGE', '');
        $headers['Content-MD5'] = $this->getString('CONTENT_MD5', '');

        return $headers;
    }

    /** Кэшированное значение хоста */
    private string $cachedHost = '';
    /** Флаг, указывающий, что хост кеширован */
    private bool $isHostResolved = false;

    /** Кешированное значение порта */
    private int $cachedPort = 0;
    /** Флаг, указывающий, что порт кеширован */
    private bool $isPortResolved = false;

    /**
     * Возвращает имя хоста текущего запроса без порта.
     *
     * @return string имя хоста или пустая строка, если определить невозможно
     *
     * @throws ConversionException При невозможности преобразовать параметр к строке
     */
    public function getHost(): string
    {
        if ($this->isHostResolved) {
            return $this->cachedHost;
        }
        $host = $this->getString('HTTP_HOST', '');
        if ('' === $host) {
            $host = $this->getString('SERVER_NAME', '');
        }

        if (str_starts_with($host, '[')) {
            $pos = strpos($host, ']', 1);
            if (false !== $pos) {
                $host = substr($host, 0, $pos + 1);
            }
        } else {
            $pos = strpos($host, ':');
            if (false !== $pos) {
                $host = substr($host, 0, $pos);
            }
        }
        $this->cachedHost = $host;
        $this->isHostResolved = true;

        return $this->cachedHost;
    }

    /**
     * Возвращает порт, на который ориентирован текущий запрос.
     *
     * Учитывает работу за прокси-сервером (балансировщиком нагрузки).
     * Источники данных (по приоритету):
     * 1. Заголовок X-Forwarded-Port (HTTP_X_FORWARDED_PORT)
     * 2. Порт, извлеченный из заголовка Host (HTTP_HOST)
     * 3. Системная переменная SERVER_PORT
     * 4. Стандартный порт, исходя из схемы (80 для http, 443 для https)
     *
     * @return int номер порта (в диапазоне 1-65535 или стандартные 80/443)
     */
    public function getPort(): int
    {
        if ($this->isPortResolved) {
            return $this->cachedPort;
        }

        $port = $this->portFromForwarded();
        if ($port <= 0) {
            $port = $this->portFromHost();
        }
        if ($port <= 0) {
            $port = $this->portFromServerPort();
        }

        if ($port <= 0) {
            $port = $this->isSecureByHeader() ? 443 : 80;
        }
        $this->cachedPort = $port;
        $this->isPortResolved = true;

        return $this->cachedPort;
    }

    /**
     * Пытается извлечь порт из заголовка Host (например, example.com:8080).
     *
     * @return int порт из заголовка Host или 0, если порт не указан или невалиден
     *
     * @throws ConversionException При невозможности преобразовать параметр к строке
     */
    private function portFromHost(): int
    {
        $host = $this->getString('HTTP_HOST', '');
        if ('' !== $host && str_contains($host, ':')) {
            $parts = explode(':', $host);
            $portFromHost = (int) end($parts);

            return $this->filterPort($portFromHost);
        }

        return 0;
    }

    /**
     * Проверяет корректность порта.
     * Возвращает порт, если он в допустимом диапазоне (1-65535), иначе 0.
     *
     * @param int $port проверяемое значение порта
     *
     * @return int валидный порт или 0
     */
    private function filterPort(int $port): int
    {
        return ($port > 0 && $port <= 65535) ? $port : 0;
    }

    /**
     * Извлекает порт из системной переменной SERVER_PORT.
     *
     * @return int системный порт или 0 в случае ошибки
     */
    private function portFromServerPort(): int
    {
        try {
            $port = $this->getInt('SERVER_PORT', 0);

            return $this->filterPort($port);
        } catch (JokeException $e) {
            return 0;
        }
    }

    /**
     * Извлекает порт из заголовка X-Forwarded-Port.
     *
     * @return int порт из заголовка или 0, если заголовок отсутствует/невалиден
     */
    private function portFromForwarded(): int
    {
        try {
            $port = $this->getInt('HTTP_X_FORWARDED_PORT', 0);

            return $this->filterPort($port);
        } catch (JokeException $e) {
            return 0;
        }
    }

    /**
     * Проверяет, является ли соединение безопасным (HTTPS), на основе заголовков сервера.
     *
     * Проверяет переменную $_SERVER['HTTPS'] на наличие значений 'on', '1' или 'yes'.
     *
     * @return bool true, если соединение считается HTTPS
     *
     * @throws ConversionException При невозможности преобразовать параметр к строке
     */
    private function isSecureByHeader(): bool
    {
        $https = $this->getString('HTTPS', '');
        $value = strtolower($https);

        return 'on' === $value || '1' === $value || 'yes' === $value;
    }

    /**
     * Возвращает схему протокола текущего запроса (http или https).
     *
     * @return string 'http' или 'https'
     *
     * @throws ConversionException При невозможности преобразовать параметр к строке
     *
     * @todo Реализовать список доверенных прокси и доверять ему, то верить полностью заголовку форварда
     */
    public function getScheme(): string
    {
        $proto = $this->getString('HTTP_X_FORWARDED_PROTO', '');
        if ('https' === strtolower($proto)) {
            return 'https';
        }

        if ($this->isSecureByHeader()) {
            return 'https';
        }

        return 443 === $this->getPort() ? 'https' : 'http';
    }

    /**
     * Возвращает базовый URL сервера (схема + хост + опциональный порт) или пустую строку если хост не определен.
     *
     * Используется для генерации абсолютных ссылок, редиректов и сравнения Origin в CORS.
     * Стандартные порты (80 для http, 443 для https) не добавляются в результат.
     *
     * Примеры:
     * - http://example.com
     * - https://api.example.com:8080
     * - http://[::1]:3000
     *
     * @return string базовый URL без завершающего слэша
     *
     * @security Внимание: Метод основывается на заголовке HTTP_HOST, который контролируется клиентом.
     *            При генерации чувствительных ссылок (например, сброс пароля или подтверждение email)
     *            необходимо дополнительно валидировать полученный хост согласно списка доверенных доменов
     *            из конфигурации приложения, чтобы избежать атак типа Host Header Injection.
     *
     * @throws ConversionException При невозможности преобразовать параметр к строке
     */
    public function getBaseUrl(): string
    {
        $host = $this->getHost();
        if ('' === $host) {
            return '';
        }
        $scheme = $this->getScheme();
        $port = $this->getPort();

        $standardPort = ('https' === $scheme) ? 443 : 80;
        $portPart = ($port !== $standardPort) ? ':' . $port : '';

        return $scheme . '://' . $this->getHost() . $portPart;
    }
}
