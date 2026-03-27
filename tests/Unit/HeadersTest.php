<?php

declare(strict_types=1);

namespace WordPressPsr\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WordPressPsr\Headers;

/**
 * Tests for the Headers instance class.
 *
 * Headers manages PSR-7 response headers, status codes, and cookies
 * without requiring a WordPress environment.
 *
 * After the coroutine-safety refactor, all state is held on a Headers
 * instance rather than static class properties.  The static registry
 * (setCurrent / getCurrent / clearCurrent) is tested separately.
 */
class HeadersTest extends TestCase
{
    private Headers $headers;

    protected function setUp(): void
    {
        $this->headers = new Headers();
        // Ensure the static registry is clean between tests.
        Headers::clearCurrent();
    }

    protected function tearDown(): void
    {
        Headers::clearCurrent();
    }

    // -------------------------------------------------------------------------
    // add_header
    // -------------------------------------------------------------------------

    public function testAddHeaderStoresHeader(): void
    {
        $this->headers->add_header('Content-Type: application/json');

        $this->assertSame(['Content-Type' => ' application/json'], $this->headers->get_headers());
    }

    public function testAddHeaderReplacesExistingByDefault(): void
    {
        $this->headers->add_header('Content-Type: text/html');
        $this->headers->add_header('Content-Type: application/json');

        $this->assertSame(' application/json', $this->headers->get_headers()['Content-Type']);
    }

    public function testAddHeaderDoesNotReplaceWhenReplaceFalse(): void
    {
        $this->headers->add_header('Content-Type: text/html');
        $this->headers->add_header('Content-Type: application/json', false);

        $this->assertSame(' text/html', $this->headers->get_headers()['Content-Type']);
    }

    public function testAddHeaderSetsStatusCodeWhenProvided(): void
    {
        $this->headers->add_header('X-Custom: value', true, 201);

        $this->assertSame(201, $this->headers->get_status_code());
    }

    public function testAddHeaderSetsRedirectStatusCodeForLocation(): void
    {
        $this->headers->add_header('location: https://example.com/new');

        $this->assertSame(302, $this->headers->get_status_code());
    }

    public function testAddHeaderDoesNotOverrideNon200StatusForLocation(): void
    {
        $this->headers->set_status_code(301);
        $this->headers->add_header('location: https://example.com/new');

        $this->assertSame(301, $this->headers->get_status_code());
    }

    public function testAddHeaderIgnoresInvalidHeaderName(): void
    {
        $this->headers->add_header('Invalid Header Name: value');

        $this->assertEmpty($this->headers->get_headers());
    }

    public function testAddHeaderIgnoresInvalidHeaderValue(): void
    {
        // Control characters are invalid in header values.
        $this->headers->add_header("X-Custom: value\x00with-null");

        $this->assertEmpty($this->headers->get_headers());
    }

    public function testAddMultipleDistinctHeaders(): void
    {
        $this->headers->add_header('Content-Type: text/html');
        $this->headers->add_header('X-Frame-Options: DENY');
        $this->headers->add_header('Cache-Control: no-cache');

        $headers = $this->headers->get_headers();
        $this->assertCount(3, $headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertArrayHasKey('Cache-Control', $headers);
    }

    // -------------------------------------------------------------------------
    // remove_header
    // -------------------------------------------------------------------------

    public function testRemoveHeaderDeletesExistingHeader(): void
    {
        $this->headers->add_header('X-Custom: value');
        $this->headers->remove_header('X-Custom');

        $this->assertArrayNotHasKey('X-Custom', $this->headers->get_headers());
    }

    public function testRemoveHeaderIsNoopForNonExistentHeader(): void
    {
        // Should not throw.
        $this->headers->remove_header('X-Does-Not-Exist');

        $this->assertEmpty($this->headers->get_headers());
    }

    // -------------------------------------------------------------------------
    // status code
    // -------------------------------------------------------------------------

    public function testDefaultStatusCodeIs200(): void
    {
        $this->assertSame(200, $this->headers->get_status_code());
    }

    public function testSetStatusCode(): void
    {
        $this->headers->set_status_code(404);

        $this->assertSame(404, $this->headers->get_status_code());
    }

    public function testSetStatusCodeCastsToInt(): void
    {
        $this->headers->set_status_code('500');

        $this->assertSame(500, $this->headers->get_status_code());
    }

    // -------------------------------------------------------------------------
    // cookies
    // -------------------------------------------------------------------------

    public function testSetCookieReturnsTrueOnSuccess(): void
    {
        $result = $this->headers->set_cookie('session', 'abc123');

        $this->assertTrue($result);
    }

    public function testSetCookieIsReflectedInGetCookies(): void
    {
        $this->headers->set_cookie('session', 'abc123');

        $cookies = $this->headers->get_cookies();
        $cookie  = $cookies->get('session');

        $this->assertNotNull($cookie);
        $this->assertSame('abc123', $cookie->getValue());
    }

    public function testSetMultipleCookies(): void
    {
        $this->headers->set_cookie('a', '1');
        $this->headers->set_cookie('b', '2');

        $cookies = $this->headers->get_cookies();
        $this->assertNotNull($cookies->get('a'));
        $this->assertNotNull($cookies->get('b'));
    }

    public function testGetCookiesReturnsEmptySetCookiesWhenNoneSet(): void
    {
        $cookies = $this->headers->get_cookies();

        $this->assertInstanceOf(\Dflydev\FigCookies\SetCookies::class, $cookies);
    }

    // -------------------------------------------------------------------------
    // reset
    // -------------------------------------------------------------------------

    public function testResetClearsHeaders(): void
    {
        $this->headers->add_header('X-Custom: value');
        $this->headers->reset();

        $this->assertEmpty($this->headers->get_headers());
    }

    public function testResetRestoresDefaultStatusCode(): void
    {
        $this->headers->set_status_code(500);
        $this->headers->reset();

        $this->assertSame(200, $this->headers->get_status_code());
    }

    public function testResetClearsCookies(): void
    {
        $this->headers->set_cookie('session', 'abc');
        $this->headers->reset();

        $cookies = $this->headers->get_cookies();
        $this->assertNull($cookies->get('session'));
    }

    // -------------------------------------------------------------------------
    // Instance isolation (coroutine-safety guarantee)
    // -------------------------------------------------------------------------

    public function testTwoInstancesDoNotShareState(): void
    {
        $a = new Headers();
        $b = new Headers();

        $a->add_header('X-Instance: A');
        $b->add_header('X-Instance: B');

        $this->assertSame(' A', $a->get_headers()['X-Instance']);
        $this->assertSame(' B', $b->get_headers()['X-Instance']);
    }

    public function testTwoInstancesDoNotShareStatusCode(): void
    {
        $a = new Headers();
        $b = new Headers();

        $a->set_status_code(404);

        $this->assertSame(404, $a->get_status_code());
        $this->assertSame(200, $b->get_status_code());
    }

    public function testTwoInstancesDoNotShareCookies(): void
    {
        $a = new Headers();
        $b = new Headers();

        $a->set_cookie('token', 'abc');

        $this->assertNotNull($a->get_cookies()->get('token'));
        $this->assertNull($b->get_cookies()->get('token'));
    }

    // -------------------------------------------------------------------------
    // Static registry (setCurrent / getCurrent / clearCurrent)
    // -------------------------------------------------------------------------

    public function testSetCurrentAndGetCurrent(): void
    {
        $instance = new Headers();
        Headers::setCurrent($instance);

        $this->assertSame($instance, Headers::getCurrent());
    }

    public function testGetCurrentReturnsNullWhenNotSet(): void
    {
        $this->assertNull(Headers::getCurrent());
    }

    public function testClearCurrentRemovesInstance(): void
    {
        $instance = new Headers();
        Headers::setCurrent($instance);
        Headers::clearCurrent();

        $this->assertNull(Headers::getCurrent());
    }

    public function testSetCurrentReplacesExistingInstance(): void
    {
        $first  = new Headers();
        $second = new Headers();

        Headers::setCurrent($first);
        Headers::setCurrent($second);

        $this->assertSame($second, Headers::getCurrent());
    }

    public function testCurrentInstanceReceivesHeadersViaRegistry(): void
    {
        $instance = new Headers();
        Headers::setCurrent($instance);

        $current = Headers::getCurrent();
        $this->assertNotNull($current);
        $current->add_header('X-Via-Registry: yes');

        $this->assertSame(' yes', $instance->get_headers()['X-Via-Registry']);
    }
}
