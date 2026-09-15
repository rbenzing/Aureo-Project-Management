<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Builds the company → project → sprint → task chain the domain flows need, and
 * removes it again.
 *
 * Deliberately not built on DatabaseTestCase, which wraps each test in one
 * transaction and rolls it back: Sprint::assignTask() opens a transaction of its
 * own, and PDO does not nest — the inner begin throws, and the rollback that
 * follows unwinds the *outer* transaction. Explicit inserts and explicit
 * deletes keep the flows under test in charge of their own transactions.
 *
 * Rows are deleted in reverse dependency order, so a failing test leaves nothing
 * behind for the next one to trip over.
 */
trait DomainFixtures
{
    /** @var list<array{0:string,1:int}> table/id pairs, newest first */
    private array $fixtureRows = [];

    /** @var list<array{0:int,1:int}> sprint/task pairs to unlink */
    private array $fixtureSprintTasks = [];

    private function createCompany(string $name = 'Fixture Co'): int
    {
        $this->db->executeInsertUpdate(
            'INSERT INTO companies (guid, name, email) VALUES (UUID(), :name, :email)',
            [':name' => $name . ' ' . uniqid(), ':email' => 'fixture-' . uniqid() . '@example.test']
        );

        return $this->recordRow('companies');
    }

    private function createProject(int $companyId, string $name = 'Fixture Project'): int
    {
        $this->db->executeInsertUpdate(
            'INSERT INTO projects (guid, company_id, owner_id, status_id, name)
             VALUES (UUID(), :company, 1, 1, :name)',
            [':company' => $companyId, ':name' => $name]
        );

        return $this->recordRow('projects');
    }

    /**
     * @param array<string,mixed> $overrides columns to set beyond the defaults
     */
    private function createTask(int $projectId, array $overrides = []): int
    {
        $columns = $overrides + [
            'title' => 'Fixture task',
            'status_id' => 1,
            'is_subtask' => 0,
        ];

        $names = array_keys($columns);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $names);

        $params = [':project_id' => $projectId];
        foreach ($columns as $column => $value) {
            $params[':' . $column] = $value;
        }

        $this->db->executeInsertUpdate(
            sprintf(
                'INSERT INTO tasks (guid, project_id, %s) VALUES (UUID(), :project_id, %s)',
                implode(', ', $names),
                implode(', ', $placeholders)
            ),
            $params
        );

        return $this->recordRow('tasks');
    }

    private function createSprint(int $projectId, int $statusId = 1, string $name = 'Fixture Sprint'): int
    {
        $this->db->executeInsertUpdate(
            'INSERT INTO sprints (guid, project_id, name, start_date, end_date, status_id)
             VALUES (UUID(), :project, :name, CURDATE(), CURDATE() + INTERVAL 14 DAY, :status)',
            [':project' => $projectId, ':name' => $name, ':status' => $statusId]
        );

        return $this->recordRow('sprints');
    }

    private function linkSprintTask(int $sprintId, int $taskId): void
    {
        $this->db->executeInsertUpdate(
            'INSERT INTO sprint_tasks (sprint_id, task_id) VALUES (:sprint, :task)',
            [':sprint' => $sprintId, ':task' => $taskId]
        );

        $this->fixtureSprintTasks[] = [$sprintId, $taskId];
    }

    /** Remembers the row just inserted so tearDown can remove it. */
    private function recordRow(string $table): int
    {
        $id = (int) $this->db->getConnection()->lastInsertId();
        array_unshift($this->fixtureRows, [$table, $id]);

        return $id;
    }

    /** Call from tearDown(), before parent::tearDown(). */
    private function removeFixtures(): void
    {
        if ($this->db === null) {
            return;
        }

        foreach ($this->fixtureSprintTasks as [$sprintId, $taskId]) {
            $this->db->executeQuery(
                'DELETE FROM sprint_tasks WHERE sprint_id = :sprint AND task_id = :task',
                [':sprint' => $sprintId, ':task' => $taskId]
            );
        }
        $this->fixtureSprintTasks = [];

        foreach ($this->fixtureRows as [$table, $id]) {
            // sprint_tasks rows created by the code under test, rather than by
            // linkSprintTask(), are not tracked individually — clearing by id
            // covers both directions of that join.
            if ($table === 'sprints') {
                $this->db->executeQuery('DELETE FROM sprint_tasks WHERE sprint_id = :id', [':id' => $id]);
                $this->db->executeQuery('DELETE FROM sprint_milestones WHERE sprint_id = :id', [':id' => $id]);
            }
            if ($table === 'tasks') {
                $this->db->executeQuery('DELETE FROM sprint_tasks WHERE task_id = :id', [':id' => $id]);
                $this->db->executeQuery('DELETE FROM time_entries WHERE task_id = :id', [':id' => $id]);
            }

            $this->db->executeQuery(sprintf('DELETE FROM %s WHERE id = :id', $table), [':id' => $id]);
        }

        $this->fixtureRows = [];
    }
}
