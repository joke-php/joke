<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

use Vasoft\Joke\Collections\HeadersCollection;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Exceptions\HttpException;

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
        try {
            $this->headers->sanitize();
        } catch (JokeException $e) {
            throw new HttpException($e->getMessage(), previous: $e);
        }
        foreach ($this->headers->getAll() as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        foreach ($this->cookies as $cookie) {
            $cookieHeader = 'Set-Cookie: ' . $cookie->headerValue();
            $this->assertNoControlCharacters($cookieHeader);
            header($cookieHeader);
        }

        header('HTTP/1.1 ' . $this->status->value . ' ' . $this->status->http());
    }

    /**
     * Проверяет корректность имени и значения HTTP-заголовка.
     *
     * Имя должно состоять только из token-символов (tchar по RFC 7230),
     * значение не должно содержать управляющих символов.
     */
    private function assertValidHeader(string $name, string $value): void
    {
        if (preg_match('/[^a-zA-Z0-9!#$%&\'*+\-.^_`|~]/', $name)) {
            throw new HttpException(sprintf('Invalid header name "%s": contains invalid characters.', trim($name)));
        }

        $this->assertNoControlCharacters($value, $name);
    }

    /**
     * Запрещает управляющие символы (CTL) в значении заголовка.
     *
     * HTAB (\t) разрешён: RFC 7230 допускает SP и HTAB внутри field-value.
     */
    private function assertNoControlCharacters(string $value, string $context = 'header'): void
    {
        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value)) {
            throw new HttpException(sprintf('Invalid "%s" header value: contains control characters.', $context));
        }
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
