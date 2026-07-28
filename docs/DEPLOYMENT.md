# Aureo — Deployment and Operations

Installing, configuring, upgrading and operating Aureo on a real server. For day-to-day use see
[USERGUIDE.md](./USERGUIDE.md); for the security posture behind these steps see
[SECURITY.md](./SECURITY.md).

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration reference](#configuration-reference)
- [Web server](#web-server)
- [Database and migrations](#database-and-migrations)
- [Go-live checklist](#go-live-checklist)
- [Upgrading](#upgrading)
- [Backups and restore](#backups-and-restore)
- [Operations](#operations)
- [Troubleshooting](#troubleshooting)

---

## Requirements

| Component | Requirement |
|---|---|
| PHP | 8.1 or newer, with `pdo`, `pdo_mysql`, `mbstring` |
| MySQL | 8.0 or newer |
| Composer | 2.x |
| Node.js + npm | Required at build time only, to compile Tailwind CSS |
| Web server | Nginx + PHP-FPM, or Apache with the bundled `.htaccess` |
| TLS | Required in production |

MySQL 8.0 specifically: the codebase relies on `ROW(...)` table constructors (8.0.19+) and window
functions (8.0.20+).

---

## Installation

```bash
git clone https://github.com/rbenzing/Aureo-Project-Management.git
cd Aureo-Project-Management

# Production dependencies, optimized autoloader
composer install --no-dev --optimize-autoloader

# Build the stylesheet (compiled CSS is not committed)
npm install
npm run build
```

Then configure `.env` and run migrations — see the two sections below.

`bin/setup.php` exists for interactive local setup (it prompts for credentials, runs migrations,
sets the admin password and optionally imports sample data). **On a production host, prefer
configuring `.env` by hand and running migrations explicitly** so nothing is guessed and no
sample data is imported.

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

## Web server

**Point the document root at `public/`.** Serving the repository root exposes `.env`, `vendor/`
and `log/` — this is the single most important deployment detail.

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
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Never serve dotfiles
    location ~ /\. { deny all; }
}
```

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

- [ ] Document root is `public/`, not the repository root
- [ ] `.env` is `chmod 600` and not in version control
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_SCHEME=https`
- [ ] `SESSION_SECURE=true`, `SESSION_HTTP_ONLY=true`
- [ ] `DB_PASSWORD` non-empty; the database user is not root
- [ ] `PASSWORD_PEPPER` set to a unique value and recorded somewhere safe
- [ ] Seeded admin password changed
- [ ] HTTPS working; HTTP redirects to HTTPS
- [ ] `composer install --no-dev --optimize-autoloader` used
- [ ] `npm run build` run so `public/assets/css/styles.css` exists
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
sudo systemctl reload php8.1-fpm
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
`public/assets/css/styles.css` is a build artifact and is not committed. Run `npm run build`.

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
