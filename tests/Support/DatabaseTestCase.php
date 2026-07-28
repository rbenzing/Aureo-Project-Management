<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Base test case for tests that require database transactions
 * Automatically rolls back database changes after each test
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Skips the test when no database is reachable rather than fataling on a
        // null connection.
        $this->requireDatabase();

        // Start transaction for test isolation
        if (!self::$transactionStarted) {
            $this->db->beginTransaction();
            self::$transactionStarted = true;
        }
    }

    protected function tearDown(): void
    {
        // Rollback transaction to clean up test data
        if (self::$transactionStarted && $this->db !== null) {
            $this->db->rollBack();
            self::$transactionStarted = false;
        }

        parent::tearDown();
    }

    /**
     * Insert test data and return inserted ID
     */
    protected function insertTestData(string $table, array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn ($field) => ":{$field}", $fields);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $field => $value) {
            $params[":{$field}"] = $value;
        }

        $this->db->executeInsertUpdate($sql, $params);

        // Database exposes lastInsertId() directly; there is no getPDO().
        return $this->db->lastInsertId();
    }

    /**
     * Assert record exists in database
     */
    protected function assertDatabaseHas(string $table, array $conditions): void
    {
        $where = [];
        $params = [];

        foreach ($conditions as $field => $value) {
            $where[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $sql = sprintf(
            "SELECT COUNT(*) FROM %s WHERE %s",
            $table,
            implode(' AND ', $where)
        );

        $stmt = $this->db->executeQuery($sql, $params);
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(
            0,
            $count,
            "Failed asserting that table {$table} has matching record"
        );
    }

    /**
     * Assert record does not exist in database
     */
    protected function assertDatabaseMissing(string $table, array $conditions): void
    {
        $where = [];
        $params = [];

        foreach ($conditions as $field => $value) {
            $where[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $sql = sprintf(
            "SELECT COUNT(*) FROM %s WHERE %s",
            $table,
            implode(' AND ', $where)
        );

        $stmt = $this->db->executeQuery($sql, $params);
        $count = (int)$stmt->fetchColumn();

        $this->assertEquals(
            0,
            $count,
            "Failed asserting that table {$table} does not have matching record"
        );
    }
}
