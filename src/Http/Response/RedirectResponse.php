<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

use Vasoft\Joke\Http\Cookies\CookieCollection;
use Vasoft\Joke\Http\Exceptions\HttpException;

/**
 * Представляет HTTP-ответ для перенаправления клиента (Redirect).
 *
 * Этот класс специализируется на отправке заголовка Location.
 * Тело ответа и Content-Type намеренно игнорируются, так как они не используются
 * браузерами при обработке редиректов.
 */
class RedirectResponse extends Response
{
    /**
     * @param CookieCollection $cookies     коллекция кук для установки при ответе
     * @param string           $redirectUrl целевой URL для перенаправления
     * @param ResponseStatus   $status      HTTP-статус редиректа (по умолчанию 302 Found)
     *
     * @throws HttpException если передан пустой URL
     */
    public function __construct(
        CookieCollection $cookies,
        private readonly string $redirectUrl,
        ResponseStatus $status = ResponseStatus::FOUND,
    ) {
        if ('' === $redirectUrl) {
            throw new HttpException('Redirect URL cannot be empty.');
        }
        parent::__construct($cookies);
        $this->setStatus($status);
        $this->headers->set('Location', $this->redirectUrl);
    }

    /**
     * Игнорирует попытку установки тела ответа.
     * Редиректы не содержат полезной нагрузки в теле.
     *
     * @param mixed $body
     */
    #[\Override]
    public function setBody($body): static
    {
        return $this;
    }

    /**
     * Возвращает пустую строку, так как редиректы не имеют тела.
     */
    #[\Override]
    public function getBody(): string
    {
        return '';
    }

    /**
     * Возвращает пустую строку, так как для редиректов Content-Type не применим.
     */
    #[\Override]
    public function getBodyAsString(): string
    {
        return '';
    }

    #[\Override]
    public function getContentType(): string
    {
        return '';
    }

    /**
     * Отправляет HTTP-заголовки, включая статус, Location и куки.
     *
     * Метод не завершает выполнение скрипта,
     * что позволяет выполнить дополнительную логику после отправки ответа.
     */
    #[\Override]
    public function send(): static
    {
        $this->sendHeaders();

        return $this;
    }
}
