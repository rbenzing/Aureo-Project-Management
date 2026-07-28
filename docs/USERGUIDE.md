# Aureo — User Guide

How to use Aureo day to day. For installation and server operation see
[DEPLOYMENT.md](./DEPLOYMENT.md); for what the system does and why, see [SPEC.md](./SPEC.md).

---

## Contents

- [Getting started](#getting-started)
- [Finding your way around](#finding-your-way-around)
- [Companies](#companies)
- [Projects](#projects)
- [Epics and milestones](#epics-and-milestones)
- [Tasks](#tasks)
- [The backlog](#the-backlog)
- [Sprints](#sprints)
- [Sprint planning](#sprint-planning)
- [Time tracking](#time-tracking)
- [Templates](#templates)
- [Users, roles and permissions](#users-roles-and-permissions)
- [Settings](#settings)
- [Activity log](#activity-log)
- [FAQ](#faq)

---

## Getting started

### First login

Your administrator provides a URL and credentials. A fresh installation seeds a single
administrator:

| Email | Password |
|---|---|
| `admin@aureo.us` | `password` |

**Change this password immediately** after the first login — Settings → Profile.

### Registering and activating an account

If registration is open on your instance, sign up at `/register`. You will receive an activation
email; the link is valid for **24 hours**. If it expires, ask an administrator to re-send it.

### Forgotten password

Use **Forgot password** on the login screen. The reset link is valid for **1 hour** and works
**once** — request a new one if it expires or you have already used it.

---

## Finding your way around

### The command palette

Press **Ctrl+K** (**Cmd+K** on macOS) anywhere in the application. This is the fastest way to
move around: start typing and it searches projects, tasks, milestones and sprints, and offers
direct navigation to pages.

### Global search

The same index powers the search box. Results are ranked, and the system learns from which
results people actually open.

If search results look stale or incomplete, an administrator can rebuild the index — see
[DEPLOYMENT.md](./DEPLOYMENT.md#rebuilding-the-search-index).

### Favorites

Pin the things you touch daily. You can favorite **projects, tasks, milestones, sprints and
pages**. Favorites appear in the sidebar and can be reordered by dragging.

### Dashboard

The landing page: workload, status breakdowns and progress across the projects you can see.

### What you can see

Aureo hides what you lack permission for. If a menu item or button is missing, that is
permissions rather than a bug — ask an administrator. See
[Users, roles and permissions](#users-roles-and-permissions).

---

## Companies

Companies are the top grouping — typically a client or a business unit. Projects belong to
companies, and users can be associated with several.

**Create:** Companies → Create. **Manage:** open a company to see its projects and people.

Deleting a company is a soft delete: it disappears from listings but nothing is destroyed.

---

## Projects

A project owns milestones, sprints and tasks.

**Create:** Projects → Create. Set a name, description, owning company, owner, status and
optional start/end dates. You can base a new project on a **project template**.

### The four project views

The project list presents the same data four ways — switch between them at the top:

| View | Best for |
|---|---|
| **Table** | Scanning and sorting a lot of projects |
| **Charts** | Status and progress at a glance |
| **Pivot** | Cross-tabulating, e.g. status against company |
| **Gantt** | Schedules and overlap across time |

### Project status

**Ready → In Progress → Completed**, with **On Hold**, **Delayed** and **Cancelled** available.
Only *In Progress* counts as actively running; *Completed* and *Cancelled* are final.

---

## Epics and milestones

Both live under Milestones and differ by **type**:

- **Epic** — a large body of work that groups milestones.
- **Milestone** — a dated checkpoint that groups tasks.

A milestone can sit beneath an epic, giving you two levels of grouping inside a project.

**Views:** table, cards, or a **timeline** showing due dates in sequence.

**Status:** Not Started → In Progress → Completed.

---

## Tasks

The unit of work.

**Create:** Tasks → Create, or from within a project.

### Fields worth understanding

| Field | Notes |
|---|---|
| **Type** | `story`, `bug`, `task` or `epic`. Only **stories and epics** carry story points. |
| **Priority** | `none`, `low`, `medium`, `high` — drives ordering. |
| **Story points** | Effort estimate for stories and epics. |
| **Acceptance criteria** | What "done" means. Fill this in — it is what reviewers check against. |
| **Estimated time** | Expected hours, compared against tracked time. |
| **Assignee** | Who owns it. Tasks may be left unassigned and picked up later. |
| **Ready for sprint** | Marks the task as groomed and eligible for sprint planning. |
| **Due date** | Drives overdue indicators. |

### Subtasks

Break a task into subtasks from the task detail screen. Subtasks nest **one level** — a subtask
cannot itself have subtasks.

### Comments and history

Each task keeps a comment thread and an automatic change history, so you can see what changed
and when without asking.

### Task status

**Open → In Progress → In Review → Completed**, with **On Hold** and **Closed** available.

*Open* means "ready to be picked up" and is deliberately **not** counted as work in flight —
only *In Progress* and *In Review* are. *Closed* and *Completed* differ: Closed is for work
stopped without completion.

### Filtering

Task lists filter by project, by assignee, or to unassigned work.

---

## The backlog

**Tasks → Backlog** is your ordered queue of upcoming work.

- Drag tasks to reorder. The order is stored, so everyone sees the same priority.
- Mark groomed tasks **ready for sprint** so they surface during planning.
- Keep the top of the backlog genuinely ready — that is what makes planning fast.

---

## Sprints

A time-boxed block of work inside a project.

**Create:** Sprints → Create, from a **sprint template**, from selected **milestones**, or
directly from the planning screen.

### Fields

Name, goal, description, start and end dates, plus optional **planning**, **review** and
**retrospective** dates. Capacity can be set in **hours** and in **story points**.

> Capacity is **advisory** — Aureo shows it but will not stop you over-committing a sprint.

### Sprint lifecycle

Four explicit actions rather than free-form status editing:

| Action | Effect |
|---|---|
| **Start** | Planning → Active |
| **Complete** | → Completed (final) |
| **Delay** | → Delayed |
| **Cancel** | → Cancelled (final) |

There is also a **Review** status for sprints in review.

Only sprints in **Planning** or **Active** accept task assignment.

### The sprint board

**Sprints → Board** shows the sprint's tasks by status — the day-to-day working view during a
sprint.

### Current sprint

**Sprints → Current** jumps straight to what is running now.

---

## Sprint planning

**Tasks → Sprint Planning** is where the backlog meets sprints.

1. Pick a project.
2. Your backlog appears alongside that project's assignable sprints.
3. **Drag tasks onto a sprint** to assign them.

Things to know:

- Only **Planning** and **Active** sprints appear as drop targets. A completed or cancelled
  sprint will not accept work.
- Assigning requires the **`edit_sprints`** permission. Without it the board is **read-only** —
  you can see the plan but not change it.
- If the project has no assignable sprints, the board still renders — create a sprint first.

---

## Time tracking

### Timers

Start a timer from a task. A **floating timer** follows you across pages so you do not lose
track of what is running. Stop it from the widget or the task.

### Manual entries

**Time Tracking → Create** to log time after the fact: task, start and end, notes, and whether
the time is **billable**.

> Time is always logged **against a task**. If you need to track something not represented by a
> task, create the task first.

### Reviewing time

The Time Tracking list filters by project, user and date range. Tracked time rolls up onto the
task so estimates can be compared against reality.

---

## Templates

Templates save re-typing the same structure.

| Type | Use |
|---|---|
| **Project** | Standard project shape for repeat engagements |
| **Task** | Recurring task definitions |
| **Milestone** | Standard checkpoints |
| **Sprint** | Standard sprint setup, managed under Sprint Templates |

Create under Templates; apply when creating the corresponding record.

---

## Users, roles and permissions

Administrative area — requires the relevant `manage_*` permissions.

### Users

Create, edit, deactivate. Users are associated with companies and projects, which governs what
they see.

### Roles and permissions

Aureo checks **permissions**, not job titles. A role is a named bundle of permissions, and there
are **55** covering every area: viewing, creating, editing, deleting and managing projects,
tasks, users, roles, companies, milestones, sprints, templates, time tracking, settings and the
activity log.

The seeded **`admin`** role holds all 55. Build additional roles to match how your team works —
for example a delivery lead with `edit_sprints` and full task permissions, and a stakeholder role
with only the `view_*` permissions.

Within each area, the `manage_*` permission means authority over **everyone's** records, not just
your own. Grant it deliberately.

---

## Settings

**Settings** (requires `view_settings`; changes require the corresponding `manage_*` or `edit_*`
permission) is organized into eight tabs:

| Tab | Covers |
|---|---|
| **General** | Results per page, date format, timezone, autosave interval, session timeout |
| **Security** | CSRF protection, rate limiting, redirect validation, content sanitization |
| **Projects** | Project defaults |
| **Tasks** | Task defaults and time units |
| **Milestones** | Milestone defaults |
| **Sprints** | Sprint defaults |
| **Templates** | Template behavior |
| **Developer** | Local development tooling |

Security settings change how the application protects itself. Read
[SECURITY.md](./SECURITY.md) before altering them on a production instance.

---

## Activity log

**Activity** (requires `view_activity`) is the audit trail — who did what, filterable and
paginated. Use it to answer "when did this change and who changed it".

---

## FAQ

**A menu item disappeared.**
Almost always permissions. Aureo hides what you cannot access. Ask an administrator which role
you hold.

**I deleted something by mistake.**
Deletion is *soft* — the record is hidden, not destroyed. An administrator can recover it from
the database.

**My activation or reset link does not work.**
Activation links last 24 hours, reset links 1 hour and are single-use. Request a new one.

**Search is not finding a task I know exists.**
The index may be stale. Ask an administrator to rebuild it (see
[DEPLOYMENT.md](./DEPLOYMENT.md#rebuilding-the-search-index)).

**I cannot drag tasks onto a sprint.**
Either you lack the `edit_sprints` permission, or the sprint is not in **Planning** or **Active**
status. Completed and cancelled sprints do not accept work.

**Aureo let me over-fill a sprint.**
Capacity is advisory by design. It informs your planning conversation; it does not block you.

**Can I log time without a task?**
No. Create a task first — every time entry belongs to one.

**Everything is signing me out.**
Check the session timeout under Settings → General. On self-hosted HTTP instances, misconfigured
cookie settings can also cause it — that is an administrator issue, covered in
[DEPLOYMENT.md](./DEPLOYMENT.md).
