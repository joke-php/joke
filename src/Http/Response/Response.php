<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

use Vasoft\Joke\Collections\HeadersCollection;
use Vasoft\Joke\Http\Cookies\CookieCollection;

/**
 * Абстрактный базовый класс HTTP-ответа.
 *
 * Определяет общую структуру и поведение всех типов ответов (HTML, JSON и др.).
 * Управляет HTTP-статусом, заголовками и отправкой тела ответа клиенту.
 * Конкретные реализации должны определять формат тела ответа через абстрактные методы.
 */
abstract class Response
{
    /**
     * HTTP-статус ответа.
     *
     * По умолчанию: OK (200).
     */
    public private(set) ResponseStatus $status {
        get => $this->status ??= ResponseStatus::OK;
    }
    /**
     * Коллекция HTTP-заголовков ответа.
     *
     * Лениво инициализируется при первом обращении.
     */
    public private(set) ?HeadersCollection $headers = null {
        get {
            return $this->headers ??= new HeadersCollection([]);
        }
    }

    public function __construct(public readonly CookieCollection $cookies)
    {
        $this->headers->setContentType($this->getContentType());
    }

    /**
     * Устанавливает тело ответа.
     *
     * Конкретная реализация определяет допустимые типы входных данных
     * (например, строка для HtmlResponse, массив для JsonResponse).
     *
     * @param null|array<string,mixed>|bool|float|int|list<mixed>|object|string $body Тело ответа
     */
    abstract public function setBody($body): static;

    /**
     * Возвращает текущее тело ответа.
     *
     * Тип возвращаемого значения зависит от реализации.
     */
    abstract public function getBody(): mixed;

    /**
     * Отправляет ответ клиенту.
     *
     * Выполняет следующие действия:
     * 1. Отправляет все установленные HTTP-заголовки
     * 2. Отправляет тело ответа через echo
     */
    public function send(): static
    {
        $this->sendHeaders();
        echo $this->getBodyAsString();

        return $this;
    }

    /**
     * Возвращает строковое представление тела ответа.
     *
     * Используется при отправке ответа. Должен быть реализован
     * с учётом специфики формата (например, json_encode для JSON).
     */
    abstract public function getBodyAsString(): string;

    /**
     * Отправляет HTTP-заголовки клиенту.
     *
     * Включает все пользовательские заголовки и строку статуса HTTP.
     * Вызывается автоматически методом send().
     */
    protected function sendHeaders(): void
    {
        $headers = $this->headers->getAll();
        foreach ($headers as $name => $value) {
            if (empty($value)) {
                continue;
            }
            header(sprintf('%s: %s', $name, $value));
        }
        foreach ($this->cookies as $cookie) {
            header('Set-Cookie: ' . $cookie->headerValue());
        }
        header('HTTP/1.1 ' . $this->status->value . ' ' . $this->status->http());
    }

    /**
     * Устанавливает HTTP-статус ответа.
     *
     * @param ResponseStatus $status Статус из перечисления ResponseStatus
     */
    public function setStatus(ResponseStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Возвращает MIME-тип содержимого ответа.
     *
     * @return string MIME-тип (например, 'text/html', 'application/json', 'image/png')
     */
    abstract public function getContentType(): string;
}
