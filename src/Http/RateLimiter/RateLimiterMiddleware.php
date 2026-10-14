<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\RateLimiter;

use Vasoft\Joke\Container\Exceptions\ContainerException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\Exceptions\ServiceNotFoundException;
use Vasoft\Joke\Contract\Http\RateLimiter\ClientIdentifierInterface;
use Vasoft\Joke\Contract\Http\RateLimiter\StorageInterface;
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\RateLimiter\Exceptions\RateLimiterException;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Http\Response\ResponseStatus;

/**
 * Middleware для ограничения частоты запросов.
 *
 * Реализует алгоритм Sliding Window: каждый успешный запрос обновляет TTL счётчика,
 * поэтому окно "скользит" вслед за активностью клиента. Блокировка действует до тех пор,
 * пока клиент не прекратит активность на длительность {@see $window}.
 *
 * Поведение:
 * - При отключённом ограничении ({@see RateLimiterConfig::$enabled}) пропускает все запросы
 * - При ошибке хранилища — fail-open: запрос пропускается без проверки лимитов
 * - Если клиент не идентифицирован — используется fallback на IP-адрес
 * - Заголовки X-Rate-Limit-* добавляются только при включённой опции {@see RateLimiterConfig::$withHeaders}
 *
 * @see MiddlewareInterface
 * @see RateLimiterConfig
 * @see StorageInterface
 * @see ClientIdentifierInterface
 */
readonly class RateLimiterMiddleware implements MiddlewareInterface
{
    /**
     * Создаёт middleware ограничения частоты запросов.
     *
     * @param StorageInterface          $storage          Хранилище счётчиков запросов
     * @param ClientIdentifierInterface $clientIdentifier Стратегия идентификации клиента
     * @param RateLimiterConfig         $config           Конфигурация ограничителя
     * @param ResponseBuilder           $builder          Фабрика для создания/нормализации HTTP-ответов
     * @param int                       $limit            Максимальное количество разрешённых запросов в окне (должен быть > 0)
     * @param int                       $window           Длительность скользящего окна в секундах (должна быть > 0)
     *
     * @throws RateLimiterException Если limit или window <= 0
     */
    public function __construct(
        private StorageInterface $storage,
        private ClientIdentifierInterface $clientIdentifier,
        private RateLimiterConfig $config,
        private ResponseBuilder $builder,
        private int $limit = 100,
        private int $window = 3600,
    ) {
        if ($limit <= 0) {
            throw new RateLimiterException('Rate limit must be greater than zero.');
        }
        if ($window <= 0) {
            throw new RateLimiterException('Window duration must be greater than zero.');
        }
    }

    /**
     * {@inheritDoc}
     *
     * Проверяет лимит запросов для идентифицированного клиента.
     * При превышении лимита возвращает ответ 429 Too Many Requests.
     * При успешной проверке передаёт запрос дальше и опционально
     * добавляет заголовки rate limiting к ответу.
     *
     * @return mixed Объект {@see Response} или сырые данные от следующего обработчика
     *
     * @throws JokeException             При ошибке создания ответа
     * @throws ContainerException        в случае ошибок контейнера
     * @throws ParameterResolveException в случае ошибок определения параметров
     * @throws ServiceNotFoundException  если требующийся сервис не найден
     * @throws ConversionException       в случае ошибок приведения типов
     */
    public function handle(HttpRequest $request, callable $next): mixed
    {
        if (!$this->config->enabled) {
            return $next($request);
        }

        $clientId = $this->clientIdentifier->identify($request) ?? 'fallback:' . $request->server->getClientIp();

        try {
            $counter = $this->storage->increment($clientId, $this->window);
        } catch (\Throwable $e) {
            // @todo логировать
            return $next($request);
        }
        $remaining = max(0, $this->limit - $counter);
        $isBlocked = $counter > $this->limit;

        $resetTime = time() + $this->window;

        if ($isBlocked) {
            return $this->buildBlockedResponse($remaining, $resetTime);
        }
        $response = $next($request);
        if ($this->config->withHeaders) {
            $response = $this->builder->make($response);
            $this->appendHeaders($response, $remaining, $resetTime);
        }

        return $response;
    }

    /**
     * Создаёт HTTP-ответ 429 Too Many Requests с заголовками rate limiting.
     *
     * @param int $remaining Оставшееся количество запросов (всегда 0 при блокировке)
     * @param int $resetTime Unix-метка времени сброса скользящего окна
     *
     * @return Response Ответ со статусом 429 и опциональными заголовками X-Rate-Limit-* и Retry-After
     *
     * @throws JokeException При ошибке создания ответа
     */
    protected function buildBlockedResponse(int $remaining, int $resetTime): Response
    {
        $response = $this->builder->makeDefault();
        $response->setStatus(ResponseStatus::TOO_MANY_REQUESTS);
        if ($this->config->withHeaders) {
            $this->appendHeaders($response, $remaining, $resetTime);
            $response->headers->set('Retry-After', $this->window);
        }

        return $response;
    }

    /**
     * Добавляет стандартные заголовки rate limiting к HTTP-ответу.
     *
     * Заголовки:
     * - X-Rate-Limit-Limit: максимальное количество разрешённых запросов
     * - X-Rate-Limit-Remaining: оставшееся количество запросов в текущем окне
     * - X-Rate-Limit-Reset: Unix-метка времени сброса окна
     *
     * @param Response $response  HTTP-ответ для модификации
     * @param int      $remaining Оставшееся количество запросов
     * @param int      $resetTime Unix-метка времени сброса скользящего окна
     */
    protected function appendHeaders(Response $response, int $remaining, int $resetTime): void
    {
        $response->headers->set('X-Rate-Limit-Limit', $this->limit);
        $response->headers->set('X-Rate-Limit-Remaining', $remaining);
        $response->headers->set('X-Rate-Limit-Reset', $resetTime);
    }
}
