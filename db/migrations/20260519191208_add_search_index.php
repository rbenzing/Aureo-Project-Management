<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSearchIndex extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE `searchable_index` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_type` VARCHAR(32) NOT NULL,
                `entity_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL DEFAULT '',
                `snippet` VARCHAR(500) NOT NULL DEFAULT '',
                `project_id` BIGINT UNSIGNED NULL,
                `search_blob` TEXT NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_entity` (`entity_type`, `entity_id`),
                INDEX `idx_entity_project` (`entity_type`, `project_id`),
                FULLTEXT INDEX `ft_search_blob` (`search_blob`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("
            CREATE TABLE `search_queries` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `query` VARCHAR(255) NOT NULL,
                `result_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `clicked_position` TINYINT UNSIGNED NULL,
                `took_ms` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_user_created` (`user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `searchable_index`");
        $this->execute("DROP TABLE IF EXISTS `search_queries`");
    }
}
