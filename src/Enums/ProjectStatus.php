<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Project status enumeration
 * Maps to statuses_project table
 */
enum ProjectStatus: int
{
    case READY = 1;
    case IN_PROGRESS = 2;
    case COMPLETED = 3;
    case ON_HOLD = 4;
    case DELAYED = 6;
    case CANCELLED = 7;

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::READY => 'Ready',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::ON_HOLD => 'On Hold',
            self::DELAYED => 'Delayed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get status description
     */
    public function description(): string
    {
        return match ($this) {
            self::READY => 'Project is ready to start',
            self::IN_PROGRESS => 'Project is in progress',
            self::COMPLETED => 'Project is completed',
            self::ON_HOLD => 'Project is on hold',
            self::DELAYED => 'Project is delayed',
            self::CANCELLED => 'Project is cancelled',
        };
    }

    /**
     * Get CSS color class for badges/displays
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::READY => 'text-blue-800 bg-blue-100',
            self::IN_PROGRESS => 'text-yellow-800 bg-yellow-100',
            self::COMPLETED => 'text-green-800 bg-green-100',
            self::ON_HOLD => 'text-gray-800 bg-gray-100',
            self::DELAYED => 'text-orange-800 bg-orange-100',
            self::CANCELLED => 'text-red-800 bg-red-100',
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
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if status represents a blocked/paused state
     */
    public function isBlocked(): bool
    {
        return match ($this) {
            self::ON_HOLD, self::DELAYED => true,
            default => false,
        };
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
