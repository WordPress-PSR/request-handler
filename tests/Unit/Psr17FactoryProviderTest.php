<?php

declare(strict_types=1);

namespace WordPressPsr\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WordPressPsr\Psr17\Psr17FactoryProvider;
use WordPressPsr\Psr17\LaminasDiactorosPsr17Factory;
use WordPressPsr\Psr17\NyholmPsr17Factory;
use WordPressPsr\Psr17\SlimPsr17Factory;
use WordPressPsr\Psr17\GuzzlePsr17Factory;
use WordPressPsr\Psr17\ZendDiactorosPsr17Factory;

/**
 * Tests for Psr17FactoryProvider — factory detection and management.
 */
class Psr17FactoryProviderTest extends TestCase
{
    private array $originalFactories;

    protected function setUp(): void
    {
        // Preserve original factory list so tests are isolated
        $this->originalFactories = Psr17FactoryProvider::getFactories();
    }

    protected function tearDown(): void
    {
        Psr17FactoryProvider::setFactories($this->originalFactories);
    }

    // -------------------------------------------------------------------------
    // getFactories
    // -------------------------------------------------------------------------

    public function testGetFactoriesReturnsArray(): void
    {
        $factories = Psr17FactoryProvider::getFactories();

        $this->assertIsArray($factories);
    }

    public function testGetFactoriesContainsDefaultFactories(): void
    {
        $factories = Psr17FactoryProvider::getFactories();

        $this->assertContains(NyholmPsr17Factory::class, $factories);
        $this->assertContains(LaminasDiactorosPsr17Factory::class, $factories);
    }

    public function testDefaultFactoryListHasFiveEntries(): void
    {
        $factories = Psr17FactoryProvider::getFactories();

        $this->assertCount(5, $factories);
    }

    // -------------------------------------------------------------------------
    // setFactories
    // -------------------------------------------------------------------------

    public function testSetFactoriesReplacesAll(): void
    {
        Psr17FactoryProvider::setFactories([NyholmPsr17Factory::class]);

        $this->assertSame([NyholmPsr17Factory::class], Psr17FactoryProvider::getFactories());
    }

    public function testSetFactoriesWithEmptyArray(): void
    {
        Psr17FactoryProvider::setFactories([]);

        $this->assertSame([], Psr17FactoryProvider::getFactories());
    }

    // -------------------------------------------------------------------------
    // addFactory
    // -------------------------------------------------------------------------

    public function testAddFactoryPrependsToList(): void
    {
        Psr17FactoryProvider::setFactories([NyholmPsr17Factory::class]);
        Psr17FactoryProvider::addFactory(LaminasDiactorosPsr17Factory::class);

        $factories = Psr17FactoryProvider::getFactories();
        $this->assertSame(LaminasDiactorosPsr17Factory::class, $factories[0]);
    }

    public function testAddFactoryIncreasesCount(): void
    {
        Psr17FactoryProvider::setFactories([NyholmPsr17Factory::class]);
        Psr17FactoryProvider::addFactory(LaminasDiactorosPsr17Factory::class);

        $this->assertCount(2, Psr17FactoryProvider::getFactories());
    }

    // -------------------------------------------------------------------------
    // Factory availability detection
    // -------------------------------------------------------------------------

    public function testNyholmResponseFactoryIsAvailable(): void
    {
        // nyholm/psr7 is installed — response factory class exists
        $this->assertTrue(NyholmPsr17Factory::isResponseFactoryAvailable());
    }

    public function testNyholmServerRequestCreatorRequiresPsr7Server(): void
    {
        // nyholm/psr7-server is NOT installed (only nyholm/psr7 is a dev dep)
        // so the server request creator is not available
        $this->assertFalse(NyholmPsr17Factory::isServerRequestCreatorAvailable());
    }

    public function testLaminasFactoryIsAvailable(): void
    {
        // laminas/laminas-diactoros is installed as a dev dependency
        $this->assertTrue(LaminasDiactorosPsr17Factory::isServerRequestCreatorAvailable());
    }

    public function testSlimFactoryIsNotAvailable(): void
    {
        // slim/psr7 is not installed
        $this->assertFalse(SlimPsr17Factory::isServerRequestCreatorAvailable());
    }

    public function testGuzzleFactoryIsNotAvailable(): void
    {
        // guzzlehttp/psr7 is not installed
        $this->assertFalse(GuzzlePsr17Factory::isServerRequestCreatorAvailable());
    }

    public function testZendFactoryIsAvailableViaLaminasBridge(): void
    {
        // laminas/laminas-zendframework-bridge provides Zend namespace aliases
        $this->assertTrue(ZendDiactorosPsr17Factory::isServerRequestCreatorAvailable());
    }
}
