<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\SearchIndex;
use PDO;

/**
 * SearchRepository
 *
 * Provides search dispatch (full-text vs prefix) and search-query telemetry.
 * Does NOT implement RepositoryInterface — search has a different shape to CRUD.
 */
class SearchRepository
{
    private SearchIndex $model;
    private Database $db;

    public function __construct(?SearchIndex $model = null, ?Database $db = null)
    {
        $this->model = $model ?? new SearchIndex();
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Run a search against the searchable index.
     *
     * Delegates to fullTextSearch when the query is >= 3 characters long,
     * and to prefixSearch for shorter queries (MySQL FULLTEXT minimum token length).
     *
     * @param string   $query
     * @param string[] $entityTypes  e.g. ['task', 'project']
     * @param int      $limit
     * @return array<object>
     */
    public function search(string $query, array $entityTypes = [], int $limit = 30): array
    {
        if (strlen($query) >= 3) {
            return $this->model->fullTextSearch($query, $entityTypes, $limit);
        }

        return $this->model->prefixSearch($query, $entityTypes, $limit);
    }

    /**
     * Persist a row in search_queries for analytics/telemetry.
     *
     * @param int      $userId
     * @param string   $query
     * @param int      $resultCount
     * @param int      $tookMs
     * @param int|null $clickedPosition  1-based position the user clicked, or null
     * @return bool
     */
    public function logQuery(
        int $userId,
        string $query,
        int $resultCount,
        int $tookMs,
        ?int $clickedPosition = null
    ): bool {
        $sql = "
            INSERT INTO `search_queries`
                (`user_id`, `query`, `result_count`, `took_ms`, `clicked_position`)
            VALUES
                (:user_id, :query, :result_count, :took_ms, :clicked_position)
        ";

        return $this->db->executeInsertUpdate($sql, [
            ':user_id' => $userId,
            ':query' => $query,
            ':result_count' => $resultCount,
            ':took_ms' => $tookMs,
            ':clicked_position' => $clickedPosition,
        ]);
    }

    /**
     * Return the most recent search queries recorded for a user.
     *
     * @param int $userId
     * @param int $limit
     * @return array<object>
     */
    public function getRecentQueries(int $userId, int $limit = 10): array
    {
        $sql = "
            SELECT *
            FROM `search_queries`
            WHERE `user_id` = :user_id
            ORDER BY `created_at` DESC
            LIMIT :limit
        ";

        $stmt = $this->db->executeQuery($sql, [
            ':user_id' => $userId,
            ':limit' => $limit,
        ]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
