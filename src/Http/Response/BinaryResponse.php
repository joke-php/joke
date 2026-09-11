<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

use Vasoft\Joke\Routing\Exceptions\NotFoundException;

abstract class BinaryResponse extends Response
{
    /**
     * Тело файла.
     */
    protected string $body = '';
    public string $filename = '' {
        set(string $value) => $this->filename = basename($value);
        get => $this->filename;
    }

    /**
     * Загрузка тела из файла.
     *
     * @param string $filename Полное имя файла
     *
     * @return $this
     *
     * @throws NotFoundException Если не удалось считать файл
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
     * @param string $body
     *
     * @return $this
     */
    public function setBody($body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getBodyAsString(): string
    {
        return $this->body;
    }

    public function send(): static
    {
        $this->headers->set('Content-Length', strlen($this->body));
        $this->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $this->filename));

        return parent::send();
    }
}
