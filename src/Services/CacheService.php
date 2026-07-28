<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Simple in-memory cache service
 * Provides request-level caching for frequently accessed data
 * Cache is cleared between requests
 */
class CacheService
{
    private static ?self $instance = null;
    private array $store = [];
    private array $expirations = [];

    private function __construct()
    {
        // Private constructor for singleton
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get cached value or execute callback and cache the result
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to call if cache miss
     * @return mixed Cached or computed value
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        // Check if cached and not expired
        if ($this->has($key)) {
            return $this->get($key);
        }

        // Execute callback and cache result
        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    /**
     * Check if key exists and is not expired
     *
     * @param string $key Cache key
     * @return bool True if exists and valid
     */
    public function has(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }

        // Check expiration
        if (isset($this->expirations[$key]) && $this->expirations[$key] < time()) {
            $this->forget($key);

            return false;
        }

        return true;
    }

    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key];
    }

    /**
     * Store value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = forever for request duration)
     */
    public function put(string $key, mixed $value, int $ttl = 0): void
    {
        $this->store[$key] = $value;

        if ($ttl > 0) {
            $this->expirations[$key] = time() + $ttl;
        } else {
            unset($this->expirations[$key]);
        }
    }

    /**
     * Remove item from cache
     *
     * @param string $key Cache key
     */
    public function forget(string $key): void
    {
        unset($this->store[$key], $this->expirations[$key]);
    }

    /**
     * Clear all cached items
     */
    public function flush(): void
    {
        $this->store = [];
        $this->expirations = [];
    }

    /**
     * Get multiple values at once
     *
     * @param array $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array Associative array of key => value pairs
     */
    public function many(array $keys, mixed $default = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Put multiple values at once
     *
     * @param array $values Associative array of key => value pairs
     * @param int $ttl Time to live in seconds
     */
    public function putMany(array $values, int $ttl = 0): void
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }
    }

    /**
     * Increment numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to increment by
     * @return int New value
     */
    public function increment(string $key, int $value = 1): int
    {
        $current = (int)$this->get($key, 0);
        $new = $current + $value;
        $this->put($key, $new, 0);

        return $new;
    }

    /**
     * Decrement numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to decrement by
     * @return int New value
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * Get all cached keys
     *
     * @return array Array of cache keys
     */
    public function keys(): array
    {
        return array_keys($this->store);
    }

    /**
     * Get cache statistics
     *
     * @return array Cache stats
     */
    public function stats(): array
    {
        $expired = 0;
        $now = time();

        foreach ($this->expirations as $expiration) {
            if ($expiration < $now) {
                $expired++;
            }
        }

        return [
            'total_items' => count($this->store),
            'expired_items' => $expired,
            'valid_items' => count($this->store) - $expired,
        ];
    }
}
