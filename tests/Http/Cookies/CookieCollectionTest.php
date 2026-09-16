<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Cookies;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Vasoft\Joke\Http\Cookies\Cookie;
use Vasoft\Joke\Http\Cookies\CookieCollection;
use PHPUnit\Framework\TestCase;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Cookies\SameSiteOption;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Cookies\CookieCollection
 */
final class CookieCollectionTest extends TestCase
{
    private CookieConfig $config;
    private CookieCollection $collection;

    protected function setUp(): void
    {
        $this->config = new CookieConfig()
            ->setLifetime(3600)
            ->setPath('/')
            ->setDomain(null)
            ->setSecure(true)
            ->setHttpOnly(true)
            ->setSameSite(SameSiteOption::Lax);

        $this->collection = new CookieCollection($this->config, true);
    }

    #[TestDox('Должен при автоопределении защищенного соединения правильно устанавливать secure')]
    #[DataProvider('provideAutoDetectSecureCases')]
    public function testAutoDetectSecure(bool $requestSecureMode): void
    {
        $config = new CookieConfig();
        $config->setSecure(null);
        $collection = new CookieCollection($config, $requestSecureMode);
        $collection->add('session_id', 'abc123');
        $cookies = iterator_to_array($collection);
        self::assertSame($requestSecureMode, $cookies['session_id##/']->secure);
    }

    public static function provideAutoDetectSecureCases(): iterable
    {
        yield 'secure' => [true];
        yield 'not secure' => [false];
    }

    public function testAddWithDefaultConfig(): void
    {
        $this->collection->add('session_id', 'abc123');

        $cookies = iterator_to_array($this->collection);

        self::assertCount(1, $cookies);
        self::assertArrayHasKey('session_id##/', $cookies);

        $cookie = $cookies['session_id##/'];
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertSame('session_id', $cookie->name);
        self::assertSame('abc123', $cookie->value);
        self::assertSame(3600, $cookie->lifetime);
        self::assertSame('/', $cookie->path);
        self::assertNull($cookie->domain);
        self::assertTrue($cookie->secure);
        self::assertTrue($cookie->httpOnly);
        self::assertSame(SameSiteOption::Lax, $cookie->sameSite);
    }

    public function testAddWithOverrideParameters(): void
    {
        $this->collection->add(
            'custom_cookie',
            'value',
            lifetime: 7200,
            path: '/admin',
            domain: 'example.com',
            secure: false,
            httpOnly: false,
            sameSite: SameSiteOption::None,
        );

        $cookies = iterator_to_array($this->collection);
        self::assertArrayHasKey('custom_cookie#example.com#/admin', $cookies);

        $cookie = $cookies['custom_cookie#example.com#/admin'];
        self::assertSame(7200, $cookie->lifetime);
        self::assertSame('/admin', $cookie->path);
        self::assertSame('example.com', $cookie->domain);
        self::assertFalse($cookie->secure);
        self::assertFalse($cookie->httpOnly);
        self::assertSame(SameSiteOption::None, $cookie->sameSite);
    }

    public function testUniqueKeysByDomainAndPath(): void
    {
        $this->collection->add('token', 'val1');
        $this->collection->add('token', 'val2', domain: 'example.com');
        $this->collection->add('token', 'val3', path: '/api');

        $cookies = iterator_to_array($this->collection);

        self::assertCount(3, $cookies);

        self::assertArrayHasKey('token##/', $cookies);
        self::assertArrayHasKey('token#example.com#/', $cookies);
        self::assertArrayHasKey('token##/api', $cookies);

        self::assertSame('val1', $cookies['token##/']->value);
        self::assertSame('val2', $cookies['token#example.com#/']->value);
        self::assertSame('val3', $cookies['token##/api']->value);
    }

    public function testAddOverwritesExistingCookie(): void
    {
        $this->collection->add('user', 'old_value');
        self::assertSame('old_value', iterator_to_array($this->collection)['user##/']->value);

        $this->collection->add('user', 'new_value');

        $cookies = iterator_to_array($this->collection);
        self::assertCount(1, $cookies);
        self::assertSame('new_value', $cookies['user##/']->value);
    }

    public function testRemoveWithExplicitParameters(): void
    {
        $this->collection->add('temp', 'data', domain: 'test.com', path: '/tmp');

        $key = 'temp#test.com#/tmp';
        self::assertArrayHasKey($key, iterator_to_array($this->collection));

        $this->collection->remove('temp', 'test.com', '/tmp');

        $cookies = iterator_to_array($this->collection);
        self::assertArrayHasKey($key, $cookies);

        $removedCookie = $cookies[$key];
        self::assertSame('', $removedCookie->value);
        self::assertSame(0, $removedCookie->lifetime);
    }

    public function testRemoveUsesConfigDefaults(): void
    {
        $this->collection->add('session', 'xyz');
        $key = 'session##/';
        self::assertArrayHasKey($key, iterator_to_array($this->collection));

        $this->collection->remove('session');

        $cookies = iterator_to_array($this->collection);
        self::assertArrayHasKey($key, $cookies);

        $removedCookie = $cookies[$key];
        self::assertSame('', $removedCookie->value);
        self::assertSame(0, $removedCookie->lifetime);
    }

    public function testRemoveDoesNotAffectOtherCookiesWithSameName(): void
    {
        $this->collection->add('multi', 'v1', domain: 'a.com');
        $this->collection->add('multi', 'v2', domain: 'b.com');

        $this->collection->remove('multi', 'a.com');

        $cookies = iterator_to_array($this->collection);

        self::assertSame('', $cookies['multi#a.com#/']->value);
        self::assertSame('v2', $cookies['multi#b.com#/']->value);
    }

    public function testIteratorReturnsTraversable(): void
    {
        $this->collection->add('c1', 'v1');
        $this->collection->add('c2', 'v2', domain: 'ex.com');

        $count = 0;
        foreach ($this->collection as $key => $cookie) {
            ++$count;
            self::assertInstanceOf(Cookie::class, $cookie);
            self::assertIsString($key);
        }

        self::assertSame(2, $count);
    }
}
