<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Priority Enum
 *
 * Represents task priority levels.
 * Matches database ENUM: tasks.priority enum('none','low','medium','high')
 *
 * @see schema.sql:683
 */
enum Priority: string
{
    case NONE = 'none';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    /**
     * Get human-readable label for this priority
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
        };
    }

    /**
     * Get sort order value for database queries
     * Higher number = higher priority
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::HIGH => 3,
            self::MEDIUM => 2,
            self::LOW => 1,
            self::NONE => 0,
        };
    }

    /**
     * Get CSS color class for this priority
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::HIGH => 'text-red-600',
            self::MEDIUM => 'text-yellow-600',
            self::LOW => 'text-blue-600',
            self::NONE => 'text-gray-400',
        };
    }

    /**
     * Get badge color class for this priority
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::HIGH => 'bg-red-100 text-red-800',
            self::MEDIUM => 'bg-yellow-100 text-yellow-800',
            self::LOW => 'bg-blue-100 text-blue-800',
            self::NONE => 'bg-gray-100 text-gray-800',
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
    public static function fromOrDefault(string $value, self $default = self::NONE): self
    {
        return self::tryFrom($value) ?? $default;
    }
}
