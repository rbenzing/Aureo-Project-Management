# Aureo — Deployment and Operations

Installing, configuring, upgrading and operating Aureo on a real server. For day-to-day use see
[USERGUIDE.md](./USERGUIDE.md); for the security posture behind these steps see
[SECURITY.md](./SECURITY.md).

---

## Contents

- [Requirements](#requirements)
- [Deployment layouts](#deployment-layouts)
- [Installation](#installation)
- [Configuration reference](#configuration-reference)
- [Configuration sources](#configuration-sources)
- [Web server](#web-server)
- [Database and migrations](#database-and-migrations)
- [Go-live checklist](#go-live-checklist)
- [Upgrading](#upgrading)
- [Backups and restore](#backups-and-restore)
- [Operations](#operations)
- [Troubleshooting](#troubleshooting)
- [Known issues](#known-issues)

---

## Requirements

| Component | Requirement |
|---|---|
| PHP | 8.2 or newer, with `pdo`, `pdo_mysql`, `mbstring` |
| MySQL | 8.0 or newer |
| Composer | 2.x |
| Node.js + npm | Required only to *change* the stylesheet — the compiled CSS ships in the repository |
| Web server | Nginx + PHP-FPM, or Apache with the bundled `.htaccess` |
| TLS | Required in production |

`composer.json` requires `^8.2` and pins `config.platform.php` to `8.2.0`, so Composer will not
resolve dependencies for an older PHP even if one happens to be on the host. Nothing in the
codebase depends on a specific MySQL 8.0.x point release — no `ROW(...)` table constructor and no
window function appears anywhere in `src/`, `db/` or `tests/`. "8.0 or newer" is a floor chosen for
`utf8mb4_unicode_ci` and JSON column support, not a version-specific SQL feature.

---

## Deployment layouts

Aureo supports two layouts. Both are handled by one request-path resolver
(`App\Core\RequestPath`, see
[ARCHITECTURE.md](./ARCHITECTURE.md#requestpath-and-configloader)), so the application code does
not change between them — only where the document root points and how configuration is supplied.

| Layout | Document root | Configuration | Notes |
|---|---|---|---|
| **Recommended** | `<app>/public` | `.env`, one level *above* the document root — outside the served tree, so unreachable by URL regardless of server rules | Nothing web-reachable but the front controller and assets |
| Drop-in | `<app>` (the application root) | `config/config.php`, *inside* the served tree at `<app>/config/` — reachable in principle, kept out by the `config` deny rule in `.htaccess`/`web.config` | Requires the release archive, never a `git clone` |

**Both layouts mount the application at the domain root.** Routes and links throughout
`src/Views` and `src/Controllers` are written root-absolute (`href="/tasks"`,
`action="/login"`, `redirect('/projects')`), so the app must be reachable at `https://host/`,
not `https://host/somewhere/`. Installing into a subdirectory is **not supported** — see
[Known issues](#known-issues).

**Document root at `public/` remains recommended.** It is the only layout where the web server can
serve *only* the front controller and static assets — everything else (`.env`, `vendor/`, `db/`,
`tests/`, and the rest of `src/`) sits outside the document root entirely and is unreachable by
URL, full stop, regardless of `.htaccess`/`web.config` rules or a misconfiguration in either. In the
drop-in layout, `config/config.php` sits *inside* the served tree (`ConfigLoader` resolves it
from `dirname(BASE_PATH)`, which is fixed relative to the application, not to the document root) —
it is reachable the same way `.env` would be, and stays hidden only because the deny rules in the
shipped `.htaccess` / `web.config` say so. **Do not assume these files are categorically
unreachable the way `.env` is in the recommended layout** — verify the deny rules are actually in
effect (`curl` the config path and confirm a 403, not a 200) before trusting the drop-in layout
in production.

**A `git clone` must never be extracted at a document root.** A `.git/` directory under a served
root discloses the full source history via `GET /.git/config`, `GET /.git/HEAD`, and the object
store underneath — no application-level fix prevents this once `.git/` itself is web-reachable. The
release archive (built for the drop-in layout) omits `.git/` and most
non-runtime files entirely; a `git clone` does not. Use the recommended `public/`-as-document-root
layout for a clone, or the release archive for the drop-in layout.

### Drop-in layout: nginx

Apache picks up the bundled root `.htaccess` automatically. nginx has no per-directory
configuration, so the equivalent rules have to live in the server block:

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/aureo;              # application root, NOT public/
    index index.php;

    # Deny directories that must never be served. .htaccess enforces the same
    # list; nginx has no per-directory config, so it has to be here instead.
    #
    # `src` is here but NOT in the root .htaccess: Apache reads src/.htaccess
    # (Require all denied) on its own, and nginx cannot. Omitting it exposes
    # every view and class file as source.
    #
    # ~* (not ~) mirrors the [NC] flag on the .htaccess RewriteRule. nginx is
    # case-sensitive by default, so GET /.Git/config would otherwise be served
    # on a case-insensitive filesystem.
    location ~* ^/(\.git|\.github|\.claude|\.superpowers|src|db|tests|bin|node_modules|var|log|vendor|config|tools|docs)(/|$) {
        deny all;
    }

    location ~ \.(env|log|sql|md|lock|json|xml|dist|yml|yaml|gz|config|diff)$ {
        deny all;
    }

    # Extensionless and multi-dot files the extension list cannot reach. No
    # bare \.js$ rule: that would also deny /public/assets/js/*.js.
    location ~ ^/(VERSION|tailwind\.config\.js|postcss\.config\.js)$ {
        deny all;
    }

    # Any dotfile - matches .htaccess's ^\. alternative, which is a blanket
    # "filename starts with a dot" rule, not just the extension list above.
    # MUST precede the PHP location block below: a dotfile that happens to
    # end in .php (e.g. the tracked .php-cs-fixer.php) would otherwise fall
    # through both deny blocks above and be executed by php-fpm instead of
    # denied, since nginx picks the first matching regex location in file
    # order and \.php$ would win if listed first.
    location ~ /\. {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

This block is written to have the same coverage as the bundled `.htaccess`: the same denied
directories, the same denied extensions, and — via the blanket dotfile rule above — the same
"any filename starting with a dot" catch-all that `.htaccess`'s `<FilesMatch "(^\.|...)">` provides
and a plain extension list cannot.

The root `index.php` delegate sets `AUREO_ASSET_PREFIX` to `/public/assets` before including
`public/index.php`, so assets are served straight out of the real `public/assets` directory without
copying or symlinking anything. `BASE_PATH` still resolves to `public/` internally, which is what
keeps every `require`/`include` in the codebase working unchanged in this layout — see
[ARCHITECTURE.md](./ARCHITECTURE.md#why-base_path-stays-at-public).

---

## Installation

```bash
git clone https://github.com/rbenzing/Aureo-Project-Management.git
cd Aureo-Project-Management

# Production dependencies, optimized autoloader
composer install --no-dev --optimize-autoloader

# Rebuild the stylesheet only if you are changing it — public/assets/css/styles.css
# ships in the repository, so this step is optional for a stock install.
npm install
npm run build
```

Then configure `.env` and run migrations — see the two sections below.

`bin/setup.php` exists for interactive local setup (it prompts for credentials, runs migrations,
sets the admin password, optionally imports sample data, and writes `config/installed.lock` on
success). **On a production host, prefer configuring `.env` by hand and running migrations
explicitly** so nothing is guessed and no sample data is imported.

`config/installed.lock` is what keeps the web installer (`/install`) from being reachable once a
site is configured. Deleting it re-opens that unauthenticated route, which can rewrite the site's
configuration — never delete it on a live installation.

> **Do not run `composer install` without `--no-dev` in production.** Dev dependencies include
> PHPUnit and PHP-CS-Fixer, which have no business on a production host.

---

## Configuration reference

Copy `.env.example` to `.env` and set the following. `.env` must never be committed.

### Database

| Key | Notes |
|---|---|
| `DB_HOST` | Host, optionally `host:port` (e.g. `127.0.0.1:3306`) |
| `DB_NAME` | Database name |
| `DB_USERNAME` | Dedicated application user — **not** root |
| `DB_PASSWORD` | **Must be non-empty in production; the app refuses to boot otherwise** |
| `DB_CHARSET` | `utf8mb4` |

### Application

| Key | Production value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — must never be true in production |
| `APP_SCHEME` | `https` |
| `APP_DOMAIN` | Your domain |
| `APP_TIMEZONE` | Your timezone |
| `APP_COMPANY` | Displayed organization name |
| `APP_LOCALE`, `APP_CURRENCY_FORMAT` | Formatting |

`APP_ENV=production` also enables DI container compilation into `var/cache`, so that directory
must be writable.

### Session and security

| Key | Production value |
|---|---|
| `SESSION_SECURE` | `true` (requires HTTPS) |
| `SESSION_HTTP_ONLY` | `true` |
| `CSRF_TOKEN_EXPIRY` | Seconds; `3600` is a reasonable default |
| `PASSWORD_PEPPER` | A unique, long random string. **Set once and never change it** — changing it invalidates every stored password. |

### Email

`SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_DEBUG`, `EMAIL_FROM`,
`EMAIL_FROM_NAME`. Required for account activation and password reset to work — without working
SMTP, users cannot activate accounts or reset passwords.

Keep `SMTP_DEBUG` off in production; it is verbose and can disclose configuration.

### Environment names

`phinx.php` declares `production`, `local`, `development` and `testing`. **Setting `APP_ENV` to a
value with no matching block makes Phinx fail.** If you introduce a new environment name, add the
corresponding block.

Note that the `testing` environment appends `_test` to `DB_NAME`.

---

## Configuration sources

`App\Core\ConfigLoader` resolves configuration from the first available source, in this order:

1. **Real environment variables.** If all five required keys (`APP_DEBUG`, `DB_HOST`, `DB_NAME`,
   `DB_USERNAME`, `DB_PASSWORD`) are already set in the process environment — via `$_ENV`,
   `$_SERVER`, or `getenv()` — nothing else is read. This is what makes a container or PaaS
   deployment work with no config file at all: set the environment and boot. Every other key the
   app reads (`PASSWORD_PEPPER`, `SMTP_*`, `APP_SCHEME`, ...) is copied in from `getenv()` at this
   point too, not only the five required ones.
2. **`AUREO_CONFIG` override.** An environment variable (or `$_SERVER` entry) naming an absolute
   path to a config file — `.env` format or a PHP file returning an array — checked before any
   fixed location.
3. **`config/config-path.php`**. A pointer file the installer writes when it places the real
   secrets somewhere else on the filesystem; it `require`s to a string path, which is then loaded
   the same way as rung 4 or 5.
4. **`config/config.php`** — a PHP file `require`d and expected to `return` an array of key/value
   pairs. This exists for the drop-in layout: a plain-text `.env` is
   unreadable-by-default only while it sits *above* the document root, and an install that places
   the application *at* the document root cannot rely on that — nginx has no per-directory rule to
   deny it with, either. A PHP file served directly executes and emits nothing, so it degrades
   safely even when a request does land on it.
5. **`.env`**, loaded via `vlucas/phpdotenv`. This remains the developer default and the
   recommended-layout convention; nothing about an existing `.env`-based setup changes.

**Rungs 3-5 all resolve relative to the application root — `dirname(BASE_PATH)`, one level above
`public/` — which is a single fixed location regardless of deployment layout.** Whether that
location is actually safe from direct requests depends on where the document root points, and it is
*not* uniformly "above the document root":

- **Recommended layout** (`<app>/public` is the document root): the application root sits outside
  the served tree entirely. `.env` and `config/config.php` are unreachable by URL, full stop, with
  no dependency on any deny rule.
- **Drop-in layout** (`<app>` is the document root): the application root *is* the document root, so
  `config/config.php` at `<app>/config/config.php` sits **inside** the served tree. It is reachable
  in principle and is kept out only by the `config` directory deny rule shipped in `.htaccess` /
  `web.config` — see [Deployment layouts](#deployment-layouts). A `.env` placed at `<app>/.env`
  would equally be inside the served tree here, kept out only by the `.env` extension deny rule
  rather than a directory rule — the installer for this layout writes `config/config.php`, not
  `.env`, precisely so a plain-text file isn't the thing standing between an attacker and your
  database credentials.
Do not assume rungs 3-5 are categorically unreachable the way `.env` is in the recommended layout;
in the drop-in layout, verify the deny rules are actually in effect (request the config path
directly and confirm a 403, not a 200) before trusting them in production.

If none of the five sources yields all five required keys, boot fails with a `RuntimeException`
naming every path that was tried — deliberately, since a silent partial boot against the wrong
database is worse than refusing to start. `phinx.php` resolves configuration through the same
`ConfigLoader` chain, so migrations work identically regardless of which source a given install
uses.

**Before this existed:** the application could not boot from environment variables at all —
`Config::loadEnvironment()` required a `.env` file one level above the document root and threw a
`RuntimeException` otherwise. That broke every containerized or PaaS deployment (Docker, Heroku,
Fly.io, ...) where configuration is supplied purely as environment variables. Rung 1 above is the
fix; rungs 2-5 are the rest of the chain that also makes the drop-in layout possible.

---

## Web server

**Point the document root at `public/` when you can.** Serving the repository root exposes `.env`,
`vendor/` and `log/` unless the hardening rules below are in place — pointing at `public/` makes
the question moot, which is why it is the single most important deployment detail. See
[Deployment layouts](#deployment-layouts) for the drop-in alternative.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/aureo/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Never serve dotfiles
    location ~ /\. { deny all; }
}
```

This is the recommended (`public/`-as-document-root) layout. See
[Deployment layouts](#deployment-layouts) above for the drop-in layout's server block, which needs
more than this — nginx has no per-directory configuration to fall back on.

Redirect port 80 to 443.

### Apache

Works with the bundled `public/.htaccess`, provided `AllowOverride All` is set for the directory
and `mod_rewrite` is enabled.

### Filesystem permissions

| Path | Needs |
|---|---|
| `log/` | Writable by the web server user |
| `var/cache/` | Writable (DI container compilation in production) |
| `.env` | Readable by the web server user only — `chmod 600` |

Everything else can be read-only to the web server.

---

## Database and migrations

Create the database and a dedicated user:

```sql
CREATE DATABASE aureo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aureo'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON aureo_db.* TO 'aureo'@'localhost';
FLUSH PRIVILEGES;
```

Apply migrations:

```bash
vendor/bin/phinx migrate -e production
vendor/bin/phinx status  -e production   # verify
```

The canonical migration creates the full schema and seeds the `admin` role, the administrator
user and all 55 permissions.

**Change the seeded administrator password immediately** — a fresh install ships with
`admin@aureo.us` / `password`.

---

## Go-live checklist

Before exposing the instance:

- [ ] Document root is `public/` (recommended layout) — or, if using the drop-in layout
      instead, the hardening rules in `.htaccess`/`web.config` are in place and verified
- [ ] `.env` (or the equivalent config source — see [Configuration sources](#configuration-sources))
      is `chmod 600` and not in version control
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_SCHEME=https`
- [ ] `SESSION_SECURE=true`, `SESSION_HTTP_ONLY=true`
- [ ] `DB_PASSWORD` non-empty; the database user is not root
- [ ] `PASSWORD_PEPPER` set to a unique value and recorded somewhere safe
- [ ] Seeded admin password changed
- [ ] HTTPS working; HTTP redirects to HTTPS
- [ ] `composer install --no-dev --optimize-autoloader` used
- [ ] `public/assets/css/styles.css` present (it ships in the repository; rebuild with
      `npm run build` only if you changed `src/css/input.css`)
- [ ] `log/` and `var/cache/` writable
- [ ] SMTP verified by triggering a real password reset
- [ ] `composer audit` shows no unaddressed advisories
- [ ] Database backups scheduled and a restore tested
- [ ] `log/aureo.log` shows no warnings after a smoke test

---

## Upgrading

```bash
# 1. Back up the database first — always
mysqldump -u aureo -p aureo_db > backup-$(date +%F).sql

# 2. Fetch changes
git pull

# 3. Update dependencies and rebuild assets
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 4. Apply new migrations
vendor/bin/phinx migrate -e production

# 5. Clear the compiled container
rm -rf var/cache/*

# 6. Reload PHP-FPM so opcache picks up new code
sudo systemctl reload php8.2-fpm
```

Step 5 matters: with `APP_ENV=production` the DI container is compiled, and stale compiled
definitions survive a deploy otherwise.

Step 6 matters equally — PHP opcache will keep serving the previous bytecode until the pool is
reloaded.

---

## Backups and restore

**Back up:**

- The **database** — this is the entire application state.
- **`.env`** — particularly `PASSWORD_PEPPER`. Lose it and no password will validate.

Everything else is reproducible from the repository.

```bash
# Backup
mysqldump --single-transaction --routines -u aureo -p aureo_db | gzip > aureo-$(date +%F).sql.gz

# Restore
gunzip < aureo-2026-07-28.sql.gz | mysql -u aureo -p aureo_db
```

Use `--single-transaction` so the dump is consistent without locking the application out.

**Test your restore.** An untested backup is a hypothesis.

---

## Operations

### Logs

`log/aureo.log` is the first place to look for any 500 or blank page.

The path resolves via `dirname(BASE_PATH)` where `BASE_PATH` is `public/`. If the log is empty
when you expect entries, verify the directory is writable by the web server user — a
non-writable `log/` silently swallows errors.

4xx responses are logged as terse warnings without stack traces; 5xx get full exception logging.

### Rebuilding the search index

The index is maintained incrementally by event listeners. Rebuild it after a bulk import, a
restore, or if results look stale:

```bash
php bin/reindex-search.php
```

### Rotating logs

`log/aureo.log` grows unbounded. Add a logrotate entry:

```
/var/www/aureo/log/aureo.log {
    weekly
    rotate 12
    compress
    missingok
    notifempty
    create 0640 www-data www-data
}
```

### Dependency advisories

```bash
composer audit
```

Run it periodically, not only at install.

---

## Troubleshooting

**Blank page or HTTP 500.**
Read `log/aureo.log`. If it is empty, check that `log/` is writable — and confirm `APP_DEBUG` is
`false` but that you are reading the server log rather than expecting browser output.

**Application refuses to start in production.**
`APP_ENV=production` requires a non-empty `DB_PASSWORD`. This is deliberate.

**Phinx fails with an unknown environment.**
`APP_ENV` has no matching block in `phinx.php`. Add one or use a declared name.

**Sessions or CSRF fail silently.**
`SESSION_SECURE=true` requires HTTPS. Over plain HTTP the cookie is never sent and every
state-changing request fails. For an HTTP-only internal instance, set `SESSION_SECURE=false` and
`APP_SCHEME=http` — and understand you are transmitting sessions in the clear.

**Unstyled pages.**
`public/assets/css/styles.css` **is tracked in the repository** — a stock checkout is already
styled and `npm`/`npm run build` are not required to see it. If pages are unstyled, check instead
that the document root or `AUREO_ASSET_PREFIX`/`Config::basePath()` combination is producing the
right URL for a drop-in install (see
[Deployment layouts](#deployment-layouts)), or that `npm run build` was run *after* a local edit to
`src/css/input.css` and produced a stylesheet that didn't get deployed.

**Changes not taking effect after deploy.**
PHP opcache is serving old bytecode, and/or `var/cache` holds a stale compiled container. Reload
PHP-FPM and clear `var/cache`.

**`Unknown table 'u'` in the log.**
An application bug: a `queryBuilder` call is missing its `'alias'` option. See
[ARCHITECTURE.md](./ARCHITECTURE.md).

**HTTP 429 for legitimate users.**
Rate limiting is database-persisted and applies before routing. Tune it under
Settings → Security. Check whether all users share an egress IP behind NAT or a proxy.

**Users cannot activate accounts or reset passwords.**
SMTP is not delivering. Verify the `SMTP_*` values and test a real password reset. Activation
links expire after 24 hours and reset links after 1 hour.

**MySQL CLI errors with `caching_sha2_password could not be loaded` (Windows/XAMPP).**
A client-side limitation. Use PDO from a PHP script, or `composer pma`, instead of `mysql.exe`.

---

## Known issues

**Subdirectory installs are not supported.**
Aureo must be mounted at the domain root — `https://host/`, not `https://host/aureo/`. Serving
it from a subdirectory renders the login page but **cannot log in**: the form posts to
`/login`, which resolves against the parent document root rather than the application.

The mount point *is* resolved correctly at boot (`App\Core\RequestPath::basePath()`, fed into
`Config::setBasePath()`), but only one production consumer reads it — the `asset()` view helper
in `src/Views/Layouts/ViewHelpers.php`. So **`basePath()` reaches assets only**. Every other URL
in the application is written root-absolute and unprefixed: roughly 350 `href="/…"` and
`action="/…"` attributes across `src/Views`, and about 200 `redirect('/…')` calls across
`src/Controllers`. CSS and images load; navigation, form posts and redirects do not.

Making this work needs a `url()` helper applied to all ~550 of those sites, not a configuration
change. `RequestPath` and its tests are kept because the resolver itself is correct and would be
the foundation of that sweep. Note that `RequestPath::usesPathInfo()` likewise has no production
consumer today — it exists for the no-rewrite-rule fallback and is exercised only by tests.

Use the recommended (`public/` as document root) or drop-in (application root as document root)
layout — see [Deployment layouts](#deployment-layouts).
