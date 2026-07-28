<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Milestone status enumeration
 * Maps to statuses_milestone table
 */
enum MilestoneStatus: int
{
    case NOT_STARTED = 1;
    case IN_PROGRESS = 2;
    case COMPLETED = 3;

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Not Started',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Get status description
     */
    public function description(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'The milestone has not yet been started',
            self::IN_PROGRESS => 'The milestone is currently being worked on',
            self::COMPLETED => 'The milestone has been successfully completed',
        };
    }

    /**
     * Get CSS color class for badges/displays
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'text-gray-800 bg-gray-100',
            self::IN_PROGRESS => 'text-yellow-800 bg-yellow-100',
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
        return $this === self::IN_PROGRESS;
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
