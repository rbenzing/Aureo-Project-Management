<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case with common testing utilities
 */
abstract class TestCase extends BaseTestCase
{
    protected ?Database $db = null;

    /** Cached probe result so an unreachable database is not retried per test. */
    protected static ?bool $dbAvailable = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test gets its own instance, so $this->db must be assigned per
        // instance — a static "already initialized" guard would leave it null for
        // every test after the first.
        $this->initializeTestDatabase();
    }

    /**
     * Initialize test database connection.
     *
     * Never throws: tests that genuinely need the database call requireDatabase()
     * and are skipped when it is unreachable.
     */
    protected function initializeTestDatabase(): void
    {
        // Set test environment variables
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_HOST'] = getenv('DB_HOST') ?: '127.0.0.1:3306';
        $_ENV['DB_NAME'] = getenv('DB_NAME') ?: 'pms_test';
        $_ENV['DB_USERNAME'] = getenv('DB_USERNAME') ?: 'root';
        $_ENV['DB_PASSWORD'] = getenv('DB_PASSWORD') ?: '';

        if (self::$dbAvailable === false) {
            return;
        }

        try {
            $db = Database::getInstance();

            // getInstance() connects lazily, so it succeeds even with no server
            // listening. Force the connection to prove the database is reachable.
            $db->getConnection();

            $this->db = $db;
            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            self::$dbAvailable = false;
            $this->db = null;
        }
    }

    /**
     * Skip the current test when no test database is reachable.
     *
     * Integration tests are meaningless without a database, but a developer
     * without MySQL running should see "skipped", not a wall of connection errors
     * that masks real failures.
     */
    protected function requireDatabase(): Database
    {
        if ($this->db === null) {
            $this->markTestSkipped(
                'No test database reachable. Start MySQL and run: vendor/bin/phinx migrate -e testing'
            );
        }

        return $this->db;
    }

    /**
     * Create a mock object with expectations
     */
    protected function mockWithExpectations(string $class, array $methods = []): object
    {
        $mock = $this->createMock($class);

        foreach ($methods as $method => $returnValue) {
            $mock->expects($this->any())
                ->method($method)
                ->willReturn($returnValue);
        }

        return $mock;
    }

    /**
     * Assert array has keys
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array missing key: {$key}");
        }
    }

    /**
     * Assert object has properties
     */
    protected function assertObjectHasProperties(array $properties, object $object, string $message = ''): void
    {
        foreach ($properties as $property) {
            $this->assertObjectHasProperty($property, $object, $message ?: "Object missing property: {$property}");
        }
    }

    /**
     * Simulate authenticated user session
     */
    protected function actingAs(array $userData): void
    {
        $_SESSION['user'] = $userData;
        $_SESSION['authenticated'] = true;
    }

    /**
     * Clear session data
     */
    protected function clearSession(): void
    {
        $_SESSION = [];
    }

    /**
     * Create test request data
     */
    protected function createRequestData(array $data, string $method = 'POST'): array
    {
        return array_merge([
            '_method' => $method,
            'csrf_token' => $_SESSION['csrf_token'] ?? 'test-token',
        ], $data);
    }
}
