<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Milestone Type Enum
 *
 * Represents milestone types in the system.
 * Matches database ENUM: milestones.milestone_type enum('epic','milestone')
 *
 * @see schema.sql:125
 */
enum MilestoneType: string
{
    case EPIC = 'epic';
    case MILESTONE = 'milestone';

    /**
     * Get human-readable label for this milestone type
     */
    public function label(): string
    {
        return match ($this) {
            self::EPIC => 'Epic',
            self::MILESTONE => 'Milestone',
        };
    }

    /**
     * Get description for this milestone type
     */
    public function description(): string
    {
        return match ($this) {
            self::EPIC => 'A large body of work containing multiple stories or milestones',
            self::MILESTONE => 'A significant checkpoint or deliverable in the project timeline',
        };
    }

    /**
     * Get icon class for this milestone type
     */
    public function icon(): string
    {
        return match ($this) {
            self::EPIC => 'fas fa-layer-group',
            self::MILESTONE => 'fas fa-flag-checkered',
        };
    }

    /**
     * Get CSS color class for this milestone type
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::EPIC => 'text-purple-600',
            self::MILESTONE => 'text-blue-600',
        };
    }

    /**
     * Get badge color class for this milestone type
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::EPIC => 'bg-purple-100 text-purple-800',
            self::MILESTONE => 'bg-blue-100 text-blue-800',
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
    public static function fromOrDefault(string $value, self $default = self::MILESTONE): self
    {
        return self::tryFrom($value) ?? $default;
    }
}
