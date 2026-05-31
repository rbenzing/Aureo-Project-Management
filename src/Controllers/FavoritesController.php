<?php

//file: Controllers/FavoritesController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Middleware\AuthMiddleware;
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
    public function index(): void
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                Response::json(['error' => 'User not authenticated'], 401);

                return;
            }

            $favorites = $this->favoriteModel->getUserFavorites($userId);

            Response::json([
                'success' => true,
                'favorites' => $favorites,
            ]);
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::index');
            Response::json(['error' => 'Failed to get favorites: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add a favorite (AJAX endpoint)
     */
    public function add(): void
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                Response::json(['error' => 'User not authenticated'], 401);

                return;
            }

            // Validate CSRF token
            if (!$this->validateCsrfToken()) {
                Response::json(['error' => 'Invalid CSRF token'], 403);

                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                Response::json(['error' => 'Invalid JSON input'], 400);

                return;
            }

            $type = $input['type'] ?? '';
            $title = $input['title'] ?? '';
            $itemId = $input['item_id'] ?? null;
            $url = $input['url'] ?? null;
            $icon = $input['icon'] ?? null;

            if (empty($type) || empty($title)) {
                Response::json(['error' => 'Type and title are required'], 400);

                return;
            }

            // Validate type
            $validTypes = ['project', 'task', 'milestone', 'sprint', 'page'];
            if (!in_array($type, $validTypes)) {
                Response::json(['error' => 'Invalid favorite type'], 400);

                return;
            }

            $success = $this->favoriteModel->addFavorite($userId, $type, $title, $itemId, $url, $icon);

            if ($success) {
                Response::json([
                    'success' => true,
                    'message' => 'Favorite added successfully',
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Favorite already exists or could not be added',
                ]);
            }
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::add');
            Response::json(['error' => 'Failed to add favorite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove a favorite (AJAX endpoint)
     */
    public function remove(): void
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                Response::json(['error' => 'User not authenticated'], 401);

                return;
            }

            // Validate CSRF token (was disabled — left remove() CSRF-able while
            // add() and updateOrder() validated; restored for consistency).
            if (!$this->validateCsrfToken()) {
                Response::json(['error' => 'Invalid CSRF token'], 403);

                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                Response::json(['error' => 'Invalid JSON input'], 400);

                return;
            }

            $type = $input['type'] ?? '';
            $itemId = $input['item_id'] ?? null;
            $url = $input['url'] ?? null;

            if (empty($type)) {
                Response::json(['error' => 'Type is required'], 400);

                return;
            }

            $success = $this->favoriteModel->removeFavorite($userId, $type, $itemId, $url);

            if ($success) {
                Response::json([
                    'success' => true,
                    'message' => 'Favorite removed successfully',
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Favorite not found or could not be removed',
                ]);
            }
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::remove');
            Response::json(['error' => 'Failed to remove favorite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update favorites sort order (AJAX endpoint)
     */
    public function updateOrder(): void
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                Response::json(['error' => 'User not authenticated'], 401);

                return;
            }

            // Validate CSRF token
            if (!$this->validateCsrfToken()) {
                Response::json(['error' => 'Invalid CSRF token'], 403);

                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['favorite_ids']) || !is_array($input['favorite_ids'])) {
                Response::json(['error' => 'Invalid input: favorite_ids array required'], 400);

                return;
            }

            $favoriteIds = array_map('intval', $input['favorite_ids']);

            $success = $this->favoriteModel->updateSortOrder($userId, $favoriteIds);

            if ($success) {
                Response::json([
                    'success' => true,
                    'message' => 'Sort order updated successfully',
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to update sort order',
                ]);
            }
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::updateOrder');
            Response::json(['error' => 'Failed to update sort order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if item is favorited (AJAX endpoint)
     */
    public function check(): void
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                Response::json(['error' => 'User not authenticated'], 401);

                return;
            }

            $type = $_GET['type'] ?? '';
            $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
            $url = $_GET['url'] ?? null;

            if (empty($type)) {
                Response::json(['error' => 'Type is required'], 400);

                return;
            }

            $exists = $this->favoriteModel->favoriteExists($userId, $type, $itemId, $url);

            Response::json([
                'success' => true,
                'is_favorited' => $exists,
            ]);
        } catch (\Throwable $e) {
            $this->logException($e, 'FavoritesController::check');
            Response::json(['error' => 'Failed to check favorite: ' . $e->getMessage()], 500);
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
