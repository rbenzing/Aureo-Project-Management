<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Template Type Enum
 *
 * Represents available template types in the system.
 * Matches database ENUM: templates.template_type enum('project','task','milestone','sprint')
 *
 * Replaces: Template::TEMPLATE_TYPES constant
 * @see schema.sql:768
 */
enum TemplateType: string
{
    case PROJECT = 'project';
    case TASK = 'task';
    case MILESTONE = 'milestone';
    case SPRINT = 'sprint';

    /**
     * Get human-readable label for this template type
     */
    public function label(): string
    {
        return match ($this) {
            self::PROJECT => 'Project',
            self::TASK => 'Task',
            self::MILESTONE => 'Milestone',
            self::SPRINT => 'Sprint',
        };
    }

    /**
     * Get plural label for this template type
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::PROJECT => 'Projects',
            self::TASK => 'Tasks',
            self::MILESTONE => 'Milestones',
            self::SPRINT => 'Sprints',
        };
    }

    /**
     * Get icon class for this template type
     */
    public function icon(): string
    {
        return match ($this) {
            self::PROJECT => 'fas fa-folder',
            self::TASK => 'fas fa-tasks',
            self::MILESTONE => 'fas fa-flag',
            self::SPRINT => 'fas fa-running',
        };
    }

    /**
     * Get CSS color class for this template type
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::PROJECT => 'text-blue-600',
            self::TASK => 'text-green-600',
            self::MILESTONE => 'text-purple-600',
            self::SPRINT => 'text-orange-600',
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
    public static function fromOrDefault(string $value, self $default = self::PROJECT): self
    {
        return self::tryFrom($value) ?? $default;
    }
}
