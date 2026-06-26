<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Session;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Session\Exceptions\SessionException;
use Vasoft\Joke\Session\SessionCollection;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Session\SessionCollection
 */
final class SessionCollectionTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSaveNotModified(): void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $session = new SessionCollection(['foo' => 'bar']);
        $session->save();
        self::assertArrayNotHasKey('foo', $_SESSION);
    }

    #[RunInSeparateProcess]
    public function testSave(): void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $session = new SessionCollection(['foo' => 'bar']);
        $session->set('foo', 'bar');
        $session->save();
        self::assertArrayHasKey('foo', $_SESSION);
    }

    #[RunInSeparateProcess]
    public function testSaveWhenReset(): void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $session = new SessionCollection(['foo' => 'bar']);
        $session->reset(['foo' => 'bar']);
        $session->save();
        self::assertArrayHasKey('foo', $_SESSION);
    }

    #[RunInSeparateProcess]
    public function testReadonlyMode(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }
        $session = new SessionCollection(['foo' => 'bar']);
        $session->reset(['foo' => 'bar']);

        self::expectException(SessionException::class);
        self::expectExceptionMessageIs('Readonly session mode. Can\'t write');
        $session->save();
    }

    #[RunInSeparateProcess]
    public function testLoadReadonlyMode(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }
        $session = new SessionCollection([]);

        self::expectException(SessionException::class);
        self::expectExceptionMessageIs('Readonly session mode. Can\'t write');
        $session->load();
    }

    #[RunInSeparateProcess]
    public function testUnset(): void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $session = new SessionCollection(['foo' => 'bar']);
        $session->reset(['foo' => 'bar']);
        $session->save();
        self::assertArrayHasKey('foo', $_SESSION);
        $session->unset('foo');
        $session->save();
        self::assertArrayNotHasKey('foo', $_SESSION);
    }

    #[RunInSeparateProcess]
    public function testUnsetAndSet(): void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $session = new SessionCollection(['foo' => 'bar']);
        $session->unset('foo');
        $session->set('foo', 'bar1');
        $session->save();
        self::assertArrayHasKey('foo', $_SESSION);
    }

    #[RunInSeparateProcess]
    public function testUnsetAndReset(): void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $session = new SessionCollection(['foo' => 'bar']);
        $session->unset('foo');
        $session->reset(['foo' => 'bar']);
        $session->save();
        self::assertArrayHasKey('foo', $_SESSION);
    }
}
