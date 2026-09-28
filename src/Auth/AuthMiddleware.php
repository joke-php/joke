<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth;

use Vasoft\Joke\Auth\Rights\RightsChecker;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\Exceptions\ContainerException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\Exceptions\ServiceNotFoundException;
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\RedirectResponse;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Http\Response\ResponseStatus;
use Vasoft\Joke\Exceptions\ConversionException;

/**
 * Middleware для проверки аутентификации и прав доступа.
 *
 * Блокирует выполнение контроллера, если:
 * 1. Пользователь не прошел аутентификацию (возвращает 401 или редирект).
 * 2. У пользователя отсутствуют требуемые права (возвращает 403).
 *
 * @see MiddlewareInterface
 * @see AuthService
 */
readonly class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @param AuthService     $authService     Сервис для получения текущего пользователя
     * @param RightsChecker   $rightsChecker   Сервис для проверки прав доступа
     * @param ResponseBuilder $responseBuilder Фабрика HTTP-ответов
     * @param list<string>    $requiredRights  Список требуемых прав в формате 'module:right'
     * @param null|string     $redirectUrl     URL для редиректа неавторизованных пользователей
     */
    public function __construct(
        private AuthService $authService,
        private RightsChecker $rightsChecker,
        private ResponseBuilder $responseBuilder,
        private array $requiredRights,
        private ?string $redirectUrl,
    ) {}

    /**
     * @throws ConfigException           в случае ошибок конфигурации
     * @throws ContainerException        в случае ошибок контейнера
     * @throws ConversionException       в случае ошибок приведения типов
     * @throws ParameterResolveException в случае ошибок определения параметров
     * @throws ServiceNotFoundException  если требующийся сервис не найден
     */
    #[\Override]
    public function handle(HttpRequest $request, callable $next): mixed
    {
        $user = $this->authService->getUser();
        foreach ($this->requiredRights as $right) {
            $data = explode(':', $right, 2);
            if (2 !== count($data)) {
                throw new ConfigException("Invalid right format: \"{$right}\". Expected \"module:right\".");
            }
            [$module, $action] = $data;
            if (!$this->rightsChecker->can($user, $module, $action)) {
                return $user->authorized
                    ? $this->createResponse(ResponseStatus::FORBIDDEN)
                    : $this->createUnauthorizedResponse();
            }
        }

        return $next($request);
    }

    /**
     * Создает ответ для неавторизованного пользователя.
     *
     * Если задан URL редиректа, возвращает RedirectResponse. Иначе возвращает ответ со статусом 401 Unauthorized.
     *
     * @throws ContainerException        В случае ошибок контейнера
     * @throws ConversionException       В случае ошибок приведения типов
     * @throws ParameterResolveException В случае ошибок разрешения параметров
     * @throws ServiceNotFoundException  Если сервис не найден
     */
    private function createUnauthorizedResponse(): Response
    {
        if (!empty($this->redirectUrl)) {
            $existsResponse = $this->responseBuilder->make('');

            /** @noinspection PhpUnhandledExceptionInspection */
            return new RedirectResponse($existsResponse->cookies, $this->redirectUrl);
        }

        return $this->createResponse(ResponseStatus::UNAUTHORIZED);
    }

    /**
     * Создает пустой HTTP-ответ с указанным статусом.
     *
     * @param ResponseStatus $status Статус ответа
     *
     * @throws ContainerException        В случае ошибок контейнера
     * @throws ConversionException       В случае ошибок приведения типов
     * @throws ParameterResolveException В случае ошибок разрешения параметров
     * @throws ServiceNotFoundException  Если сервис не найден
     */
    private function createResponse(ResponseStatus $status): Response
    {
        $response = $this->responseBuilder->make('');
        $response->setStatus($status);

        return $response;
    }
}
