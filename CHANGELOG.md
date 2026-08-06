# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The canonical version lives in the `VERSION` file at the repository root. Bump it
with `composer version:patch` (or `:minor` / `:major`), which keeps `package.json`
in step.

## [Unreleased]

## [1.1.0] - 2026-08-06

Host-layout independence: the application no longer assumes it owns the document root or that
configuration arrives as a `.env` file above it. Three deployment layouts are now supported end to
end (document root at `public/`, document root at the application root, and a subdirectory
install), configuration can come from real environment variables alone, and a long-broken
time-tracking feature was completed and wired up. No breaking change — minor bump.

### Added

- **Three supported deployment layouts** — document root at `public/` (recommended, unchanged),
  document root at the application root ("drop-in", for hosts that allow no other document root),
  and a subdirectory install — all served by one `App\Core\RequestPath` resolver rather than
  per-layout branches. See [docs/DEPLOYMENT.md](./docs/DEPLOYMENT.md#deployment-layouts).
- **`App\Core\RequestPath`** derives the application's mount point and route path from
  `SCRIPT_NAME`/`REQUEST_URI`, replacing two duplicated, domain-root-only URL-segmentation blocks
  in the front controller (the auth gate and the router dispatch each had their own copy). A pure
  value object with segment-boundary-aware prefix matching, so `/a` is never mistaken for a prefix
  of `/abc/projects`.
- **`App\Core\ConfigLoader`** resolves configuration from a five-rung chain — real environment
  variables, an `AUREO_CONFIG` override, an installer-written `config/config-path.php` pointer,
  `config/config.php`, then `.env` — instead of a single hardcoded `.env` lookup. See
  [docs/DEPLOYMENT.md](./docs/DEPLOYMENT.md#configuration-sources). `phinx.php` uses the same
  chain, so migrations work identically under every layout.
- Root `index.php` delegate, `AUREO_ASSET_PREFIX`, and the `asset()` view helper
  (`src/Views/Layouts/ViewHelpers.php`), which compose `Config::basePath()` with the asset prefix
  so bundled CSS/JS resolves correctly under all three layouts. 53 hardcoded asset URLs across 51
  views were rewritten to use it.
- Root `.htaccess` and `web.config` hardening for the drop-in layout: denies `.git`, `db`, `tests`,
  `bin`, `node_modules`, `var`, `log`, `vendor`, `config`, and non-PHP files that would otherwise be
  disclosed (dotfiles, logs, SQL dumps, lockfiles) if a full tree is extracted at a document root.
- **Time-tracking edit, update and delete endpoints**, plus an `App\Models\TimeEntry` model. The
  time-tracking list has always rendered an edit link and a delete button that pointed at routes
  which did not exist. Delete uses **POST, not DELETE** — `CsrfMiddleware` validates only POST
  requests, so a DELETE route would have shipped a destructive, CSRF-unprotected endpoint.
  `TimeEntry` is the project's **first hard-deleting model**: `time_entries` has no `is_deleted`
  column, so `usesSoftDeletes = false`.
- Guard tests: `DeadViewTest` (every view under `src/Views` must be a `render()` target or included
  by another view), `AssetUrlTest` (fails on any new hardcoded `/assets/` URL),
  `ViewHelperLoadingTest` (forbids a view from loading `ViewHelpers.php` itself), and
  `FormComponentsTest`.

### Fixed

- **The application could not boot from environment variables at all.**
  `Config::loadEnvironment()` required a `.env` file one level above the document root and threw a
  `RuntimeException` when it was absent — breaking every containerized or PaaS deployment that
  supplies configuration purely as environment variables. `ConfigLoader`'s first rung fixes this.
- **`ConfigLoader`'s environment rung did not actually populate `$_ENV`.** PHP's default
  `variables_order` is `GPCS` (no `E`), so real environment variables never reach `$_ENV` even
  though `getenv()` sees them; `environmentIsComplete()` checks all three sources and reported
  success, but every one of the 104 `$_ENV[...]` reads across the app then found nothing. This
  mattered beyond a cosmetic gap: `PASSWORD_PEPPER` silently falling back to its default would have
  invalidated every stored password. Rung 1 now copies the full real environment into `$_ENV`
  before returning.
- **`renderCSRFToken()` emitted an empty CSRF token for every caller.** It read `$csrfToken` from
  its own function body, but `BaseController::render()` only `extract()`s it into *view* scope —
  functions don't see caller-local variables. Five forms were unusable as a result: Projects
  create, Roles create and edit, Templates create and edit. Now reads `$_SESSION['csrf_token']`
  directly, the value `CsrfMiddleware` itself writes and validates against.
- **Subdirectory installs could not work.** Asset URLs were hardcoded root-absolute in 53 places
  across 51 views (every view carries its own `<head>`), and `Config` had no notion of a mount
  point to prepend. Fixed by `RequestPath` + `Config::basePath()` + `asset()` together.
- **`ViewHelpers.php` was not loaded before a view's `<head>` ran.** Only about 19 of 54 views
  loaded it themselves, and always below their own `<head>` — so `asset()` was undefined at runtime
  for most pages the moment their `<head>` called it. `BaseController::render()` now
  `require_once`s it unconditionally before any view content runs.

### Removed

- `schema.sql` — drifted from the canonical Phinx migration and was informational-only for some
  time; the migration is now the sole schema representation. Five enum `@see` anchors that pointed
  at its line numbers now point at the migration by table name instead.
- Three unreferenced `TimeTracking` views (`create.php`, `edit.php`, `view.php`) — rendered by no
  controller action.
- Sixteen views' redundant per-view `ViewHelpers.php` loads, now that `render()` guarantees the
  file is loaded once. Five of them used a plain `include` rather than `include_once`, which
  re-executed the file and fataled on function redeclaration once `render()` started loading it too
  (`/activity`, `/tasks`, `/tasks/backlog`, `/tasks/sprint-planning`, `/time-tracking`).

### Known issues

Found during this work and deliberately left unfixed — recorded here so they are not lost.

- **`ProjectController::create()` is broken.** It inserts a `key_code` column that does not exist
  on the `projects` table (`SQLSTATE[42S22]`). Project creation currently fails for every caller.
- **`RoleController::create()` is broken.** Fails with `PDO->rollBack(): There is no active
  transaction`.
- **`TimeTrackingController::startTimer()` rejects users who should be allowed.** Line ~357:
  `!$this->requirePermission('manage_tasks')`. `requirePermission()` is `void` and halts on
  failure, so `!null` is always `true` — a user *with* `manage_tasks` is still rejected.
- **`Tasks/backlog.php` reads `$selectedProjectId`, but `TaskController::backlog()` passes
  `$projectId`.** The project filter dropdown never shows a pre-selected project.
- **`ViewHelpers.php::renderTimerControls()` has the same function-scope `$csrfToken` bug fixed in
  `FormComponents.php::renderCSRFToken()`.** It is currently dead code (nothing calls it), so the
  bug has not shipped — but copying its pattern elsewhere will silently reproduce it.
- **`src/Http/Requests/*`** (the `FormRequest` base class and its four subclasses) is referenced by
  no controller. Deleting it would drop Tier 1 coverage to within 0.08 points of the coverage
  gate's fail threshold — inside the gate's own documented run-to-run drift — so it was flagged
  rather than removed.
- **Local integration tests cannot run as configured, and the skip message's instructions do not
  work.** `phpunit.xml` hardcodes `DB_NAME=pms_test` with an empty password, while `phinx.php`'s
  `testing` environment derives its database name as `$_ENV['DB_NAME'] . '_test'` from whatever
  `.env` contains — the two can never agree on a stock local `.env`. CI only works because the
  workflow rewrites `.env` (`sed -i 's/^DB_NAME=.*/DB_NAME=pms/' .env`) immediately before running
  migrations. **The real local procedure:** set `DB_NAME=pms` in your local `.env` (so the
  `testing` environment computes `pms_test`, matching `phpunit.xml`), point `DB_USERNAME`/
  `DB_PASSWORD` at a MySQL user matching `phpunit.xml`'s hardcoded `root` / empty password (or edit
  `phpunit.xml` to match your local credentials instead), then run
  `vendor/bin/phinx migrate -e testing` before `composer test`.

## [1.0.2] - 2026-08-02

Six production defects, all found by writing tests against code that had none.
Three share one root cause: a narrow type that turned a failure into an
uncatchable `TypeError`.

### Fixed

- **Seventeen `require` paths were broken on every case-sensitive filesystem.**
  They referenced `src/views/…` while the directory is `src/Views/…`. Windows and
  macOS resolve that silently; Linux does not, so each was a fatal
  "Failed opening required" the moment its line executed. Affected Dashboard,
  Projects view, Milestones view/index, Tasks view/edit/create, Sprints
  edit/view, Settings, the sidebar, FloatingTimer and `Task::getStatusName()`.
  Note `getStatusName()` wraps the include in `catch (\Exception)`, but a failed
  `require` raises an `Error`, so the guard never applied. `PathCaseSensitivityTest`
  now asserts that every `BASE_PATH` include and every `render()` target resolves
  with exact casing — it fails on Windows too, where the defect is otherwise
  invisible.
- **Creating or updating a company always failed.** `CompanyController` used a
  `regex:` validation rule, but `regex` is not in `Validator::AVAILABLE_RULES`,
  and `Validator::fails()` throws for any unregistered rule name — before
  `nullable` can short-circuit. Every POST to create/update died with
  "Unknown validation rule: regex" instead of validating. Now uses the built-in
  `phone` rule, which applies the same check the pattern was reaching for.
- **Three `Company` methods could never run against MySQL.**
  `getRecentProjectsByUser()`, `addUser()` and `addProject()` reused one named
  placeholder across several bind positions. With
  `PDO::ATTR_EMULATE_PREPARES = false` the driver rejects that as
  "Invalid parameter number", so adding a user to a company was simply broken.
  `User::addCompany()` already used distinct `_dup` names.
- **`Task::create()` threw an uncatchable `TypeError`.** It declares `: int`
  while `BaseModel::create()` returns `int|false`; returning that `false` raised
  a `TypeError`, which extends `Error` not `Exception`, so it bypassed the
  method's own `catch` and surfaced as a 500 instead of the handled failure the
  method promises. A failed insert now raises that `RuntimeException`.
- **`Role::findWithDetails()`** returned `find()`'s `object|false` from a
  `?object` method — same defect, hit on every lookup of a non-existent role,
  including via `findBasic()` and `findWithPermissions()`.
- **`Config::getErrorMessage()`** type-hinted `\Exception` while `UserController`,
  `CompanyController` and `DashboardController` all call it from inside
  `catch (\Throwable)`. Handing it an `Error` raised a second `TypeError` and
  escaped the catch — the error handler failed on exactly the errors it exists to
  handle. Widened to `\Throwable`.
- **Activity events were misclassified for nearly every detail view.**
  `ActivityMiddleware::determineEventType()` classified off `REQUEST_URI`
  verbatim, so `/projects/view?id=5` yielded the action `view?id=5`, matched
  nothing and fell through to a generic `page_view`. Detail views are precisely
  the URLs carrying `?id=`, so `activity_logs` was wrong for the most common
  request shape. `trackRecentView()` in the same file already stripped the query.
- **`FormRequest` rejected integers that were in the allowed set.**
  `validateIn()` compared strictly against rule parameters that are always
  strings, so passing `ProjectStatus::READY->value` was told it "must be one of
  1,2,3". Form posts escaped it only because `$_POST` values are strings.

### Changed

- Every controller redirect now goes through `BaseController`'s
  `redirect()`/`redirectWithSuccess()`/`redirectWithError()` helpers. 36 raw
  `header('Location: …') + exit` sites across 11 controllers were removed
  (net −67 lines). Behaviour is unchanged by construction — the helpers are
  exactly the code that was inlined — but the raw form could not be overridden,
  so those branches killed the test runner and were untestable.
  `SprintController`'s create/update/delete/addTasks POST paths were the bulk of
  it.
- Test coverage: tier 2 went from 3.42% to 67.78% — Controllers 1.73% → 44.68%,
  Models 62.17% → 95.70%, Middleware 0.48% → 86.16%. The suite grew from 989 to
  1767 tests. Tier 1 holds at 95.12%.

## [1.0.1] - 2026-07-30

### Fixed

- **Password reset tokens were born already expired.** The expiry was computed in
  PHP from the display timezone (default `America/New_York`) and stored as a
  naive string against a UTC database, landing hours behind the database clock,
  while `findByResetToken()` compares it to MySQL's `NOW()`. A "+1 hour" token was
  invalid the moment it was created, so password reset could never succeed in any
  deployment whose app timezone trails the database by more than an hour — the
  default configuration. Activation tokens shared the defect; their 24-hour window
  merely absorbed the skew. Both now use `DATE_ADD(NOW(), ...)` so the value and
  the comparison share one clock. The redundant `strtotime()` rechecks in
  `AuthController` are gone; the model's SQL already enforces expiry.
- **Password strength was never enforced.** `Validator` resolved rule handlers
  with `ucfirst()`, so the snake_case rule `strong_password` looked for
  `validateStrong_password` while the real method is `validateStrongPassword`.
  `validateField()` skips unresolved rules silently, so the rule was a no-op on
  both registration and password reset — only `min:8` was actually applied.
  Dispatch now studly-cases rule names, and a guard test asserts every registered
  rule resolves to a handler.
- **18 runtime fatals from a private method call.** `TaskService` (9),
  `ProjectService` (6) and the two task listeners (3) called
  `LoggerService::log()`, which is `private`. Every one of those paths — assign
  task, start/stop timer, create project, add/remove team member, archive — threw
  on invocation. They now use the public level methods, which also restores the
  intended uppercase level in the log format.
- `LoggerService` raised `mkdir(): File exists` instead of reporting the real
  cause when its log path was occupied by a non-directory, and did not clear the
  stat cache before checking.
- The `users` insert in the authentication integration test used a `password`
  column that does not exist (it is `password_hash`) and omitted the `NOT NULL`
  columns `guid` and `role_id`. These tests had never executed, because they skip
  silently without MySQL.
- Collapsed 14 case-colliding view paths: the whole `src/Views/Projects`
  directory was tracked twice, differing only in case, which no case-insensitive
  checkout can represent.

### Added

- Tiered coverage gate (`bin/coverage-gate.php`, `composer coverage:check`) with
  monotonic ratchet floors in `coverage-floor.json`.
- CI guard rejecting tracked paths that differ only by case.
- Coverage for the previously untested core: tier-1 line coverage went from
  26.49% to 95.18%, and the suite from 337 to 989 tests.

### Changed

- **`composer.json` now pins `config.platform.php` to `8.2.0`.** Without it,
  Composer resolved against whatever PHP the developer happened to run, and the
  lock file had drifted to Symfony 8.1.x (requiring PHP >= 8.4.1) — a lock that
  the PHP 8.2 floor could not install at all. Re-resolved to Symfony 7.4.x.
- CI now runs a PHP 8.2/8.3/8.4/8.5 matrix, verifies `check-platform-reqs`, and
  passes `--fail-on-skipped` so a skipped integration test is a failure rather
  than a silent pass.
- `phpunit.xml` adds `failOnNotice` and `failOnDeprecation`.
- Removed the copy-pasted `BASE_PATH` web guard from 10 PSR-4 class files. As
  top-level code it violated PSR-1 §2.3 and voided coverage for every test that
  loaded those classes. The 97 View files keep theirs.

## [1.0.0] - 2026-07-29

Initial tagged release.

[Unreleased]: https://github.com/rbenzing/Aureo-Project-Management/compare/1.1.0...HEAD
[1.1.0]: https://github.com/rbenzing/Aureo-Project-Management/compare/1.0.2...1.1.0
[1.0.2]: https://github.com/rbenzing/Aureo-Project-Management/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/rbenzing/Aureo-Project-Management/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/rbenzing/Aureo-Project-Management/releases/tag/1.0.0
