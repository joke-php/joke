<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Http\Csrf;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Csrf\CsrfConfig;
use Vasoft\Joke\Http\Csrf\CsrfMiddleware;
use Vasoft\Joke\Http\Csrf\CsrfTokenManager;
use Vasoft\Joke\Http\Csrf\CsrfTransportMode;
use Vasoft\Joke\Http\HttpRequest;
use Vasoft\Joke\Http\Response\ResponseBuilder;
use Vasoft\Joke\Logging\LogLevel;
use Vasoft\Joke\Middleware\Exceptions\CsrfMismatchException;
use Vasoft\Joke\Tests\Fixtures\Logger\FakeLogger;

/**
 * @internal
 *
 * @coversDefaultClass \Vasoft\Joke\Http\Csrf\CsrfTokenManager
 */
final class CsrfTokenManagerTest extends TestCase
{
    use PHPMock;

    private CsrfTokenManager $tokenManager;
    private HttpRequest $getRequest;
    private static ServiceContainer $container;
    private FakeLogger $logger;

    public static function setUpBeforeClass(): void
    {
        self::$container = new ServiceContainer();
        self::$container->registerSingleton(CookieConfig::class, CookieConfig::class);
    }

    protected function setUp(): void
    {
        $this->logger = new FakeLogger();
        $this->tokenManager = new CsrfTokenManager(new CsrfConfig(), $this->logger);
        $this->getRequest = new HttpRequest(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/csrf']);
    }

    public function testSaveSessionToken(): void
    {
        self::assertNull($this->getRequest->session->get(CsrfTokenManager::CSRF_TOKEN_NAME));
        $token = $this->tokenManager->validate($this->getRequest);
        self::assertNotNull($this->getRequest->session->get(CsrfTokenManager::CSRF_TOKEN_NAME));
        self::assertNotEmpty($this->getRequest->session->get(CsrfTokenManager::CSRF_TOKEN_NAME));
        self::assertSame(64, strlen($token));
    }

    public function testTokenInHeader(): void
    {
        $expectToken = $this->tokenManager->validate($this->getRequest);
        $request = new HttpRequest(
            cookies: [CsrfTokenManager::CSRF_TOKEN_COOKIE => 'wrongCookie'],
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/csrf',
                'HTTP_' . str_replace('-', '_', strtoupper(CsrfTokenManager::CSRF_TOKEN_HEADER)) => $expectToken,
            ],
        );
        $request->session->set(CsrfTokenManager::CSRF_TOKEN_NAME, $expectToken);
        $token = $this->tokenManager->validate($request);
        self::assertSame($expectToken, $token);
    }

    public function testTokenInPost(): void
    {
        $expectToken = $this->tokenManager->validate($this->getRequest);
        $request = new HttpRequest(
            post: [CsrfMiddleware::CSRF_TOKEN_NAME => $expectToken],
            cookies: [CsrfTokenManager::CSRF_TOKEN_COOKIE => 'wrongCookie'],
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/csrf',
                'HTTP_' . str_replace('-', '_', strtoupper(CsrfTokenManager::CSRF_TOKEN_HEADER)) => 'wrongHeader',
            ],
        );
        $request->session->set(CsrfTokenManager::CSRF_TOKEN_NAME, $expectToken);
        $token = $this->tokenManager->validate($request);
        self::assertSame($expectToken, $token);
    }

    public function testEmptyClientToken(): void
    {
        $expectToken = $this->tokenManager->validate($this->getRequest);
        $request = new HttpRequest(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/csrf']);
        $request->session->set(CsrfTokenManager::CSRF_TOKEN_NAME, $expectToken);
        self::expectException(CsrfMismatchException::class);
        self::expectExceptionMessageIs('CSRF token mismatch');
        $this->tokenManager->validate($request);
    }

    public function testWrongClientToken(): void
    {
        $expectToken = $this->tokenManager->validate($this->getRequest);
        $request = new HttpRequest(
            get: [CsrfMiddleware::CSRF_TOKEN_NAME => 'wrongToken'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/csrf'],
        );
        $request->session->set(CsrfTokenManager::CSRF_TOKEN_NAME, $expectToken);
        self::expectException(CsrfMismatchException::class);
        self::expectExceptionMessageIs('CSRF token mismatch');
        $this->tokenManager->validate($request);
    }

    #[DataProvider('provideSafeMethodsCases')]
    public function testSafeMethods(string $method): void
    {
        $expectToken = $this->tokenManager->validate($this->getRequest);
        $request = new HttpRequest(
            get: [CsrfMiddleware::CSRF_TOKEN_NAME => 'wrongToken'],
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/csrf'],
        );
        $request->session->set(CsrfTokenManager::CSRF_TOKEN_NAME, $expectToken);
        $token = $this->tokenManager->validate($request);
        self::assertSame($expectToken, $token);
    }

    public static function provideSafeMethodsCases(): iterable
    {
        yield ['GET'];
        yield ['HEAD'];
    }

    public function testReset(): void
    {
        $oldToken = $this->tokenManager->validate($this->getRequest);
        $response = new ResponseBuilder(new ApplicationConfig(), self::$container)->makeDefault();
        $newToken = $this->tokenManager->reset($this->getRequest, $response);
        $headers = $response->headers->getAll();
        self::assertNotSame($oldToken, $newToken);
        self::assertSame($newToken, $this->getRequest->session->get(CsrfTokenManager::CSRF_TOKEN_NAME, ''));
        self::assertArrayHasKey('X-Csrf-Token', $headers);
        self::assertSame($newToken, $headers['X-Csrf-Token']);
        self::assertSame(64, strlen($newToken));
    }

    public function testInvalidate(): void
    {
        $oldToken = $this->tokenManager->validate($this->getRequest);
        $response = new ResponseBuilder(new ApplicationConfig(), self::$container)->makeDefault();
        $this->tokenManager->invalidate($this->getRequest, $response);
        $headers = $response->headers->getAll();
        self::assertNotSame($oldToken, $this->getRequest->session->get(CsrfTokenManager::CSRF_TOKEN_NAME, ''));
        self::assertArrayHasKey('X-Csrf-Token', $headers);
        self::assertNotSame($oldToken, $headers['X-Csrf-Token']);
    }

    public function testAttach(): void
    {
        $token = $this->tokenManager->validate($this->getRequest);
        $response = new ResponseBuilder(new ApplicationConfig(), self::$container)->makeDefault();
        $this->tokenManager->attach($this->getRequest, $response);

        $headers = $response->headers->getAll();
        self::assertArrayHasKey('X-Csrf-Token', $headers);
        self::assertSame($token, $headers['X-Csrf-Token']);
    }

    public function testAttachInCookie(): void
    {
        $tokenManager = new CsrfTokenManager(
            new CsrfConfig()->setTransportMode(CsrfTransportMode::COOKIE),
            $this->logger,
        );

        $token = $tokenManager->validate($this->getRequest);
        $response = new ResponseBuilder(new ApplicationConfig(), self::$container)->makeDefault();
        $tokenManager->attach($this->getRequest, $response);

        $hasCookie = false;
        foreach ($response->cookies as $cookie) {
            if ('XSRF-TOKEN' === $cookie->name && $cookie->value === $token
                && !$cookie->httpOnly) {
                $hasCookie = true;
            }
        }
        self::assertTrue($hasCookie);
    }

    #[RunInSeparateProcess]
    public function testErrorRandomLevel3(): void
    {
        $mockRandomBytes = self::getFunctionMock('Vasoft\Joke\Http\Csrf', 'random_bytes');
        $mockRandomBytes->expects(self::exactly(2))->willThrowException(new RandomException('Test Error 1'));
        $mockFunctionExists = self::getFunctionMock('Vasoft\Joke\Http\Csrf', 'function_exists');
        $mockFunctionExists->expects(self::exactly(2))->willReturn(false);

        $token1 = $this->tokenManager->validate($this->getRequest);
        $this->getRequest->session->set(CsrfTokenManager::CSRF_TOKEN_NAME, '');
        $token2 = $this->tokenManager->validate($this->getRequest);
        self::assertNotSame($token1, $token2);
        $logs = $this->logger->getRecords();
        self::assertCount(4, $logs);
        self::assertSame('CSRF token generation: Using alternative CSPRNG source: Test Error 1', $logs[0]['message']);
        self::assertSame(LogLevel::INFO, $logs[0]['level']);
        self::assertSame(
            'CSRF token generation: Primary CSPRNG unavailable, using alternative source (random_int)',
            $logs[1]['message'],
        );
        self::assertSame(64, strlen($token1));
        self::assertSame(LogLevel::WARNING, $logs[1]['level']);
    }

    #[RunInSeparateProcess]
    public function testErrorRandomLevel2(): void
    {
        $mockRandomBytes = self::getFunctionMock('Vasoft\Joke\Http\Csrf', 'random_bytes');
        $mockRandomBytes->expects(self::exactly(2))->willThrowException(new RandomException('Test Error 1'));
        $mockFunctionExists = self::getFunctionMock('Vasoft\Joke\Http\Csrf', 'function_exists');
        $mockFunctionExists->expects(self::exactly(2))->willReturn(true);
        $mockOpenSslRandom = self::getFunctionMock('Vasoft\Joke\Http\Csrf', 'openssl_random_pseudo_bytes');
        $mockOpenSslRandom->expects(self::exactly(2))->willReturnCallback(
            static function (int $length, ?bool &$crypto_strong = null) {
                static $count = 0;
                ++$count;
                $crypto_strong = true;

                return 'token ' . $count;
            },
        );

        $token1 = $this->tokenManager->validate($this->getRequest);
        $this->getRequest->session->set(CsrfTokenManager::CSRF_TOKEN_NAME, '');
        $token2 = $this->tokenManager->validate($this->getRequest);
        self::assertNotSame($token1, $token2);
        $logs = $this->logger->getRecords();
        self::assertCount(2, $logs);
        self::assertSame('CSRF token generation: Using alternative CSPRNG source: Test Error 1', $logs[0]['message']);
        self::assertSame('CSRF token generation: Using alternative CSPRNG source: Test Error 1', $logs[1]['message']);
    }
}
