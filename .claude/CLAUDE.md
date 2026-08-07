# CLAUDE.md — Aureo Agentic Guidance
Only facts an agent can't infer from code search. Update when something bites.

## Architecture
- Custom PHP 8.2+ MVC. NOT Laravel/Symfony/Eloquent/Blade. No ORM, no annotation routing.
- Routes registered explicitly in [public/index.php](../public/index.php).
- DI: small custom container at [config/container.php](../config/container.php) — `$container->get(Class::class)`.
- Views: plain PHP in [src/Views/](../src/Views/). Escape with `htmlspecialchars()`.
- DB: raw PDO via `BaseModel::queryBuilder()`. Soft deletes auto-injected via `is_deleted = 0`.
- Two supported deployment layouts (docroot at `public/`, recommended; docroot at app root, "drop-in") resolved by `App\Core\RequestPath`/`Config::basePath()`. Both mount the app at the domain root — subdirectory installs are **not** supported; routes/links are root-absolute throughout `src/Views`/`src/Controllers`, so a subdirectory mount renders but can't log in. `RequestPath`'s subdirectory-mount resolution ships and is tested as groundwork only, with no production consumer today — see [docs/DEPLOYMENT.md#known-issues](../docs/DEPLOYMENT.md#known-issues). `BASE_PATH` always stays `public/` regardless of layout — see [docs/ARCHITECTURE.md](../docs/ARCHITECTURE.md#why-base_path-stays-at-public).

## Lifecycle
- `composer install` runs npm install + npm run build only.
- DB setup: `composer setup` (non-interactive, uses `.env` defaults) or `php bin/setup.php` (interactive prompts). Writes `.env`, runs Phinx, sets admin password to `"password"`, optionally imports sample data, writes `config/installed.lock` on success. That lock file disables the web installer's `/install` route; deleting it re-opens that unauthenticated route, which can rewrite the site's configuration.
- `composer start` = `php -S` (opcaches aggressively — restart on edits). Composer times out at 300s unless `process-timeout: 0`.
- App logs: `<repo>/log/aureo.log` — FIRST place to look on any failure. Resolved via `dirname(BASE_PATH)` where `BASE_PATH = public/` (one level up = repo root, matching `Config.php`). NOTE: `dirname(BASE_PATH, 2)` is WRONG — it writes to the repo's PARENT dir, silently hiding every logged error (this bit us).

## Load-bearing rules
- **`BaseController::render($view, $data)` includes from `BASE_PATH . '/../src/Views/...'`.** ANY variable the view needs must be in `$data`; controller-local vars are NOT in view scope. Auto-injected: `$currentUser`, `$csrfToken`, `$error/$success/$info`.
- **`queryBuilder` REQUIRES `'alias' => 'x'` whenever `select`/`joins`/`where`/`orderBy` use a table alias.** Without it, FROM has no alias and soft-delete uses the bare table — every `x.*` reference errors with `Unknown table 'x'`. Returns `stdClass`, not arrays.
- **`Validator` rule names are snake_case; handlers are StudlyCase.** Dispatch is `'validate' . str_replace('_', '', ucwords($rule, '_'))`. It used `ucfirst()`, which silently broke `strong_password` (resolved to `validateStrong_password`, `method_exists()` false) — and because `validateField()` SKIPS unknown rules without erroring, password strength went unenforced on registration and reset. Any new multi-word rule needs a StudlyCase handler; `ValidatorEdgeCaseTest` has a guard test asserting every entry in `AVAILABLE_RULES` resolves (`nullable` excepted — it is a modifier consumed by `isNullable()`).
- **`LoggerService::log()` is PRIVATE.** Call `error()/warning()/info()/debug()/exception()/activity()/security()`. 18 call sites across TaskService, ProjectService and the task listeners used `->log('info', ...)` and fataled at runtime for years. Mocking `'log'` in a test throws `MethodCannotBeConfiguredException`. LoggerService also redirects PHP's `error_log` into its own log file, so fallback output lands there in error_log's format.
- **`Task::buildOrderByClause` returns clauses WITHOUT the `ORDER BY` prefix.** Pass directly as queryBuilder `'orderBy'` option. For raw SQL, prepend `"ORDER BY "` at the call site.
- **Do NOT redefine `tryFrom()` on backed enums** — fatal `Cannot redeclare ::tryfrom()`. Use `fromOrDefault()` / `tryFromInt()` per project convention.
- **Permission helpers read `$_SESSION['user']['permissions']` directly**, not `$currentUser`. `$currentUser` only exists in view-main scope (via `extract()`), not inside function bodies.
- **Top-level catch in [public/index.php](../public/index.php) is `\Throwable`**, and `LoggerService::exception()` accepts `\Throwable`. Don't narrow these.
- **Auth gate runs BEFORE routing.** New public route → add first URL segment to `$publicPaths`.
- **Session shape:** `$_SESSION['user'] = ['id','profile'=>[...],'roles'=>[],'permissions'=>[],'config'=>[]]`. Permissions via `hasUserPermission($name)` or `requirePermission($name)`.
- **`requirePermission()` is `void` and halts on failure** (403 + exit path), it does not return a boolean. `!$this->requirePermission(...)` is always `!null` — always `true` — and reads as "check failed" when it never can. `TimeTrackingController::startTimer()` has exactly this bug: `!$this->requirePermission('manage_tasks')` rejects users who DO have the permission. Check permissions with `hasUserPermission()` when you need a boolean; use `requirePermission()` only for its side effect.
- **`asset()` is the only way to reference bundled CSS/JS.** Asset URLs were hardcoded root-absolute in 53 places across 51 views (every view has its own `<head>`), which breaks the drop-in layout (docroot at app root, where assets need the `AUREO_ASSET_PREFIX`-supplied `/public/assets` prefix that `asset()` provides). `AssetUrlTest` fails on any new hardcoded `/assets/` URL. A miss is invisible on a dev machine, where the base path is empty.
- **Do NOT locate the config file with `dirname(BASE_PATH, 2)`.** Correct for both supported layouts (docroot-is-`public/` and docroot-is-app-root), but resolves *inside* the web root for a subdirectory mount — not a supported layout, but `RequestPath` still resolves one correctly as groundwork, so don't reintroduce this footgun if that ever gets built out. Derive from `DOCUMENT_ROOT`. (Separately, `dirname(BASE_PATH, 2)` is also wrong for the log path — same footgun, different concern.)
- **`renderCSRFToken()` reading `$csrfToken` from its own function body always returns an empty token.** `BaseController::render()` only `extract()`s `$csrfToken` into *view* scope, and a function body cannot see a caller's local variables — this is the same defect class as the `render()` rule above, just inside a helper instead of a view. Fixed in `FormComponents.php` by reading `$_SESSION['csrf_token']` directly (the value `CsrfMiddleware` itself writes and validates against). `ViewHelpers.php::renderTimerControls()` has the identical bug and is currently unfixed — it is dead code (nothing calls it), so it has not manifested, but copying its pattern into live code will silently ship a broken CSRF field.

## Testing & coverage
- **Coverage needs a driver.** Local = Xdebug (`xdebug.mode=coverage` in php.ini); CI = PCOV. Without one, `--coverage-*` silently produces nothing and every strictness check below becomes a no-op — the suite looks green while proving nothing. This bit us: CI was red for 45 risky tests that never appeared locally.
- **PHPUnit only COLLECTS coverage when a `--coverage-*` flag is passed.** A bare `phpunit` run reports 0 risky tests even with strict metadata on. Always verify with `composer coverage:check`.
- **`beStrictAboutCoverageMetadata=true` + `failOnRisky=true` means a test annotated `#[CoversClass(X)]` MUST also declare `#[UsesClass(Y)]` for EVERY other `App\` class it executes transitively.** Miss one and the test is marked risky AND its coverage is silently discarded — the class shows 0% while its tests pass.
- **NEVER put file-level executable code in a PSR-4 class file.** The old `if (!defined('BASE_PATH'))` web guard was copy-pasted from Views into 10 class files; being a top-level statement it counted as "code executed but not covered", voiding coverage for every test that loaded those classes. Views keep the guard (they emit HTML and are include-only). Class files must declare symbols and nothing else (PSR-1 §2.3).
- Tiered gate: `bin/coverage-gate.php` (`composer coverage:check`). Tier 1 (Core, Enums, Events, Exceptions, Http, Listeners, Repositories, Services, Utils) targets 90% and currently sits at 95.11%. Tier 2 (Controllers, Middleware, Models) ratchets from its floor. ALL floors live in `coverage-floor.json` and may only rise, via `composer coverage:ratchet`. Running `--update` while regressed does NOT lower them. The gate allows 0.5 points of slack below a floor: measured coverage drifts a few hundredths between runs because singleton first-init attribution moves with execution order, and a zero-tolerance ratchet turns that into spurious CI failures.
- **`#[UsesClass]` must be declared broadly on anything reaching a singleton.** `Config`, `Database`, `Setting`, `SettingsService` and `LoggerService` initialize once per process, so only the FIRST test in a given run to touch them actually executes their bodies. Which test that is depends on `executionOrder="depends,defects"`, so the strict-metadata check is order-dependent: a suite green on one run can report risky tests on the next. Declare the whole reachable chain rather than only what a single run reports. Overdeclaring is safe — `#[UsesClass]` permits execution without contributing coverage credit.
- **Methods ending in `exit` cannot be covered.** Every public method of `Core/ApiResponse` and `Core/Response` terminates in `exit`, which is a language construct (not shadowable like `file_exists`) and kills the runner mid-test. That caps `src/Core` at ~69%. Do NOT chase it with process isolation. Making these return/throw instead of exiting is the only real fix, and it is an app-wide refactor.
- Namespace-scoped function shadowing (declaring `App\Core\file_exists()` in a test file) is the working technique for forcing `Config`/`Database` fallback branches without touching `src/`. See `tests/Unit/Core/Support/`.
- CI adds `--fail-on-skipped`; it provisions MySQL, so a skip there is a broken harness. Locally skips stay graceful — do NOT add that flag to `phpunit.xml`.

## PHP version floor
- **`config.platform.php = "8.2.0"` in composer.json is load-bearing.** It forces Composer to resolve as if on 8.2 regardless of the local runtime. Without it, resolving on a newer PHP silently locks packages that the 8.2 floor cannot install — this happened: Symfony 8.1.x (`php >=8.4.1`) got locked while CI ran 8.2.
- Never run `composer update` with that pin removed. Re-check with `composer check-platform-reqs`.
- CI matrix covers 8.2/8.3/8.4/8.5; only the 8.2 job runs the coverage gate (a single floor file cannot be ratcheted by parallel jobs).

## Schema & migrations
- Canonical migration [db/migrations/20251222180705_initial_database_schema.php](../db/migrations/20251222180705_initial_database_schema.php) IS the install path AND seeds the admin user with all 55 permissions. Do not rename/rewrite — add NEW Phinx migrations instead. There is no separate `schema.sql` — it was deleted (dead, drifted from the migration) and the canonical migration is the only schema representation. **One narrow, deliberate exception exists:** the admin seed's password hash algorithm was changed in place (unconditional `PASSWORD_ARGON2ID` → `App\Services\InstallerService::preferredPasswordAlgorithm()`), because the hardcoded constant threw `ValueError` on any PHP built without libargon2 — i.e. the install path itself was not portable. It changes no schema and the seed only ever runs on a fresh database. This is not a precedent for editing the migration otherwise.
- `phinx.php` declares `production`/`local`/`development`/`testing` envs. New `APP_ENV` value → add a matching block or Phinx fails. It resolves config via the same `App\Core\ConfigLoader` five-rung chain as the app (env vars → `AUREO_CONFIG` override → `config/config-path.php` → `config/config.php` → `.env` — see [docs/DEPLOYMENT.md#configuration-sources](../docs/DEPLOYMENT.md#configuration-sources)), so migrations work under every deployment layout.

## SQL gotchas
- **Native MySQL prepares** (with `PDO::ATTR_EMULATE_PREPARES=false`) require one named placeholder per binding. Reusing `:foo` twice in one statement throws `Invalid parameter number` — use distinct names (`:foo_a`, `:foo_b`).
- **`INSERT ... ON DUPLICATE KEY UPDATE`** is the project's pattern for upserts — see `SecurityService::checkRateLimit` for an example.

## Env quirks
- `APP_ENV=production` requires non-empty `DB_PASSWORD`. `setup.php` writes `APP_ENV=local`.
- For local HTTP dev: `SESSION_SECURE=false`, `APP_SCHEME=http` (otherwise cookies/CSRF silently fail).

## Known footguns
- Windows XAMPP `mysql.exe` may fail with `caching_sha2_password could not be loaded` — use PDO from PHP scripts or `composer pma` instead.
- PHP opcache: the dev server caches compiled files. Restart `composer start` after editing PHP — don't waste cycles debugging stale bytecode.

## Do
- Match the closest existing pattern over textbook ideals — codebase isn't uniformly idiomatic.
- Run `composer cs:check` + `composer test` after edits. Tail `log/aureo.log` to confirm no warnings.
- Update this file when you hit a new non-obvious constraint.

## Don't
- No new frameworks/ORMs/templating engines/JS frameworks. Vanilla PHP + Tailwind only.
- Don't edit the canonical migration in place. Don't redefine `tryFrom`. Don't call `render()` without view data.
- No string interpolation in SQL — parameterized PDO only. No `@` error suppression. No catching `\Exception` where `\Throwable` is correct.
- Don't commit `.env`, secrets, `vendor/`, `log/`, `tools/`, compiled CSS/JS, or anything `.gitignore` blocks. Surface policy questions before un-ignoring.
- Don't write comments that restate code. WHY only, when non-obvious.
