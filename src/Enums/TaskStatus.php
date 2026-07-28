<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Task status enumeration
 * Maps to statuses_task table
 */
enum TaskStatus: int
{
    case OPEN = 1;
    case IN_PROGRESS = 2;
    case ON_HOLD = 3;
    case IN_REVIEW = 4;
    case CLOSED = 5;
    case COMPLETED = 6;

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::ON_HOLD => 'On Hold',
            self::IN_REVIEW => 'In Review',
            self::CLOSED => 'Closed',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Get status description
     */
    public function description(): string
    {
        return match ($this) {
            self::OPEN => 'Task is open and ready for work',
            self::IN_PROGRESS => 'Task is currently being worked on',
            self::ON_HOLD => 'Task is temporarily on hold',
            self::IN_REVIEW => 'Task is being reviewed',
            self::CLOSED => 'Task has been closed',
            self::COMPLETED => 'Task has been completed',
        };
    }

    /**
     * Get CSS color class for badges/displays
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::OPEN => 'text-blue-800 bg-blue-100',
            self::IN_PROGRESS => 'text-yellow-800 bg-yellow-100',
            self::ON_HOLD => 'text-gray-800 bg-gray-100',
            self::IN_REVIEW => 'text-purple-800 bg-purple-100',
            self::CLOSED => 'text-red-800 bg-red-100',
            self::COMPLETED => 'text-green-800 bg-green-100',
        };
    }

    /**
     * Check if status represents a completed state
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if status represents an active state
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::IN_PROGRESS, self::IN_REVIEW => true,
            default => false,
        };
    }

    /**
     * Check if status represents a blocked/paused state
     */
    public function isBlocked(): bool
    {
        return match ($this) {
            self::ON_HOLD, self::CLOSED => true,
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
