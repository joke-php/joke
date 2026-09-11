<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

use Vasoft\Joke\Routing\Exceptions\NotFoundException;

/**
 * Базовый класс для бинарных HTTP-ответов.
 *
 * Предназначен для отправки файлов и бинарных данных (изображения, PDF, архивы и т.д.).
 * Автоматически устанавливает заголовки Content-Length и Content-Disposition.
 *
 * @see Response
 */
abstract class BinaryResponse extends Response
{
    /**
     * Тело ответа (бинарные данные).
     */
    protected string $body = '';
    /**
     * Имя файла для загрузки клиентом.
     *
     * При установке автоматически извлекается базовое имя файла через basename().
     */
    public string $filename = '' {
        set(string $value) => $this->filename = basename($value);
        get => $this->filename;
    }

    /**
     * Загружает содержимое файла в тело ответа.
     *
     * Если свойство {@see $filename} ещё не установлено, оно будет автоматически
     * заполнено переданным путём к файлу.
     *
     * @param string $filename Полный путь к файлу на сервере
     *
     * @throws NotFoundException Если файл не существует или не удалось прочитать его содержимое
     */
    public function load(string $filename): static
    {
        if (
            !file_exists($filename)
            || ($body = file_get_contents($filename)) === false) {
            throw new NotFoundException('File not found');
        }
        $this->body = $body;
        if ('' === $this->filename) {
            $this->filename = $filename;
        }

        return $this;
    }

    /**
     * Устанавливает тело ответа.
     *
     * @param string $body Бинарные данные или строка
     */
    public function setBody($body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Возвращает тело ответа.
     *
     * @return string Содержимое ответа
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Возвращает тело ответа в виде строки.
     *
     * Метод идентичен {@see getBody()}, добавлен для совместимости с интерфейсами,
     * требующими явного метода получения строкового представления.
     *
     * @return string Содержимое ответа
     */
    public function getBodyAsString(): string
    {
        return $this->body;
    }

    /**
     * Отправляет HTTP-ответ клиенту.
     *
     * Автоматически устанавливает заголовки:
     * - Content-Length: размер тела ответа
     * - Content-Disposition: attachment с именем файла
     */
    public function send(): static
    {
        $this->headers->set('Content-Length', strlen($this->body));
        $this->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $this->filename));

        return parent::send();
    }
}
