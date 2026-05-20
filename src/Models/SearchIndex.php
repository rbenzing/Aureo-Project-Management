<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class SearchIndex extends BaseModel
{
    protected string $table = 'searchable_index';
    protected bool $usesSoftDeletes = false;

    protected array $fillable = [
        'entity_type', 'entity_id', 'title', 'snippet',
        'project_id', 'search_blob', 'updated_at', 'is_deleted',
    ];

    /**
     * Upsert an index row for an entity.
     */
    public function upsert(
        string $entityType,
        int $entityId,
        string $title,
        string $snippet,
        ?int $projectId,
        string $searchBlob,
        bool $isDeleted = false
    ): bool {
        $sql = "
            INSERT INTO {$this->table}
                (entity_type, entity_id, title, snippet, project_id, search_blob, updated_at, is_deleted)
            VALUES
                (:entity_type, :entity_id, :title, :snippet, :project_id, :search_blob, :updated_at, :is_deleted)
            ON DUPLICATE KEY UPDATE
                title       = VALUES(title),
                snippet     = VALUES(snippet),
                project_id  = VALUES(project_id),
                search_blob = VALUES(search_blob),
                updated_at  = VALUES(updated_at),
                is_deleted  = VALUES(is_deleted)
        ";

        return $this->db->executeInsertUpdate($sql, [
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':title' => $title,
            ':snippet' => $snippet,
            ':project_id' => $projectId,
            ':search_blob' => $searchBlob,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':is_deleted' => (int) $isDeleted,
        ]);
    }

    /**
     * Mark an entity as deleted in the index (soft-delete the index row).
     */
    public function markDeleted(string $entityType, int $entityId): bool
    {
        $sql = "UPDATE {$this->table}
                SET is_deleted = 1, updated_at = :now
                WHERE entity_type = :type AND entity_id = :id";

        return $this->db->executeInsertUpdate($sql, [
            ':now' => date('Y-m-d H:i:s'),
            ':type' => $entityType,
            ':id' => $entityId,
        ]);
    }

    /**
     * Full-text search returning raw rows with score.
     *
     * @param string $query
     * @param array  $entityTypes   e.g. ['task','project']
     * @param int    $limit
     * @return array<object>        each row has all columns + `score` float
     */
    public function fullTextSearch(string $query, array $entityTypes = [], int $limit = 30): array
    {
        $params = [':query' => $query, ':limit' => $limit];

        $typeFilter = '';
        if (!empty($entityTypes)) {
            $placeholders = [];
            foreach ($entityTypes as $i => $type) {
                $placeholders[] = ":type_{$i}";
                $params[":type_{$i}"] = $type;
            }
            $typeFilter = 'AND entity_type IN (' . implode(',', $placeholders) . ')';
        }

        $sql = "
            SELECT *, MATCH(search_blob) AGAINST(:query IN NATURAL LANGUAGE MODE) AS score
            FROM {$this->table}
            WHERE is_deleted = 0
              AND MATCH(search_blob) AGAINST(:query IN NATURAL LANGUAGE MODE) > 0
              {$typeFilter}
            ORDER BY score DESC
            LIMIT :limit
        ";

        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Prefix search on title when query is too short for FULLTEXT (< 3 chars).
     */
    public function prefixSearch(string $query, array $entityTypes = [], int $limit = 30): array
    {
        $params = [':query' => $query . '%', ':limit' => $limit];

        $typeFilter = '';
        if (!empty($entityTypes)) {
            $placeholders = [];
            foreach ($entityTypes as $i => $type) {
                $placeholders[] = ":type_{$i}";
                $params[":type_{$i}"] = $type;
            }
            $typeFilter = 'AND entity_type IN (' . implode(',', $placeholders) . ')';
        }

        $sql = "
            SELECT *, 0.5 AS score
            FROM {$this->table}
            WHERE is_deleted = 0
              AND title LIKE :query
              {$typeFilter}
            ORDER BY updated_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
