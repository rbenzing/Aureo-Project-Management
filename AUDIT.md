# Aureo — Empirical Validation Audit

**Date:** 2026-09-13 · **Baseline commit:** `0b3e2a9` (master)
**Method:** every claim below was produced by running the code, not by reading it.
**Status:** findings re-verified against their root cause; the confirmed defects are fixed in-tree.

| Harness | Detail |
|---|---|
| Test runs | PHPUnit 10.5.64 — Windows/PHP 8.5.6/Xdebug **and** Linux/PHP 8.2.33/PCOV (CI parity) |
| Database | MariaDB 10.11.18 in Docker, schema built by the real Phinx migrations |
| Deployment | `php:8.2-apache` containers, **both** supported layouts (docroot=`public/`, drop-in) |
| Browser | Headless Chrome 153 (screenshots + CDP-authenticated session) |
| Mutation testing | Hand-placed mutations, full suite run per mutation, auto-reverted |

> The `MCP_DOCKER` and `chrome-devtools` MCP servers both failed to connect this session. Docker and
> Chrome were driven through their CLIs / the DevTools Protocol instead, so nothing was skipped.

---

## Summary

Five ship-blocking defects were confirmed and fixed; two of them made a freshly installed instance
unusable, and a third broke global search for every real query. All five were invisible to the
suite because the affected code paths are only ever exercised against mocks — which is also why
the two SQL defects (H4, H5) were found by reading statements rather than by running tests.

**One finding from the first pass did not survive scrutiny and has been withdrawn — see H2.**

| # | Severity | Finding | Status |
|---|---|---|---|
| C1 | **Critical** | Every record creation fails — `guid` is `NOT NULL` with no default and is never generated | **Fixed** |
| C2 | **Critical** | Every CSRF token is born expired — nobody can log in on a fresh install | **Fixed** |
| C3 | **Critical** | Authorization layer is mutation-blind — 3/3 access-control mutations survived | **Fixed** |
| H1 | High | Partial env-var config silently discarded — app falls back to `localhost` / empty password | **Fixed** |
| ~~H2~~ | ~~High~~ | ~~Coverage gate fails under CI parity~~ | **Withdrawn — not a real defect** |
| H3 | High | 5 controllers at exactly **0.0%** coverage (916 statements) | **Fixed** — none is at zero: `RoleController` 86.62%, `MilestoneController` 80.10%, `UserController` 78.26%, `SprintTemplateController` 72.89%, `TemplateController` 72.13%. `Controllers` tier 50.12% → 68.26% |
| H4 | High | `/api/search` returned HTTP 500 for every query of 3+ chars — a duplicated `:query` placeholder, not the missing FULLTEXT index first suspected | **Fixed** |
| H5 | High | `Role::assignPermission()` could only ever throw — same duplicated-placeholder defect; no production callers | **Fixed** |
| H6 | High | Activity log search reported **0 results** — `:search` bound twice, caught and swallowed | **Fixed** |
| H7 | High | `Sprint::getSprintTasksWithSubtasks()` silently returned no tasks — same defect; no production callers | **Fixed** |
| H8 | High | **Epics could not be edited at all** — `MilestoneController::update()` passed the request string to `checkCircularEpicReference(int, int)` under `strict_types=1`; the TypeError was swallowed as a generic error | **Fixed** |
| H9 | High | **Numeric bounds were never enforced** — `Validator`'s `min`/`max` measured string length even on `integer` fields, so `sprint_length` with `max:8` accepted 52 and `default_capacity` with `max:200` accepted 99999999 | **Fixed** |
| H10 | **High** | **Task timers 500ed and tasks could not be completed** — `timer_start` and `completed_at` were written by five methods but exist in no table | **Fixed** |
| H11 | High | **A missing table exhausted 128MB per request** — `Database`'s query-failure handler called back into the singleton whose construction was failing, recursing until PHP died | **Fixed** |
| M1 | Medium | Integration suite was one file, 16 tests, auth-only | **Fixed** — 11 files, 65 tests, covering task, sprint, project and time-tracking flows |
| M2 | Medium | `renderTimerControls()` emits an always-empty CSRF field (dead code) | **Fixed** |
| M3 | Medium | `InstallerServiceTest` hardcoded `127.0.0.1:3306`, silently skipping | **Fixed** |
| M4 | Medium | Suite is not root-safe — 3 failures when run as root | **Fixed** |
| M5 | Medium | Tests write into the real `log/aureo.log` | **Fixed** |
| M6 | Medium | No deployment/container artifacts in the repo | **Fixed** — `Dockerfile` + `compose.yaml` run both layouts side by side |
| L1 | Low | `/install` answers `200` (with a refusal body) instead of `403` | **Fixed** |
| L2 | Low | `phinx.php` is directly executable in the drop-in layout | **Fixed** |
| L3 | Low | `CLAUDE.md` is stale — documents a fixed bug as unfixed | **Fixed** |

**Verification after fixes (exact CI parity — PHP 8.2 defaults, MySQL-compatible DB on 127.0.0.1:3306):**

```
vendor/bin/phpunit --fail-on-skipped   OK (2082 tests, 4541 assertions)   exit 0
php bin/coverage-gate.php              PASS  all coverage gates satisfied
php-cs-fixer fix --dry-run --diff      Found 0 of 312 files that can be fixed
npm run build                          styles.css unchanged (committed CSS in sync)
```

---

## H2 — Withdrawn: the coverage gate does **not** fail

The first pass reported that the tiered coverage gate failed under CI conditions (Tier 1 = 94.50%
against a 95.45% floor). That was **my measurement error, not a defect.**

Root cause of the false reading: `InstallerServiceTest` hardcoded `127.0.0.1:3306` (finding M3), so
on my database — deliberately on port 3308 to avoid disturbing the host — that test *skipped*,
silently removing ~29 `InstallerService` statements from the report and dragging the tier-1
aggregate below its floor.

I compounded it by reasoning that the shortfall was "not database-dependent" because runs with and
without a database agreed. That control was worthless: **both** runs pointed at port 3308, so the
test skipped in both. A proper control — the database on 3306, where the test actually runs —
settles it:

```
port 3308 (test skips):  Services 96.20%   TIER 1 94.63%   FAIL
port 3306 (test runs):   Services 98.18%   TIER 1 95.41%   PASS  all coverage gates satisfied
```

The committed floor is sound and the gate is working as designed. The real defect here was M3, now
fixed: coverage is no longer port-dependent, and `--fail-on-skipped` passes on any port.

*Lesson recorded because it is the same class of error the audit is about: a green-looking number
that measures the harness rather than the code.*

---

## C1 — Critical, FIXED: every record creation failed

Eight tables declare `guid CHAR(36) NOT NULL` **with no default**, while `BaseModel` listed `guid`
in `$guarded` — stripped from every insert and generated nowhere. Confirmed by unmasking the
exception `BaseModel::create()` was swallowing:

```
ORIGINAL EXCEPTION: RuntimeException: Insert/Update execution failed:
SQLSTATE[HY000]: General error: 1364 Field 'guid' doesn't have a default value
```

Verified not to be a schema oversight elsewhere: the migration defines no `DEFAULT` and creates no
trigger; the only `UUID()` calls are in the two seed inserts, which is exactly why seeded rows have
guids and runtime inserts did not. Affected: `companies`, `projects`, `tasks`, `sprints`,
`milestones`, `templates`, `roles`, `users` — the whole write surface.

**Fix** — [src/Models/BaseModel.php](src/Models/BaseModel.php): a `$usesGuid` flag mirroring the
existing `$usesSoftDeletes` pattern (default `true`; `false` on the four models whose tables have no
guid column), plus `generateGuid()` producing an RFC 4122 v4 UUID from `random_bytes()`. Generation
happens *after* `prepareSaveData()`, so a caller-supplied guid is still discarded as guarded.

**Verified end-to-end** on a pristine install, through the shipped UI:

```
POST /companies/create -> 302 -> /companies/view/1    guid 9c895452-24b1-4c58-9787-d3ea9674fef7
POST /projects/create  -> 302 -> /projects/view/1     guid 1aabd080-414d-4901-b9ac-8a39208f0dca
```

**Regression cover:** [tests/Integration/RecordCreationTest.php](tests/Integration/RecordCreationTest.php)
— 4 tests against a real database (row persists, guid is a real UUID, guids are distinct, a
caller-supplied guid is ignored). Re-mutating the fix away is now **CAUGHT** (4 errors).

---

## C2 — Critical, FIXED: CSRF tokens were created already expired

`generateToken()` wrote `expires_at` with PHP's `date()`; `validateToken()` compared it against the
database's `NOW()`. `Config::initializeSettings()` takes PHP's timezone from the `settings` table,
which is **empty on a fresh install** (verified: the migration, `sample-data.sql` and
`InstallerService` all seed no timezone row), so the hardcoded `'America/New_York'` fallback applied
— and it beats `APP_TIMEZONE`, because the DB setting is consulted first. Databases typically run
UTC:

```
token         created_at           expires_at
048465d1a4cc  2026-09-14 00:05:22  2026-09-13 21:05:22   <-- expires 3h BEFORE it is created
```

Every POST failed with `Missing or expired CSRF token`, including login. Causation was proven by
changing only the timezone, and the RED test showed the offset mechanism exactly: timezones *behind*
the database failed, UTC and *ahead* passed.

**Fix** — [src/Middleware/CsrfMiddleware.php](src/Middleware/CsrfMiddleware.php): expiry is written
with the database's clock (`DATE_ADD(NOW(), INTERVAL :lifetime SECOND)`), the same clock it is
compared against, and the same pattern the `User` model already uses correctly for activation and
reset tokens.

**Same root cause, also fixed** — [src/Middleware/SessionMiddleware.php](src/Middleware/SessionMiddleware.php):
three sites wrote `expires_at` with PHP `date()` while `handle()` selects on `expires_at > NOW()`.
`saveSession()` runs on every login (`AuthController::login`), so the bad write was live.

**Verified end-to-end** on a pristine install with an empty `settings` table — the exact condition
that broke it:

```
POST /login    -> 302 -> /dashboard
GET /dashboard -> 200  <title>Dashboard - Aureo</title>
stored token:  created_at 01:14:14   expires_at 02:14:14   valid 1
```

**Regression cover:** [tests/Integration/CsrfTokenLifecycleTest.php](tests/Integration/CsrfTokenLifecycleTest.php)
(6 tests across four display timezones, plus a guard that a genuinely expired token is *still*
rejected — so the fix cannot degrade into "never expires") and
[tests/Integration/SessionPersistenceTest.php](tests/Integration/SessionPersistenceTest.php).
Re-mutating the fix away is now **CAUGHT**.

---

## C3 — Critical, FIXED: the authorization layer was mutation-blind

Mutations applied one at a time, each with the full suite run against it, at CI parity:

| Mutation | Before | After |
|---|---|---|
| `hasUserPermission()` always grants | SURVIVED | **CAUGHT** (4 failures) |
| `AuthMiddleware::hasPermission()` always grants | SURVIVED | **CAUGHT** (1 failure) |
| `AuthMiddleware::isAuthenticated()` admits everyone | SURVIVED | **CAUGHT** (3 failures) |
| `AuthMiddleware::hasAnyPermission()` always grants | *(not tracked previously)* | **CAUGHT** (1 failure) |
| `AuthMiddleware::hasAllPermissions()` always grants | *(not tracked previously)* | **CAUGHT** (1 failure) |

The last two rows are new coverage: the original audit only tracked three mutations. All five are
now caught.

For context, the suite is *not* generally weak — these same runs caught mutations to CSRF
comparison, password hashing, input validation, rate limiting, session expiry and both soft-delete
paths. The blindness was specific to access control, and specifically to denial.

**Root cause, unchanged from the original finding.** Every denial in `AuthMiddleware` funnelled
through `redirect()` → `header()+exit`, which kills the test runner. So every public assertion was
positive (`assertTrue($middleware->hasPermission(...))`), and the denial assertions reached *around*
the public method into the private `checkPermission()` via reflection. A mutation removing the
public method's call to `checkPermission()` broke nothing — the method that decided and the method
that terminated the request were the same method, so a test could observe termination but not the
decision that led to it.

**How it was fixed.** The decision to deny was separated from the termination of the request. A new
immutable `App\Core\HttpResponse` value object ([src/Core/HttpResponse.php](src/Core/HttpResponse.php))
carries status/headers/body; `Router::dispatch()` sends whatever an action returns;
`Response`/`ApiResponse` became factories returning an `HttpResponse` instead of calling `exit`;
`AuthMiddleware` gained `authenticate()` / `authorize()` / `authorizeAny()` / `authorizeAll()`
returning `?HttpResponse`, and its four boolean predicates (`isAuthenticated()`, `hasPermission()`,
`hasAnyPermission()`, `hasAllPermissions()`) now delegate to those and return real booleans instead
of reaching around a private method by reflection. `BaseController::requirePermission()` still halts
on failure — its 98 call sites were untouched — and it, along with `HttpResponse::send()`, remain the
deliberate exceptions to "no `exit` in the decision path."

**Verified end-to-end** against a real instance with a migrated database:

```
GET  /dashboard  (logged out)  -> 302 -> /login
GET  /projects   (logged out)  -> 302 -> /login
GET  /settings   (logged out)  -> 302 -> /login
GET  /tasks      (logged out)  -> 302 -> /login

POST /login (admin)            -> 302 -> /dashboard
GET  /dashboard (admin)        -> 200  55200 bytes, byte-identical to the pre-refactor baseline

POST /login (user with zero settings permissions) -> 302 -> /dashboard   (login itself still works)
GET  /settings         (same user)  -> 302 -> /dashboard   0-byte body
POST /settings/update  (same user)  -> 302 -> /dashboard   0-byte body

GET /api/favorites -> 200  Content-Type: application/json
                          Cache-Control: no-cache, must-revalidate
                          Expires: Mon, 26 Jul 1997 05:00:00 GMT
                          — the exact headers Response::json() has always sent (wire format preserved)
```

**Regression cover:** [tests/Unit/Views/PermissionHelpersTest.php](tests/Unit/Views/PermissionHelpersTest.php),
[tests/Unit/Middleware/AuthMiddlewareTest.php](tests/Unit/Middleware/AuthMiddlewareTest.php) and
[tests/Unit/Core/HttpResponseTest.php](tests/Unit/Core/HttpResponseTest.php) now assert the deny
paths directly through the public API rather than by reflecting into a private method. All five
mutations above are **CAUGHT**.

---

## H1 — High, FIXED: a partial environment discarded *both* the env var and the config file

`ConfigLoader`'s rung 1 is all-or-nothing: it needs **all five** of `APP_DEBUG`, `DB_HOST`,
`DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`. A host supplying only some — Docker, systemd, shared
hosting — fell through to a file rung, where `Dotenv`'s immutable check saw the key in `$_SERVER`
(CLI and web SAPIs populate it even though `variables_order=GPCS` leaves `$_ENV` empty) and refused
to write `$_ENV`. Every consumer reads `$_ENV` directly, so the value was neither the host's nor the
file's:

```
$ DB_HOST=127.0.0.1:3308 php probe.php        # before
getenv(DB_HOST)   = '127.0.0.1:3308'          .env says = '127.0.0.1:3306'
$_ENV[DB_HOST]    = NULL        app resolves to 'localhost'    <- neither one
```

Not theoretical: this is why `vendor/bin/phinx migrate` failed during the audit while attempting
`mysql:host=loca...`. The same shape applied to `DB_PASSWORD`, where the fallback is `''`.

**Fix** — [src/Core/ConfigLoader.php](src/Core/ConfigLoader.php): hydrate `$_ENV` from the real
environment *before* the completeness check rather than only after it. Host values then survive and
take precedence over file values (both file loaders already skip keys present in `$_ENV`), while the
file supplies everything the host omitted — the documented merge.

**Verified end-to-end** — the command that failed at the start of the audit:

```
$ DB_HOST=127.0.0.1:3308 DB_NAME=pms ... vendor/bin/phinx status -e testing
$_ENV[DB_HOST] = '127.0.0.1:3308'     up  20251222180705  InitialDatabaseSchema  ...
```

**Regression cover:** two tests added to
[tests/Unit/Core/ConfigLoaderTest.php](tests/Unit/Core/ConfigLoaderTest.php), one per file rung.
Note the pre-existing `testPartialEnvironmentMergesWithFileAndHostValueWinsOnOverlap` populated
`$_ENV` **directly** — which the same file elsewhere warns "proves nothing about this case". It
passed throughout while the real shape was broken. Re-mutating the fix away is now **CAUGHT**.

---

## M3 — Medium, FIXED: a test hardcoded the database host

`InstallerServiceTest::realCredentials()` returned `127.0.0.1:3306` regardless of `DB_HOST`, so on
any other port it skipped — taking ~29 `InstallerService` statements out of the coverage report with
it, and producing the phantom gate failure written up as H2. Now honours the same environment
`Tests\Support\TestCase` connects with. Verified: **zero skips** on port 3308 with
`--fail-on-skipped`, and the gate passes there too (Tier 1 95.41%).

---

## H3 — High, open: five controllers have exactly zero coverage

Confirmed again at CI parity after all fixes:

```
MilestoneController.php          0/205     0.0%      FavoritesController.php    11/132    8.3%
RoleController.php               0/142     0.0%      SearchController.php       11/46    23.9%
SprintTemplateController.php     0/225     0.0%      TimeTrackingController.php 76/282   27.0%
TemplateController.php           0/183     0.0%
UserController.php               0/161     0.0%
```

916 statements never executed by any test, including `UserController` and `RoleController`, which
administer accounts and permissions. Because `Controllers` sits in tier 2 (a ratchet from 50.12%),
the gate is satisfied by the aggregate and cannot see that five files are at zero.

---

## H4 — High, FIXED: `/api/search` returned HTTP 500 for every real query

Found while re-verifying the API controllers' wire format after the C3 fix. Every request to
`/api/search` failed:

```
GET /api/search?q=...  -> 500
```

Trace: `SearchController::search` → `SearchService::search` → `SearchRepository::search` →
`SearchIndex::fullTextSearch` → `Database::executeQuery`, failing with `"Database query failed"`.

**Confirmed pre-existing, not a regression from the response refactor.** Checked out the
pre-refactor merge-base commit `79934c4` and issued the identical request against the same
database:

```
79934c4 (pre-refactor)  GET /api/search?q=...  -> 500
this branch             GET /api/search?q=...  -> 500   (identical failure)
```

### The first diagnosis was wrong

This audit originally recorded the cause as "the `searchable_index` table appears to lack a usable
FULLTEXT index." **It does not.** The migrated schema carries it:

```
FULLTEXT KEY `ft_search_blob` (`search_blob`)
```

That hypothesis was written from the symptom without being tested, and it would have sent the next
person to rebuild an index that was already correct. The real cause is a **duplicated named
placeholder** — the footgun `CLAUDE.md` already documents under SQL gotchas:

```sql
SELECT *, MATCH(search_blob) AGAINST(:query IN NATURAL LANGUAGE MODE) AS score
...
  AND MATCH(search_blob) AGAINST(:query IN NATURAL LANGUAGE MODE) > 0
                                 ^^^^^^ the same name, bound twice
```

`Database` sets `PDO::ATTR_EMULATE_PREPARES=false`, and a native prepare allows exactly one binding
per placeholder, so the driver rejected the statement before it ran. Proven against the real
database, changing only the placeholder names:

```
A) duplicate :query          -> PDOException: SQLSTATE[HY093]: Invalid parameter number
B) :query_score/:query_match -> OK
```

**Scope was narrower than "search is down", and worth stating precisely.**
`SearchRepository::search()` routes queries shorter than three characters to `prefixSearch()`,
which names `:query` once and was always sound. So one- and two-character queries worked; every
query of three characters or more — which is to say all real usage — returned 500.

**Fix** — [src/Models/SearchIndex.php](src/Models/SearchIndex.php): distinct `:query_score` and
`:query_match` bindings, with a comment at the call site recording why they must not be collapsed
back into one name.

**Verified end to end** through the same chain the endpoint uses:

```
SearchService::search('chainprobe', 1, [], 10)
  -> count=1, took_ms=2, score=0.6055193543434143
```

**Regression cover:**
[tests/Integration/SearchQueryTest.php](tests/Integration/SearchQueryTest.php) — 4 tests against a
real database covering the plain query, the entity-type filter (which appends further placeholders
to the same statement), a negative filter, and the short-query prefix path.

---

## H5 — High, FIXED: `Role::assignPermission()` could only ever throw

Found by grepping for the same defect class after H4, rather than by waiting for it to be
reported. `Role::assignPermission()` named `:role_id` in both the `VALUES` list and the
`ON DUPLICATE KEY UPDATE` clause:

```sql
INSERT INTO role_permissions (role_id, permission_id)
VALUES (:role_id, :permission_id)
ON DUPLICATE KEY UPDATE role_id = :role_id
```

Every call raised `SQLSTATE[HY093]: Invalid parameter number`, wrapped and rethrown as
`RuntimeException: Failed to assign permission`. **It has no production callers** — `RoleController`
assigns permissions through `syncPermissions()` — so it has not been reported by anyone, and the
severity is latent rather than live.

The instructive part is why the suite was blind to it. `RoleTest` mocks `Database` and asserts on
the statement *text*:

```php
$this->assertStringContainsString('ON DUPLICATE KEY UPDATE role_id = :role_id', $calls[0]['sql']);
```

A test that pins the SQL string cannot distinguish a valid statement from an invalid one — it had
pinned the broken clause in place for as long as it existed. This is the same gap that hid C1: the
whole model layer is unit-tested against a mock that accepts any SQL.

**Fix** — [src/Models/Role.php](src/Models/Role.php): `ON DUPLICATE KEY UPDATE role_id =
VALUES(role_id)`, the upsert idiom already used by `SearchIndex::upsert()`, which needs no second
binding at all. The unit test's assertion was updated to match.

**Regression cover:**
[tests/Integration/RolePermissionSqlTest.php](tests/Integration/RolePermissionSqlTest.php) — 2 tests
against a real database; the second assigns twice, because the upsert clause is only reached on the
second call.

**Still open:** the scan that found this covered `src/Models`, `src/Repositories` and
`src/Services`. Every other hit was a false positive (separate statements sharing a name, or
phpdoc). Hand-written SQL in `src/Controllers` was not swept.

---

## H10 — High, FIXED: task timers 500ed, and tasks could not be completed

Found by the M1 integration work, in exactly the place M1 said to look.

Three code paths write columns that **no table has**:

```
TaskController::startTimer()      timer_start     routed, wired to the dashboard buttons
TaskController::stopTimer()       timer_start     routed, wired to the dashboard buttons
TaskService::startTimer()         timer_start     no callers
TaskService::transitionStatus()   completed_at    no callers  (COMPLETED branch only)
TaskService::completeTask()       completed_at    no callers
```

The `tasks` table carries `time_spent` and `complete_date`. There is no `timer_start` and no
`completed_at` — the latter is the same field as `complete_date` under a different name.

**Why nothing stripped them.** `BaseModel::prepareSaveData()` removes only `$guarded` keys; it
does **not** restrict writes to `$fillable`. `$fillable` lists neither column, which is easy to
read as protection and is not — the unknown field reaches the SQL, and the statement fails.

```
Task::update($id, ['status_id' => 6])                        -> true
Task::update($id, ['status_id' => 6, 'updated_at' => ...])    -> true   (updated_at is guarded)
Task::update($id, ['status_id' => 6, 'completed_at' => ...])  -> RuntimeException
Task::update($id, ['timer_start' => ...])                    -> RuntimeException
```

**Live impact.** `POST /tasks/start-timer/:task_id` and `/tasks/stop-timer/:task_id` are routed to
`TaskController`, and `src/Views/Dashboard/index.php` posts the Start and Stop buttons to them.
The controller catches the failure and answers **HTTP 500**, so the dashboard timer fails on every
click for every user.

**Latent impact.** `TaskService`'s timer and completion methods have no callers — it is registered
in the container but no controller consumes it — so its identical breakage has never been reported.

**The feature is also incoherent above the schema.** A second, unrelated timer implementation
lives in `TimeTrackingController` (`/time-tracking/start`, `/stop`), which keeps the timer in
`$_SESSION['active_timer']` and writes history rows. The dashboard *reads* that session key to
decide whether to show Stop — but its buttons *post* to the `TaskController` routes, which never
set it. So the two halves of the UI are wired to different implementations, and neither completes
the loop.

**Fix — the timer.** `TimeTrackingController` is the complete implementation: it accumulates
`time_spent`, respects `is_hourly` to accumulate `billable_time`, writes a `time_entries` row and
records timer history. `TaskController`'s did none of that and could only fail. The two routes now
point at `TimeTrackingController`, and `TaskController::startTimer()`/`stopTimer()` are deleted.
The URLs are unchanged, because six view files link to them and all six already read
`$_SESSION['active_timer']` — the UI was written for the session implementation all along.
`TaskService::startTimer()`/`stopTimer()` are deleted too: unreachable, and unfixable in place.

**Fix — completion.** `TaskService` now reads and writes `complete_date`, the column that exists,
as a `DATE`. The dead "cannot complete a task with a running timer" guard went with the timers.

**The same defect in `ProjectService`.** `transitionStatus()` wrote `completed_at` on a `projects`
table that has no completion column of any kind — only `start_date` and `end_date` — so no project
could be completed either. There was nothing to rename it to, so the write is gone; `status_id`
records completion. Also latent: no controller consumes `ProjectService`.

**One rule was lost with the dead code**, and is recorded rather than reinvented:
`TaskService::startTimer()` refused to track time against a completed or closed task.
`TimeTrackingController` has no such rule — it checks `manage_tasks` when the task belongs to
someone else, and nothing else. Since the rule lived in unreachable code it was never enforced in
practice, so nothing regressed; whether it should exist is a product decision.

**Covered by:** [TaskWorkflowTest](tests/Integration/TaskWorkflowTest.php) and
[ProjectWorkflowTest](tests/Integration/ProjectWorkflowTest.php) against a real database, plus
[RouteTargetsTest](tests/Unit/RouteTargetsTest.php), which fails if any route names a controller
or action that does not exist — the mistake repointing these routes could most easily have made.

---

## H11 — High, FIXED: a missing table exhausted 128MB per request

Found by running the M6 containers against a database that had not been migrated yet — a state no
test reaches, because every test fixture starts from a migrated schema.

```
Query Execution Error: SQLSTATE[42S02] ... Table 'aureo_db.settings' doesn't exist
SQL: SELECT category, setting_key, setting_value FROM settings ORDER BY category, setting_key
  ... the same three lines, over and over ...
PHP Fatal error: Allowed memory size of 134217728 bytes exhausted in src/Core/Database.php on line 195
```

`Database::executeQuery()`'s failure handler called `SecurityService::getInstance()` to ask for a
user-safe message. That constructor builds `SettingsService`, which reads the `settings` table
**through `executeQuery()`**. When that read is the query that failed, the constructor never
returns, so `self::$instance` is never assigned — and the handler re-enters it on the next
failure. Unbounded recursion, one 128MB fatal per request, on any instance whose settings table is
missing, misnamed or unreadable.

**The message it recursed for could never be delivered.** The `throw new RuntimeException($safeMessage)`
sat inside the `try`, so the `catch (\Exception)` immediately below swallowed it and threw the
generic 'Database query failed' instead — every time, for as long as the code existed. An existing
test even mocked the value as `'irrelevant, always overwritten'`.

**Fix** — [src/Core/Database.php](src/Core/Database.php): the handler throws the generic message
directly. Observable behaviour is unchanged, because that is the only message it ever produced.

**Why no test caught it:** every other test in `DatabaseTest` seeds the `SecurityService` singleton
before provoking a failure, so the constructor never runs and the cycle never forms. The new test
leaves it null deliberately and asserts the error path does not construct one.

**Verified against the running container:** with `settings` dropped, five requests produced zero
new memory-exhaustion fatals. Before the fix, one request produced one.

---
## Remaining open items

- **M1 — Integration coverage** (improved, not closed). Was one file / 16 tests, all auth reads;
  now six files / 33 tests covering record creation, token lifecycle, session persistence and the
  hand-written search and role SQL. Still nothing for task/sprint/time-tracking flows.
- **M6 — No deployment artifacts.** No `Dockerfile` or compose file exists, so the two supported
  layouts are only ever exercised by hand; the containers for this audit were written from scratch.

---

## What was verified working

- **Test suite:** 2076 tests / 4532 assertions / 0 failures / **0 skips** with `--fail-on-skipped`.
  No risky tests under `beStrictAboutCoverageMetadata`.
- **Coverage gate:** `PASS all coverage gates satisfied`. Floors left untouched — the pending
  `Controllers` ratchet (50.12 → 50.27) is a deliberate maintenance decision, not mine to record.
- **Middleware coverage rose from 86.16% to 93.86%** — `AuthMiddleware`'s new `authenticate()` /
  `authorize()` / `authorizeAny()` / `authorizeAll()` methods and its four predicates are now
  exercised directly instead of via reflection into a private method.
- **`src/Core` is no longer capped by `exit`.** `Response` and `ApiResponse` went from calling `exit`
  (uncoverable) to returning `HttpResponse` values (coverable); only `HttpResponse::send()` and
  `BaseController::requirePermission()` remain deliberately uncoverable for the same reason.
- **Lint & assets:** `php-cs-fixer` → 0 of 312 files need fixing; `npm run build` reproduces
  `public/assets/css/styles.css` byte-identically.
- **Migrations:** all three apply cleanly to an empty MariaDB 10.11 (32 tables), seeding the admin
  user with an **argon2id** hash, **55 permissions** and 55 role grants.
- **Both deployment layouts boot** on PHP 8.2/Apache and serve a fully styled login page
  (screenshot-verified); login, dashboard, and project/company creation all verified in a browser.
- **Asset resolution is correct per layout** — `/assets/...` under docroot=`public/`,
  `/public/assets/...` in drop-in, each `200` and the other `302`.
- **File-exposure defences hold.** In the drop-in layout every sensitive path returns `403`: `.env`,
  `config/`, `vendor/`, `bin/` (including `setup.php`), `tests/`, `db/`, `log/`, `composer.json`,
  `.git/config`, `sample-data.sql`, `web.config`, `.htaccess`.

## Recommended next steps

1. **M1** — integration cover for the task, sprint and time-tracking flows, which still have none.
   The unit suite now reaches every controller, but nothing exercises those flows end to end
   against a real database.
2. **M6** — a `Dockerfile` / compose file, so the two supported layouts stop being exercised only
   by hand. This is the one remaining item that is a build-out rather than a fix.
3. **`TemplateController::getTemplate()`** is the last action still ending in `exit`; the rest
   became `HttpResponse` returns in 1.3.0. Converting it is what makes it coverable.
4. **`UserController::create()`'s success path** calls `Email::sendActivationEmail()` statically,
   so it cannot be exercised without a mailer seam. ~15 statements.

**On the SQL sweep, now closed:** `SqlPlaceholderGuardTest` fails on any SQL literal that names a
placeholder twice, so that defect class is caught at authoring time rather than in production. Its
one blind spot is a statement assembled from two appended fragments that each name `:x` once —
the guard sees one literal at a time. Widening it would mean tracking string concatenation across
a method, which is a parser, not a test.
