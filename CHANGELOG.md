# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The canonical version lives in the `VERSION` file at the repository root. Bump it
with `composer version:patch` (or `:minor` / `:major`), which keeps `package.json`
in step.

## [Unreleased]

## [1.0.2] - 2026-08-02

Six production defects, all found by writing tests against code that had none.
Three share one root cause: a narrow type that turned a failure into an
uncatchable `TypeError`.

### Fixed

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

[Unreleased]: https://github.com/rbenzing/Aureo-Project-Management/compare/1.0.2...HEAD
[1.0.2]: https://github.com/rbenzing/Aureo-Project-Management/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/rbenzing/Aureo-Project-Management/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/rbenzing/Aureo-Project-Management/releases/tag/1.0.0
