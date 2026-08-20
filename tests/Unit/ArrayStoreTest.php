<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\StoreInterface;
use IQuix\Tests\Support\ArrayStore;
use PHPUnit\Framework\TestCase;

final class ArrayStoreTest extends TestCase
{
    public function testItImplementsTheStoreInterface(): void
    {
        $this->assertInstanceOf(StoreInterface::class, new ArrayStore());
    }

    public function testGetReturnsTheDefaultForMissingNames(): void
    {
        $store = new ArrayStore();

        $this->assertSame('', $store->get('license_key'));
        $this->assertSame('fallback', $store->get('license_key', 'fallback'));
    }

    public function testSetThenGetRoundTrips(): void
    {
        $store = new ArrayStore();
        $store->set('license_key', 'abc123');

        $this->assertSame('abc123', $store->get('license_key'));
        $this->assertTrue($store->has('license_key'));
    }

    public function testHasIsFalseForAnEmptyStoredValue(): void
    {
        $store = new ArrayStore(['license_key' => '']);

        $this->assertFalse($store->has('license_key'));
    }

    public function testSetManyWritesEveryPair(): void
    {
        $store = new ArrayStore();
        $store->setMany(['license_key' => 'k', 'activated' => '1']);

        $this->assertSame('k', $store->get('license_key'));
        $this->assertSame('1', $store->get('activated'));
    }
}
