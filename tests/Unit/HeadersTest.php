<?php

declare(strict_types=1);

namespace WordPressPsr\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WordPressPsr\Headers;

/**
 * Tests for the Headers static class.
 *
 * Headers manages PSR-7 response headers, status codes, and cookies
 * without requiring a WordPress environment.
 */
class HeadersTest extends TestCase
{
    protected function setUp(): void
    {
        Headers::reset();
    }

    protected function tearDown(): void
    {
        Headers::reset();
    }

    // -------------------------------------------------------------------------
    // add_header
    // -------------------------------------------------------------------------

    public function testAddHeaderStoresHeader(): void
    {
        Headers::add_header('Content-Type: application/json');

        $this->assertSame(['Content-Type' => ' application/json'], Headers::get_headers());
    }

    public function testAddHeaderReplacesExistingByDefault(): void
    {
        Headers::add_header('Content-Type: text/html');
        Headers::add_header('Content-Type: application/json');

        $this->assertSame(' application/json', Headers::get_headers()['Content-Type']);
    }

    public function testAddHeaderDoesNotReplaceWhenReplaceFalse(): void
    {
        Headers::add_header('Content-Type: text/html');
        Headers::add_header('Content-Type: application/json', false);

        $this->assertSame(' text/html', Headers::get_headers()['Content-Type']);
    }

    public function testAddHeaderSetsStatusCodeWhenProvided(): void
    {
        Headers::add_header('X-Custom: value', true, 201);

        $this->assertSame(201, Headers::get_status_code());
    }

    public function testAddHeaderSetsRedirectStatusCodeForLocation(): void
    {
        Headers::add_header('location: https://example.com/new');

        $this->assertSame(302, Headers::get_status_code());
    }

    public function testAddHeaderDoesNotOverrideNon200StatusForLocation(): void
    {
        Headers::set_status_code(301);
        Headers::add_header('location: https://example.com/new');

        $this->assertSame(301, Headers::get_status_code());
    }

    public function testAddHeaderIgnoresInvalidHeaderName(): void
    {
        Headers::add_header('Invalid Header Name: value');

        $this->assertEmpty(Headers::get_headers());
    }

    public function testAddHeaderIgnoresInvalidHeaderValue(): void
    {
        // Control characters are invalid in header values
        Headers::add_header("X-Custom: value\x00with-null");

        $this->assertEmpty(Headers::get_headers());
    }

    public function testAddMultipleDistinctHeaders(): void
    {
        Headers::add_header('Content-Type: text/html');
        Headers::add_header('X-Frame-Options: DENY');
        Headers::add_header('Cache-Control: no-cache');

        $headers = Headers::get_headers();
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
        Headers::add_header('X-Custom: value');
        Headers::remove_header('X-Custom');

        $this->assertArrayNotHasKey('X-Custom', Headers::get_headers());
    }

    public function testRemoveHeaderIsNoopForNonExistentHeader(): void
    {
        // Should not throw
        Headers::remove_header('X-Does-Not-Exist');

        $this->assertEmpty(Headers::get_headers());
    }

    // -------------------------------------------------------------------------
    // status code
    // -------------------------------------------------------------------------

    public function testDefaultStatusCodeIs200(): void
    {
        $this->assertSame(200, Headers::get_status_code());
    }

    public function testSetStatusCode(): void
    {
        Headers::set_status_code(404);

        $this->assertSame(404, Headers::get_status_code());
    }

    public function testSetStatusCodeCastsToInt(): void
    {
        Headers::set_status_code('500');

        $this->assertSame(500, Headers::get_status_code());
    }

    // -------------------------------------------------------------------------
    // cookies
    // -------------------------------------------------------------------------

    public function testSetCookieReturnsTrueOnSuccess(): void
    {
        $result = Headers::set_cookie('session', 'abc123');

        $this->assertTrue($result);
    }

    public function testSetCookieIsReflectedInGetCookies(): void
    {
        Headers::set_cookie('session', 'abc123');

        $cookies = Headers::get_cookies();
        $cookie  = $cookies->get('session');

        $this->assertNotNull($cookie);
        $this->assertSame('abc123', $cookie->getValue());
    }

    public function testSetMultipleCookies(): void
    {
        Headers::set_cookie('a', '1');
        Headers::set_cookie('b', '2');

        $cookies = Headers::get_cookies();
        $this->assertNotNull($cookies->get('a'));
        $this->assertNotNull($cookies->get('b'));
    }

    public function testGetCookiesReturnsEmptySetCookiesWhenNoneSet(): void
    {
        $cookies = Headers::get_cookies();

        $this->assertInstanceOf(\Dflydev\FigCookies\SetCookies::class, $cookies);
    }

    // -------------------------------------------------------------------------
    // reset
    // -------------------------------------------------------------------------

    public function testResetClearsHeaders(): void
    {
        Headers::add_header('X-Custom: value');
        Headers::reset();

        $this->assertEmpty(Headers::get_headers());
    }

    public function testResetRestoresDefaultStatusCode(): void
    {
        Headers::set_status_code(500);
        Headers::reset();

        $this->assertSame(200, Headers::get_status_code());
    }

    public function testResetClearsCookies(): void
    {
        Headers::set_cookie('session', 'abc');
        Headers::reset();

        $cookies = Headers::get_cookies();
        $this->assertNull($cookies->get('session'));
    }
}
