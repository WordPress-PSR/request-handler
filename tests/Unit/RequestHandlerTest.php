<?php

declare(strict_types=1);

namespace WordPressPsr\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WordPressPsr\Headers;
use WordPressPsr\RequestHandler;

/**
 * Unit tests for RequestHandler that do not require a WordPress environment.
 *
 * Tests cover:
 * - Constructor wiring
 * - set_globals: populates $_SERVER, $_GET, $_POST, $_COOKIE, $_REQUEST
 * - ENDPOINT_OVERRIDES constant
 * - wp_special_endpoints / wp_admin_endpoints_to_bootstrap maps
 */
class RequestHandlerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;

    protected function setUp(): void
    {
        $factory               = new Psr17Factory();
        $this->responseFactory = $factory;
        $this->streamFactory   = $factory;

        Headers::reset();
    }

    protected function tearDown(): void
    {
        Headers::reset();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a testable subclass that exposes protected methods.
     */
    private function makeHandler(string $wordpressPath = '/tmp/fake-wp'): RequestHandlerTestable
    {
        return new RequestHandlerTestable($wordpressPath, $this->responseFactory, $this->streamFactory);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorSetsWordpressPath(): void
    {
        $handler = $this->makeHandler('/var/www/wordpress');

        $this->assertSame('/var/www/wordpress', $handler->getWordpressPath());
    }

    public function testConstructorSetsBootstrappedFalse(): void
    {
        $handler = $this->makeHandler();

        $this->assertFalse($handler->isBootstrapped());
    }

    // -------------------------------------------------------------------------
    // ENDPOINT_OVERRIDES constant
    // -------------------------------------------------------------------------

    public function testEndpointOverridesContainsLoadScripts(): void
    {
        $this->assertArrayHasKey('/wp-admin/load-scripts.php', RequestHandler::ENDPOINT_OVERRIDES);
    }

    public function testEndpointOverridesContainsLoadStyles(): void
    {
        $this->assertArrayHasKey('/wp-admin/load-styles.php', RequestHandler::ENDPOINT_OVERRIDES);
    }

    public function testEndpointOverridesPointToExistingFiles(): void
    {
        foreach (RequestHandler::ENDPOINT_OVERRIDES as $endpoint => $file) {
            $this->assertFileExists($file, "Override file for $endpoint does not exist: $file");
        }
    }

    // -------------------------------------------------------------------------
    // set_globals
    // -------------------------------------------------------------------------

    public function testSetGlobalsPopulatesServerParams(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest('GET', 'http://example.com/test', [], null, '1.1', [
            'REQUEST_URI' => '/test',
            'HTTP_HOST'   => 'example.com',
        ]);

        $handler->exposeSetGlobals($request);

        $this->assertSame('/test', $_SERVER['REQUEST_URI']);
    }

    public function testSetGlobalsPopulatesGetParams(): void
    {
        $handler = $this->makeHandler();
        $request = (new ServerRequest('GET', 'http://example.com/?foo=bar'))
            ->withQueryParams(['foo' => 'bar']);

        $handler->exposeSetGlobals($request);

        $this->assertSame('bar', $_GET['foo']);
    }

    public function testSetGlobalsPopulatesPostParams(): void
    {
        $handler = $this->makeHandler();
        $request = (new ServerRequest('POST', 'http://example.com/'))
            ->withParsedBody(['name' => 'Alice']);

        $handler->exposeSetGlobals($request);

        $this->assertSame('Alice', $_POST['name']);
    }

    public function testSetGlobalsPopulatesCookieParams(): void
    {
        $handler = $this->makeHandler();
        $request = (new ServerRequest('GET', 'http://example.com/'))
            ->withCookieParams(['session' => 'xyz']);

        $handler->exposeSetGlobals($request);

        $this->assertSame('xyz', $_COOKIE['session']);
    }

    public function testSetGlobalsPopulatesRequestFromCookiePostGet(): void
    {
        $handler = $this->makeHandler();
        $request = (new ServerRequest('POST', 'http://example.com/?q=search'))
            ->withQueryParams(['q' => 'search'])
            ->withParsedBody(['action' => 'submit'])
            ->withCookieParams(['token' => 'abc']);

        $handler->exposeSetGlobals($request);

        // $_REQUEST = $_COOKIE + $_POST + $_GET (later keys win)
        $this->assertSame('search', $_REQUEST['q']);
        $this->assertSame('submit', $_REQUEST['action']);
        $this->assertSame('abc', $_REQUEST['token']);
    }

    public function testSetGlobalsSetsPHPSelf(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest('GET', 'http://example.com/my-page?foo=bar', [], null, '1.1', [
            'REQUEST_URI' => '/my-page?foo=bar',
        ]);

        $handler->exposeSetGlobals($request);

        $this->assertSame('/my-page', $_SERVER['PHP_SELF']);
    }

    public function testSetGlobalsSetsScriptNameEqualToPHPSelf(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest('GET', 'http://example.com/page', [], null, '1.1', [
            'REQUEST_URI' => '/page',
        ]);

        $handler->exposeSetGlobals($request);

        $this->assertSame($_SERVER['PHP_SELF'], $_SERVER['SCRIPT_NAME']);
    }

    public function testSetGlobalsConvertsHeadersToHttpServerVars(): void
    {
        $handler = $this->makeHandler();
        $request = (new ServerRequest('GET', 'http://example.com/'))
            ->withHeader('X-Custom-Header', 'test-value');

        $handler->exposeSetGlobals($request);

        $this->assertSame('test-value', $_SERVER['HTTP_X_CUSTOM_HEADER']);
    }

    // -------------------------------------------------------------------------
    // Admin endpoint maps
    // -------------------------------------------------------------------------

    public function testWpAdminEndpointsMapContainsIndexPhp(): void
    {
        $handler   = $this->makeHandler();
        $endpoints = $handler->getWpAdminEndpoints();

        $this->assertArrayHasKey('/wp-admin/index.php', $endpoints);
        $this->assertSame('/wp-admin/admin.php', $endpoints['/wp-admin/index.php']);
    }

    public function testWpSpecialEndpointsContainsWpLogin(): void
    {
        $handler   = $this->makeHandler();
        $endpoints = $handler->getWpSpecialEndpoints();

        $this->assertArrayHasKey('/wp-login.php', $endpoints);
    }

    public function testWpSpecialEndpointsContainsAdminAjax(): void
    {
        $handler   = $this->makeHandler();
        $endpoints = $handler->getWpSpecialEndpoints();

        $this->assertArrayHasKey('/wp-admin/admin-ajax.php', $endpoints);
    }
}

/**
 * Testable subclass that exposes protected members for unit testing.
 */
class RequestHandlerTestable extends RequestHandler
{
    public function getWordpressPath(): string
    {
        return $this->wordpress_path;
    }

    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    public function exposeSetGlobals(\Psr\Http\Message\ServerRequestInterface $request): void
    {
        $this->set_globals($request);
    }

    public function getWpAdminEndpoints(): array
    {
        return $this->wp_admin_endpoints_to_bootstrap;
    }

    public function getWpSpecialEndpoints(): array
    {
        return $this->wp_special_endpoints;
    }
}
