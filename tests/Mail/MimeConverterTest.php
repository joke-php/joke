<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Mail;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Mail\MimeConverter;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Mail\MimeConverter
 */
#[TestDox('MimeConverter для преобразования строк')]
final class MimeConverterTest extends TestCase
{
    use PHPMock;

    private array $preferences = [
        'scheme' => 'B',
        'input-charset' => 'UTF-8',
        'output-charset' => 'UTF-8',
        'line-length' => 76,
        'line-break-chars' => "\r\n",
    ];

    #[TestDox('encode Простое значение без имени просто возвращается')]
    public function testEncodeSingleCharWithOutName(): void
    {
        $value = 'Test Subject';
        self::assertSame($value, MimeConverter::encode($value));
    }

    #[TestDox('encode Простое значение c именем формирует строку для заголовка')]
    public function testEncodeSingleCharWithName(): void
    {
        $value = 'Test Subject';
        $name = 'X-Custom';
        self::assertSame($name . ': ' . $value, MimeConverter::encode($value, $name));
    }

    #[TestDox('encode Значение требующее кодирования без имени просто возвращается')]
    #[RunInSeparateProcess]
    public function testEncodeRequireCharWithOutName(): void
    {
        $value = 'Яблоко';
        $encoded = 'Encoded value';

        $encode = self::getFunctionMock('Vasoft\Joke\Mail', 'iconv_mime_encode');
        $encode->expects(self::once())
            ->with(MimeConverter::TEMPORARY_KEY_NAME, $value, $this->preferences)
            ->willReturn(MimeConverter::TEMPORARY_KEY_NAME . ': ' . $encoded);


        self::assertSame($encoded, MimeConverter::encode($value));
    }

    #[TestDox('encode Значение требующее кодирования c именем формирует строку для заголовка')]
    #[RunInSeparateProcess]
    public function testEncodeRequireCharWithName(): void
    {
        $name = 'X-Custom';
        $value = 'Яблоко';
        $encodedValue = $name . ': Encoded value';

        $encode = self::getFunctionMock('Vasoft\Joke\Mail', 'iconv_mime_encode');
        $encode->expects(self::once())
            ->with($name, $value, $this->preferences)
            ->willReturn($encodedValue);

        self::assertSame($encodedValue, MimeConverter::encode($value, $name));
    }
}
