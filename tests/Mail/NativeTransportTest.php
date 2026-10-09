<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Mail;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Collections\HeadersCollection;
use Vasoft\Joke\Mail\Address;
use Vasoft\Joke\Mail\Email;
use Vasoft\Joke\Mail\MailException;
use Vasoft\Joke\Mail\NativeTransport;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Mail\NativeTransport
 */
#[TestDox('NativeTransport - простой транспорт для отправки почты')]
#[CoversClass(NativeTransport::class)]
final class NativeTransportTest extends TestCase
{
    use PHPMock;

    #[TestDox('Добавляет необходимые заголовки, в параметр to - только email, plain, тело base64')]
    public function testSendPlain(): void
    {
        $from = new Address('user1@example.com', 'Company "Root"');
        $to = [new Address('user3@example.com', 'Sys Admin')];
        $body = 'Example';
        $expectedBody = "RXhhbXBsZQ==\r\n";
        $expectedSubject = $subject = 'Subject';
        $headers = new HeadersCollection(['C-Custom1' => 'Value 1']);

        $expectedTo = 'user3@example.com';
        $expectedHeaders = "c-custom1: Value 1\r\n"
            . "content-type: text/plain; charset=UTF-8\r\n"
            . "mime-version: 1.0\r\n"
            . "from: \"Company \\\"Root\\\"\" <user1@example.com>\r\n"
            . "to: Sys Admin <user3@example.com>\r\n"
            . "subject: Subject\r\n"
            . 'content-transfer-encoding: base64';

        $mail = self::getFunctionMock('Vasoft\Joke\Mail', 'mail');
        $mail->expects(self::once())
            ->with($expectedTo, $expectedSubject, $expectedBody, $expectedHeaders)
            ->willReturn(true);

        $mail = new Email($from, $to, $subject, $body, headers: $headers);
        new NativeTransport()->send($mail);
    }

    #[TestDox('Html имеет приоритет перед plain, кодирует тему')]
    public function testSendHtmlAndEncodeSubject(): void
    {
        $from = new Address('user1@example.com', 'Root');
        $to = [new Address('user3@example.com', 'Sys Admin')];
        $body = 'html';
        $expectedBody = "aHRtbA==\r\n";
        $expectedSubject = '=?UTF-8?B?0JfQsNC60L7QtNC40YDQvtCy0LDQvdCw?=';
        $subject = 'Закодирована';
        $expectedTo = 'user3@example.com';
        $expectedHeaders = "content-type: text/html; charset=UTF-8\r\n"
            . "mime-version: 1.0\r\n"
            . "from: Root <user1@example.com>\r\n"
            . "to: Sys Admin <user3@example.com>\r\n"
            . 'subject: ' . $expectedSubject . "\r\n"
            . 'content-transfer-encoding: base64';

        $mail = self::getFunctionMock('Vasoft\Joke\Mail', 'mail');
        $mail->expects(self::once())
            ->with($expectedTo, $expectedSubject, $expectedBody, $expectedHeaders)
            ->willReturn(true);

        $mail = new Email($from, $to, $subject, 'plain text', $body);
        new NativeTransport()->send($mail);
    }

    #[TestDox('Имя автора кодируется при необходимости')]
    public function testSendEncodeFromName(): void
    {
        $from = new Address('user1@example.com', 'Директор');
        $to = [new Address('user3@example.com', 'Sys Admin')];
        $body = 'plain';
        $expectedBody = "cGxhaW4=\r\n";
        $subject = $expectedSubject = 'Subject';
        $expectedTo = 'user3@example.com';
        $expectedHeaders = "content-type: text/plain; charset=UTF-8\r\n"
            . "mime-version: 1.0\r\n"
            . "from: =?UTF-8?B?0JTQuNGA0LXQutGC0L7RgA==?= <user1@example.com>\r\n"
            . "to: Sys Admin <user3@example.com>\r\n"
            . 'subject: ' . $subject . "\r\n"
            . 'content-transfer-encoding: base64';

        $mail = self::getFunctionMock('Vasoft\Joke\Mail', 'mail');
        $mail->expects(self::once())
            ->with($expectedTo, $expectedSubject, $expectedBody, $expectedHeaders)
            ->willReturn(true);

        $mail = new Email($from, $to, $subject, $body);
        new NativeTransport()->send($mail);
    }

    #[TestDox('Кодирует имена адресатов и значения заголовков при необходимости')]
    public function testSendEncodeToName(): void
    {
        $from = new Address('user1@example.com', 'Директор');
        $to = [
            new Address('user3@example.com', 'Sys Admin'),
            new Address('user4@example.com', 'Сторож'),
            new Address('user5@example.com'),
        ];
        $headers = new HeadersCollection([
            'C-Custom1' => 'Value 1',
            'C-Custom2' => 'яблоко',
            'C-Custom3' => '',
        ]);
        $body = 'plain';
        $expectedBody = "cGxhaW4=\r\n";
        $subject = $expectedSubject = 'Subject';
        $expectedTo = 'user3@example.com, user4@example.com, user5@example.com';
        $expectedHeaders = "c-custom1: Value 1\r\n"
            . "c-custom2: =?UTF-8?B?0Y/QsdC70L7QutC+?=\r\n"
            . "content-type: text/plain; charset=UTF-8\r\n"
            . "mime-version: 1.0\r\n"
            . "from: =?UTF-8?B?0JTQuNGA0LXQutGC0L7RgA==?= <user1@example.com>\r\n"
            . "to: Sys Admin <user3@example.com>, =?UTF-8?B?0KHRgtC+0YDQvtC2?= <user4@example.com>, user5@example.com\r\n"
            . 'subject: ' . $subject . "\r\n"
            . 'content-transfer-encoding: base64';

        $mail = self::getFunctionMock('Vasoft\Joke\Mail', 'mail');
        $mail->expects(self::once())
            ->with($expectedTo, $expectedSubject, $expectedBody, $expectedHeaders)
            ->willReturn(true);

        $mail = new Email($from, $to, $subject, $body, headers: $headers);
        new NativeTransport()->send($mail);
    }

    #[TestDox('Исключение при неправильном заголовке, оборачивает в MailException')]
    public function testExceptionOnWrongHeader(): void
    {
        $from = new Address('user1@example.com');
        $to = [new Address('user3@example.com')];
        $headers = new HeadersCollection(['Недопустимый' => 'Value 1']);
        $body = 'plain';
        $subject = 'Subject';

        $mail = self::getFunctionMock('Vasoft\Joke\Mail', 'mail');
        $mail->expects(self::never());

        $mail = new Email($from, $to, $subject, $body, headers: $headers);

        self::expectException(MailException::class);
        self::expectExceptionMessageIs('Invalid header name "Недопустимый": contains invalid characters.');

        new NativeTransport()->send($mail);
    }

    #[TestDox('Бросает исключение при ошибке отправки письма')]
    public function testSendException(): void
    {
        $from = new Address('user1@example.com');
        $to = [new Address('user3@example.com')];
        $body = 'plain';
        $expectedBody = "cGxhaW4=\r\n";
        $subject = $expectedSubject = 'Subject';
        $expectedTo = 'user3@example.com';
        $expectedHeaders = "content-type: text/plain; charset=UTF-8\r\n"
            . "mime-version: 1.0\r\n"
            . "from: user1@example.com\r\n"
            . "to: user3@example.com\r\n"
            . 'subject: ' . $subject . "\r\n"
            . 'content-transfer-encoding: base64';

        $mail = self::getFunctionMock('Vasoft\Joke\Mail', 'mail');
        $mail->expects(self::once())
            ->with($expectedTo, $expectedSubject, $expectedBody, $expectedHeaders)
            ->willReturn(false);

        $mail = new Email($from, $to, $subject, $body);
        self::expectException(MailException::class);
        self::expectExceptionMessageIs('Failed to send email via native mail().');

        new NativeTransport()->send($mail);
    }
}
