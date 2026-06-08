<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Csrf;

use Random\RandomException;
use Vasoft\Joke\Contract\Logging\LoggerInterface;
use Vasoft\Joke\Http\Cookies\Exceptions\CookieException;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Middleware\Exceptions\CsrfMismatchException;
use Vasoft\Joke\Exceptions\JokeException;

/**
 * Менеджер CSRF-токенов для защиты от межсайтовой подделки запросов.
 *
 * Отвечает за полный жизненный цикл токена:
 * - Генерация криптографически стойких токенов
 * - Валидация токенов от клиента
 * - Сброс и инвалидация токенов
 * - Внедрение токенов в HTTP-ответ
 *
 * Поддерживает два режима доставки токена:
 * 1. Через HTTP-заголовок (X-Csrf-Token) — рекомендуется для API
 * 2. Через Cookie (XSRF-TOKEN) — рекомендуется для веб-приложений с паттерном Double Submit
 */
class CsrfTokenManager
{
    /** Имя поля для токена в GET/POST параметрах */
    public const string CSRF_TOKEN_NAME = 'csrf_token';
    /** Имя HTTP-заголовка для токена */
    public const string CSRF_TOKEN_HEADER = 'X-Csrf-Token';
    /** Имя Cookie для токена */
    public const string CSRF_TOKEN_COOKIE = 'XSRF-TOKEN';

    /** HTTP-методы, не требующие CSRF-защиты */
    private const array SAFE_METHODS = [HttpMethod::GET, HttpMethod::HEAD];

    /**
     * @param CsrfConfig      $config Конфигурация CSRF-защиты
     * @param LoggerInterface $logger Логгер для записи событий генерации токенов
     */
    public function __construct(
        private readonly CsrfConfig $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Валидирует CSRF-токен из запроса и возвращает актуальный серверный токен.
     *
     * Для небезопасных HTTP-методов (POST, PUT, DELETE и др.) проверяет совпадение
     * клиентского токена с серверным. При несоответствии выбрасывает исключение.
     *
     * Токен ищется в порядке приоритета:
     * 1. GET/POST параметр с именем {@see CSRF_TOKEN_NAME}
     * 2. HTTP-заголовок {@see CSRF_TOKEN_HEADER}
     * 3. Cookie {@see CSRF_TOKEN_COOKIE}
     *
     * Если токен отсутствует в сессии — автоматически генерируется новый.
     *
     * @param HttpRequest $request Объект HTTP-запроса для извлечения токена клиента
     *
     * @return string Актуальный CSRF-токен для внедрения в ответ
     *
     * @throws JokeException         Если значение не может быть преобразовано в строку - фактически это не возможно
     *                               при стандартном создании объекта запроса
     * @throws CsrfMismatchException Если токен клиента не совпадает с серверным
     * @throws RandomException       Если не удается найти подходящий источник случайности
     *
     * @see attach() Для внедрения возвращённого токена в HTTP-ответ
     */
    public function validate(HttpRequest $request): string
    {
        $serverToken = $this->getServerToken($request);
        if (!in_array($request->method, self::SAFE_METHODS, true)) {
            $clientToken = $this->getClientToken($request);
            if ('' === $clientToken || !hash_equals($serverToken, $clientToken)) {
                throw new CsrfMismatchException();
            }
        }

        return $serverToken;
    }

    /**
     * Перегенерирует CSRF-токен и внедряет его в HTTP-ответ.
     *
     * Вызывайте этот метод при смене состояния безопасности пользователя:
     * - Успешная аутентификация (логин)
     * - Смена пароля
     * - Эскалация привилегий
     * - Любые действия, требующие разрыва связи со старым токеном
     *
     * Старый токен в сессии заменяется новым, клиент получает обновлённое значение.
     *
     * @param HttpRequest $request  Объект HTTP-запроса для доступа к сессии
     * @param Response    $response Объект HTTP-ответа для внедрения нового токена
     *
     * @return string Новый сгенерированный CSRF-токен
     *
     * @throws CookieException При ошибках валидации добавления куки
     * @throws RandomException если не удается найти подходящий источник случайности
     *
     * @see invalidate() Для сценариев логаута (семантический алиас этого метода)
     */
    public function reset(HttpRequest $request, Response $response): string
    {
        $token = $this->setToken($request);
        $this->attach($request, $response);

        return $token;
    }

    /**
     * Инвалидирует текущий CSRF-токен и генерирует новый.
     *
     * Семантический алиас метода {@see reset()}. Используйте для обозначения
     * намерения отозвать токен при завершении сеанса (логаут, блокировка).
     *
     * @param HttpRequest $request  Объект HTTP-запроса для доступа к сессии
     * @param Response    $response Объект HTTP-ответа для внедрения нового токена
     *
     * @throws CookieException При ошибках валидации добавления куки
     * @throws RandomException если не удается найти подходящий источник случайности
     *
     * @see reset() Фактическая реализация метода
     */
    public function invalidate(HttpRequest $request, Response $response): void
    {
        $this->reset($request, $response);
    }

    /**
     * Получает актуальный токен из сессии и внедряет его в HTTP-ответ.
     *
     * Способ доставки токена определяется конфигурацией ({@see CsrfConfig}):
     * - Режим HEADER: добавляет заголовок {@see CSRF_TOKEN_HEADER}
     * - Режим COOKIE: устанавливает Cookie {@see CSRF_TOKEN_COOKIE} с параметрами безопасности
     *
     * @param HttpRequest $request  Объект HTTP-запроса для доступа к сессии
     * @param Response    $response Объект HTTP-ответа для модификации
     *
     * @throws CookieException При ошибках валидации добавления куки
     * @throws JokeException   Если значение не может быть преобразовано в строку - фактически это не возможно при
     *                         стандартном создании объекта запрос
     * @throws RandomException если не удается найти подходящий источник случайности
     *
     * @see validate() Для получения токена перед внедрением
     */
    public function attach(HttpRequest $request, Response $response): void
    {
        $token = $this->getServerToken($request);
        if (CsrfTransportMode::HEADER === $this->config->transportMode) {
            $response->headers->set(self::CSRF_TOKEN_HEADER, $token);
        } else {
            $cookieConfig = $this->config->cookieConfig;
            $response->cookies->add(
                self::CSRF_TOKEN_COOKIE,
                $token,
                $cookieConfig->lifetime,
                $cookieConfig->path,
                $cookieConfig->domain,
                $cookieConfig->secure,
                httpOnly: false,
                sameSite: $cookieConfig->sameSite,
            );
        }
    }

    /**
     * @throws JokeException Если значение не может быть преобразовано в строку - фактически это не возможно при
     *                       стандартном создании объекта запроса
     */
    private function getClientToken(HttpRequest $request): string
    {
        return trim(
            $request->post->getString(self::CSRF_TOKEN_NAME, '')
                ?: $request->headers->getString(self::CSRF_TOKEN_HEADER, '')
                ?: $request->cookies->getString(self::CSRF_TOKEN_COOKIE, ''),
        );
    }

    /**
     * Возвращает CSRF токен для запроса.
     *
     * @throws JokeException   Если значение не может быть преобразовано в строку - фактически это не возможно
     *                         при стандартном создании объекта запроса
     * @throws RandomException если не удается найти подходящий источник случайности
     */
    public function getServerToken(HttpRequest $request): string
    {
        $token = $request->session->getString(self::CSRF_TOKEN_NAME, '');
        if ('' === $token) {
            $token = $this->setToken($request);
        }

        return $token;
    }

    /**
     * @throws RandomException если не удается найти подходящий источник случайности
     */
    private function setToken(HttpRequest $request): string
    {
        $token = bin2hex($this->random());
        $request->session->set(self::CSRF_TOKEN_NAME, $token);

        return $token;
    }

    /**
     * @throws RandomException если не удается найти подходящий источник случайности
     */
    private function random(): string
    {
        $length = 32;

        try {
            return random_bytes($length);
        } catch (RandomException $e) {
            $this->logger->info('CSRF token generation: Using alternative CSPRNG source: ' . $e->getMessage());
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($length, $crypto_strong);
            if ($crypto_strong) {
                return $bytes;
            }
        }
        $bytes = '';
        for ($i = 0; $i < $length; ++$i) {
            $bytes .= chr(random_int(0, 255));
        }
        $this->logger->warning(
            'CSRF token generation: Primary CSPRNG unavailable, using alternative source (random_int)',
        );

        return $bytes;
    }
}
