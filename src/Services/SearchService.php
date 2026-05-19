<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SearchRepository;

/**
 * SearchService
 *
 * Orchestrates search queries: delegates to the repository, records telemetry,
 * and formats results for callers.
 */
class SearchService
{
    private SearchRepository $repository;

    public function __construct(?SearchRepository $repository = null)
    {
        $this->repository = $repository ?? new SearchRepository();
    }

    /**
     * Run a search query and return formatted results.
     * Records query telemetry via logQuery().
     *
     * @param string   $query
     * @param int      $userId      The requesting user ID (for telemetry)
     * @param string[] $entityTypes Optional filter e.g. ['task','project']
     * @param int      $limit       Max results (default 30)
     * @return array{results: array, query: string, took_ms: int, count: int}
     */
    public function search(string $query, int $userId, array $entityTypes = [], int $limit = 30): array
    {
        $start = microtime(true);

        $trimmed = trim($query);

        if ($trimmed === '') {
            return [
                'results' => [],
                'query' => $trimmed,
                'took_ms' => 0,
                'count' => 0,
            ];
        }

        $rows = $this->repository->search($trimmed, $entityTypes, $limit);

        $tookMs = (int) round((microtime(true) - $start) * 1000);

        $this->repository->logQuery($userId, $trimmed, count($rows), $tookMs);

        return [
            'results' => $rows,
            'query' => $trimmed,
            'took_ms' => $tookMs,
            'count' => count($rows),
        ];
    }

    /**
     * Record that user clicked result at position $position.
     * Updates telemetry by logging a click event via the repository.
     *
     * @param int    $userId
     * @param string $query
     * @param int    $position 1-based position the user clicked
     * @return bool
     */
    public function recordClick(int $userId, string $query, int $position): bool
    {
        return $this->repository->logQuery($userId, $query, 0, 0, $position);
    }

    /**
     * Return the user's recent queries (pass-through to repository).
     *
     * @param int $userId
     * @param int $limit
     * @return array<object>
     */
    public function getRecentQueries(int $userId, int $limit = 10): array
    {
        return $this->repository->getRecentQueries($userId, $limit);
    }
}
