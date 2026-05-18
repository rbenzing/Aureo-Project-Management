<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Task Type Enum
 *
 * Represents Scrum/Agile task types.
 * Matches database ENUM: tasks.task_type enum('story','bug','task','epic')
 *
 * @see schema.sql:695
 */
enum TaskType: string
{
    case STORY = 'story';
    case BUG = 'bug';
    case TASK = 'task';
    case EPIC = 'epic';

    /**
     * Get human-readable label for this task type
     */
    public function label(): string
    {
        return match ($this) {
            self::STORY => 'User Story',
            self::BUG => 'Bug',
            self::TASK => 'Task',
            self::EPIC => 'Epic',
        };
    }

    /**
     * Get short label for this task type
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::STORY => 'Story',
            self::BUG => 'Bug',
            self::TASK => 'Task',
            self::EPIC => 'Epic',
        };
    }

    /**
     * Get icon class for this task type
     */
    public function icon(): string
    {
        return match ($this) {
            self::STORY => 'fas fa-book',
            self::BUG => 'fas fa-bug',
            self::TASK => 'fas fa-check-square',
            self::EPIC => 'fas fa-layer-group',
        };
    }

    /**
     * Get CSS color class for this task type
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::STORY => 'text-green-600',
            self::BUG => 'text-red-600',
            self::TASK => 'text-blue-600',
            self::EPIC => 'text-purple-600',
        };
    }

    /**
     * Get badge color class for this task type
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::STORY => 'bg-green-100 text-green-800',
            self::BUG => 'bg-red-100 text-red-800',
            self::TASK => 'bg-blue-100 text-blue-800',
            self::EPIC => 'bg-purple-100 text-purple-800',
        };
    }

    /**
     * Get description for this task type
     */
    public function description(): string
    {
        return match ($this) {
            self::STORY => 'A user story represents a feature from the end-user perspective',
            self::BUG => 'A bug is a defect or issue that needs to be fixed',
            self::TASK => 'A general task or work item',
            self::EPIC => 'A large body of work that can be broken down into stories',
        };
    }

    /**
     * Whether this task type typically has story points
     */
    public function hasStoryPoints(): bool
    {
        return match ($this) {
            self::STORY, self::EPIC => true,
            self::BUG, self::TASK => false,
        };
    }

    /**
     * Get all enum values as array (for validation)
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get validation rule string for use in validators
     */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * Get array for dropdowns [value => label]
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    /**
     * Create enum from string value or return default
     */
    public static function fromOrDefault(string $value, self $default = self::TASK): self
    {
        return self::tryFrom($value) ?? $default;
    }
}
