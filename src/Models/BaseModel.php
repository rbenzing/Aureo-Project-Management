<?php

// file: Models/BaseModel.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Exceptions\NotFoundException;
use App\Services\SecurityService;
use InvalidArgumentException;
use PDO;
use RuntimeException;

abstract class BaseModel
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $guarded = ['id', 'guid', 'created_at', 'updated_at'];
    protected array $hidden = [];
    protected array $dates = ['created_at', 'updated_at'];
    protected bool $usesSoftDeletes = true;
    protected array $validationRules = [];
    protected array $searchable = [];

    public function __construct()
    {
        $this->db = Database::getInstance();

        if (empty($this->table)) {
            // Convert CamelCase to snake_case and pluralize
            $className = (new \ReflectionClass($this))->getShortName();
            $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
            $this->table = $this->pluralize($snakeCase);
        }
    }

    /**
     * Count with conditions
     */
    public function count(array $conditions = []): int
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            // Validate column name to prevent SQL injection
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                throw new InvalidArgumentException("Invalid column name: {$column}");
            }

            if (is_array($value)) {
                // Handle complex conditions
                $operatorKeys = array_keys($value);
                $operator = strtoupper(is_string($operatorKeys[0]) ? $operatorKeys[0] : 'EQ');
                $comparisonValue = $value[$operatorKeys[0]];

                switch ($operator) {
                    case '>':
                        $whereClauses[] = "{$column} > :$column";
                        $params[":$column"] = $comparisonValue;

                        break;
                    case '<':
                        $whereClauses[] = "{$column} < :$column";
                        $params[":$column"] = $comparisonValue;

                        break;
                    case 'NOT IN':
                        // Ensure all values are validated
                        if (!is_array($comparisonValue)) {
                            throw new InvalidArgumentException("NOT IN requires an array of values");
                        }

                        $inPlaceholders = [];
                        foreach ($comparisonValue as $k => $val) {
                            $placeholder = ":not_in_{$column}_{$k}";
                            $inPlaceholders[] = $placeholder;
                            $params[$placeholder] = $val;
                        }

                        $whereClauses[] = "{$column} NOT IN (" . implode(', ', $inPlaceholders) . ")";

                        break;
                    case 'IS':
                        if ($comparisonValue === null) {
                            $whereClauses[] = "{$column} IS NULL";
                        } else {
                            $whereClauses[] = "{$column} = :$column";
                            $params[":$column"] = $comparisonValue;
                        }

                        break;
                    case 'IS NOT':
                        if ($comparisonValue === null) {
                            $whereClauses[] = "{$column} IS NOT NULL";
                        } else {
                            $whereClauses[] = "{$column} != :$column";
                            $params[":$column"] = $comparisonValue;
                        }

                        break;
                    default:
                        // Fallback to equality
                        $whereClauses[] = "{$column} = :$column";
                        $params[":$column"] = $comparisonValue;
                }
            } else {
                // Simple equality check
                $whereClauses[] = "{$column} = :$column";
                $params[":$column"] = $value;
            }
        }

        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $this->table)) {
            throw new RuntimeException("Invalid table name");
        }

        $sql = "SELECT COUNT(*) FROM `{$this->table}`";

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        try {
            $stmt = $this->db->executeQuery($sql, $params);

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Error counting records: " . $e->getMessage());
        }
    }

    /**
     * Create a new record
     *
     * @param array $data Record data
     * @return int|false The new record ID or false on failure
     * @throws RuntimeException
     */
    public function create(array $data): int|false
    {
        try {
            $data = $this->prepareSaveData($data);

            $fields = array_keys($data);
            $placeholders = array_map(fn ($field) => ":$field", $fields);

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $this->table,
                implode(', ', $fields),
                implode(', ', $placeholders)
            );

            $params = array_combine($placeholders, array_values($data));

            $success = $this->db->executeInsertUpdate($sql, $params);

            return $success ? $this->db->lastInsertId() : false;
        } catch (\Exception $e) {
            $safeMessage = "Failed to create {$this->table} record";
            try {
                $safeMessage = SecurityService::getInstance()->getSafeErrorMessage($e->getMessage(), $safeMessage);
            } catch (\Exception $ignored) {
            }
            throw new RuntimeException($safeMessage);
        }
    }

    /**
     * Update an existing record
     *
     * @param int $id Record ID
     * @param array $data Updated record data
     * @return bool Success status
     * @throws RuntimeException
     */
    public function update(int $id, array $data): bool
    {
        try {
            $data = $this->prepareSaveData($data);

            if (empty($data)) {
                return true; // Nothing to update
            }

            $updates = array_map(fn ($field) => "$field = :$field", array_keys($data));
            $sql = sprintf(
                "UPDATE %s SET %s WHERE %s = :id%s",
                $this->table,
                implode(', ', $updates),
                $this->primaryKey,
                $this->usesSoftDeletes ? " AND is_deleted = 0" : ""
            );



            $params = array_combine(
                array_map(fn ($field) => ":$field", array_keys($data)),
                array_values($data)
            );
            $params[':id'] = $id;

            return $this->db->executeInsertUpdate($sql, $params);
        } catch (\Exception $e) {
            try {
                $securityService = SecurityService::getInstance();
                $safeMessage = $securityService->getSafeErrorMessage($e->getMessage(), "Failed to update {$this->table} record");

                throw new RuntimeException($safeMessage);
            } catch (\Exception $securityException) {
                throw new RuntimeException("Failed to update {$this->table} record");
            }
        }
    }

    /**
     * Find a record by ID
     *
     * @param int $id Record ID
     * @return object|false Record data or false if not found
     */
    public function find(int $id): object|false
    {
        $sql = sprintf(
            "SELECT * FROM %s WHERE %s = :id%s",
            $this->table,
            $this->primaryKey,
            $this->usesSoftDeletes ? " AND is_deleted = 0" : ""
        );

        $stmt = $this->db->executeQuery($sql, [':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        return $result ? $this->hideAttributes($result) : false;
    }

    /**
     * Find a record by ID or throw NotFoundException
     *
     * @param int $id Record ID
     * @return object Record data
     * @throws NotFoundException If record not found
     */
    public function findOrFail(int $id): object
    {
        $result = $this->find($id);

        if ($result === false) {
            // Get model name without namespace
            $modelName = (new \ReflectionClass($this))->getShortName();
            throw NotFoundException::forModel($modelName, $id);
        }

        return $result;
    }

    /**
     * Get records with optional filtering and pagination
     *
     * @param array $filters Optional filters
     * @param int $page Page number
     * @param int $limit Items per page
     * @param string $orderBy Order by field
     * @param string $orderDir Order direction
     * @return array Records list and total count
     */
    public function getAll(
        array $filters = [],
        int $page = 1,
        int $limit = 10,
        string $orderBy = 'id',
        string $orderDir = 'ASC'
    ): array {
        // Validate orderBy column
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $orderBy)) {
            throw new InvalidArgumentException("Invalid order column: {$orderBy}");
        }

        // Validate orderDir
        $orderDir = strtoupper($orderDir);
        if (!in_array($orderDir, ['ASC', 'DESC'])) {
            throw new InvalidArgumentException("Invalid order direction: {$orderDir}");
        }

        $conditions = $this->usesSoftDeletes ? ['is_deleted = 0'] : [];
        $params = [];

        // Apply filters
        foreach ($filters as $field => $value) {
            // Skip search filter as it's handled separately
            if ($field === 'search') {
                continue;
            }

            // Validate field name
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                throw new InvalidArgumentException("Invalid filter field: {$field}");
            }

            if (is_array($value)) {
                $placeholders = array_map(function ($k) use ($field) {
                    return ":{$field}_{$k}";
                }, array_keys($value));

                $conditions[] = "$field IN (" . implode(',', $placeholders) . ")";

                foreach ($value as $k => $val) {
                    $params[":{$field}_{$k}"] = $val;
                }
            } else {
                $conditions[] = "$field = :{$field}";
                $params[":{$field}"] = $value;
            }
        }

        // Search in searchable fields if defined
        if (!empty($filters['search']) && !empty($this->searchable)) {
            $searchConditions = array_map(
                fn ($field) => "$field LIKE :{$field}_search",
                $this->searchable
            );
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';

            foreach ($this->searchable as $field) {
                $params[":{$field}_search"] = '%' . $filters['search'] . '%';
            }
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$this->table} $whereClause";
        $stmt = $this->db->executeQuery($countSql, $params);
        $total = $stmt->fetchColumn();

        // Get paginated results
        $offset = ($page - 1) * $limit;
        $sql = sprintf(
            "SELECT * FROM %s %s ORDER BY %s %s LIMIT :offset, :limit",
            $this->table,
            $whereClause,
            $orderBy,
            $orderDir
        );

        $params[':offset'] = $offset;
        $params[':limit'] = $limit;

        $stmt = $this->db->executeQuery($sql, $params);

        $records = array_map(
            fn ($record) => $this->hideAttributes($record),
            $stmt->fetchAll(PDO::FETCH_OBJ)
        );

        return [
            'total' => $total,
            'records' => $records,
        ];
    }

    /**
     * Soft or hard delete a record
     *
     * @param int $id Record ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        if ($this->usesSoftDeletes) {
            $sql = "UPDATE {$this->table} SET is_deleted = 1 WHERE {$this->primaryKey} = :id";
        } else {
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        }

        return $this->db->executeInsertUpdate($sql, [':id' => $id]);
    }

    /**
     * Prepare data for saving by filtering out guarded fields
     *
     * @param array $data Input data
     * @return array Filtered data
     */
    protected function prepareSaveData(array $data): array
    {
        return array_diff_key(
            $data,
            array_flip($this->guarded)
        );
    }

    /**
     * Hide specified attributes from the record
     *
     * @param object $record Record object
     * @return object Modified record
     */
    protected function hideAttributes(object $record): object
    {
        foreach ($this->hidden as $attribute) {
            unset($record->$attribute);
        }

        return $record;
    }

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit a database transaction
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * Rollback a database transaction
     */
    public function rollBack(): bool
    {
        return $this->db->rollBack();
    }

    /**
     * Simple pluralization for table names
     * Handles common English pluralization rules
     *
     * @param string $word Singular word to pluralize
     * @return string Pluralized word
     */
    private function pluralize(string $word): string
    {
        // Irregular plurals
        $irregulars = [
            'person' => 'people',
            'man' => 'men',
            'woman' => 'women',
            'child' => 'children',
            'tooth' => 'teeth',
            'foot' => 'feet',
            'mouse' => 'mice',
            'goose' => 'geese',
        ];

        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }

        // Words ending in s, x, z, ch, sh: add es
        if (preg_match('/(s|x|z|ch|sh)$/', $word)) {
            return $word . 'es';
        }

        // Words ending in consonant + y: change y to ies
        if (preg_match('/[^aeiou]y$/', $word)) {
            return substr($word, 0, -1) . 'ies';
        }

        // Words ending in f or fe: change to ves
        if (preg_match('/fe?$/', $word)) {
            return preg_replace('/fe?$/', 'ves', $word);
        }

        // Words ending in consonant + o: add es
        if (preg_match('/[^aeiou]o$/', $word)) {
            return $word . 'es';
        }

        // Default: just add s
        return $word . 's';
    }

    /**
     * Flexible query builder for complex queries with joins
     * Reduces code duplication across models
     *
     * @param array $options Query options:
     *   - select: string - SELECT clause (default: *)
     *   - joins: array - JOIN clauses [{type, table, on}, ...]
     *   - where: array - WHERE conditions [{column, operator, value}, ...]
     *   - whereRaw: array - Raw WHERE conditions with params [{sql, params}, ...]
     *   - orderBy: string - ORDER BY clause
     *   - limit: int - LIMIT value
     *   - offset: int - OFFSET value
     *   - params: array - Additional parameter bindings
     * @return array Query results
     * @throws RuntimeException
     */
    protected function queryBuilder(array $options = []): array
    {
        $defaults = [
            'select' => '*',
            'alias' => '',
            'joins' => [],
            'where' => [],
            'whereRaw' => [],
            'orderBy' => '',
            'limit' => null,
            'offset' => null,
            'params' => [],
        ];

        $opts = array_merge($defaults, $options);
        $params = $opts['params'];

        // Validate alias (single identifier only)
        if ($opts['alias'] !== '' && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $opts['alias'])) {
            throw new InvalidArgumentException("Invalid alias: {$opts['alias']}");
        }
        $tableRef = $opts['alias'] !== '' ? "{$this->table} {$opts['alias']}" : $this->table;
        $softDeleteRef = $opts['alias'] !== '' ? $opts['alias'] : $this->table;

        // Build SELECT clause
        $sql = "SELECT {$opts['select']} FROM {$tableRef} ";

        // Build JOINs
        foreach ($opts['joins'] as $join) {
            $type = strtoupper($join['type'] ?? 'LEFT');
            $table = $join['table'];
            $on = $join['on'];

            // Validate JOIN type
            if (!in_array($type, ['INNER', 'LEFT', 'RIGHT', 'CROSS'])) {
                throw new InvalidArgumentException("Invalid JOIN type: {$type}");
            }

            // Validate table name
            if (!preg_match('/^[a-zA-Z0-9_]+(\s+[a-zA-Z0-9_]+)?$/', $table)) {
                throw new InvalidArgumentException("Invalid table name: {$table}");
            }

            $sql .= "{$type} JOIN {$table} ON {$on} ";
        }

        // Build WHERE clause
        $whereClauses = [];

        // Add soft delete filter if enabled
        if ($this->usesSoftDeletes && !isset($opts['ignoreSoftDelete'])) {
            $whereClauses[] = "{$softDeleteRef}.is_deleted = 0";
        }

        // Build WHERE conditions from array
        foreach ($opts['where'] as $index => $condition) {
            if (!isset($condition['column'], $condition['value'])) {
                continue;
            }

            $column = $condition['column'];
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'];

            // Validate column name
            if (!preg_match('/^[a-zA-Z0-9_.]+$/', $column)) {
                throw new InvalidArgumentException("Invalid column name: {$column}");
            }

            // Validate operator
            $validOperators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'IN', 'IS NULL', 'IS NOT NULL'];
            if (!in_array(strtoupper($operator), $validOperators)) {
                throw new InvalidArgumentException("Invalid operator: {$operator}");
            }

            if (strtoupper($operator) === 'IS NULL') {
                $whereClauses[] = "{$column} IS NULL";
            } elseif (strtoupper($operator) === 'IS NOT NULL') {
                $whereClauses[] = "{$column} IS NOT NULL";
            } elseif (strtoupper($operator) === 'IN') {
                if (!is_array($value)) {
                    throw new InvalidArgumentException("IN operator requires array value");
                }

                $placeholders = [];
                foreach ($value as $k => $val) {
                    $placeholder = ":where_{$index}_{$k}";
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $val;
                }

                $whereClauses[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
            } else {
                $placeholder = ":where_{$index}";
                $whereClauses[] = "{$column} {$operator} {$placeholder}";
                $params[$placeholder] = $value;
            }
        }

        // Add raw WHERE conditions
        foreach ($opts['whereRaw'] as $rawWhere) {
            if (isset($rawWhere['sql'])) {
                $whereClauses[] = "(" . $rawWhere['sql'] . ")";

                if (isset($rawWhere['params']) && is_array($rawWhere['params'])) {
                    $params = array_merge($params, $rawWhere['params']);
                }
            }
        }

        if (!empty($whereClauses)) {
            $sql .= "WHERE " . implode(' AND ', $whereClauses) . " ";
        }

        // Build ORDER BY clause
        if (!empty($opts['orderBy'])) {
            $sql .= "ORDER BY {$opts['orderBy']} ";
        }

        // Build LIMIT and OFFSET
        if ($opts['limit'] !== null) {
            $sql .= "LIMIT :limit ";
            $params[':limit'] = (int)$opts['limit'];

            if ($opts['offset'] !== null) {
                $sql .= "OFFSET :offset ";
                $params[':offset'] = (int)$opts['offset'];
            }
        }

        try {
            $stmt = $this->db->executeQuery($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Query error: " . $e->getMessage() . " SQL: " . $sql);
        }
    }

    /**
     * Count records using flexible query builder
     *
     * @param array $options Same options as queryBuilder (select, joins, where ignored for COUNT)
     * @return int Record count
     */
    protected function queryBuilderCount(array $options = []): int
    {
        // Override select to COUNT
        $options['select'] = 'COUNT(*)';
        unset($options['orderBy'], $options['limit'], $options['offset']);

        $params = $options['params'] ?? [];
        $whereClauses = [];

        $alias = $options['alias'] ?? '';
        if ($alias !== '' && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException("Invalid alias: {$alias}");
        }
        $tableRef = $alias !== '' ? "{$this->table} {$alias}" : $this->table;
        $softDeleteRef = $alias !== '' ? $alias : $this->table;

        // Add soft delete filter if enabled
        if ($this->usesSoftDeletes && !isset($options['ignoreSoftDelete'])) {
            $whereClauses[] = "{$softDeleteRef}.is_deleted = 0";
        }

        // Build WHERE conditions
        foreach ($options['where'] ?? [] as $index => $condition) {
            if (!isset($condition['column'], $condition['value'])) {
                continue;
            }

            $column = $condition['column'];
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'];

            if (strtoupper($operator) === 'IS NULL') {
                $whereClauses[] = "{$column} IS NULL";
            } elseif (strtoupper($operator) === 'IS NOT NULL') {
                $whereClauses[] = "{$column} IS NOT NULL";
            } else {
                $placeholder = ":where_{$index}";
                $whereClauses[] = "{$column} {$operator} {$placeholder}";
                $params[$placeholder] = $value;
            }
        }

        // Add raw WHERE conditions
        foreach ($options['whereRaw'] ?? [] as $rawWhere) {
            if (isset($rawWhere['sql'])) {
                $whereClauses[] = "(" . $rawWhere['sql'] . ")";

                if (isset($rawWhere['params']) && is_array($rawWhere['params'])) {
                    $params = array_merge($params, $rawWhere['params']);
                }
            }
        }

        $sql = "SELECT COUNT(*) FROM {$tableRef} ";

        // Build JOINs if needed for count
        foreach ($options['joins'] ?? [] as $join) {
            $type = strtoupper($join['type'] ?? 'LEFT');
            $table = $join['table'];
            $on = $join['on'];
            $sql .= "{$type} JOIN {$table} ON {$on} ";
        }

        if (!empty($whereClauses)) {
            $sql .= "WHERE " . implode(' AND ', $whereClauses);
        }

        try {
            $stmt = $this->db->executeQuery($sql, $params);
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Count query error: " . $e->getMessage());
        }
    }
}
