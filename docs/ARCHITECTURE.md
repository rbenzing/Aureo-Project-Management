# Architecture

How Aureo is put together, why it is put together that way, and the conventions that keep it
consistent. Read this before making a non-trivial change; read [.claude/CLAUDE.md](../.claude/CLAUDE.md)
for the sharp edges that will bite you in the first hour.

---

## Design principles

**No framework.** Aureo is a custom MVC application on plain PHP 8.1+. There is no Laravel, no
Symfony HttpKernel, no Eloquent, no Blade, no annotation routing, and no JavaScript framework. The
tradeoff is deliberate: the entire request path is readable in an afternoon, upgrades are ours to
schedule, and nothing behaves by magic. The cost is that conventions are enforced by review and by
this document rather than by a framework, which is exactly why both exist.

**Explicit over implicit.** Routes are registered by hand. Dependencies are declared in one
container file. View data is passed as an explicit array. Nothing is auto-discovered.

**Raw SQL, safely.** All persistence is PDO with prepared statements. There is no ORM and no query
DSL beyond the shared `queryBuilder()` helper. String interpolation into SQL is prohibited without
exception.

**Strict types everywhere.** Every non-view PHP file starts with `declare(strict_types=1);`.

---

## Request lifecycle

A request enters through [public/index.php](../public/index.php) — the single front controller and
the only route registry — and moves through these stages in order:

| # | Stage | What happens |
|---|-------|--------------|
| 1 | **Session** | `session_start()` before anything else. |
| 2 | **CSP header** | Content-Security-Policy emitted immediately, before any code can output. |
| 3 | **Autoload** | Composer autoloader; `App\` → `src/`, PSR-4. |
| 4 | **Config** | `Config::init()` loads `.env` **before** the container, because container factories read `$_ENV`. |
| 5 | **Container** | `config/container.php` returns a built PHP-DI container. |
| 6 | **Security headers** | `SecurityService::applySecurityHeaders()`, with a hardcoded fallback set if settings are unavailable. |
| 7 | **Rate limit** | Database-persisted check; 429 and exit on breach. |
| 8 | **Input size** | POST bodies over the configured limit get 413 and exit. |
| 9 | **Middleware** | `CsrfMiddleware::handleToken()`, then `ActivityMiddleware::handle()`. |
| 10 | **Auth gate** | Runs **before routing**. Any first URL segment not in `$publicPaths` requires an authenticated session. |
| 11 | **Event wiring** | Listeners registered on the `EventDispatcher` singleton. |
| 12 | **Routing** | `Router::dispatch($method, $segments)` resolves the controller from the container and invokes the action. |
| 13 | **Error handling** | `\PDOException` and `\Throwable` catches log and render a response whose detail level depends on `shouldHideErrorDetails()`. |

Two consequences worth internalizing:

- **A new public route must be added to `$publicPaths`** in `public/index.php`, or the auth gate
  will bounce it to login before the router ever sees it.
- **The top-level catch is `\Throwable`, not `\Exception`.** PHP `Error`s (fatal type errors) must
  be caught here. Do not narrow it — `LoggerService::exception()` accepts `\Throwable` for the same
  reason.

4xx responses are logged as terse warnings without stack traces; only 5xx gets full exception
logging. Bots probing `/.well-known/*` would otherwise drown the log.

---

## Layers

```
public/index.php          front controller + route registry
        │
        ▼
  Core\Router             segment matching, container resolution, dispatch
        │
        ▼
  Middleware              Session, Auth, Csrf, Activity
        │
        ▼
  Controllers             HTTP concerns only; extend BaseController
        │
        ├──► Http\Requests    input validation (FormRequest subclasses)
        ├──► Services         business logic, orchestration, infrastructure
        ├──► Repositories     heavier / composite read paths
        └──► Models           table-level persistence; extend BaseModel
                  │
                  ▼
            Core\Database     PDO connection, prepared statements
```

### `Core/`

`Router`, `Config`, `Database`, `Response`, `ApiResponse`. Small, boring, and stable — changes here
affect everything, so they get the most scrutiny in review.

### `Controllers/`

HTTP handlers. They read request data, enforce permissions, delegate to services or models, and
render or redirect. They do not contain business rules and do not build SQL.

`BaseController` provides the shared vocabulary:

- `render(string $view, array $data = [])` — includes `BASE_PATH . '/../src/Views/...'`
- `requirePermission(string $permission)` — 403 unless the session grants it
- `redirectWithSuccess()` / `redirectWithError()` / `redirectWithInfo()` — flash + redirect
- `getPaginationParams()`, `getSortParams()`, `buildFilters()` — list-page plumbing
- `handleException()` — log, flash, redirect in one call

**The load-bearing rule:** every variable a view needs must be in the `$data` array passed to
`render()`. Controller-local variables are not in view scope. `render()` auto-injects only
`$currentUser`, `$csrfToken`, and `$error` / `$success` / `$info`.

### `Http/Requests/`

`FormRequest` subclasses declare `rules()` (and optionally `messages()` and `authorize()`), then
`validate()` returns clean data or throws `ValidationException`. Use these for anything with more
than a couple of fields; `Utils\Validator` covers the small cases.

### `Services/`

Business logic and infrastructure: `ProjectService`, `TaskService`, `SearchService`,
`SecurityService`, `SettingsService`, `CacheService`, `LoggerService`. Services may use models and
repositories; they never touch `$_GET`, `$_POST`, or `$_SESSION` directly. Anything a second
controller might one day need belongs here rather than in a controller.

### `Repositories/`

Query objects implementing `RepositoryInterface` (`find`, `findOrFail`, `getAll`, `create`,
`update`, `delete`, `count`, `exists`) for the paths where models alone got unwieldy — projects,
tasks, sprints, users, search. Models remain the table-level primitive; repositories compose them.

### `Models/`

Extend `BaseModel`, which supplies `find`, `findOrFail`, `getAll`, `create`, `update`, `delete`,
`count`, transaction helpers, `afterSave` / `afterDelete` hooks, and the protected `queryBuilder()`
/ `queryBuilderCount()` helpers. Soft deletes (`is_deleted = 0`) are injected automatically.

**Two `queryBuilder` rules that cause real outages:**

1. Pass `'alias' => 'x'` whenever `select`, `joins`, `where`, or `orderBy` reference a table alias.
   Without it the `FROM` clause has no alias, the soft-delete predicate targets the bare table, and
   every `x.*` reference fails with `Unknown table 'x'`.
2. `queryBuilder()` returns `stdClass` objects, not associative arrays.

### `Views/`

Plain PHP templates, no engine. Escape with `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`. Shared
partials live in `Views/Layouts/`; per-feature fragments live in a `inc/` subdirectory next to the
pages that include them. Includes are absolute: `BASE_PATH . '/../src/Views/Feature/inc/foo.php'`.

Permission helpers inside view partials read `$_SESSION['user']['permissions']` directly rather
than `$currentUser` — `$currentUser` exists only in the main view scope via `extract()`, not inside
function bodies.

### `Events/` and `Listeners/`

A minimal in-process dispatcher (`EventDispatcher::getInstance()`). Events (`TaskAssigned`,
`TaskCompleted`, `ProjectCreated`) decouple side effects — assignment logging, notification email,
search index updates — from the code that performs the write. Listeners are registered explicitly
in `public/index.php`. There is no queue; listeners run synchronously in the request.

### `Enums/`

Backed enums for status and type columns (`TaskStatus`, `Priority`, `SprintStatus`, …).

**Never redefine `tryFrom()` on a backed enum** — PHP fatals with `Cannot redeclare ::tryfrom()`.
The project convention is `fromOrDefault()` / `tryFromInt()`.

---

## Data layer

**Schema of record** is the canonical Phinx migration,
`db/migrations/20251222180705_initial_database_schema.php`. It is both the install path and the
seed for the admin user and all 55 permissions. Never edit it in place and never rename it — add a
new migration instead. `schema.sql` is informational only and may lag.

**Environments** are declared in `phinx.php`: `production`, `local`, `development`, `testing`. A new
`APP_ENV` value without a matching block makes Phinx fail.

### MySQL specifics that have bitten this codebase

- Native prepares are on (`PDO::ATTR_EMULATE_PREPARES = false`), so **each binding needs its own
  named placeholder**. Reusing `:foo` twice in one statement throws `Invalid parameter number` —
  use `:foo_a` / `:foo_b`.
- `VALUES (...)` table constructors need the `ROW(...)` keyword on MySQL 8.0.19+.
- Window functions (8.0.20+) reject non-deterministic `ORDER BY` such as `RAND()`.
- `INSERT ... ON DUPLICATE KEY UPDATE` is the project's upsert idiom — see
  `SecurityService::checkRateLimit()`.

---

## Authentication and authorization

Session shape:

```php
$_SESSION['user'] = [
    'id'          => 1,
    'profile'     => [...],
    'roles'       => [...],
    'permissions' => [...],
    'config'      => [...],
];
```

Authorization is permission-based, not role-based, at the check site: roles are bundles of
permissions, but code asks `hasUserPermission('edit_sprints')` or `requirePermission(...)`. There
are 55 permissions seeded by the canonical migration.

---

## Frontend

Tailwind CSS 3.4 compiled from `src/css/input.css` to `public/assets/css/styles.css` via
`npm run build`. The compiled stylesheet is a **build artifact and is not committed** — `composer
install` builds it, and `npm run watch` rebuilds on change.

JavaScript is vanilla and lives in `public/assets/js/`: `scripts.js` (shared behavior),
`command-palette.js` (global keyboard search), `favorites.js` / `favorites-utils.js`. Feature-local
scripts sit beside their views (e.g. `Views/Tasks/inc/task_filtering.js`). No bundler, no
transpiler, no framework.

---

## Observability

Application log: **`log/aureo.log`**. It is the first place to look on any 500 or blank page.

The path resolves via `dirname(BASE_PATH)` where `BASE_PATH` is `public/`, putting the log at the
repo root and matching `Config.php`. `dirname(BASE_PATH, 2)` is wrong — it writes to the repo's
*parent* directory and silently hides every logged error.

---

## Development environment notes

- `composer install` runs `npm install` + `npm run build` only. Database setup is a separate,
  explicit step (`php bin/setup.php`).
- `composer start` uses PHP's built-in server, which caches compiled bytecode aggressively.
  **Restart it after editing PHP** rather than debugging stale opcache behavior.
- Local HTTP development requires `SESSION_SECURE=false` and `APP_SCHEME=http`, or cookies and CSRF
  fail silently.
- `APP_ENV=production` requires a non-empty `DB_PASSWORD` and enables container compilation into
  `var/cache`.

---

## Extending the application

**Adding a feature end to end:**

1. New migration in `db/migrations/` — never touch the canonical one.
2. Model in `src/Models/` extending `BaseModel`; add a repository if reads get composite.
3. Business logic in `src/Services/`.
4. `FormRequest` in `src/Http/Requests/` for input validation.
5. Controller in `src/Controllers/` extending `BaseController`; gate actions with
   `requirePermission()`.
6. Views in `src/Views/Feature/` with fragments under `inc/`.
7. Routes in `public/index.php` — and `$publicPaths` too if the route is unauthenticated.
8. New permission? Add it in a migration and assign it to the roles that need it.
9. Tests in `tests/Unit/` or `tests/Integration/`.

**Prefer the closest existing pattern over the textbook ideal.** The codebase is not uniformly
idiomatic, and a change that matches its neighbors is easier to review and maintain than one that
is locally perfect but locally unique.
