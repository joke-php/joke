<?php

declare(strict_types=1);

namespace Vasoft\Joke\Application;

use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\Response;

class ApplicationConfig extends AbstractConfig
{
    /**
     * @var null|class-string Тип ответа по умолчанию
     */
    private ?string $responseClass = null;
    private string $fileRoutes = 'routes/web.php';
    /**
     * Типы ответов по умолчанию для групп
     *
     * @var array<string,null|class-string<Response>>
     */
    private array $groupResponseClass = [];

    /**
     * Устанавливает путь к файлу маршрутов абсолютный или относительно корня проекта.
     *
     * @param string $fileRoutes путь к файлу маршрутов
     *
     * @return $this
     *
     * @throws ConfigException
     */
    public function setFileRoutes(string $fileRoutes): static
    {
        $this->guard();
        $this->fileRoutes = $fileRoutes;

        return $this;
    }

    /**
     * Возвращает путь к файлу маршрутов.
     */
    public function getFileRoutes(): string
    {
        return $this->fileRoutes;
    }

    /**
     * @return null|class-string
     */
    public function getResponseClass(): ?string
    {
        return $this->responseClass;
    }

    /**
     * Устанавливает тип ответа по умолчанию для всего приложения.
     *
     * Может быть переопределено на уровне группы или на уровне маршрута.
     *
     * По умолчанию включено авто-определение типа (массив -> JsonResponse, остальное -> HtmlResponse).
     * Для включения строгого режима передайте имя класса (например, JsonResponse::class).
     * Если передать null - режим автоопределения
     *
     * @param null|class-string<Response> $responseClass Тип ответа по умолчанию
     *
     * @return $this
     *
     * @throws ConfigException
     */
    public function setResponseClass(?string $responseClass): static
    {
        $this->guard();
        $this->responseClass = $responseClass;

        return $this;
    }

    /**
     * Устанавливает тип ответа по умолчанию для группы маршрутов.
     *
     *  Может быть переопределено на уровне маршрута.
     *
     * По умолчанию включено авто-определение типа (массив -> JsonResponse, остальное -> HtmlResponse).
     * Для включения строгого режима передайте имя класса (например, JsonResponse::class).
     * Если передать null - режим автоопределения
     *
     * @param non-empty-string            $groupName     Имя группы
     * @param null|class-string<Response> $responseClass Тип ответа по умолчанию
     *
     * @return $this
     *
     * @throws ConfigException
     */
    public function setGroupResponseClass(string $groupName, ?string $responseClass): static
    {
        $this->guard();
        if (null !== $responseClass && '' === trim($responseClass)) {
            $responseClass = null;
        }
        $this->groupResponseClass[$groupName] = $responseClass;

        return $this;
    }

    /**
     * Возвращает класс ответа по умолчанию для группы или null - если определение делегируется на другой уровень.
     *
     * @param non-empty-string $groupName Имя группы
     *
     * @return null|class-string<Response>
     */
    public function getGroupResponseClass(string $groupName): ?string
    {
        return $this->groupResponseClass[$groupName] ?? null;
    }
}
