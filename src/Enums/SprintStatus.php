<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sprint status enumeration
 * Maps to statuses_sprint table
 */
enum SprintStatus: int
{
    case PLANNING = 1;
    case ACTIVE = 2;
    case COMPLETED = 3;
    case CANCELLED = 4;
    case DELAYED = 5;
    case REVIEW = 6;

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PLANNING => 'Planning',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::DELAYED => 'Delayed',
            self::REVIEW => 'Review',
        };
    }

    /**
     * Get status description
     */
    public function description(): string
    {
        return match ($this) {
            self::PLANNING => 'Sprint is in planning phase',
            self::ACTIVE => 'Sprint is currently active',
            self::COMPLETED => 'Sprint has been completed',
            self::CANCELLED => 'Sprint was cancelled',
            self::DELAYED => 'Sprint has been delayed',
            self::REVIEW => 'Sprint in review phase',
        };
    }

    /**
     * Get CSS color class for badges/displays
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::PLANNING => 'text-blue-800 bg-blue-100',
            self::ACTIVE => 'text-yellow-800 bg-yellow-100',
            self::COMPLETED => 'text-green-800 bg-green-100',
            self::CANCELLED => 'text-red-800 bg-red-100',
            self::DELAYED => 'text-orange-800 bg-orange-100',
            self::REVIEW => 'text-purple-800 bg-purple-100',
        };
    }

    /**
     * Check if status represents a completed/final state
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::CANCELLED => true,
            default => false,
        };
    }

    /**
     * Check if status represents an active state
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Get all status values as array
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get all status options for dropdowns
     * @return array<int, string>
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
     * Get validation rule string
     */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * Safe from integer with null return
     */
    public static function tryFromInt(?int $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
