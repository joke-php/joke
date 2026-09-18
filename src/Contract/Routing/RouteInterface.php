<?php

declare(strict_types=1);

namespace Vasoft\Joke\Contract\Routing;

use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Middleware\MiddlewareDto;
use Vasoft\Joke\Http\HttpMethod;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Container\ServiceContainer;

/**
 * Интерфейс отдельного маршрута в системе маршрутизации.
 *
 * Представляет собой неизменяемый (immutable) объект, описывающий соответствие между
 * HTTP-методом, URI-шаблоном и обработчиком запроса. Роут отвечает за:
 * - хранение исходного пути и HTTP-метода
 * - компиляцию пути в регулярное выражение для быстрого сопоставления
 * - проверку соответствия входящего запроса своему шаблону
 * - выполнение связанного обработчика с использованием DI-контейнера.
 *
 * Роуты могут быть именованными, что позволяет использовать их для генерации URL.
 * Поддерживается создание копии маршрута с другим HTTP-методом через метод withMethod(),
 * что полезно при регистрации одного пути для нескольких методов.
 */
interface RouteInterface
{
    /**
     * Скомпилированное регулярное выражение, соответствующее URI-шаблону маршрута.
     *
     * Используется для эффективного сопоставления входящего запроса с шаблоном пути.
     * Значение генерируется лениво при первом обращении или во время компиляции.
     * Может быть null, если компиляция ещё не выполнена.
     */
    public ?string $compiledPattern {
        get;
    }

    /**
     * HTTP-метод, с которым ассоциирован данный маршрут.
     *
     * Определяет, какие типы запросов (GET, POST и т.д.) может обрабатывать этот маршрут.
     */
    public HttpMethod $method {
        get;
    }
    /**
     * Тип ответа по умолчанию для маршрута.
     * Имеет больший приоритет чем настройки группы и приложения.
     *
     * Если передан null или пустая строка - используется настройка уровня группы, приложения или автоопределение
     *
     * @var null|class-string<Response>
     */
    public ?string $defaultResponseClass {
        get;
    }
    /**
     * Возвращает группу по умолчанию для маршрута.
     *
     * @var string имя группы или пустая строка если группа не задана или не существует
     */
    public string $defaultGroup {
        get;
    }

    /**
     * Устанавливает для маршрута тип ответа по умолчанию.
     *
     * Если передан null или пустая строка - используется настройка уровня группы, приложения или автоопределение
     *
     * @param null|class-string<Response> $class
     */
    public function setDefaultResponseClass(?string $class): static;

    /**
     * Конструктор маршрута.
     *
     * @param ServiceContainer                                   $serviceContainer DI-контейнер, используемый для разрешения
     *                                                                             зависимостей при вызове обработчика
     * @param string                                             $path             URI-шаблон маршрута (например, '/users/{id}')
     * @param HttpMethod                                         $method           HTTP-метод, который будет обрабатываться
     * @param array{class-string,non-empty-string}|object|string $handler          Обработчик запроса, возвращающий ответ. Может быть:
     *                                                                             - замыканием или callable-функцией;
     *                                                                             - строкой с именем класса, реализующего метод __invoke;
     *                                                                             - массивом вида [класс, метод], где класс может быть указан
     *                                                                             как строка (имя класса), объект или интерфейс,
     *                                                                             а метод — строка с именем публичного метода.
     * @param string                                             $name             необязательное имя маршрута, используемое
     *                                                                             для обратной маршрутизации (генерации URL)
     */
    public function __construct(
        ServiceContainer $serviceContainer,
        string $path,
        HttpMethod $method,
        array|object|string $handler,
        string $name = '',
    );

    /**
     * Добавляет middleware к маршруту.
     *
     * @param MiddlewareInterface|string $middleware middleware
     * @param string                     $name       Имя middleware (если задано, то возможен только единственный вариант)
     *
     * @return $this
     */
    public function addMiddleware(MiddlewareInterface|string $middleware, string $name = ''): static;

    /**
     * Создаёт новый экземпляр маршрута с тем же путём и обработчиком, но другим HTTP-методом.
     *
     * Используется, когда один URI-шаблон должен обрабатывать несколько HTTP-методов
     * (например, GET и POST). Возвращает копию текущего маршрута с заменённым методом.
     *
     * @param HttpMethod $method новый HTTP-метод
     *
     * @return static новый экземпляр маршрута с указанным методом
     */
    public function withMethod(HttpMethod $method): static;

    /**
     * Проверяет, соответствует ли данный маршрут переданному HTTP-запросу.
     *
     * Выполняет сопоставление URI запроса с шаблоном маршрута (включая извлечение
     * параметров и добавление в объект запроса, если они есть) и проверяет совпадение HTTP-метода.
     *
     * @param HttpRequest $request входящий HTTP-запрос
     *
     * @return bool true, если маршрут подходит для запроса, иначе false
     */
    public function matches(HttpRequest $request): bool;

    /**
     * Выполняет обработчик маршрута с переданным запросом.
     *
     * Перед вызовом обработчика DI-контейнер может быть использован для внедрения
     * зависимостей (например, если обработчик — это метод контроллера).
     * Возвращает результат выполнения обработчика.
     *
     * @param HttpRequest $request входящий HTTP-запрос
     *
     * @return mixed результат работы обработчика (например, строка, массив, Response-объект)
     */
    public function run(HttpRequest $request): mixed;

    /**
     * Возвращает список групп маршрута.
     *
     * @return array<string>
     */
    public function getGroups(): array;

    /**
     * Добавление маршрута в группу.
     *
     * @return $this
     */
    public function addGroup(string $groupName): static;

    /**
     * Добавление маршрута в список групп
     *
     * @param array<string> $groups
     *
     * @return $this
     */
    public function mergeGroup(array $groups): static;

    /**
     * Устанавливает основную группу маршрута.
     *
     * @param string $groupName Имя группы, если передана пустая строка - выбирается автоматически
     *
     * @return $this
     *
     * @throws JokeException Если группа еще не добавлена
     */
    public function setDefaultGroup(string $groupName): static;

    /**
     * Возвращает список middleware привязанных к маршруту.
     *
     * @return array<MiddlewareDto>
     */
    public function getMiddlewares(): array;
}
