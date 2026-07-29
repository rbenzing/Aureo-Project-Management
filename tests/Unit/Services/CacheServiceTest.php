<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CacheService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for CacheService
 *
 * Covers singleton behavior, cache hit/miss/expiry paths, bulk helpers,
 * increment/decrement semantics and cache statistics.
 */
#[CoversClass(CacheService::class)]
final class CacheServiceTest extends TestCase
{
    private CacheService $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset the singleton so every test starts from an empty cache.
        // setAccessible() is a no-op since PHP 8.1 and deprecated in 8.5.
        $reflection = new ReflectionClass(CacheService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        $this->cache = CacheService::getInstance();
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(CacheService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        parent::tearDown();
    }

    /**
     * Directly rewrite a private expirations entry to simulate an already-expired key
     * without depending on real elapsed time / sleeping in the test.
     */
    private function forceExpire(string $key): void
    {
        $reflection = new ReflectionClass(CacheService::class);
        $property = $reflection->getProperty('expirations');
        $expirations = $property->getValue($this->cache);
        $expirations[$key] = time() - 10;
        $property->setValue($this->cache, $expirations);
    }

    public function testGetInstanceReturnsSameSingletonInstance(): void
    {
        $again = CacheService::getInstance();

        $this->assertSame($this->cache, $again);
    }

    public function testPutAndGetStoresAndRetrievesValue(): void
    {
        $this->cache->put('name', 'Aureo');

        $this->assertSame('Aureo', $this->cache->get('name'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertSame('fallback', $this->cache->get('missing', 'fallback'));
        $this->assertNull($this->cache->get('missing-without-default'));
    }

    public function testHasReturnsTrueForExistingUnexpiredKey(): void
    {
        $this->cache->put('key', 'value', 100);

        $this->assertTrue($this->cache->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->has('does-not-exist'));
    }

    public function testHasForgetsAndReturnsFalseForExpiredKey(): void
    {
        $this->cache->put('temp', 'value', 100);
        $this->forceExpire('temp');

        $this->assertFalse($this->cache->has('temp'));
        // has() must have evicted the expired entry entirely.
        $this->assertNotContains('temp', $this->cache->keys());
    }

    public function testGetReturnsDefaultForExpiredKey(): void
    {
        $this->cache->put('temp', 'value', 100);
        $this->forceExpire('temp');

        $this->assertSame('default-value', $this->cache->get('temp', 'default-value'));
    }

    public function testPutWithZeroTtlSetsNoExpiration(): void
    {
        $reflection = new ReflectionClass(CacheService::class);
        $property = $reflection->getProperty('expirations');

        $this->cache->put('forever', 'value', 0);

        $this->assertArrayNotHasKey('forever', $property->getValue($this->cache));
        $this->assertTrue($this->cache->has('forever'));
    }

    public function testPuttingSameKeyWithZeroTtlClearsPreviousExpiration(): void
    {
        $reflection = new ReflectionClass(CacheService::class);
        $property = $reflection->getProperty('expirations');

        $this->cache->put('rotating', 'first', 100);
        $this->assertArrayHasKey('rotating', $property->getValue($this->cache));

        $this->cache->put('rotating', 'second', 0);

        $this->assertArrayNotHasKey('rotating', $property->getValue($this->cache));
        $this->assertSame('second', $this->cache->get('rotating'));
    }

    public function testRememberExecutesCallbackOnCacheMissAndStoresResult(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'computed-value';
        };

        $result = $this->cache->remember('computed', 60, $callback);

        $this->assertSame('computed-value', $result);
        $this->assertSame(1, $calls);
        $this->assertTrue($this->cache->has('computed'));
    }

    public function testRememberReturnsCachedValueWithoutInvokingCallbackAgain(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'value-' . $calls;
        };

        $first = $this->cache->remember('once', 60, $callback);
        $second = $this->cache->remember('once', 60, $callback);

        $this->assertSame('value-1', $first);
        $this->assertSame('value-1', $second);
        $this->assertSame(1, $calls);
    }

    public function testRememberRecomputesAfterExpiry(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'value-' . $calls;
        };

        $first = $this->cache->remember('expiring', 100, $callback);
        $this->forceExpire('expiring');
        $second = $this->cache->remember('expiring', 100, $callback);

        $this->assertSame('value-1', $first);
        $this->assertSame('value-2', $second);
        $this->assertSame(2, $calls);
    }

    public function testForgetRemovesStoredKey(): void
    {
        $this->cache->put('temporary', 'value');
        $this->cache->forget('temporary');

        $this->assertFalse($this->cache->has('temporary'));
        $this->assertNull($this->cache->get('temporary'));
    }

    public function testForgetOnMissingKeyIsSilentNoOp(): void
    {
        $this->cache->forget('never-existed');

        $this->assertFalse($this->cache->has('never-existed'));
    }

    public function testFlushClearsAllStoredItemsAndExpirations(): void
    {
        $this->cache->put('a', 1);
        $this->cache->put('b', 2, 100);

        $this->cache->flush();

        $this->assertSame([], $this->cache->keys());
        $this->assertSame(['total_items' => 0, 'expired_items' => 0, 'valid_items' => 0], $this->cache->stats());
    }

    public function testManyReturnsValuesForExistingKeysAndDefaultsForMissingOnes(): void
    {
        $this->cache->put('one', 1);
        $this->cache->put('two', 2);

        $result = $this->cache->many(['one', 'two', 'three'], 'n/a');

        $this->assertSame(['one' => 1, 'two' => 2, 'three' => 'n/a'], $result);
    }

    public function testPutManyStoresEveryProvidedValue(): void
    {
        $this->cache->putMany(['x' => 'X', 'y' => 'Y'], 60);

        $this->assertSame('X', $this->cache->get('x'));
        $this->assertSame('Y', $this->cache->get('y'));
        $this->assertTrue($this->cache->has('x'));
        $this->assertTrue($this->cache->has('y'));
    }

    public function testIncrementFromUnsetKeyStartsAtZero(): void
    {
        $result = $this->cache->increment('counter');

        $this->assertSame(1, $result);
        $this->assertSame(1, $this->cache->get('counter'));
    }

    public function testIncrementAddsCustomAmountToExistingValue(): void
    {
        $this->cache->put('counter', 10);

        $result = $this->cache->increment('counter', 5);

        $this->assertSame(15, $result);
    }

    public function testIncrementCastsNonNumericStoredValueToZeroBeforeAdding(): void
    {
        $this->cache->put('counter', 'not-a-number');

        $result = $this->cache->increment('counter', 3);

        $this->assertSame(3, $result);
    }

    public function testDecrementSubtractsAmountFromExistingValue(): void
    {
        $this->cache->put('counter', 10);

        $result = $this->cache->decrement('counter', 4);

        $this->assertSame(6, $result);
    }

    public function testDecrementFromUnsetKeyGoesNegative(): void
    {
        $result = $this->cache->decrement('fresh-counter', 3);

        $this->assertSame(-3, $result);
    }

    public function testKeysReturnsAllStoredKeyNames(): void
    {
        $this->cache->put('alpha', 1);
        $this->cache->put('beta', 2);

        $keys = $this->cache->keys();

        $this->assertCount(2, $keys);
        $this->assertContains('alpha', $keys);
        $this->assertContains('beta', $keys);
    }

    public function testStatsCountsTotalExpiredAndValidItems(): void
    {
        $this->cache->put('valid-1', 'a', 100);
        $this->cache->put('valid-2', 'b'); // no expiration at all
        $this->cache->put('expired-1', 'c', 100);
        $this->forceExpire('expired-1');

        $stats = $this->cache->stats();

        $this->assertSame(3, $stats['total_items']);
        $this->assertSame(1, $stats['expired_items']);
        $this->assertSame(2, $stats['valid_items']);
    }
}
