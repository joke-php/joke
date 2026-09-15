<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http;

use Vasoft\Joke\Collections\PropsCollection;
use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\Cookies\InputCookieCollection;
use Vasoft\Joke\Session\SessionCollection;
use Vasoft\Joke\Http\Exceptions\WrongRequestMethodException;
use Vasoft\Joke\Foundation\Request;

/**
 * HTTP-запрос, инкапсулирующий данные входящего запроса.
 *
 * Предоставляет объектную обёртку над суперглобальными переменными ($_GET, $_POST и т.д.)
 * и разбирает тело запроса в зависимости от Content-Type (JSON, URL-encoded).
 * Используется как основной объект запроса во всём фреймворке.
 */
class HttpRequest extends Request
{
    /** Кешированное значение Origin */
    private ?string $cachedOrigin = null;
    /** Флаг показывает, что Origin запроса кеширован */
    private bool $isOriginResolved = false;
    /**
     * Данные GET-параметров запроса.
     */
    public private(set) PropsCollection $get {
        get {
            return $this->get;
        }
    }
    /**
     * Данные POST-параметров запроса.
     */
    public private(set) PropsCollection $post {
        get {
            return $this->post;
        }
    }
    /**
     * Данные cookie запроса.
     */
    public private(set) InputCookieCollection $cookies {
        get {
            return $this->cookies;
        }
    }
    /**
     * Информация о загруженных файлах.
     */
    public private(set) PropsCollection $files {
        get {
            return $this->files;
        }
    }
    /**
     * Серверные переменные (аналог $_SERVER).
     */
    public private(set) ServerCollection $server {
        get {
            return $this->server;
        }
    }
    /**
     * Произвольные свойства, привязанные к запросу (например, параметры маршрута).
     */
    public private(set) ?PropsCollection $props {
        get {
            return $this->props ??= new PropsCollection([]);
        }
    }
    /**
     * Хранилище данных сессии.
     */
    public private(set) SessionCollection $session {
        get {
            return $this->session;
        }
    }
    /**
     * Заголовки HTTP-запроса.
     *
     * Лениво инициализируется из серверных переменных при первом обращении.
     */
    public private(set) ?PropsCollection $headers = null {
        get {
            if (null === $this->headers) {
                $this->headers = new PropsCollection($this->server->getHeaders());
            }

            return $this->headers;
        }
    }
    /**
     * HTTP-метод запроса.
     *
     * Лениво определяется из SERVER['REQUEST_METHOD'] и преобразуется в HttpMethod enum.
     * Выбрасывает исключение при неизвестном методе.
     */
    public private(set) ?HttpMethod $method = null {
        get => $this->method ??= $this->parseMethod();
    }

    /**
     * Кэшированный путь URI без query string.
     */
    private ?string $path = null;

    /**
     * Разобранные JSON-данные из тела запроса.
     *
     * Заполняется автоматически, если Content-Type = application/json.
     *
     * @var array<string,mixed>|list<mixed>
     */
    public array $json = [] {
        get => $this->json;
    }

    /**
     * Конструктор запроса.
     *
     * Автоматически парсит тело запроса в зависимости от Content-Type:
     * - application/json → массив в свойство $json
     * - application/x-www-form-urlencoded → параметры в $post
     *
     * @param array<string, mixed>  $get     GET-параметры
     * @param array<string, mixed>  $post    POST-параметры
     * @param array<string, mixed>  $cookies Cookie
     * @param array<string, mixed>  $files   Информация о загруженных файлах
     * @param array<string, string> $server  Серверные переменные
     * @param string                $rawBody Сырое тело запроса (например, из php://input)
     */
    public function __construct(
        array $get = [],
        array $post = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        protected string $rawBody = '',
    ) {
        $this->get = new PropsCollection($get);
        $this->post = new PropsCollection($post);
        $this->cookies = new InputCookieCollection($cookies);
        $this->files = new PropsCollection($files);
        $this->server = new ServerCollection($server);
        $this->props = new PropsCollection([]);
        $this->session = new SessionCollection([]);
        if ($this->isUrlEncoded()) {
            $params = [];
            /** @phpstan-var array<string, mixed> $params */
            parse_str($rawBody, $params);
            $this->post->reset($params);
        } elseif ($this->isJson()) {
            $this->json = json_decode($rawBody, true) ?: [];
        }
    }

    /**
     * Возвращает метод запроса полученный из глобальной константы $_SERVER.
     *
     * @throws WrongRequestMethodException
     * @throws ConversionException         При невозможности преобразовать параметр к строке
     */
    private function parseMethod(): HttpMethod
    {
        $method = strtoupper($this->server->getString('REQUEST_METHOD', 'GET'));
        $methodParsed = HttpMethod::tryFrom($method);
        if (null === $methodParsed) {
            throw new WrongRequestMethodException($method);
        }

        return $methodParsed;
    }

    /**
     * Проверяет, является ли Content-Type application/json.
     */
    private function isJson(): bool
    {
        $contentType = $this->server->getHeaders()['Content-Type'] ?? '';

        return str_starts_with(strtolower($contentType), 'application/json');
    }

    /**
     * Проверяет, является ли Content-Type application/x-www-form-urlencoded.
     */
    private function isUrlEncoded(): bool
    {
        $contentType = $this->server->getHeaders()['Content-Type'] ?? '';

        return str_starts_with(strtolower($contentType), 'application/x-www-form-urlencoded');
    }

    /**
     * Устанавливает произвольные свойства запроса.
     *
     * Обычно используется для передачи параметров маршрута или контекстных данных.
     *
     * @param array<string, mixed> $props Ассоциативный массив свойств
     */
    public function setProps(array $props): static
    {
        $this->props->reset($props);

        return $this;
    }

    /**
     * Возвращает путь URI без query string.
     *
     * Например, для /user/123?tab=profile вернёт /user/123.
     *
     * @throws ConversionException При невозможности преобразовать параметр к строке
     */
    public function getPath(): string
    {
        if (null === $this->path) {
            $path = explode('?', $this->server->getString('REQUEST_URI', '/'));
            $this->path = $path[0];
        }

        return $this->path;
    }

    /**
     * Создаёт экземпляр запроса из суперглобальных переменных PHP.
     *
     * Используется в точке входа (public/index.php) для создания запроса
     * на основе реальных данных текущего HTTP-запроса.
     */
    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, file_get_contents('php://input') ?: '');
    }

    /**
     * Определяет, было ли соединение установлено через HTTPS.
     *
     * Проверяет серверную переменную {@see $_SERVER['HTTPS']} (значения '1' или 'on')
     * и номер порта {@see $_SERVER['SERVER_PORT']} (значение 443).
     *
     * Заголовки прокси (например, X-Forwarded-Proto) намеренно игнорируются,
     * пока не будет реализован механизм доверенных прокси (Trusted Proxies).
     *
     * @return bool true, если соединение защищено TLS/SSL, иначе False
     *
     * @throws ConversionException При невозможности преобразовать параметр к строке
     *
     * @todo После реализации определять на основе $this->server->getSchema
     * @todo Реализовать поддержку доверенных прокси для корректной работы за балансировщиками.
     */
    public function isSecure(): bool
    {
        $https = $this->server->getString('HTTPS', '');
        $value = strtolower($https);

        if ('on' === $value || '1' === $value || 'yes' === $value) {
            return true;
        }

        return 443 === $this->server->getPort();
    }

    /**
     * Возвращает Origin запроса если таковой существует
     *
     * @throws JokeException
     */
    public function getOrigin(): string
    {
        if ($this->isOriginResolved) {
            return $this->cachedOrigin;
        }
        $raw = $this->headers->getString('Origin', '');
        $this->cachedOrigin = '' !== $raw && filter_var($raw, FILTER_VALIDATE_URL) ? $raw : '';
        $this->isOriginResolved = true;

        return $this->cachedOrigin;
    }
}
