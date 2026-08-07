<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * `key_code` is a short human-readable project identifier (e.g. "APO" for
 * "Apollo Program"). Model, $fillable, ProjectService::generateKeyCode(),
 * keyCodeExists() and the CreateProjectRequest validation all shipped, but the
 * column never did - so every INSERT from ProjectController::create() failed
 * with SQLSTATE[42S22].
 *
 * NULL is permitted because rows created before this migration have no value,
 * and MySQL allows any number of NULLs under a UNIQUE index, so the constraint
 * can be added without backfilling.
 */
final class AddProjectKeyCode extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE `projects`
            ADD COLUMN `key_code` VARCHAR(10) NULL DEFAULT NULL AFTER `name`,
            ADD UNIQUE INDEX `uq_projects_key_code` (`key_code`)
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE `projects`
            DROP INDEX `uq_projects_key_code`,
            DROP COLUMN `key_code`
        ");
    }
}
