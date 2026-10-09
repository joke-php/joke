<?php

declare(strict_types=1);

namespace Vasoft\Joke\Mail;

/**
 * Утилитарный класс для кодирования значений заголовков в формат MIME.
 *
 * Обеспечивает корректную передачу не-ASCII символов (например, кириллицы)
 * в заголовках электронных писем согласно стандарту RFC 2047.
 */
class MimeConverter
{
    public const string TEMPORARY_KEY_NAME = 'TEMPORARY_KEY_NAME';

    /**
     * Кодирует строку в формат MIME Encoded-Word для использования в заголовках письма.
     *
     * Если строка содержит только ASCII-символы, она возвращается без изменений.
     * Для не-ASCII строк используется кодирование Base64 с автоматической разбивкой
     * на строки длиной не более 76 символов (RFC 2822).
     *
     * @param string $value Исходная строка (например, тема письма или имя отправителя)
     * @param string $key   Имя заголовка. Используется как префикс при кодировании.
     *                      Если передана пустая строка, метод вернет только закодированное значение
     *                      без имени заголовка.
     *
     * @return string Закодированная строка или исходная, если кодирование не требуется
     */
    public static function encode(string $value, string $key = ''): string
    {
        if (!preg_match('/[\x80-\xFF]/', $value)) {
            return '' !== $key ? sprintf('%s: %s', $key, $value) : $value;
        }

        $preferences = [
            'scheme' => 'B',
            'input-charset' => 'UTF-8',
            'output-charset' => 'UTF-8',
            'line-length' => 76,      // Максимальная длина строки по RFC
            'line-break-chars' => "\r\n",
        ];

        $encoded = iconv_mime_encode('' === $key ? self::TEMPORARY_KEY_NAME : $key, $value, $preferences);
        if ('' === $key && str_contains($encoded, ' ')) {
            return substr($encoded, strpos($encoded, ' ') + 1);
        }

        return $encoded;
    }
}
