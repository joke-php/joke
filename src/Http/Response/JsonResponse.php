<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

use Vasoft\Joke\Http\Response\Response as NewResponse;

/**
 * HTTP-ответ в формате JSON.
 *
 * Автоматически устанавливает заголовок Content-Type: application/json
 * и преобразует переданные данные в JSON при отправке.
 * Поддерживает только массивы и объекты, совместимые с json_encode().
 */
class JsonResponse extends NewResponse
{
    /**
     * Тело ответа в виде массива (или объекта, совместимого с json_encode).
     *
     * @var array<string,mixed>|list<mixed>
     */
    protected array $body = [];

    /**
     * Устанавливает тело ответа.
     *
     * Ожидается массив или объект, который может быть сериализован в JSON.
     * Несовместимые типы (например, ресурсы) вызовут JsonException при отправке.
     *
     * @param array<string,mixed>|list<mixed> $body Данные для сериализации в JSON
     */
    public function setBody($body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Возвращает текущее тело ответа как массив.
     *
     * @return array<string,mixed>|list<mixed> $body Тело ответа
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Сериализует тело ответа в строку JSON.
     *
     * Использует флаг JSON_THROW_ON_ERROR — при ошибке сериализации
     * будет выброшено исключение JsonException.
     *
     * @return string Строка в формате JSON
     *
     * @throws \JsonException Если данные не могут быть сериализованы в JSON
     *
     * @todo Преобразовать в исключение типа JokeException
     */
    public function getBodyAsString(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR);
    }

    public function getContentType(): string
    {
        return 'application/json';
    }
}
