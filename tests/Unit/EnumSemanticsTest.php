<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\MilestoneStatus;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Behavioral tests for the enum predicates that drive business decisions —
 * sprint boards, backlog filters, progress rollups and story-point maths.
 *
 * EnumContractTest proves every accessor answers for every case; this file
 * pins down the answers that matter.
 */
#[CoversClass(Priority::class)]
#[CoversClass(TaskStatus::class)]
#[CoversClass(TaskType::class)]
#[CoversClass(ProjectStatus::class)]
#[CoversClass(SprintStatus::class)]
#[CoversClass(MilestoneStatus::class)]
final class EnumSemanticsTest extends TestCase
{
    // -------------------------------------------------------------- Priority

    public function testPrioritySortOrderRanksHighAboveLow(): void
    {
        $this->assertGreaterThan(Priority::MEDIUM->sortOrder(), Priority::HIGH->sortOrder());
        $this->assertGreaterThan(Priority::LOW->sortOrder(), Priority::MEDIUM->sortOrder());
        $this->assertGreaterThan(Priority::NONE->sortOrder(), Priority::LOW->sortOrder());
    }

    public function testPrioritySortOrderIsATotalOrdering(): void
    {
        $orders = array_map(fn ($c) => $c->sortOrder(), Priority::cases());

        $this->assertSame(
            count($orders),
            count(array_unique($orders)),
            'Two priorities share a sort order — ordering would be non-deterministic'
        );
    }

    public function testPriorityDefaultsToNoneOnUnknownInput(): void
    {
        $this->assertSame(Priority::NONE, Priority::fromOrDefault('urgent'));
        $this->assertSame(Priority::NONE, Priority::fromOrDefault(''));
    }

    public function testPriorityValuesMatchTheDatabaseEnum(): void
    {
        // tasks.priority is enum('none','low','medium','high') — drift here means
        // inserts fail at runtime with a truncation error.
        $this->assertSame(['none', 'low', 'medium', 'high'], Priority::values());
    }

    // ------------------------------------------------------------ TaskStatus

    public function testOnlyCompletedCountsAsCompleted(): void
    {
        foreach (TaskStatus::cases() as $case) {
            $this->assertSame(
                $case === TaskStatus::COMPLETED,
                $case->isCompleted(),
                "TaskStatus::{$case->name}->isCompleted() is wrong"
            );
        }
    }

    public function testActiveTaskStatusesAreInProgressAndInReview(): void
    {
        $active = array_values(array_filter(TaskStatus::cases(), fn ($c) => $c->isActive()));

        $this->assertSame([TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW], $active);
    }

    public function testBlockedTaskStatusesAreOnHoldAndClosed(): void
    {
        $blocked = array_values(array_filter(TaskStatus::cases(), fn ($c) => $c->isBlocked()));

        $this->assertSame([TaskStatus::ON_HOLD, TaskStatus::CLOSED], $blocked);
    }

    public function testTaskStatusPredicatesAreMutuallyExclusive(): void
    {
        foreach (TaskStatus::cases() as $case) {
            $flags = array_filter([$case->isCompleted(), $case->isActive(), $case->isBlocked()]);

            $this->assertLessThanOrEqual(
                1,
                count($flags),
                "TaskStatus::{$case->name} reports more than one lifecycle state"
            );
        }
    }

    public function testOpenIsNeitherActiveBlockedNorCompleted(): void
    {
        // OPEN means "ready to be picked up" — it must not be counted as work
        // in flight by dashboards.
        $this->assertFalse(TaskStatus::OPEN->isActive());
        $this->assertFalse(TaskStatus::OPEN->isBlocked());
        $this->assertFalse(TaskStatus::OPEN->isCompleted());
    }

    // -------------------------------------------------------------- TaskType

    public function testOnlyStoriesAndEpicsCarryStoryPoints(): void
    {
        $this->assertTrue(TaskType::STORY->hasStoryPoints());
        $this->assertTrue(TaskType::EPIC->hasStoryPoints());
        $this->assertFalse(TaskType::BUG->hasStoryPoints());
        $this->assertFalse(TaskType::TASK->hasStoryPoints());
    }

    public function testTaskTypeDefaultsToAConcreteCase(): void
    {
        $this->assertInstanceOf(TaskType::class, TaskType::fromOrDefault('nonsense'));
    }

    // --------------------------------------------------------- ProjectStatus

    public function testFinalProjectStatusesAreCompletedAndCancelled(): void
    {
        $final = array_values(array_filter(ProjectStatus::cases(), fn ($c) => $c->isFinal()));

        $this->assertSame([ProjectStatus::COMPLETED, ProjectStatus::CANCELLED], $final);
    }

    public function testFinalProjectStatusesAreNeitherActiveNorBlocked(): void
    {
        foreach (ProjectStatus::cases() as $case) {
            if (!$case->isFinal()) {
                continue;
            }

            $this->assertFalse($case->isActive(), "{$case->name} is final but reports active");
            $this->assertFalse($case->isBlocked(), "{$case->name} is final but reports blocked");
        }
    }

    public function testBlockedProjectStatusesAreOnHoldAndDelayed(): void
    {
        $blocked = array_values(array_filter(ProjectStatus::cases(), fn ($c) => $c->isBlocked()));

        $this->assertSame([ProjectStatus::ON_HOLD, ProjectStatus::DELAYED], $blocked);
    }

    public function testProjectStatusSkipsValueFive(): void
    {
        // The gap is deliberate (a removed status). Documented so a future edit
        // does not "helpfully" renumber and orphan existing rows.
        $this->assertNull(ProjectStatus::tryFromInt(5));
        $this->assertNotContains(5, ProjectStatus::values());
    }

    // ---------------------------------------------------------- SprintStatus

    public function testOnlyActiveSprintStatusIsActive(): void
    {
        foreach (SprintStatus::cases() as $case) {
            $this->assertSame($case === SprintStatus::ACTIVE, $case->isActive());
        }
    }

    public function testFinalSprintStatusesAreCompletedAndCancelled(): void
    {
        $final = array_values(array_filter(SprintStatus::cases(), fn ($c) => $c->isFinal()));

        $this->assertSame([SprintStatus::COMPLETED, SprintStatus::CANCELLED], $final);
    }

    public function testPlanningAndActiveSprintsAreNotFinal(): void
    {
        // Sprint planning assigns work to Planning + Active sprints; if either
        // ever reported final, the planning board would silently go empty.
        $this->assertFalse(SprintStatus::PLANNING->isFinal());
        $this->assertFalse(SprintStatus::ACTIVE->isFinal());
    }

    // ------------------------------------------------------- MilestoneStatus

    public function testMilestoneCompletionAndActivityAreDistinct(): void
    {
        $this->assertTrue(MilestoneStatus::COMPLETED->isCompleted());
        $this->assertFalse(MilestoneStatus::COMPLETED->isActive());

        $this->assertTrue(MilestoneStatus::IN_PROGRESS->isActive());
        $this->assertFalse(MilestoneStatus::IN_PROGRESS->isCompleted());

        $this->assertFalse(MilestoneStatus::NOT_STARTED->isActive());
        $this->assertFalse(MilestoneStatus::NOT_STARTED->isCompleted());
    }
}
