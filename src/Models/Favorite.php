<?php

//file: Models/Favorite.php
declare(strict_types=1);

namespace App\Models;

use App\Enums\FavoriteType;
use PDO;
use RuntimeException;

/**
 * Favorite Model
 *
 * Handles user favorites for pages, projects, tasks, milestones, and sprints
 */
class Favorite extends BaseModel
{
    protected string $table = 'user_favorites';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Favorite properties
     */
    public ?int $id = null;
    public int $user_id;
    public string $favorite_type;
    public ?int $favorite_id = null;
    public ?string $page_url = null;
    public string $page_title;
    public ?string $page_icon = null;
    public int $sort_order = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Define fillable fields
     */
    protected array $fillable = [
        'user_id', 'favorite_type', 'favorite_id', 'page_url',
        'page_title', 'page_icon', 'sort_order',
    ];

    /**
     * Define validation rules
     */
    protected array $validationRules = [
        'user_id' => ['required', 'integer'],
        'favorite_type' => ['required', 'enum:App\Enums\FavoriteType'],
        'page_title' => ['required', 'string', 'max:255'],
        'sort_order' => ['integer'],
    ];

    /**
     * Get all favorites for a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserFavorites(int $userId): array
    {
        try {
            error_log("Favorite::getUserFavorites called for user: " . $userId);
            $sql = "SELECT * FROM {$this->table}
                    WHERE user_id = :user_id
                    ORDER BY sort_order ASC, created_at ASC";

            error_log("SQL: " . $sql);
            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            error_log("Found " . count($results) . " favorites");

            return $results;
        } catch (\Exception $e) {
            error_log("Favorite::getUserFavorites error: " . $e->getMessage());

            throw new RuntimeException("Failed to get user favorites: " . $e->getMessage());
        }
    }

    /**
     * Add a favorite for a user
     *
     * @param int $userId
     * @param string $type
     * @param string $title
     * @param int|null $itemId
     * @param string|null $url
     * @param string|null $icon
     * @return bool
     */
    public function addFavorite(int $userId, string $type, string $title, ?int $itemId = null, ?string $url = null, ?string $icon = null): bool
    {
        try {
            // Check if favorite already exists
            if ($this->favoriteExists($userId, $type, $itemId, $url)) {
                return false; // Already exists
            }

            // Get next sort order
            $sortOrder = $this->getNextSortOrder($userId);

            $sql = "INSERT INTO {$this->table} 
                    (user_id, favorite_type, favorite_id, page_url, page_title, page_icon, sort_order) 
                    VALUES (:user_id, :type, :item_id, :url, :title, :icon, :sort_order)";

            $params = [
                ':user_id' => $userId,
                ':type' => $type,
                ':item_id' => $itemId,
                ':url' => $url,
                ':title' => $title,
                ':icon' => $icon,
                ':sort_order' => $sortOrder,
            ];

            $stmt = $this->db->executeQuery($sql, $params);

            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to add favorite: " . $e->getMessage());
        }
    }

    /**
     * Remove a favorite for a user
     *
     * @param int $userId
     * @param string $type
     * @param int|null $itemId
     * @param string|null $url
     * @return bool
     */
    public function removeFavorite(int $userId, string $type, ?int $itemId = null, ?string $url = null): bool
    {
        try {
            $sql = "DELETE FROM {$this->table} 
                    WHERE user_id = :user_id 
                    AND favorite_type = :type";

            $params = [
                ':user_id' => $userId,
                ':type' => $type,
            ];

            if ($itemId !== null) {
                $sql .= " AND favorite_id = :item_id";
                $params[':item_id'] = $itemId;
            } else {
                $sql .= " AND favorite_id IS NULL";
            }

            if ($url !== null) {
                $sql .= " AND page_url = :url";
                $params[':url'] = $url;
            } else {
                $sql .= " AND page_url IS NULL";
            }

            $stmt = $this->db->executeQuery($sql, $params);

            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to remove favorite: " . $e->getMessage());
        }
    }

    /**
     * Check if a favorite exists
     *
     * @param int $userId
     * @param string $type
     * @param int|null $itemId
     * @param string|null $url
     * @return bool
     */
    public function favoriteExists(int $userId, string $type, ?int $itemId = null, ?string $url = null): bool
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE user_id = :user_id 
                    AND favorite_type = :type";

            $params = [
                ':user_id' => $userId,
                ':type' => $type,
            ];

            if ($itemId !== null) {
                $sql .= " AND favorite_id = :item_id";
                $params[':item_id'] = $itemId;
            } else {
                $sql .= " AND favorite_id IS NULL";
            }

            if ($url !== null) {
                $sql .= " AND page_url = :url";
                $params[':url'] = $url;
            } else {
                $sql .= " AND page_url IS NULL";
            }

            $stmt = $this->db->executeQuery($sql, $params);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            return $result->count > 0;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to check favorite existence: " . $e->getMessage());
        }
    }

    /**
     * Get next sort order for user
     *
     * @param int $userId
     * @return int
     */
    private function getNextSortOrder(int $userId): int
    {
        try {
            $sql = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order 
                    FROM {$this->table} 
                    WHERE user_id = :user_id";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            return (int)$result->next_order;
        } catch (\Exception $e) {
            return 1; // Default to 1 if error
        }
    }

    /**
     * Update sort order for favorites
     *
     * @param int $userId
     * @param array $favoriteIds Array of favorite IDs in new order
     * @return bool
     */
    public function updateSortOrder(int $userId, array $favoriteIds): bool
    {
        try {
            $this->db->beginTransaction();

            foreach ($favoriteIds as $index => $favoriteId) {
                $sql = "UPDATE {$this->table} 
                        SET sort_order = :sort_order 
                        WHERE id = :id AND user_id = :user_id";

                $params = [
                    ':sort_order' => $index + 1,
                    ':id' => $favoriteId,
                    ':user_id' => $userId,
                ];

                $this->db->executeQuery($sql, $params);
            }

            $this->db->commit();

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw new RuntimeException("Failed to update sort order: " . $e->getMessage());
        }
    }
}
