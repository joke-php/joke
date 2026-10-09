<?php

declare(strict_types=1);

namespace Vasoft\Joke\Mail;

use Vasoft\Joke\Collections\HeadersCollection;

/**
 * Представляет электронное письмо (MIME-сообщение).
 *
 * Содержит всю необходимую информацию для отправки: адресатов, тему,
 * текстовое и HTML-тела, а также коллекцию заголовков.
 */
final class Email
{
    /**
     * Коллекция заголовков письма.
     */
    public private(set) ?HeadersCollection $headers = null {
        get {
            return $this->headers ??= new HeadersCollection([]);
        }
    }

    /**
     * Создает новый экземпляр электронного письма.
     *
     * @param Address                                              $from    Адрес отправителя
     * @param list<Address>                                        $to      Список адресатов
     * @param string                                               $subject Тема письма
     * @param string                                               $text    Текстовая версия тела письма (plain text)
     * @param string                                               $html    HTML-версия тела письма
     * @param null|array<non-empty-string,mixed>|HeadersCollection $headers Начальные заголовки (массив или объект коллекции)
     */
    public function __construct(
        public Address $from,
        public array $to,
        public string $subject,
        public string $text = '',
        public string $html = '',
        array|HeadersCollection|null $headers = [],
    ) {
        if (is_array($headers)) {
            $this->headers = new HeadersCollection($headers);
        } elseif ($headers instanceof HeadersCollection) {
            $this->headers = $headers;
        }
    }

    /**
     * Гарантирует наличие базовых заголовков, необходимых для корректной MIME-структуры.
     *
     * Если заголовок Content-Type еще не установлен, метод добавляет его,
     * определяя тип контента на основе наличия HTML-версии тела письма.
     * Автоматически указывает кодировку UTF-8.
     */
    public function ensureBaseHeaders(): void
    {
        if (!$this->headers->has('content-type')) {
            $type = ('' !== $this->html)
                ? 'text/html; charset=UTF-8'
                : 'text/plain; charset=UTF-8';
            $this->headers->set('Content-Type', $type);
        }
    }
}
