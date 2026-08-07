# Aureo — Functional Specification

**Status:** Living document · **Applies to:** `master` · **Last reviewed:** 2026-07-28

This is the reference for *what Aureo does* — its domain model, lifecycles, rules and
constraints. [ARCHITECTURE.md](./ARCHITECTURE.md) covers *how it is built*;
[USERGUIDE.md](./USERGUIDE.md) covers *how to operate it*.

> **This document is living.** It describes the behavior of the code on `master`, not an
> aspiration. When you change behavior, update the affected section in the same pull request.
> Anything not yet built belongs under [Out of scope](#out-of-scope) or
> [Planned](#planned-not-yet-built) — never described in the present tense.

---

## 1. Purpose and scope

Aureo is a self-hosted project, sprint and time-tracking system for agile teams. It targets
organizations that want to run delivery tracking on their own infrastructure and own the
underlying data outright.

**In scope:** multi-company portfolio structure, projects, epics and milestones, sprints, tasks
and subtasks, backlog management, sprint planning and boards, time tracking, reusable templates,
role-based access control, activity auditing and global search.

**Explicitly out of scope:** see [§10](#out-of-scope).

---

## 2. Domain model

31 tables. The core entities and their significant fields:

### Company
Top-level organizational grouping. Users and projects are associated with companies through the
`user_companies` and `company_projects` join tables, so both relationships are many-to-many.

### Project
`company_id`, `owner_id`, `status_id`, `name`, `description`, `start_date`, `end_date`.
The unit that owns milestones, sprints and tasks.

### Milestone
`project_id`, `epic_id`, `status_id`, `title`, `description`, `milestone_type`, `start_date`,
`due_date`, `complete_date`.

`milestone_type` is either **`epic`** or **`milestone`**. The self-referencing `epic_id` lets a
milestone hang beneath an epic, giving a two-level hierarchy within a project.

### Sprint
`project_id`, `status_id`, `name`, `description`, `sprint_goal`, `start_date`, `end_date`,
`planning_date`, `review_date`, `retrospective_date`, `capacity_hours`, `capacity_story_points`.

Sprints carry both an hours capacity and a story-point capacity; neither is enforced as a hard
limit on assignment.

### Task
`project_id`, `assigned_to`, `parent_task_id`, `is_subtask`, `title`, `description`, `priority`,
`status_id`, `task_type`, `story_points`, `acceptance_criteria`, `estimated_time`, `time_spent`,
`billable_time`, `hourly_rate`, `is_hourly`, `start_date`, `due_date`, `complete_date`,
`backlog_priority`, `is_ready_for_sprint`.

Subtasks are tasks with `parent_task_id` set and `is_subtask = 1` — one level of nesting.
`backlog_priority` drives manual backlog ordering; `is_ready_for_sprint` marks a task as
groomed and eligible for sprint planning.

### Time entry
`task_id`, `user_id`, `start_time`, `end_time`, `duration`, `notes`, `is_billable`.
Always attached to a task; there is no project-level or standalone time entry.

### User, Role, Permission
Users hold roles; roles bundle permissions (`role_permissions`). Authorization is checked at the
permission level, never the role level — see [§5](#5-authorization).

### Supporting tables
`task_comments`, `task_history`, `activity_logs`, `user_favorites`, `searchable_index`,
`search_queries`, `templates`, `sessions`, `csrf_tokens`, `rate_limits`, `settings`, and the
`statuses_*` lookup tables. Join tables: `sprint_tasks`, `sprint_milestones`, `milestone_tasks`,
`user_projects`, `user_companies`, `company_projects`, `role_permissions`.

### Cross-cutting conventions
- Every domain table carries `is_deleted`. **Deletion is always soft** — `BaseModel` injects
  `is_deleted = 0` into reads automatically. No user-facing action performs a hard delete.
- Most tables carry a `guid` alongside the numeric `id`.
- `created_at` / `updated_at` are maintained on all domain tables.

---

## 3. Lifecycles

Status values are integer-backed enums under `src/Enums/`. The integers are persisted, so
**existing values must never be renumbered**.

### Task status

| Value | Case | Semantics |
|---|---|---|
| 1 | `OPEN` | Ready to be picked up. Not counted as work in flight. |
| 2 | `IN_PROGRESS` | Active |
| 3 | `ON_HOLD` | Blocked |
| 4 | `IN_REVIEW` | Active |
| 5 | `CLOSED` | Blocked (terminal-ish; distinct from Completed) |
| 6 | `COMPLETED` | Completed |

The three predicates `isActive()`, `isBlocked()` and `isCompleted()` are **mutually exclusive** —
no status reports more than one, and `OPEN` reports none. Dashboards depend on this.

### Sprint status

| Value | Case | Active | Final |
|---|---|---|---|
| 1 | `PLANNING` | no | no |
| 2 | `ACTIVE` | **yes** | no |
| 3 | `COMPLETED` | no | **yes** |
| 4 | `CANCELLED` | no | **yes** |
| 5 | `DELAYED` | no | no |
| 6 | `REVIEW` | no | no |

Only `ACTIVE` is active. **Planning and Active sprints are the assignable ones** — sprint
planning offers exactly these as drop targets, so neither may ever report final.

Transitions are explicit operations, not free-form edits: **start**, **complete**, **delay** and
**cancel**.

### Project status

| Value | Case | Notes |
|---|---|---|
| 1 | `READY` | |
| 2 | `IN_PROGRESS` | the only status reporting active |
| 3 | `COMPLETED` | final |
| 4 | `ON_HOLD` | blocked |
| 6 | `DELAYED` | blocked |
| 7 | `CANCELLED` | final |

**Value 5 is intentionally absent** — a removed status. Do not reuse or renumber it; existing
rows reference these integers.

### Milestone status
`NOT_STARTED` (1), `IN_PROGRESS` (2), `COMPLETED` (3).

### Enumerated attributes

| Attribute | Values |
|---|---|
| Task priority | `none`, `low`, `medium`, `high` (ordered; `high` sorts highest) |
| Task type | `story`, `bug`, `task`, `epic` — only **story** and **epic** carry story points |
| Milestone type | `epic`, `milestone` |
| Template type | `project`, `task`, `milestone`, `sprint` |
| Favorite type | `project`, `task`, `milestone`, `sprint`, `page` |

---

## 4. Functional areas

### 4.1 Projects
CRUD, paginated listing, and per-project detail. The project list renders through four
interchangeable views over the same data: **table**, **charts**, **pivot** and **Gantt**.
Projects can be created from a project template.

### 4.2 Milestones and epics
CRUD, scoped globally or to a project. Presented as a table, cards or a timeline. Epics group
milestones; milestones group tasks via `milestone_tasks`.

### 4.3 Sprints
CRUD plus the four lifecycle operations. A sprint exposes a **board** view and a detail view.
Sprints may be created from a sprint template, from selected milestones, or directly out of the
planning screen.

### 4.4 Tasks and the backlog
CRUD, subtasks, comments (`task_comments`) and a change history (`task_history`). Listings filter
by project, assignee, or unassigned. The **backlog** is separately ordered by `backlog_priority`
and reorderable by drag.

### 4.5 Sprint planning
A dedicated screen for assigning backlog tasks to sprints by drag and drop.

Rules enforced:
- Only sprints in **Planning** or **Active** status are offered as targets.
- Drag-and-drop is wired **only** when sprint drop zones are present.
- Assignment requires the **`edit_sprints`** permission; without it the board is read-only.
- An invalid or soft-deleted project id shows an error rather than silently falling back to the
  project picker.

### 4.6 Time tracking
Start/stop timers on a task, plus manually entered time entries with notes and a billable flag.
A floating timer widget persists across pages. Entries roll up into the task's `time_spent`.

### 4.7 Templates
Reusable definitions for projects, tasks, milestones and sprints. Sprint templates are managed
through their own screens and can be applied to generate a sprint.

### 4.8 Search and navigation
A global index (`searchable_index`) is maintained by the `UpdateSearchIndex` listener in response
to domain events, and rebuildable via `bin/reindex-search.php`. Search is reachable from the
**command palette (Ctrl+K / Cmd+K)**. Queries are logged to `search_queries`, and click-throughs
are recorded to inform ranking. Users can pin favorites for fast access.

### 4.9 Activity log
A filterable audit trail of user activity, populated by `ActivityMiddleware` on each request.
Requires the `view_activity` permission.

### 4.10 Settings
Runtime configuration held in the `settings` table, grouped into eight sections: **General,
Security, Projects, Tasks, Milestones, Sprints, Templates, Developer**. Settings cover result
page size, date format, timezone, autosave interval, session timeout, time units, and the
security toggles in [§6](#6-security-requirements).

---

## 5. Authorization

**55 permissions** are seeded by the canonical migration. A single role — **`admin`** — is seeded
and granted all 55. Additional roles are created through the UI.

Checks are always permission-level (`hasUserPermission()` / `requirePermission()`), never
role-name comparisons.

| Domain | Permissions |
|---|---|
| Dashboard | `view_dashboard` |
| Projects | `view_projects`, `create_projects`, `edit_projects`, `delete_projects`, `manage_projects` |
| Tasks | `view_tasks`, `create_tasks`, `edit_tasks`, `delete_tasks`, `manage_tasks` |
| Users | `view_users`, `create_users`, `edit_users`, `delete_users`, `manage_users` |
| Roles | `view_roles`, `create_roles`, `edit_roles`, `delete_roles`, `manage_roles` |
| Companies | `view_companies`, `create_companies`, `edit_companies`, `delete_companies`, `manage_companies` |
| Milestones | `view_milestones`, `create_milestones`, `edit_milestones`, `delete_milestones`, `manage_milestones` |
| Sprints | `view_sprints`, `create_sprints`, `edit_sprints`, `delete_sprints`, `manage_sprints` |
| Templates | `view_templates`, `create_templates`, `edit_templates`, `delete_templates`, `manage_templates` |
| Time tracking | `view_time_tracking`, `create_time_tracking`, `edit_time_tracking`, `delete_time_tracking`, `manage_time_tracking` |
| Settings | `view_settings`, `manage_settings`, `edit_settings`, `edit_security_settings`, `manage_sprint_settings`, `manage_task_settings`, `manage_milestone_settings`, `manage_project_settings` |
| Activity | `view_activity` |

The `manage_*` permission in each domain denotes authority over **all** records in that domain,
as opposed to the holder's own.

**Adding a permission** requires a new migration that inserts the row and grants it to the roles
that need it. Never edit the canonical migration.

---

## 6. Security requirements

These are requirements, not descriptions — a change that weakens one is a defect. Operational
detail lives in [SECURITY.md](./SECURITY.md).

1. **Authentication gate runs before routing.** Any first URL segment outside `$publicPaths`
   (`login`, `register`, `activate`, `reset-password`, `forgot-password`) requires a session.
2. **Passwords are hashed with Argon2id**, through `App\Utils\PasswordHasher` — which falls back
   to `PASSWORD_DEFAULT` only on hosts whose PHP was built without libargon2, where naming
   Argon2id throws. Plaintext storage, MD5/SHA1, and hand-rolled crypto are prohibited.
3. **CSRF tokens are mandatory** on every state-changing request, validated by middleware and
   stored with expiry in `csrf_tokens`.
4. **Rate limiting is database-persisted** in `rate_limits` so it survives session resets, and is
   enforced before routing. Breach returns HTTP 429.
5. **All SQL is parameterized.** String interpolation into SQL is prohibited without exception.
6. **All output is escaped** with `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`.
7. **Request bodies over the configured limit are rejected** with HTTP 413.
8. **Security headers** — CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
   `Permissions-Policy` — are emitted on every response, with a hardcoded fallback set if
   settings are unavailable.
9. **Tokens expire.** Activation tokens last 24 hours, password-reset tokens 1 hour. Expired or
   cleared tokens must not resolve to a user, and a consumed token must not be replayable.
10. **Production refuses to boot without a non-empty `DB_PASSWORD`.**
11. **Error detail is suppressed** in production via `shouldHideErrorDetails()`.

---

## 7. Non-functional requirements

- **Platform:** PHP 8.2+, MySQL 8.0+. `declare(strict_types=1)` on all non-view PHP.
- **No framework.** No Laravel/Symfony/Eloquent/Blade, no ORM, no JavaScript framework. Vanilla
  PHP plus Tailwind CSS. This is a hard architectural constraint, not a preference.
- **Style:** PSR-12, enforced in CI across `src/`, `public/`, `tests/`, `bin/` and `config/`.
- **Schema of record** is the canonical Phinx migration. It is the only schema representation;
  there is no separate SQL dump.
- **Logging:** application errors land in `log/aureo.log`. 4xx are logged as terse warnings;
  full exception logging is reserved for 5xx.
- **Progressive enhancement:** pages render server-side; JavaScript adds the command palette,
  drag-and-drop and timers on top.

---

## 8. Known constraints

These are real, current limitations. They are documented rather than hidden.

- **Sprint capacity is advisory.** Neither `capacity_hours` nor `capacity_story_points` blocks
  over-assignment.
- **Subtasks nest one level.** `parent_task_id` is not walked recursively.
- **Time entries require a task.** There is no project-level or standalone time logging.
- **Event listeners run synchronously** in the request. There is no queue, so a slow listener
  slows the response.
- **Sessions and rate limits are database-backed**, making the database a hard dependency of
  every request.
- **No public API.** The `/api/*` routes exist to serve this application's own front end. They
  are session-authenticated and carry no versioning or stability guarantee.

---

## 9. Planned (not yet built)

Feature proposals live in `docs/specs/` (developer-local, not tracked). Nothing there should be
described as existing behavior until it ships and this document is updated.

---

## 10. Out of scope

Deliberately not part of Aureo:

- Hosted/multi-tenant SaaS operation — Aureo is single-instance, self-hosted.
- Billing, invoicing and payroll. Time entries carry a billable flag and hourly rate, but Aureo
  does not produce invoices.
- Real-time collaborative editing or push updates.
- Native mobile applications.
- Telemetry or usage analytics reported off-instance.

---

## 11. Keeping this document living

- Change behavior → update the affected section in the **same pull request**.
- Add a status, permission or entity → update [§2](#2-domain-model), [§3](#3-lifecycles) or
  [§5](#5-authorization), and add the migration.
- Hit a limitation worth recording → add it to [§8](#8-known-constraints) rather than leaving it
  folklore.
- Update the **Last reviewed** date at the top when you make a substantive pass.

Enum contract tests assert that every case answers every accessor, so an enum change that
outdates [§3](#3-lifecycles) will fail the suite before it reaches review.
