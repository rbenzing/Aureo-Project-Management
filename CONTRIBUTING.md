# Contributing to Aureo Project Management

Thanks for your interest in improving Aureo. This document covers how to get a development
environment running, the standards a change must meet, and what happens to your pull request.

By contributing, you agree that your contributions are licensed under the
[GNU Affero General Public License v3.0 or later](./LICENSE), the same license as the project.

---

## Before you start

- **Bugs and features:** open an [issue](https://github.com/rbenzing/Aureo-Project-Management/issues)
  first for anything non-trivial. A short discussion beats a rejected pull request.
- **Security vulnerabilities:** do **not** open a public issue. Follow the private disclosure
  process in [SECURITY.md](./docs/SECURITY.md).
- **Architecture:** read [ARCHITECTURE.md](./docs/ARCHITECTURE.md) before changing anything structural.
- **Agent guidance:** [.claude/CLAUDE.md](./.claude/CLAUDE.md) lists the non-obvious constraints
  that have bitten contributors before. It is short and worth the two minutes.

---

## Development setup

**Prerequisites:** PHP 8.2+, MySQL 8.0+, Composer, Node.js + npm.

```bash
git clone https://github.com/rbenzing/Aureo-Project-Management.git
cd Aureo-Project-Management

composer install      # PHP + npm dependencies, builds CSS
php bin/setup.php     # interactive: DB credentials, migrations, admin password, sample data
composer start        # http://localhost:8000
```

Log in as `admin@aureo.us` / `password`.

**Local development settings.** Keep `SESSION_SECURE=false` and `APP_SCHEME=http` in `.env` for
plain HTTP, or session cookies and CSRF tokens fail silently with no useful error.

**Restart the server after editing PHP.** `composer start` uses PHP's built-in server, which caches
compiled bytecode aggressively. A surprising number of "impossible" bugs are stale opcache.

**When something breaks, read `log/aureo.log` first.** It is the fastest path to the real cause on
any 500 or blank page.

---

## Branching and commits

Work on a branch off `master`:

```bash
git checkout -b fix/sprint-planning-drag-permission
```

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): short imperative summary

Optional body explaining WHY the change is needed, not what the diff shows.
```

Types in use: `feat`, `fix`, `refactor`, `perf`, `docs`, `test`, `chore`, `style`.

```
feat(sprint): add getPlannableByProjectId — single source for assignable sprints
fix(sprint-planning): gate drag on edit_sprints perm; dedupe project id
docs(architecture): document the request lifecycle
```

Keep commits focused. A commit that fixes one bug is reviewable; a commit that fixes one bug and
reformats four files is not.

---

## Coding standards

**PSR-12, enforced.** Run `composer cs:fix` before committing; `composer cs:check` must pass.

> The full lint pass can exceed Composer's 300-second process timeout on large working trees. If
> `composer cs:check` reports a timeout rather than violations, run the underlying tool directly:
> `php vendor/bin/php-cs-fixer fix --dry-run --diff`.

**Non-negotiables:**

- `declare(strict_types=1);` at the top of every non-view PHP file.
- **Parameterized PDO only.** No string interpolation into SQL, ever.
- **Escape all output** in views: `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`.
- No `@` error suppression.
- Catch `\Throwable` where the code must not leak a fatal — do not narrow existing `\Throwable`
  catches to `\Exception`.
- No new frameworks, ORMs, templating engines, or JavaScript frameworks. Vanilla PHP + Tailwind.

**Comments explain WHY, not WHAT.** A comment restating the line below it is noise. A comment
explaining why a non-obvious approach was necessary is valuable.

**Match the surrounding code.** The codebase is not uniformly idiomatic. A change that looks like
its neighbors is easier to review and maintain than one that is locally perfect but stylistically
unique.

---

## Conventions that will trip you up

These are the recurring review comments. Getting them right the first time speeds up your PR.

| Rule | Why |
|---|---|
| Every variable a view uses must be in the `render()` `$data` array | Controller-local variables are not in view scope. Only `$currentUser`, `$csrfToken`, and `$error`/`$success`/`$info` are auto-injected. |
| Pass `'alias' => 'x'` to `queryBuilder()` whenever you use a table alias | Otherwise `FROM` has no alias, the soft-delete predicate targets the bare table, and every `x.*` reference fails with `Unknown table 'x'`. |
| `queryBuilder()` returns `stdClass`, not arrays | Access with `->prop`, not `['prop']`. |
| Never redefine `tryFrom()` on a backed enum | Fatal: `Cannot redeclare ::tryfrom()`. Use `fromOrDefault()` / `tryFromInt()`. |
| New public route → add its first URL segment to `$publicPaths` | The auth gate runs *before* routing and will bounce it to login. |
| Never edit the canonical migration | `db/migrations/20251222180705_initial_database_schema.php` is the install path and the permission seed. Add a **new** migration. |
| Permission checks inside functions read `$_SESSION['user']['permissions']` | `$currentUser` exists only in main view scope via `extract()`. |
| `Task::buildOrderByClause()` returns clauses **without** the `ORDER BY` prefix | Pass straight to `queryBuilder`'s `orderBy`; prepend `"ORDER BY "` yourself for raw SQL. |

**View directory casing matters.** Render paths must match the directory on disk exactly —
`$this->render('TimeTracking/index')`, not `'time-tracking/index'`. Windows hides the mismatch;
Linux deployments return a broken page.

---

## Database changes

1. Create the migration: `composer migrate:create AddWidgetTable`
2. Write `up()` and a real `down()` — rollbacks should work.
3. Apply and verify: `composer migrate` then `composer migrate:status`
4. Never modify an already-committed migration. Correct it with a new one.

The canonical Phinx migration is the only schema representation; there is no separate SQL dump.

---

## Testing

```bash
composer test            # full suite
composer test:coverage   # HTML coverage report in coverage/
```

- Unit tests go in `tests/Unit/`, integration tests in `tests/Integration/`.
- Shared harness code lives in `tests/Support/` (`TestCase`, `DatabaseTestCase`,
  `ControllerTestCase`).
- Bug fixes should come with a test that fails before the fix and passes after.
- Integration tests under `tests/Integration/` need a running MySQL instance. Without one they
  error with `No connection could be made` — that is an environment problem, not a code failure,
  but do not use it as cover for real breakage. Check the failure list before assuming.

---

## Submitting a pull request

Before opening it:

```bash
composer cs:check   # PSR-12 clean
composer test       # suite passes (or only known DB-less integration errors)
composer audit      # no new dependency advisories
npm run build       # CSS builds if you touched styles
```

Also confirm:

- [ ] No new warnings or errors in `log/aureo.log` while exercising your change.
- [ ] Nothing ignored by `.gitignore` got committed — no `.env`, secrets, `vendor/`, `log/`,
      `tools/`, or compiled CSS/JS.
- [ ] New user-facing behavior is documented in the README where relevant.
- [ ] New non-obvious constraint discovered? Add it to `.claude/CLAUDE.md`.

In the pull request description, state what changed and **why**, how you tested it, and any
migration or configuration steps a deployer needs to take.

### Review

Pull requests are reviewed for correctness, security, and fit with existing patterns. Expect
questions — they are about the code, not about you. Small, focused pull requests get reviewed
faster than large ones.

---

## Reporting bugs

A useful bug report includes:

- What you expected and what happened instead
- Exact steps to reproduce
- PHP version, MySQL version, and OS
- The relevant excerpt from `log/aureo.log`
- Screenshots for UI issues

## Requesting features

Describe the problem you are trying to solve before the solution you have in mind. Include who
needs it and how they work today without it. That context frequently produces a better design than
the one originally proposed.

---

## Questions

Open an [issue](https://github.com/rbenzing/Aureo-Project-Management/issues) or reach the
maintainer at [me@russellbenzing.com](mailto:me@russellbenzing.com).
