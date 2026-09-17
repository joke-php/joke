<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Container\Exceptions\ContainerException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\Exceptions\ServiceNotFoundException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\HttpRequest;

/**
 * Фабрика-билдер для создания объектов HTTP-ответов.
 *
 * Отвечает за инкапсуляцию логики выбора конкретного класса ответа ({@see Response})
 * на основе глобальной конфигурации приложения и контекста выполнения.
 *
 * Приоритет выбора класса ответа:
 * 1. {@see $currentResponseClass} — временное переопределение для текущего маршрута/группы.
 * 2. {@see $defaultResponseClass} — класс из конфигурации приложения.
 * 3. Авто-определение ({@see determineClass()}) — fallback на основе типа данных:
 *    - Массивы оборачиваются в {@see JsonResponse}.
 *    - Скалярные значения и объекты — в {@see HtmlPageResponse}.
 */
class ResponseBuilder
{
    /**
     * Текущий класс ответа для активного контекста (маршрут/группа).
     *
     * Приоритет использования:
     * 1. Значение этого свойства (если задано) — переопределяет всё
     * 2. {@see $defaultResponseClass} из конфигурации приложения
     * 3. Авто-определение (fallback)
     *
     * @var null|class-string<Response>
     */
    private ?string $currentResponseClass = null;
    /**
     * Класс ответа по умолчанию, полученный из конфигурации приложения.
     * Значение null означает активацию режима авто-определения.
     *
     * @var null|class-string<Response>
     */
    private ?string $defaultResponseClass = null;

    /**
     * Создает экземпляр билдера и инициализирует настройки из конфигурации приложения.
     *
     * @param ApplicationConfig $appConfig        конфигурация приложения для получения настроек ответа по умолчанию
     * @param ServiceContainer  $serviceContainer Di контейнер
     */
    public function __construct(
        ApplicationConfig $appConfig,
        private readonly ServiceContainer $serviceContainer,
    ) {
        $defaultClass = $appConfig->getResponseClass();
        if (null !== $defaultClass && '' === trim($defaultClass)) {
            $defaultClass = null;
        }
        $this->defaultResponseClass = $defaultClass;
    }

    /**
     * Устанавливает текущий класс ответа для активного контекста (маршрут/группа).
     *
     * @param null|class-string<Response> $currentResponseClass
     *
     * @return ResponseBuilder
     */
    public function setCurrentResponseClass(?string $currentResponseClass): static
    {
        if (null !== $currentResponseClass && '' === trim($currentResponseClass)) {
            $currentResponseClass = null;
        }
        $this->currentResponseClass = $currentResponseClass;

        return $this;
    }

    /**
     * Создает объект ответа на основе переданных сырых данных.
     *
     * Алгоритм работы:
     * 1. Если передан уже готовый объект {@see Response}, он возвращается без изменений.
     * 2. Если установлен конкретный класс ответа (режим строгого типа), создается его экземпляр
     *    и устанавливается тело ответа.
     * 3. Если класс ответа не задан (null), вызывается метод {@see determineClass()}
     *    для автоматического определения типа на основе данных.
     *
     * @param mixed $raw Сырые данные, возвращенные контроллером или middleware (массив, строка, объект и т.д.).
     *
     * @return Response готовый объект ответа, готовый к отправке клиенту
     *
     * @throws ContainerException        в случае ошибок контейнера
     * @throws ParameterResolveException в случае ошибок определения параметров
     * @throws ServiceNotFoundException  если требующийся сервис не найден
     * @throws ConversionException       в случае ошибок приведения типов
     */
    public function make(mixed $raw): Response
    {
        if ($raw instanceof Response) {
            return $raw;
        }
        $class = $this->currentResponseClass ?? $this->defaultResponseClass ?? $this->determineClass($raw);
        $response = $this->buildResponse($class);
        $response->setBody($raw);

        return $response;
    }

    /**
     * Создает пустой объект ответа типа, установленного по умолчанию.
     *
     * В отличие от метода {@see make()}, этот метод не принимает данных и не устанавливает тело ответа.
     * Полезен в ситуациях, когда нужно предварительно создать объект ответа (например, в обработчиках ошибок),
     * чтобы затем наполнить его данными в специфичном формате.
     *
     * Логика выбора класса:
     *  - Если режим авто-определения (null) - возвращает новый {@see HtmlPageResponse}.
     *  - Если задан конкретный класс - возвращает экземпляр этого класса.
     *
     * @return Response новый экземпляр ответа без установленного тела
     *
     * @throws ContainerException        в случае ошибок контейнера
     * @throws ParameterResolveException в случае ошибок определения параметров
     * @throws ServiceNotFoundException  если требующийся сервис не найден
     * @throws ConversionException       в случае ошибок приведения типов
     */
    public function makeDefault(): Response
    {
        $class = $this->currentResponseClass ?? $this->defaultResponseClass ?? HtmlPageResponse::class;

        return $this->buildResponse($class);
    }

    /**
     * Непосредственное создание ответа заданного класса.
     *
     * @param class-string $className
     *
     * @throws ContainerException        в случае ошибок контейнера
     * @throws ParameterResolveException в случае ошибок определения параметров
     * @throws ServiceNotFoundException  если требующийся сервис не найден
     * @throws ConversionException       в случае ошибок приведения типов
     */
    private function buildResponse(string $className): Response
    {
        /** @var HttpRequest $request */
        $request = $this->serviceContainer->get(Request::class);
        /** @var CookieCollection $cookies */
        $cookies = $this->serviceContainer->make(
            CookieCollection::class,
            ['isSecureConnection' => $request->isSecure()],
        );

        return $this->serviceContainer->make($className, ['cookies' => $cookies]);
    }

    /**
     * Автоматически определяет тип ответа на основе типа входных данных.
     *
     * Используется как fallback-логика, когда явный тип ответа не задан в конфигурации.
     *
     * Правила преобразования:
     * - Массив (`array`) → {@see JsonResponse} (данные кодируются в JSON).
     * - Любые другие типы (строка, число, объект, null) → {@see HtmlPageResponse}.
     *
     * @param mixed $raw входные данные для анализа
     *
     * @return class-string класс ответа, соответствующий типу данных
     */
    private function determineClass(mixed $raw): string
    {
        return is_array($raw) ? JsonResponse::class : HtmlPageResponse::class;
    }
}
