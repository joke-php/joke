<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Response;

use Vasoft\Joke\Routing\Exceptions\NotFoundException;
use Vasoft\Joke\Tests\Fixtures\Http\Response\DummyFileResponse;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Response\BinaryResponse
 */
final class BinaryResponseTest extends TestCase
{
    public function testEmpty(): void
    {
        $instance = new DummyFileResponse();
        ob_start();
        $instance->send();
        ob_end_clean();
        self::assertSame(
            [
                'Content-Type' => 'application/test',
                'Content-Length' => 0,
                'Content-Disposition' => 'attachment; filename=""',
            ],
            $instance->sentHeaders,
        );
        self::assertSame('', $instance->getBodyAsString());
    }

    public function testSentFromFile(): void
    {
        $length = random_int(22, 256);
        $baseName = 'test' . $length . '.joke';
        $tempFile = dirname(__DIR__, 2) . '/Fixtures/cache/' . $baseName;

        file_put_contents($tempFile, str_repeat('*', $length));

        try {
            $instance = new DummyFileResponse();
            $instance->load($tempFile);
            ob_start();
            $instance->send();
            ob_end_clean();
            self::assertSame(
                [
                    'Content-Type' => 'application/test',
                    'Content-Length' => $length,
                    'Content-Disposition' => 'attachment; filename="' . $baseName . '"',
                ],
                $instance->sentHeaders,
            );
        } finally {
            unlink($tempFile);
        }
    }

    public function testSentFromFileWithCustomName(): void
    {
        $length = random_int(22, 256);
        $baseName = 'test' . $length . '.joke';
        $tempFile = dirname(__DIR__, 2) . '/Fixtures/cache/' . $baseName;

        file_put_contents($tempFile, str_repeat('*', $length));

        try {
            $instance = new DummyFileResponse();
            $instance->load($tempFile);
            $instance->filename = '/example/base.pdf';
            ob_start();
            $instance->send();
            ob_end_clean();
            self::assertSame(
                [
                    'Content-Type' => 'application/test',
                    'Content-Length' => $length,
                    'Content-Disposition' => 'attachment; filename="base.pdf"',
                ],
                $instance->sentHeaders,
            );
        } finally {
            unlink($tempFile);
        }
    }

    public function testCustomRewriteLoadedContent(): void
    {
        $length = random_int(22, 256);
        $baseName = 'test' . $length . '.joke';
        $tempFile = dirname(__DIR__, 2) . '/Fixtures/cache/' . $baseName;

        file_put_contents($tempFile, str_repeat('*', $length));

        try {
            $instance = new DummyFileResponse();
            $instance->load($tempFile);
            $instance->setBody('test');
            ob_start();
            $instance->send();
            ob_end_clean();
            self::assertSame(
                [
                    'Content-Type' => 'application/test',
                    'Content-Length' => 4,
                    'Content-Disposition' => 'attachment; filename="' . $baseName . '"',
                ],
                $instance->sentHeaders,
            );
        } finally {
            unlink($tempFile);
        }
    }

    public function testFileNotfound(): void
    {
        $length = random_int(22, 256);
        $baseName = 'test' . $length . '.joke';
        $tempFile = dirname(__DIR__, 2) . '/Fixtures/cache/' . $baseName;

        $instance = new DummyFileResponse();
        self::expectException(NotFoundException::class);
        self::expectExceptionMessageIs('File not found');
        $instance->load($tempFile);
    }

    public function testCustomContent(): void
    {
        $instance = new DummyFileResponse();
        $instance->setBody('test');
        $instance->filename = 'test.pdf';
        ob_start();
        $instance->send();
        ob_end_clean();
        self::assertSame(
            [
                'Content-Type' => 'application/test',
                'Content-Length' => 4,
                'Content-Disposition' => 'attachment; filename="test.pdf"',
            ],
            $instance->sentHeaders,
        );
    }

    public function testBody(): void
    {
        $instance = new DummyFileResponse();
        $instance->setBody('test');
        self::assertSame($instance->getBodyAsString(), $instance->getBody());
    }
}
