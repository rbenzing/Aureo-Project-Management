# CLAUDE.md — Aureo Agentic Guidance
Only facts an agent can't infer from code search. Update when something bites.

## Architecture
- Custom PHP 8.1+ MVC. NOT Laravel/Symfony/Eloquent/Blade. No ORM, no annotation routing.
- Routes registered explicitly in [public/index.php](public/index.php).
- DI: small custom container at [config/container.php](config/container.php) — `$container->get(Class::class)`.
- Views: plain PHP in [src/Views/](src/Views/). Escape with `htmlspecialchars()`.
- DB: raw PDO via `BaseModel::queryBuilder()`. Soft deletes auto-injected via `is_deleted = 0`.

## Lifecycle
- `composer install` runs npm install + npm run build only. Composer pipes STDIN through its process wrapper, which breaks interactive prompts.
- DB setup runs separately: `php bin/setup.php` (NOT `composer setup`). Writes `.env`, runs Phinx, sets admin password, optionally imports sample data.
- `composer start` = `php -S` (opcaches aggressively — restart on edits). Composer times out at 300s unless `process-timeout: 0`.
- App logs: `<repo>/log/aureo.log` — FIRST place to look on any failure. Resolved via `dirname(BASE_PATH, 2)` where `BASE_PATH = public/`.

## Load-bearing rules
- **`BaseController::render($view, $data)` includes from `BASE_PATH . '/../src/Views/...'`.** ANY variable the view needs must be in `$data`; controller-local vars are NOT in view scope. Auto-injected: `$currentUser`, `$csrfToken`, `$error/$success/$info`.
- **`queryBuilder` REQUIRES `'alias' => 'x'` whenever `select`/`joins`/`where`/`orderBy` use a table alias.** Without it, FROM has no alias and soft-delete uses the bare table — every `x.*` reference errors with `Unknown table 'x'`. Returns `stdClass`, not arrays.
- **`Task::buildOrderByClause` returns clauses WITHOUT the `ORDER BY` prefix.** Pass directly as queryBuilder `'orderBy'` option. For raw SQL, prepend `"ORDER BY "` at the call site.
- **Do NOT redefine `tryFrom()` on backed enums** — fatal `Cannot redeclare ::tryfrom()`. Use `fromOrDefault()` / `tryFromInt()` per project convention.
- **Permission helpers read `$_SESSION['user']['permissions']` directly**, not `$currentUser`. `$currentUser` only exists in view-main scope (via `extract()`), not inside function bodies.
- **Top-level catch in [public/index.php](public/index.php) is `\Throwable`**, and `LoggerService::exception()` accepts `\Throwable`. Don't narrow these.
- **Auth gate runs BEFORE routing.** New public route → add first URL segment to `$publicPaths`.
- **Session shape:** `$_SESSION['user'] = ['id','profile'=>[...],'roles'=>[],'permissions'=>[],'config'=>[]]`. Permissions via `hasUserPermission($name)` or `requirePermission($name)`.

## Schema & migrations
- Canonical migration [db/migrations/20251222180705_initial_database_schema.php](db/migrations/20251222180705_initial_database_schema.php) IS the install path AND seeds the admin user with all 55 permissions. Do not rename/rewrite — add NEW Phinx migrations instead. `schema.sql` is informational only.
- `phinx.php` declares `production`/`local`/`development`/`testing` envs. New `APP_ENV` value → add a matching block or Phinx fails.

## SQL gotchas
- **Native MySQL prepares** (with `PDO::ATTR_EMULATE_PREPARES=false`) require one named placeholder per binding. Reusing `:foo` twice in one statement throws `Invalid parameter number` — use distinct names (`:foo_a`, `:foo_b`).
- **`VALUES (...)` table constructors require `ROW(...)` keyword** on MySQL 8.0.19+. Bare `(a,b,c)` doesn't parse as a derived table.
- **Window functions** (8.0.20+) reject non-deterministic `ORDER BY` (e.g. `RAND()`).
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
