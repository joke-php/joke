<?php

declare(strict_types=1);

namespace Vasoft\Joke\Mail;

use Vasoft\Joke\Contract\Mail\TransportInterface;
use Vasoft\Joke\Exceptions\JokeException;

/**
 * Транспорт для отправки почты через нативную функцию PHP mail().
 *
 * Реализует базовую логику формирования MIME-заголовков,
 * кодирования не-ASCII символов и безопасной отправки сообщений.
 */
class NativeTransport implements TransportInterface
{
    /**
     * Преобразует массив адресов в строку для использования в заголовках (To, Cc).
     *
     * @param list<Address> $values Список объектов адресов
     *
     * @return string Строка адресов, готовая для вставки в заголовок письма
     */
    protected function renderAddressesToValue(array $values): string
    {
        return implode(', ', array_map($this->renderAddress(...), $values));
    }
    /**
     * Форматирует одиночный адрес для использования в заголовках.
     *
     * Выполняет следующие проверки:
     * 1. Если имя содержит не-ASCII символы (кириллица и др.), оно кодируется в MIME-формат.
     * 2. Если имя содержит специальные символы RFC 2822 (скобки, кавычки и т.д.), оно берется в кавычки с экранированием.
     * 3. В остальных случаях имя используется как есть.
     *
     * @param Address $address Объект адреса
     *
     * @return string Отформатированная строка адреса (например: "Name" <email@example.com>)
     */
    protected function renderAddress(Address $address): string
    {
        if (null === $address->name) {
            return $address->email;
        }

        if (preg_match('/[\x80-\xFF]/', $address->name)) {
            return sprintf('%s <%s>', MimeConverter::encode($address->name), $address->email);
        }

        if (preg_match('/[()<>\[\]:;@\,."]/', $address->name)) {
            $quoted = '"' . str_replace(['\\', '"'], ['\\\\', '\"'], $address->name) . '"';

            return sprintf('%s <%s>', $quoted, $address->email);
        }

        return sprintf('%s <%s>', $address->name, $address->email);
    }

    /**
     * Извлекает только email-адреса из списка объектов Address.
     *
     * Используется для передачи в первый аргумент функции mail(),
     * который требует чистый список получателей без имен.
     *
     * @param list<Address> $values Список объектов адресов
     *
     * @return string Строка, содержащая только email-адреса, разделенные запятыми
     */
    protected function renderAddressesToEmailsString(array $values): string
    {
        return implode(
            ', ',
            array_map(static fn(Address $a) => $a->email, $values),
        );
    }

    /**
     * Отправляет электронное письмо.
     *
     * Процесс отправки включает:
     * 1. Гарантию наличия базовых MIME-заголовков.
     * 2. Форматирование адресов и темы.
     * 3. Санитизацию коллекции заголовков (удаление пустых, проверка на инъекции).
     * 4. Кодирование всех значений заголовков в MIME-формат при необходимости.
     * 5. Base64-кодирование тела письма.
     *
     * @param Email $email Объект письма для отправки
     *
     * @throws MailException если заголовки не прошли валидацию или функция mail() вернула ошибку
     */
    public function send(Email $email): void
    {
        $email->ensureBaseHeaders();
        $email->headers
            ->set('MIME-Version', '1.0')
            ->set('From', $this->renderAddress($email->from))
            ->set('To', $this->renderAddressesToValue($email->to))
            ->set('Subject', $email->subject)
            ->set('Content-Transfer-Encoding', 'base64');

        try {
            $email->headers->sanitize();
        } catch (JokeException $e) {
            throw new MailException($e->getMessage(), previous: $e);
        }

        $lines = [];
        foreach ($email->headers->getAll() as $name => $value) {
            $lines[] = MimeConverter::encode($value, $name);
        }

        $body = '' !== $email->html ? $email->html : $email->text;
        $body = chunk_split(base64_encode($body));


        $encodedSubject = MimeConverter::encode($email->subject);
        $toEmailsOnly = $this->renderAddressesToEmailsString($email->to);

        $result = mail($toEmailsOnly, $encodedSubject, $body, implode("\r\n", $lines));

        if (false === $result) {
            throw new MailException('Failed to send email via native mail().');
        }
    }
}
