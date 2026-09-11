<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Csrf;

use Random\RandomException;
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\Cookies\Exceptions\CookieException;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Middleware\Exceptions\CsrfMismatchException;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Middleware\Exceptions\MiddlewareException;

/**
 * Обеспечивает защиту от межсайтовой подделки запроса (CSRF).
 *
 * Делегирует всю логику работы с токенами классу {@see CsrfTokenManager}:
 * - Валидация токена из запроса (метод validate())
 * - Внедрение токена в ответ (метод attach())
 *
 * Для небезопасных HTTP-методов (POST, PUT, DELETE и др.) проверяет совпадение
 * клиентского токена с серверным. При несоответствии выбрасывает CsrfMismatchException.
 *
 * @see CsrfTokenManager Основная логика работы с CSRF-токенами
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseBuilder   $responseBuilder Билдер ответа
     * @param CsrfConfig        $config          Конфигурация CSRF. Не используется. В версии 2.* будет удален
     * @param ?CsrfTokenManager $manager         Менеджер токенов CSRF. null - только для совместимости.
     *                                           Параметр является обязательным. В версии 2.* Сигнатура изменится.
     *
     * @throws MiddlewareException
     */
    public function __construct(
        private readonly ResponseBuilder $responseBuilder,
        // @phpstan-ignore-next-line
        private readonly CsrfConfig $config = new CsrfConfig(),
        private readonly ?CsrfTokenManager $manager = null,
    ) {
        if (null === $this->manager) {
            throw new MiddlewareException('CsrfMiddleware requires a valid csrf token manager.');
        }
    }

    /**
     * @throws CsrfMismatchException Если токен клиента не совпадает с серверным
     * @throws RandomException       Если не удается найти подходящий источник случайности
     * @throws CookieException       При ошибках добавления CSRF-куки в ответ
     * @throws JokeException         Если значение не может быть преобразовано в строку - фактически это не возможно
     *                               при стандартном создании объекта запроса
     */
    public function handle(HttpRequest $request, callable $next): Response
    {
        $this->manager->validate($request);
        $preparedResponse = $this->responseBuilder->make($next());
        $this->manager->attach($request, $preparedResponse);

        return $preparedResponse;
    }
}
