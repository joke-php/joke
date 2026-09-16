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
 * Поддерживает два режима работы:
 * 1. **Режим авто-определения (по умолчанию)**: Если в конфигурации не указан конкретный класс,
 *    билдер автоматически выбирает тип ответа исходя из типа данных:
 *    - Массивы оборачиваются в {@see JsonResponse}.
 *    - Скалярные значения и объекты — в {@see HtmlResponse}.
 *
 * 2. **Режим строгого типа**: Если в конфигурации или через метод {@see setDefaultResponseClass()}
 *    явно указан класс ответа, билдер создает экземпляр именно этого класса.
 *    В этом режиме разработчик обязан передавать данные, совместимые с телом ответа
 *    (например, массив для JSON), иначе возникнет ошибка типизации.
 */
class ResponseBuilder
{
    /**
     * Класс ответа по умолчанию, полученный из конфигурации приложения.
     * Пустая строка ('') означает активацию режима авто-определения.
     *
     * @var ''|class-string<Response>
     */
    private string $defaultResponseClass = '';

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
        $this->defaultResponseClass = $appConfig->getResponseClass();
    }

    /**
     * Устанавливает класс ответа по умолчанию для текущего экземпляра билдера.
     *
     * Позволяет динамически переопределять тип ответа в рамках выполнения запроса
     * (например, внутри middleware или специфических групп маршрутов).
     *
     * Если передана пустая строка, метод игнорирует вызов, сохраняя текущее состояние.
     * Это предотвращает случайный сброс настроек в режим авто-определения, если он не был активен изначально.
     *
     * @param ''|class-string<Response> $defaultResponseClass полный имя класса ответа
     */
    public function setDefaultResponseClass(string $defaultResponseClass): static
    {
        if ('' !== $defaultResponseClass) {
            $this->defaultResponseClass = $defaultResponseClass;
        }

        return $this;
    }

    /**
     * Создает объект ответа на основе переданных сырых данных.
     *
     * Алгоритм работы:
     * 1. Если передан уже готовый объект {@see Response}, он возвращается без изменений.
     * 2. Если установлен конкретный класс ответа (режим строгого типа), создается его экземпляр
     *    и устанавливается тело ответа.
     * 3. Если класс ответа не задан (пустая строка), вызывается метод {@see makeAuto()}
     *    для автоматического определения типа на основе данных.
     *
     * @param mixed $raw Сырые данные, возвращенные контроллером или middleware (массив, строка, объект и т.д.).
     *
     * @return Response готовый объект ответа, готовый к отправке клиенту
     *
     * @throws ContainerException        в случае ошибок контейнера
     * @throws ParameterResolveException в случаен ошибок определения параметров
     * @throws ServiceNotFoundException  Если требующийся сервис не найден
     * @throws ConversionException       В случае ошибок приведения типов
     */
    public function make(mixed $raw): Response
    {
        if ($raw instanceof Response) {
            return $raw;
        }
        $class = '' !== $this->defaultResponseClass ? $this->defaultResponseClass : $this->determineClass($raw);
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
     * - Если режим авто-определения (пустая строка) → возвращает новый {@see HtmlPageResponse}.
     * - Если задан конкретный класс → возвращает экземпляр этого класса.
     *
     * @return Response новый экземпляр ответа без установленного тела
     *
     * @throws ContainerException        в случае ошибок контейнера
     * @throws ParameterResolveException в случаен ошибок определения параметров
     * @throws ServiceNotFoundException  Если требующийся сервис не найден
     * @throws ConversionException       В случае ошибок приведения типов
     */
    public function makeDefault(): Response
    {
        $class = '' !== $this->defaultResponseClass ? $this->defaultResponseClass : HtmlPageResponse::class;

        return $this->buildResponse($class);
    }

    /**
     * Непосредственное создание ответа заданного класса.
     *
     * @param class-string $className
     *
     * @throws ContainerException        в случае ошибок контейнера
     * @throws ParameterResolveException в случаен ошибок определения параметров
     * @throws ServiceNotFoundException  Если требующийся сервис не найден
     * @throws ConversionException       В случае ошибок приведения типов
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
