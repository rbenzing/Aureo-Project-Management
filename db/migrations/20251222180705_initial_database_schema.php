<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialDatabaseSchema extends AbstractMigration
{
    /**
     * Migrate up - create all tables with BIGINT primary and foreign keys
     */
    public function up(): void
    {
        // Activity Logs table
        $this->execute("
            CREATE TABLE `activity_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED DEFAULT NULL,
                `session_id` VARCHAR(255) NOT NULL,
                `event_type` VARCHAR(50) NOT NULL,
                `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'Type of entity (project, task, user, etc.)',
                `entity_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID of the related entity',
                `method` VARCHAR(10) NOT NULL,
                `path` VARCHAR(255) NOT NULL,
                `query_string` TEXT DEFAULT NULL,
                `referer` VARCHAR(255) DEFAULT NULL,
                `user_agent` VARCHAR(255) DEFAULT NULL,
                `ip_address` VARCHAR(45) NOT NULL,
                `request_data` LONGTEXT DEFAULT NULL CHECK (json_valid(`request_data`)),
                `description` TEXT DEFAULT NULL COMMENT 'Human-readable description of the activity',
                `metadata` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional structured data about the activity' CHECK (json_valid(`metadata`)),
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `idx_session_id` (`session_id`),
                KEY `idx_event_type` (`event_type`),
                KEY `idx_created_at` (`created_at`),
                KEY `fk_activity_logs_user` (`user_id`),
                KEY `idx_entity` (`entity_type`,`entity_id`),
                KEY `idx_user_created` (`user_id`,`created_at`),
                KEY `idx_user_event_created` (`user_id`,`event_type`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Companies table
        $this->execute("
            CREATE TABLE `companies` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Owner/creator of the company',
                `name` VARCHAR(255) NOT NULL,
                `address` VARCHAR(500) DEFAULT NULL,
                `phone` VARCHAR(20) DEFAULT NULL,
                `email` VARCHAR(255) NOT NULL,
                `website` VARCHAR(255) DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`),
                KEY `guid` (`guid`),
                KEY `fk_companies_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Roles table
        $this->execute("
            CREATE TABLE `roles` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` CHAR(36) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `description` VARCHAR(500) DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`),
                KEY `guid` (`guid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Users table
        $this->execute("
            CREATE TABLE `users` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` CHAR(36) NOT NULL,
                `company_id` BIGINT UNSIGNED DEFAULT NULL,
                `role_id` BIGINT UNSIGNED NOT NULL,
                `first_name` VARCHAR(100) NOT NULL,
                `last_name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(15) DEFAULT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `activation_token` VARCHAR(255) DEFAULT NULL,
                `activation_token_expires_at` DATETIME DEFAULT NULL,
                `reset_password_token` VARCHAR(255) DEFAULT NULL,
                `reset_password_token_expires_at` DATETIME DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`),
                KEY `idx_users_email` (`email`),
                KEY `activation_token` (`activation_token`),
                KEY `reset_password_token` (`reset_password_token`),
                KEY `guid` (`guid`),
                KEY `fk_users_role` (`role_id`),
                KEY `fk_users_company` (`company_id`),
                KEY `idx_users_fullname` (`first_name`,`last_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Add foreign keys for companies and users
        $this->execute("ALTER TABLE `companies` ADD CONSTRAINT `fk_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
        $this->execute("ALTER TABLE `users` ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL");
        $this->execute("ALTER TABLE `users` ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)");

        // Company Projects junction table
        $this->execute("
            CREATE TABLE `company_projects` (
                `company_id` BIGINT UNSIGNED NOT NULL,
                `project_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`company_id`,`project_id`),
                KEY `company_id` (`company_id`,`project_id`),
                KEY `fk_company_projects_project` (`project_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // CSRF Tokens table
        $this->execute("
            CREATE TABLE `csrf_tokens` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `token` VARCHAR(255) NOT NULL,
                `session_id` VARCHAR(255) NOT NULL,
                `user_id` BIGINT UNSIGNED DEFAULT NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `session_id` (`session_id`,`user_id`),
                KEY `token` (`token`),
                KEY `fk_csrf_tokens_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `csrf_tokens` ADD CONSTRAINT `fk_csrf_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");

        // Permissions table
        $this->execute("
            CREATE TABLE `permissions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `description` VARCHAR(500) DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Role Permissions junction table
        $this->execute("
            CREATE TABLE `role_permissions` (
                `role_id` BIGINT UNSIGNED NOT NULL,
                `permission_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`role_id`,`permission_id`),
                KEY `permission_id` (`permission_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `role_permissions` ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `role_permissions` ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE");

        // Project Status table
        $this->execute("
            CREATE TABLE `statuses_project` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(50) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Projects table
        $this->execute("
            CREATE TABLE `projects` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` CHAR(36) NOT NULL,
                `company_id` BIGINT UNSIGNED NOT NULL COMMENT 'Associated company',
                `owner_id` BIGINT UNSIGNED NOT NULL COMMENT 'Project owner/manager',
                `status_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `start_date` DATE DEFAULT NULL,
                `end_date` DATE DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `status_id` (`status_id`),
                KEY `guid` (`guid`),
                KEY `fk_projects_company` (`company_id`),
                KEY `fk_projects_owner` (`owner_id`),
                KEY `idx_projects_dates` (`start_date`,`end_date`),
                FULLTEXT KEY `ft_project_name_description` (`name`,`description`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)");
        $this->execute("ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)");
        $this->execute("ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_status` FOREIGN KEY (`status_id`) REFERENCES `statuses_project` (`id`)");

        $this->execute("ALTER TABLE `company_projects` ADD CONSTRAINT `fk_company_projects_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `company_projects` ADD CONSTRAINT `fk_company_projects_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE");

        // Milestone Status table
        $this->execute("
            CREATE TABLE `statuses_milestone` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Milestones table
        $this->execute("
            CREATE TABLE `milestones` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` CHAR(36) NOT NULL,
                `project_id` BIGINT UNSIGNED NOT NULL COMMENT 'Associated project',
                `epic_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Parent milestone/epic',
                `status_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `milestone_type` ENUM('epic','milestone') NOT NULL DEFAULT 'milestone',
                `start_date` DATE DEFAULT NULL,
                `due_date` DATE DEFAULT NULL,
                `complete_date` DATE DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `status_id` (`status_id`),
                KEY `guid` (`guid`),
                KEY `fk_milestones_project` (`project_id`),
                KEY `fk_milestones_epic` (`epic_id`),
                FULLTEXT KEY `ft_milestone_title_description` (`title`,`description`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `milestones` ADD CONSTRAINT `fk_milestones_epic` FOREIGN KEY (`epic_id`) REFERENCES `milestones` (`id`) ON DELETE SET NULL");
        $this->execute("ALTER TABLE `milestones` ADD CONSTRAINT `fk_milestones_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `milestones` ADD CONSTRAINT `fk_milestones_status` FOREIGN KEY (`status_id`) REFERENCES `statuses_milestone` (`id`)");

        // Sprint Status table
        $this->execute("
            CREATE TABLE `statuses_sprint` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(50) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Sprints table
        $this->execute("
            CREATE TABLE `sprints` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` CHAR(36) NOT NULL,
                `project_id` BIGINT UNSIGNED NOT NULL COMMENT 'Associated project',
                `status_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `sprint_goal` TEXT DEFAULT NULL,
                `planning_date` DATETIME DEFAULT NULL,
                `review_date` DATETIME DEFAULT NULL,
                `retrospective_date` DATETIME DEFAULT NULL,
                `capacity_hours` INT(11) DEFAULT NULL,
                `capacity_story_points` INT(11) DEFAULT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `status_id` (`status_id`),
                KEY `guid` (`guid`),
                KEY `fk_sprints_project` (`project_id`),
                KEY `idx_sprints_dates` (`start_date`,`end_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `sprints` ADD CONSTRAINT `fk_sprints_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `sprints` ADD CONSTRAINT `fk_sprints_status` FOREIGN KEY (`status_id`) REFERENCES `statuses_sprint` (`id`)");

        // Sprint Milestones junction table
        $this->execute("
            CREATE TABLE `sprint_milestones` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `sprint_id` BIGINT UNSIGNED NOT NULL,
                `milestone_id` BIGINT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_sprint_milestone` (`sprint_id`,`milestone_id`),
                KEY `milestone_id` (`milestone_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `sprint_milestones` ADD CONSTRAINT `sprint_milestones_ibfk_1` FOREIGN KEY (`sprint_id`) REFERENCES `sprints` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `sprint_milestones` ADD CONSTRAINT `sprint_milestones_ibfk_2` FOREIGN KEY (`milestone_id`) REFERENCES `milestones` (`id`) ON DELETE CASCADE");

        // Task Status table
        $this->execute("
            CREATE TABLE `statuses_task` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(50) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tasks table
        $this->execute("
            CREATE TABLE `tasks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` CHAR(36) NOT NULL,
                `project_id` BIGINT UNSIGNED NOT NULL COMMENT 'Associated project',
                `assigned_to` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Assignee user',
                `parent_task_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Parent task for subtasks',
                `is_subtask` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `priority` ENUM('none','low','medium','high') NOT NULL DEFAULT 'none',
                `status_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
                `estimated_time` BIGINT DEFAULT NULL COMMENT 'Estimated time in seconds',
                `billable_time` BIGINT DEFAULT NULL COMMENT 'Billable time in seconds',
                `time_spent` BIGINT UNSIGNED DEFAULT 0 COMMENT 'Time spent in seconds',
                `hourly_rate` MEDIUMINT(9) DEFAULT NULL,
                `is_hourly` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `start_date` DATE DEFAULT NULL,
                `due_date` DATE DEFAULT NULL,
                `complete_date` DATE DEFAULT NULL,
                `story_points` TINYINT(3) UNSIGNED DEFAULT NULL COMMENT 'Story points for estimation (1-13 Fibonacci)',
                `acceptance_criteria` TEXT DEFAULT NULL COMMENT 'Definition of done criteria',
                `task_type` ENUM('story','bug','task','epic') NOT NULL DEFAULT 'task' COMMENT 'Scrum task type',
                `backlog_priority` INT(10) UNSIGNED DEFAULT NULL COMMENT 'Priority order in product backlog',
                `is_ready_for_sprint` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ready for sprint planning',
                `is_deleted` TINYINT(1) UNSIGNED DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `status_id` (`status_id`),
                KEY `guid` (`guid`),
                KEY `fk_tasks_project` (`project_id`),
                KEY `fk_tasks_assigned_to` (`assigned_to`),
                KEY `fk_tasks_parent` (`parent_task_id`),
                KEY `idx_tasks_due_date` (`due_date`),
                KEY `idx_tasks_priority_status` (`priority`,`status_id`),
                KEY `idx_tasks_assigned_status` (`assigned_to`,`status_id`),
                KEY `idx_backlog_priority` (`backlog_priority`),
                KEY `idx_task_type` (`task_type`),
                KEY `idx_ready_for_sprint` (`is_ready_for_sprint`),
                FULLTEXT KEY `ft_task_title_description` (`title`,`description`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `tasks` ADD CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL");
        $this->execute("ALTER TABLE `tasks` ADD CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `tasks` ADD CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `tasks` ADD CONSTRAINT `fk_tasks_status` FOREIGN KEY (`status_id`) REFERENCES `statuses_task` (`id`)");

        // Milestone Tasks junction table
        $this->execute("
            CREATE TABLE `milestone_tasks` (
                `milestone_id` BIGINT UNSIGNED NOT NULL,
                `task_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`milestone_id`,`task_id`),
                KEY `milestone_id` (`milestone_id`,`task_id`),
                KEY `fk_milestone_tasks_task` (`task_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `milestone_tasks` ADD CONSTRAINT `fk_milestone_tasks_milestone` FOREIGN KEY (`milestone_id`) REFERENCES `milestones` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `milestone_tasks` ADD CONSTRAINT `fk_milestone_tasks_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE");

        // Sprint Tasks junction table
        $this->execute("
            CREATE TABLE `sprint_tasks` (
                `sprint_id` BIGINT UNSIGNED NOT NULL,
                `task_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`sprint_id`,`task_id`),
                KEY `idx_sprint_tasks_task_id` (`task_id`),
                KEY `sprint_id` (`sprint_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `sprint_tasks` ADD CONSTRAINT `fk_sprint_tasks_sprint` FOREIGN KEY (`sprint_id`) REFERENCES `sprints` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `sprint_tasks` ADD CONSTRAINT `fk_sprint_tasks_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE");

        // Task Comments table
        $this->execute("
            CREATE TABLE `task_comments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `task_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `content` TEXT NOT NULL,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `fk_task_comments_task` (`task_id`),
                KEY `fk_task_comments_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `task_comments` ADD CONSTRAINT `fk_task_comments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `task_comments` ADD CONSTRAINT `fk_task_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");

        // Task History table
        $this->execute("
            CREATE TABLE `task_history` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `task_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `action` VARCHAR(50) NOT NULL COMMENT 'create, update, status_change, assignment, etc.',
                `field_changed` VARCHAR(50) DEFAULT NULL,
                `old_value` TEXT DEFAULT NULL,
                `new_value` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `fk_task_history_task` (`task_id`),
                KEY `fk_task_history_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `task_history` ADD CONSTRAINT `fk_task_history_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `task_history` ADD CONSTRAINT `fk_task_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");

        // Templates table
        $this->execute("
            CREATE TABLE `templates` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` CHAR(36) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NOT NULL,
                `template_type` ENUM('project','task','milestone','sprint') NOT NULL DEFAULT 'project',
                `company_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Can be organization-specific or null for global',
                `is_default` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `is_deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `guid` (`guid`),
                KEY `fk_templates_company` (`company_id`),
                KEY `template_type` (`template_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `templates` ADD CONSTRAINT `fk_templates_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)");

        // Time Entries table
        $this->execute("
            CREATE TABLE `time_entries` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `task_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `start_time` DATETIME NOT NULL,
                `end_time` DATETIME DEFAULT NULL,
                `duration` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Duration in seconds',
                `notes` TEXT DEFAULT NULL,
                `is_billable` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `fk_time_entries_task` (`task_id`),
                KEY `fk_time_entries_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `time_entries` ADD CONSTRAINT `fk_time_entries_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `time_entries` ADD CONSTRAINT `fk_time_entries_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");

        // Sessions table
        $this->execute("
            CREATE TABLE `sessions` (
                `id` VARCHAR(255) NOT NULL,
                `user_id` BIGINT UNSIGNED DEFAULT NULL,
                `data` TEXT NOT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(1024) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `expires_at` TIMESTAMP NULL DEFAULT NULL,
                `last_accessed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `sessions` ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");

        // Rate Limits table
        $this->execute("
            CREATE TABLE `rate_limits` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `identifier` VARCHAR(255) NOT NULL COMMENT 'User ID, IP address, or other identifier',
                `action` VARCHAR(100) NOT NULL COMMENT 'Action being rate limited (login, api_request, etc.)',
                `attempts` INT(10) UNSIGNED NOT NULL DEFAULT 1,
                `window_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `expires_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_rate_limit` (`identifier`,`action`),
                KEY `idx_rate_limits_expires` (`expires_at`),
                KEY `idx_rate_limits_identifier` (`identifier`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Settings table
        $this->execute("
            CREATE TABLE `settings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `category` VARCHAR(50) NOT NULL,
                `setting_key` VARCHAR(100) NOT NULL,
                `setting_value` TEXT DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_setting` (`category`,`setting_key`),
                KEY `idx_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // User Companies junction table
        $this->execute("
            CREATE TABLE `user_companies` (
                `user_id` BIGINT UNSIGNED NOT NULL,
                `company_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`user_id`,`company_id`),
                KEY `user_id` (`user_id`,`company_id`),
                KEY `fk_user_companies_company` (`company_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `user_companies` ADD CONSTRAINT `fk_user_companies_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `user_companies` ADD CONSTRAINT `fk_user_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");

        // User Favorites table
        $this->execute("
            CREATE TABLE `user_favorites` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `favorite_type` ENUM('project','task','milestone','sprint','page') NOT NULL,
                `favorite_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID of the favorited item (null for page favorites)',
                `page_url` VARCHAR(255) DEFAULT NULL COMMENT 'URL for page favorites',
                `page_title` VARCHAR(255) NOT NULL COMMENT 'Display title for the favorite',
                `page_icon` VARCHAR(50) DEFAULT NULL COMMENT 'Icon class or emoji for the favorite',
                `sort_order` INT(10) UNSIGNED DEFAULT 0 COMMENT 'User-defined sort order',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_user_favorite` (`user_id`,`favorite_type`,`favorite_id`,`page_url`),
                KEY `idx_user_favorites_user` (`user_id`),
                KEY `idx_user_favorites_type` (`favorite_type`),
                KEY `idx_user_favorites_sort` (`user_id`,`sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `user_favorites` ADD CONSTRAINT `fk_user_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");

        // User Projects junction table
        $this->execute("
            CREATE TABLE `user_projects` (
                `user_id` BIGINT UNSIGNED NOT NULL,
                `project_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`user_id`,`project_id`),
                KEY `user_id` (`user_id`,`project_id`),
                KEY `fk_user_projects_project` (`project_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute("ALTER TABLE `user_projects` ADD CONSTRAINT `fk_user_projects_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE");
        $this->execute("ALTER TABLE `user_projects` ADD CONSTRAINT `fk_user_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `activity_logs` ADD CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");

        // Insert seed data
        $this->insertSeedData();
    }

    /**
     * Insert default seed data
     */
    private function insertSeedData(): void
    {
        // Insert default role
        $this->execute("
            INSERT INTO `roles` (`id`, `guid`, `name`, `description`, `is_deleted`) VALUES
            (1, UUID(), 'admin', 'Administrator with full access', 0)
        ");

        // Insert permissions
        $permissions = [
            [1, 'view_dashboard', 'Can view dashboard'],
            [2, 'view_projects', 'Can view projects'],
            [3, 'create_projects', 'Can create projects'],
            [4, 'edit_projects', 'Can edit projects'],
            [5, 'delete_projects', 'Can delete projects'],
            [6, 'manage_projects', 'Can manage all projects'],
            [7, 'view_tasks', 'Can view tasks'],
            [8, 'create_tasks', 'Can create tasks'],
            [9, 'edit_tasks', 'Can edit tasks'],
            [10, 'delete_tasks', 'Can delete tasks'],
            [11, 'manage_tasks', 'Can manage all tasks'],
            [12, 'view_users', 'Can view users'],
            [13, 'create_users', 'Can create users'],
            [14, 'edit_users', 'Can edit users'],
            [15, 'delete_users', 'Can delete users'],
            [16, 'manage_users', 'Can manage all users'],
            [17, 'view_roles', 'Can view roles'],
            [18, 'create_roles', 'Can create roles'],
            [19, 'edit_roles', 'Can edit roles'],
            [20, 'delete_roles', 'Can delete roles'],
            [21, 'manage_roles', 'Can manage all roles'],
            [22, 'view_companies', 'Can view companies'],
            [23, 'create_companies', 'Can create companies'],
            [24, 'edit_companies', 'Can edit companies'],
            [25, 'delete_companies', 'Can delete companies'],
            [26, 'manage_companies', 'Can manage all companies'],
            [27, 'view_milestones', 'Can view milestones'],
            [28, 'create_milestones', 'Can create milestones'],
            [29, 'edit_milestones', 'Can edit milestones'],
            [30, 'delete_milestones', 'Can delete milestones'],
            [31, 'manage_milestones', 'Can manage all milestones'],
            [32, 'view_sprints', 'Can view sprints'],
            [33, 'create_sprints', 'Can create sprints'],
            [34, 'edit_sprints', 'Can edit sprints'],
            [35, 'delete_sprints', 'Can delete sprints'],
            [36, 'manage_sprints', 'Can manage all sprints'],
            [37, 'view_templates', 'Can view templates'],
            [38, 'create_templates', 'Can create templates'],
            [39, 'edit_templates', 'Can edit templates'],
            [40, 'delete_templates', 'Can delete templates'],
            [41, 'manage_templates', 'Can manage all templates'],
            [42, 'view_settings', 'Can view application settings'],
            [43, 'manage_settings', 'Can manage application settings'],
            [44, 'view_time_tracking', 'Can view time tracking'],
            [45, 'create_time_tracking', 'Can create time entries'],
            [46, 'edit_time_tracking', 'Can edit time entries'],
            [47, 'delete_time_tracking', 'Can delete time entries'],
            [48, 'manage_time_tracking', 'Can manage all time tracking'],
            [49, 'manage_sprint_settings', 'Manage sprint configuration settings'],
            [50, 'manage_task_settings', 'Manage task configuration settings'],
            [51, 'manage_milestone_settings', 'Manage milestone configuration settings'],
            [52, 'manage_project_settings', 'Manage project configuration settings'],
            [53, 'edit_settings', 'Edit general application settings'],
            [54, 'edit_security_settings', 'Edit security configuration settings'],
            [55, 'view_activity', 'Can view activity logs and audit trail'],
        ];

        foreach ($permissions as [$id, $name, $description]) {
            $this->execute("INSERT INTO `permissions` (`id`, `name`, `description`, `is_deleted`) VALUES ($id, '$name', '$description', 0)");
        }

        // Assign all permissions to admin role
        for ($i = 1; $i <= 55; $i++) {
            $this->execute("INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1, $i)");
        }

        // Insert status data
        $this->execute("INSERT INTO `statuses_milestone` (`id`, `name`, `description`, `is_deleted`) VALUES
            (1, 'Not Started', 'The milestone has not yet been started.', 0),
            (2, 'In Progress', 'The milestone is currently being worked on.', 0),
            (3, 'Completed', 'The milestone has been successfully completed.', 0)");

        $this->execute("INSERT INTO `statuses_project` (`id`, `name`, `description`, `is_deleted`) VALUES
            (1, 'ready', 'Project is ready to start', 0),
            (2, 'in_progress', 'Project is in progress', 0),
            (3, 'completed', 'Project is completed', 0),
            (4, 'on_hold', 'Project is on hold', 0),
            (6, 'delayed', 'Project is delayed', 0),
            (7, 'cancelled', 'Project is cancelled', 0)");

        $this->execute("INSERT INTO `statuses_sprint` (`id`, `name`, `description`, `is_deleted`) VALUES
            (1, 'planning', 'Sprint is in planning phase', 0),
            (2, 'active', 'Sprint is currently active', 0),
            (3, 'completed', 'Sprint has been completed', 0),
            (4, 'cancelled', 'Sprint was cancelled', 0),
            (5, 'delayed', 'Sprint has been delayed', 0),
            (6, 'review', 'Sprint in review phase', 0)");

        $this->execute("INSERT INTO `statuses_task` (`id`, `name`, `description`, `is_deleted`) VALUES
            (1, 'open', 'Task is open and ready for work', 0),
            (2, 'in_progress', 'Task is currently being worked on', 0),
            (3, 'on_hold', 'Task is temporarily on hold', 0),
            (4, 'in_review', 'Task is being reviewed', 0),
            (5, 'closed', 'Task has been closed', 0),
            (6, 'completed', 'Task has been completed', 0)");

        // Insert default admin user. Password = "password" (change on first login).
        $adminHash = password_hash('password', PASSWORD_ARGON2ID);
        $this->getAdapter()->getConnection()->prepare(
            "INSERT INTO `users` (`id`, `guid`, `company_id`, `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `is_active`, `is_deleted`)
             VALUES (1, UUID(), NULL, 1, 'Admin', 'User', 'admin@aureo.us', :hash, 1, 0)"
        )->execute([':hash' => $adminHash]);
    }

    /**
     * Migrate down - drop all tables
     */
    public function down(): void
    {
        // Drop tables in reverse order of dependencies
        $this->execute("SET FOREIGN_KEY_CHECKS = 0");

        $tables = [
            'user_projects', 'user_favorites', 'user_companies', 'time_entries',
            'templates', 'task_history', 'task_comments', 'sprint_tasks',
            'sprint_milestones', 'milestone_tasks', 'tasks', 'sprints',
            'milestones', 'projects', 'company_projects', 'sessions',
            'rate_limits', 'settings', 'csrf_tokens', 'role_permissions',
            'permissions', 'users', 'roles', 'companies', 'activity_logs',
            'statuses_task', 'statuses_sprint', 'statuses_milestone', 'statuses_project'
        ];

        foreach ($tables as $table) {
            $this->execute("DROP TABLE IF EXISTS `$table`");
        }

        $this->execute("SET FOREIGN_KEY_CHECKS = 1");
    }
}
