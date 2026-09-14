<?php

//file: Controllers/FavoritesController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Response;
use App\Models\Favorite;

/**
 * Favorites Controller
 *
 * Handles user favorites management
 */
class FavoritesController extends BaseController
{
    private Favorite $favoriteModel;

    public function __construct(?Favorite $favoriteModel = null)
    {
        parent::__construct();
        $this->favoriteModel = $favoriteModel ?? new Favorite();
    }

    /**
     * Get user favorites (AJAX endpoint)
     */
    public function index(): HttpResponse
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                return Response::json(['error' => 'User not authenticated'], 401);
            }

            $favorites = $this->favoriteModel->getUserFavorites($userId);

            return Response::json([
                'success' => true,
                'favorites' => $favorites,
            ]);
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::index');

            return Response::json(['error' => 'Failed to get favorites: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add a favorite (AJAX endpoint)
     */
    public function add(): HttpResponse
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                return Response::json(['error' => 'User not authenticated'], 401);
            }

            // Validate CSRF token
            if (!$this->validateCsrfToken()) {
                return Response::json(['error' => 'Invalid CSRF token'], 403);
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                return Response::json(['error' => 'Invalid JSON input'], 400);
            }

            $type = $input['type'] ?? '';
            $title = $input['title'] ?? '';
            $itemId = $input['item_id'] ?? null;
            $url = $input['url'] ?? null;
            $icon = $input['icon'] ?? null;

            if (empty($type) || empty($title)) {
                return Response::json(['error' => 'Type and title are required'], 400);
            }

            // Validate type
            $validTypes = ['project', 'task', 'milestone', 'sprint', 'page'];
            if (!in_array($type, $validTypes)) {
                return Response::json(['error' => 'Invalid favorite type'], 400);
            }

            $success = $this->favoriteModel->addFavorite($userId, $type, $title, $itemId, $url, $icon);

            if ($success) {
                return Response::json([
                    'success' => true,
                    'message' => 'Favorite added successfully',
                ]);
            }

            return Response::json([
                'success' => false,
                'message' => 'Favorite already exists or could not be added',
            ]);
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::add');

            return Response::json(['error' => 'Failed to add favorite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove a favorite (AJAX endpoint)
     */
    public function remove(): HttpResponse
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                return Response::json(['error' => 'User not authenticated'], 401);
            }

            // Validate CSRF token (was disabled — left remove() CSRF-able while
            // add() and updateOrder() validated; restored for consistency).
            if (!$this->validateCsrfToken()) {
                return Response::json(['error' => 'Invalid CSRF token'], 403);
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                return Response::json(['error' => 'Invalid JSON input'], 400);
            }

            $type = $input['type'] ?? '';
            $itemId = $input['item_id'] ?? null;
            $url = $input['url'] ?? null;

            if (empty($type)) {
                return Response::json(['error' => 'Type is required'], 400);
            }

            $success = $this->favoriteModel->removeFavorite($userId, $type, $itemId, $url);

            if ($success) {
                return Response::json([
                    'success' => true,
                    'message' => 'Favorite removed successfully',
                ]);
            }

            return Response::json([
                'success' => false,
                'message' => 'Favorite not found or could not be removed',
            ]);
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::remove');

            return Response::json(['error' => 'Failed to remove favorite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update favorites sort order (AJAX endpoint)
     */
    public function updateOrder(): HttpResponse
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                return Response::json(['error' => 'User not authenticated'], 401);
            }

            // Validate CSRF token
            if (!$this->validateCsrfToken()) {
                return Response::json(['error' => 'Invalid CSRF token'], 403);
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['favorite_ids']) || !is_array($input['favorite_ids'])) {
                return Response::json(['error' => 'Invalid input: favorite_ids array required'], 400);
            }

            $favoriteIds = array_map('intval', $input['favorite_ids']);

            $success = $this->favoriteModel->updateSortOrder($userId, $favoriteIds);

            if ($success) {
                return Response::json([
                    'success' => true,
                    'message' => 'Sort order updated successfully',
                ]);
            }

            return Response::json([
                'success' => false,
                'message' => 'Failed to update sort order',
            ]);
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::updateOrder');

            return Response::json(['error' => 'Failed to update sort order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if item is favorited (AJAX endpoint)
     */
    public function check(): HttpResponse
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                return Response::json(['error' => 'User not authenticated'], 401);
            }

            $type = $_GET['type'] ?? '';
            $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
            $url = $_GET['url'] ?? null;

            if (empty($type)) {
                return Response::json(['error' => 'Type is required'], 400);
            }

            $exists = $this->favoriteModel->favoriteExists($userId, $type, $itemId, $url);

            return Response::json([
                'success' => true,
                'is_favorited' => $exists,
            ]);
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::check');

            return Response::json(['error' => 'Failed to check favorite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrfToken(): bool
    {
        // Get token from various sources
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ??
                 $_POST['csrf_token'] ??
                 (getallheaders()['X-CSRF-Token'] ?? '');

        $sessionToken = $_SESSION['csrf_token'] ?? '';

        // Log for debugging
        $this->logger->warning('CSRF Debug', [
            'token' => substr($token, 0, 10) . '...',
            'session' => substr($sessionToken, 0, 10) . '...',
        ]);

        return !empty($token) && !empty($sessionToken) && hash_equals($sessionToken, $token);
    }
}
