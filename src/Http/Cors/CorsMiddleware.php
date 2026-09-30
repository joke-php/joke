<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Cors;

use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Http\Response\ResponseStatus;
use Vasoft\Joke\Exceptions\ConversionException;

/**
 * Middleware для реализации механизма CORS (Cross-Origin Resource Sharing).
 *
 *  Обрабатывает кросс-доменные запросы, добавляя необходимые заголовки доступа
 *  в ответ сервера. Поддерживает обработку preflight-запросов (метод OPTIONS),
 *  валидацию источников (Origin), настройку разрешенных методов и заголовков,
 *  а также работу с учетными данными (cookies, authorization).
 *
 *  Особенности реализации:
 *  - Перехватывает OPTIONS-запросы до выполнения основной логики приложения,
 *     возвращая пустой успешный ответ с заголовками.
 *  - Динамически проверяет заголовок Origin запроса на соответствие списку разрешенных.
 *  - Добавляет заголовок Vary: Origin для корректного кэширования ответов.
 *  - Блокирует установку заголовков, если источник не прошел валидацию.
 *  - При выключенном CORS (allowedCors = false) действует принцип «что не разрешено явно, то запрещено»:
 *      кросс-доменные запросы отклоняются со статусом 403 Forbidden.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Конструктор middleware.
     *
     * @param CorsConfig      $corsConfig      конфигурация правил CORS
     * @param ResponseBuilder $responseBuilder Билдер ответов для создания и нормализации объектов Response
     */
    public function __construct(
        private readonly CorsConfig $corsConfig,
        private readonly ResponseBuilder $responseBuilder,
    ) {}

    /**
     * Обрабатывает входящий HTTP-запрос, добавляя CORS-заголовки к ответу.
     *
     * Логика работы:
     * 1. Если запрос содержит заголовок Origin и CORS активирован:
     * - Проверяет допустимость Origin
     * - Для OPTIONS-запросов обрабатывает preflight
     * - Для обычных запросов проверяет допустимость HTTP-метода
     * 2. Если все проверки пройдены:
     * - Выполняет следующую middleware в цепочке
     * - Добавляет CORS-заголовки к ответу
     * 3. Возвращает финальный HTTP-ответ
     *
     * @param HttpRequest $request объект входящего HTTP-запроса
     * @param callable    $next    следующий обработчик в цепочке middleware
     *
     * @return Response объект HTTP-ответа с установленными заголовками CORS
     * */
    public function handle(HttpRequest $request, callable $next): Response
    {
        if ($this->isSameOrigin($request)) {
            return $next($request);
        }
        $origin = $request->getOrigin();
        if (!$this->corsConfig->allowedCors || !$this->isOriginAllowed($origin)) {
            return $this->makeErrorResponse($request, ResponseStatus::FORBIDDEN);
        }
        if (HttpMethod::OPTIONS === $request->method) {
            return $this->handlePreflight($request);
        }

        if (!in_array($request->method, $this->corsConfig->methods, true)) {
            return $this->makeErrorResponse($request, ResponseStatus::METHOD_NOT_ALLOWED);
        }
        $response = $next($request);
        $preparedResponse = $this->responseBuilder->make($response);
        $this->setHeaders($request, $preparedResponse);

        return $preparedResponse;
    }

    private function makeErrorResponse(HttpRequest $request, ResponseStatus $status): Response
    {
        $response = $this->responseBuilder->makeDefault();
        $response->setStatus($status);

        // Origin разрешён — отдаём его, чтобы браузер показал реальную ошибку
        if ($this->corsConfig->allowedCors && $this->isOriginAllowed($request->getOrigin())) {
            $this->setOriginAndCredentials($request, $response);
        }

        // Vary нужен всегда, когда ответ зависит от Origin
        $this->setVaryHeaders($response);

        return $response;
    }

    /**
     * Проверяет, является ли Origin запроса "своим" (совпадает с текущим хостом).
     *
     * Origin по спецификации — это scheme://host:port, поэтому сравниваются все
     * три компонента. Порт нормализуется: отсутствующий порт приравнивается
     * к стандартному для схемы (80 для http, 443 для https).
     *
     * @param HttpRequest $request объект входящего HTTP-запроса
     *
     * @return bool true если Origin совпадает с текущим хостом
     *
     * @throws JokeException
     */
    private function isSameOrigin(HttpRequest $request): bool
    {
        $origin = $request->getOrigin();
        if ('' === $origin) {
            return true;
        }
        $parsedOrigin = parse_url($origin);
        if (false === $parsedOrigin || !isset($parsedOrigin['host'])) {
            return false;
        }

        $currentScheme = $this->getCurrentScheme($request);
        [$currentHost, $currentPort] = $this->parseHostHeader(
            $request->headers->get('Host', ''),
            $currentScheme,
        );

        $originScheme = strtolower($parsedOrigin['scheme'] ?? 'http');
        $originHost = strtolower($parsedOrigin['host']);
        $originPort = $this->normalizePort($parsedOrigin['port'] ?? null, $originScheme);

        return $originScheme === $currentScheme
            && $originHost === $currentHost
            && $originPort === $currentPort;
    }

    /**
     * Определяет схему текущего запроса.
     *
     * @return string 'http' или 'https'
     *
     * @throws ConversionException При ошибках приведения к строке
     */
    private function getCurrentScheme(HttpRequest $request): string
    {
        return $request->isSecure() ? 'https' : 'http';
    }

    /**
     * Разбирает заголовок Host на хост и порт.
     *
     * Поддерживаются форматы:
     * - example.com
     * - example.com:8080
     * - [::1]:8080 (IPv6)
     *
     * @return array{0: string, 1: int} [host, port]
     */
    private function parseHostHeader(string $hostHeader, string $scheme): array
    {
        $hostHeader = trim($hostHeader);
        if ('' === $hostHeader) {
            return ['', $this->defaultPort($scheme)];
        }

        // IPv6: [::1]:8080
        if ('[' === $hostHeader[0]) {
            $end = strpos($hostHeader, ']');
            $host = substr($hostHeader, 0, $end + 1);
            $rest = substr($hostHeader, $end + 1);
            $port = str_starts_with($rest, ':')
                ? (int) substr($rest, 1)
                : $this->defaultPort($scheme);

            return [strtolower($host), $port];
        }

        if (str_contains($hostHeader, ':')) {
            [$host, $port] = explode(':', $hostHeader, 2);

            return [strtolower($host), (int) $port];
        }

        return [strtolower($hostHeader), $this->defaultPort($scheme)];
    }

    /**
     * Приводит порт Origin к числовому значению,
     * подставляя стандартный порт схемы, если порт не указан.
     */
    private function normalizePort(?int $port, string $scheme): int
    {
        return $port ?? $this->defaultPort($scheme);
    }

    private function defaultPort(string $scheme): int
    {
        return 'https' === strtolower($scheme) ? 443 : 80;
    }

    /**
     * Проверяет допустимость Origin запроса согласно конфигурации CORS.
     *
     * @param string $origin Origin входящего HTTP-запроса
     *
     * @return bool true если Origin разрешён, false в противном случае
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (!$this->corsConfig->allowCredentials && in_array('*', $this->corsConfig->origins, true)) {
            return true;
        }

        return '' !== $origin && in_array($origin, $this->corsConfig->origins, true);
    }

    /**
     * Обрабатывает preflight-запрос (HTTP метод OPTIONS).
     *
     * Проверяет валидность запрошенного метода и заголовков из preflight-запроса.
     * Если проверки не пройдены, возвращает статус 403 Forbidden.
     * В случае успеха устанавливает все необходимые CORS-заголовки.
     *
     * @param HttpRequest $request объект входящего HTTP-запроса
     *
     * @return Response объект HTTP-ответа с CORS-заголовками или ошибкой
     */
    private function handlePreflight(HttpRequest $request): Response
    {
        if ($this->isPreflightMethodInvalid($request) || $this->isPreflightHeadersInvalid($request)) {
            return $this->makeErrorResponse($request, ResponseStatus::FORBIDDEN);
        }
        $response = $this->responseBuilder->makeDefault();
        $this->setHeaders($request, $response);

        return $response;
    }

    /**
     * Проверяет валидность метода из preflight-запроса.
     *
     * Метод считается невалидным если:
     * - Заголовок Access-Control-Request-Method отсутствует или пустой
     * - Значение не является валидным HTTP-методом
     * - Метод не входит в список разрешённых в конфигурации CORS
     *
     * @param HttpRequest $request объект входящего HTTP-запроса
     *
     * @return bool true если метод невалиден, false если валиден
     */
    private function isPreflightMethodInvalid(HttpRequest $request): bool
    {
        $requestedMethod = $request->headers->get('Access-Control-Request-Method', '');
        if ('' === $requestedMethod) {
            return true;
        }

        try {
            $methodEnum = HttpMethod::from(strtoupper($requestedMethod));

            return !in_array($methodEnum, $this->corsConfig->methods, true);
        } catch (\ValueError) {
            return true;
        }
    }

    /**
     * Проверяет валидность заголовков из preflight-запроса.
     *
     * Заголовки считаются невалидными если:
     * - Заголовок Access-Control-Request-Headers содержит хотя бы один недопустимый заголовок
     *
     * Отсутствие заголовка Access-Control-Request-Headers считается валидным случаем.
     *
     * @param HttpRequest $request объект входящего HTTP-запроса
     *
     * @return bool true если заголовки невалидны, false если валидны
     */
    private function isPreflightHeadersInvalid(HttpRequest $request): bool
    {
        $requestedHeaders = $request->headers->get('Access-Control-Request-Headers', '');
        if ('' === $requestedHeaders) {
            return false;
        }
        $requestedHeadersArray = array_map('trim', explode(',', strtolower($requestedHeaders)));
        $allowedHeadersArray = array_map('strtolower', $this->corsConfig->headers);
        if (in_array('*', $allowedHeadersArray, true)) {
            return false;
        }
        foreach ($requestedHeadersArray as $header) {
            if (!in_array($header, $allowedHeadersArray, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Устанавливает все необходимые CORS-заголовки в объект ответа.
     *
     * Включает заголовки:
     * - Access-Control-Allow-Origin и Access-Control-Allow-Credentials
     * - Access-Control-Allow-Methods
     * - Access-Control-Allow-Headers
     * - Access-Control-Expose-Headers
     * - Access-Control-Max-Age
     *
     * @param HttpRequest $request  объект входящего HTTP-запроса
     * @param Response    $response объект ответа, в который будут установлены заголовки
     */
    private function setHeaders(HttpRequest $request, Response $response): void
    {
        $this->setOriginAndCredentials($request, $response);
        $allowedHeaders = $this->getAllowedHeadersAsString($request);
        if ('' !== $allowedHeaders) {
            $response->headers->set('Access-Control-Allow-Headers', $allowedHeaders);
        }
        $response->headers
            ->set('Access-Control-Allow-Methods', $this->corsConfig->getMethodsAsString())
            ->set('Access-Control-Expose-Headers', $this->corsConfig->getExposeHeadersAsString())
            ->set('Access-Control-Max-Age', (string) $this->corsConfig->maxAge);
    }

    /**
     * Возвращает значение для Access-Control-Allow-Headers.
     *
     * При allowCredentials=true нельзя отдавать '*': браузеры отклонят preflight.
     * В этом случае зеркалим заголовки, запрошенные клиентом в preflight,
     * с нормализацией (trim, lowercase, удаление пустых значений).
     */
    private function getAllowedHeadersAsString(HttpRequest $request): string
    {
        if (!$this->corsConfig->allowCredentials || !in_array('*', $this->corsConfig->headers, true)) {
            return $this->corsConfig->getHeadersAsString();
        }
        $requestedHeaders = $request->headers->get('Access-Control-Request-Headers', '');
        if ('' === $requestedHeaders) {
            return '';
        }

        $headers = array_map('trim', explode(',', strtolower($requestedHeaders)));
        $headers = array_filter(
            $headers,
            static fn(string $header): bool => '' !== $header,
        );

        return implode(', ', $headers);
    }

    /**
     * Устанавливает заголовки Access-Control-Allow-Origin и Access-Control-Allow-Credentials.
     *
     * Логика валидации источника (Origin):
     * 1. Если включен режим без учетных данных и разрешен - устанавливает Origin в '*'.
     * 2. Если передан конкретный заголовок Origin и он есть в списке разрешенных:
     *    - Устанавливает Origin в значение из запроса.
     *    - Если включены учетные данные, устанавливает Allow-Credentials: true.
     *    - Добавляет заголовок Vary: Origin для корректной работы кэша.
     *
     * @param HttpRequest $request  объект запроса для чтения заголовка HTTP_ORIGIN
     * @param Response    $response объект ответа для установки заголовков
     */
    private function setOriginAndCredentials(HttpRequest $request, Response $response): void
    {
        if (!$this->corsConfig->allowCredentials && in_array('*', $this->corsConfig->origins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');

            return;
        }
        $origin = $request->getOrigin();
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        if ($this->corsConfig->allowCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        $this->setVaryHeaders($response);
    }

    /**
     * Добавляет заголовок Vary.
     *
     * @throws ConversionException Если значение ранее добавленного заголовка нельзя привести к строке
     */
    private function setVaryHeaders(Response $response): void
    {
        $vary = $response->headers->getString('Vary', '');

        if ('' === $vary) {
            $response->headers->set('Vary', 'Origin');
        } else {
            $varyHeaders = array_map('trim', explode(',', $vary));
            if (!in_array('Origin', $varyHeaders, true)) {
                $response->headers->set('Vary', $vary . ', Origin');
            }
        }
    }
}
