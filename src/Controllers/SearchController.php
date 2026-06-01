<?php

//file: Controllers/SearchController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiResponse;
use App\Services\SearchService;

class SearchController extends BaseController
{
    private SearchService $searchService;

    public function __construct(?SearchService $searchService = null)
    {
        parent::__construct();
        $this->searchService = $searchService ?? new SearchService();
    }

    /**
     * GET /api/search?q=...&types[]=task&limit=20
     * Returns JSON search results. Auth-gated by middleware.
     */
    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        $entityTypes = (array)($_GET['types'] ?? []);
        $limit = min((int)($_GET['limit'] ?? 30), 50); // cap at 50

        if ($query === '') {
            ApiResponse::success(['results' => [], 'query' => '', 'took_ms' => 0, 'count' => 0]);

            return;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $result = $this->searchService->search($query, $userId, $entityTypes, $limit);

        // Format results for the command palette frontend
        $formatted = array_map(fn ($row) => [
            'entity_type' => $row->entity_type,
            'entity_id' => (int)$row->entity_id,
            'title' => $row->title,
            'snippet' => $row->snippet,
            'project_id' => $row->project_id !== null ? (int)$row->project_id : null,
            'url' => $this->resolveUrl($row->entity_type, (int)$row->entity_id),
        ], $result['results']);

        ApiResponse::success([
            'results' => $formatted,
            'query' => $result['query'],
            'took_ms' => $result['took_ms'],
            'count' => count($formatted),
        ]);
    }

    /**
     * POST /api/search/click  body: {query, position}
     * Records user click telemetry.
     */
    public function recordClick(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $query = trim($body['query'] ?? '');
        $position = (int)($body['position'] ?? 0);
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        if ($query === '' || $userId === 0) {
            ApiResponse::success(['ok' => true]); // silent no-op

            return;
        }

        $this->searchService->recordClick($userId, $query, $position);
        ApiResponse::success(['ok' => true]);
    }

    /**
     * GET /api/search/recent
     * Returns recent queries for the current user (for command palette history).
     */
    public function recentQueries(): void
    {
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $limit = min((int)($_GET['limit'] ?? 10), 20);

        $queries = $this->searchService->getRecentQueries($userId, $limit);
        ApiResponse::success(['queries' => $queries]);
    }

    private function resolveUrl(string $entityType, int $entityId): string
    {
        return match ($entityType) {
            'task' => "/tasks/view/{$entityId}",
            'project' => "/projects/view/{$entityId}",
            'user' => "/users/view/{$entityId}",
            'sprint' => "/sprints/view/{$entityId}",
            'milestone' => "/milestones/view/{$entityId}",
            'company' => "/companies/view/{$entityId}",
            default => "/{$entityType}s/view/{$entityId}",
        };
    }
}
