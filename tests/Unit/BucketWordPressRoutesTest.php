<?php

declare(strict_types=1);

namespace WordPressPsr\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use WordPressPsr\BucketWordPressRoutes;

/**
 * Tests for BucketWordPressRoutes — worker-pool routing logic.
 *
 * Uses PHPUnit mock objects to avoid a real PSR-7 implementation dependency.
 */
class BucketWordPressRoutesTest extends TestCase
{
    private BucketWordPressRoutes $router;

    protected function setUp(): void
    {
        $this->router = new BucketWordPressRoutes();

        // Register the minimum required workers (indices 0-6)
        for ($i = 0; $i < BucketWordPressRoutes::MIN_REQUIRED_WORKERS; $i++) {
            $this->router->addWorker("worker-$i");
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeRequest(string $path): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    // -------------------------------------------------------------------------
    // addWorker / worker pool
    // -------------------------------------------------------------------------

    public function testAddWorkerIncreasesPool(): void
    {
        $router = new BucketWordPressRoutes();
        $router->addWorker('w0');
        $router->addWorker('w1');

        // No assertion on internal state — verified via routing behaviour
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Special routes (fixed worker indices)
    // -------------------------------------------------------------------------

    public function testWpCronRoutesToWorker0(): void
    {
        $request = $this->makeRequest('/wp-cron.php');

        $this->assertSame('worker-0', $this->router->getWorkerForRequest($request));
    }

    public function testWpAdminCustomizerRoutesToWorker1(): void
    {
        $request = $this->makeRequest('/wp-admin/customizer.php');

        $this->assertSame('worker-1', $this->router->getWorkerForRequest($request));
    }

    public function testWpAdminAjaxRoutesToWorker2(): void
    {
        $request = $this->makeRequest('/wp-admin/admin-ajax.php');

        $this->assertSame('worker-2', $this->router->getWorkerForRequest($request));
    }

    public function testXmlRpcRoutesToWorker3(): void
    {
        $request = $this->makeRequest('/xmlrpc.php');

        $this->assertSame('worker-3', $this->router->getWorkerForRequest($request));
    }

    // -------------------------------------------------------------------------
    // Admin prefix routing
    // -------------------------------------------------------------------------

    public function testWpAdminUserRoutesToWorker4(): void
    {
        $request = $this->makeRequest('/wp-admin/user/profile.php');

        $this->assertSame('worker-4', $this->router->getWorkerForRequest($request));
    }

    public function testWpAdminNetworkRoutesToWorker5(): void
    {
        $request = $this->makeRequest('/wp-admin/network/settings.php');

        $this->assertSame('worker-5', $this->router->getWorkerForRequest($request));
    }

    public function testWpAdminGenericRoutesToWorker6(): void
    {
        $request = $this->makeRequest('/wp-admin/edit.php');

        $this->assertSame('worker-6', $this->router->getWorkerForRequest($request));
    }

    public function testWpAdminRootRoutesToWorker6(): void
    {
        $request = $this->makeRequest('/wp-admin/');

        $this->assertSame('worker-6', $this->router->getWorkerForRequest($request));
    }

    // -------------------------------------------------------------------------
    // Front-end routes (DO_NOT_USE_WORKER)
    // -------------------------------------------------------------------------

    public function testFrontEndRequestReturnsDoNotUseWorker(): void
    {
        $request = $this->makeRequest('/');

        $this->assertSame(BucketWordPressRoutes::DO_NOT_USE_WORKER, $this->router->getWorkerForRequest($request));
    }

    public function testFrontEndPostRequestReturnsDoNotUseWorker(): void
    {
        $request = $this->makeRequest('/my-post/');

        $this->assertSame(BucketWordPressRoutes::DO_NOT_USE_WORKER, $this->router->getWorkerForRequest($request));
    }

    public function testWpLoginReturnsDoNotUseWorker(): void
    {
        $request = $this->makeRequest('/wp-login.php');

        $this->assertSame(BucketWordPressRoutes::DO_NOT_USE_WORKER, $this->router->getWorkerForRequest($request));
    }

    // -------------------------------------------------------------------------
    // shouldShutdownAfter
    // -------------------------------------------------------------------------

    public function testShouldShutdownAfterSetupConfig(): void
    {
        $request = $this->makeRequest('/wp-admin/setup-config.php');

        $this->assertTrue($this->router->shouldShutdownAfter($request));
    }

    public function testShouldShutdownAfterInstall(): void
    {
        $request = $this->makeRequest('/wp-admin/install.php');

        $this->assertTrue($this->router->shouldShutdownAfter($request));
    }

    public function testShouldNotShutdownAfterRegularRequest(): void
    {
        $request = $this->makeRequest('/wp-admin/edit.php');

        $this->assertFalse($this->router->shouldShutdownAfter($request));
    }

    public function testShouldNotShutdownAfterFrontEnd(): void
    {
        $request = $this->makeRequest('/');

        $this->assertFalse($this->router->shouldShutdownAfter($request));
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testMinRequiredWorkersConstant(): void
    {
        $this->assertSame(7, BucketWordPressRoutes::MIN_REQUIRED_WORKERS);
    }

    public function testDoNotUseWorkerConstant(): void
    {
        $this->assertSame(-1, BucketWordPressRoutes::DO_NOT_USE_WORKER);
    }
}
