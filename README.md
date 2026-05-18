# Aureo Project Management

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-AGPLv3-blue.svg)](LICENSE)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.4-blue.svg)](https://tailwindcss.com)

Project, task, sprint, and time-tracking app for agile teams. PHP 8.1+, MySQL, TailwindCSS. No framework, no ORM — small and inspectable.

---

## Get running in under a minute

**Prerequisites:** PHP 8.1+, MySQL 8.0+, Composer, Node.js + npm, and a local `mysqld` running.

```bash
git clone https://github.com/rbenzing/Aureo-Project-Management.git
cd Aureo-Project-Management

composer install      # installs PHP + npm deps, builds CSS
php bin/setup.php     # interactive: DB creds, migrations, admin password, sample data
composer start        # serves http://localhost:8000
```

Open <http://localhost:8000> and log in:

| Email | Password |
|---|---|
| `admin@aureo.us` | `password` |

The admin user is seeded with **all 55 permissions** and full access to every feature. Change the password from Settings → Profile after first login.

> **Why `php bin/setup.php` and not `composer setup`?** Composer pipes STDIN through its process wrapper, which breaks interactive prompts. Run the setup script directly so it can talk to your terminal.

---

## What `php bin/setup.php` does

1. Copies `.env.example` → `.env` if missing.
2. Prompts for MySQL host/port/user/password/database (defaults work for stock XAMPP / local MySQL with no root password).
3. Connects, creates the database if needed, writes credentials to `.env`.
4. Runs Phinx migrations (schema + admin user + 55 permissions).
5. Prompts for the admin password (defaults to `password`).
6. Optionally imports `sample-data.sql` (5 companies, 25 fake users, 50 projects, 5000 tasks, 50 sprints).

Re-run it any time to reconfigure. Pass `--yes` to accept every default non-interactively.

---

## Common commands

```bash
composer start              # PHP dev server at http://localhost:8000
composer pma                # phpMyAdmin at http://localhost:8081
composer test               # PHPUnit
composer cs:check           # PSR-12 lint
composer cs:fix             # auto-fix style
composer migrate            # apply pending Phinx migrations
composer migrate:status     # show migration state
composer migrate:rollback   # undo the last migration
composer migrate:create Foo # create a new migration in db/migrations/
npm run build               # rebuild Tailwind CSS to public/assets/css/styles.css
npm run watch               # rebuild on change
```

---

## Configuration (`.env`)

`bin/setup.php` writes a working `.env` for local development. Key values:

```ini
DB_HOST=127.0.0.1:3306
DB_NAME=aureo_db
DB_USERNAME=root
DB_PASSWORD=

APP_ENV=local        # production requires non-empty DB_PASSWORD
APP_DEBUG=true
APP_SCHEME=http      # set to https in production
SESSION_SECURE=false # set to true in production (requires HTTPS)
```

For email/SMTP, see `.env.example`.

---

## Project layout

```
public/             # web root — point your server here
  index.php         # entry point and route registry
src/
  Controllers/      # HTTP handlers; extend BaseController
  Models/           # PDO models; extend BaseModel
  Services/         # business logic and infrastructure
  Middleware/       # Auth, CSRF, Activity, Session
  Views/            # plain PHP templates (no engine)
  Enums/            # backed enums (string and int)
  Core/             # Router, Config, Database
config/             # DI container wiring
db/
  migrations/       # Phinx migrations (canonical schema lives here)
  seeds/            # Phinx seed files
bin/
  setup.php         # interactive installer
  install.php       # composer post-install orchestrator (npm only)
  pma.php           # downloads + serves phpMyAdmin locally
log/                # application log: log/aureo.log
```

---

## Deploying to a real server

1. Point the web server's document root at `public/`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_SCHEME=https`, `SESSION_SECURE=true`.
3. Provide non-empty `DB_PASSWORD` (production refuses to boot without it).
4. Configure HTTPS — `SECURITY.md` covers the headers and cookie posture.

### Nginx example

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
}
```

Apache works with the bundled `.htaccess`.

---

## Troubleshooting

**White screen after install.** Tail `log/aureo.log` — that's the FIRST place to look on any 500 or blank page.

**`composer setup` hangs or instantly accepts defaults.** Composer pipes STDIN. Use `php bin/setup.php` directly.

**`Cannot redeclare X::tryfrom()`.** PHP opcache is serving a stale enum. Restart `composer start`.

**`Unknown table 'u'` in a query.** The Models's `queryBuilder` call is missing `'alias' => 'u'`. See [.claude/CLAUDE.md](.claude/CLAUDE.md) for the convention.

**Session/CSRF silently fails on `http://localhost`.** Set `SESSION_SECURE=false` and `APP_SCHEME=http` in `.env`.

**XAMPP `mysql.exe` errors with `caching_sha2_password could not be loaded`.** Use PDO from PHP scripts or phpMyAdmin (`composer pma`) instead of the CLI client.

---

## Contributing

- PSR-12 style, enforced by `composer cs:check` / `composer cs:fix`.
- `declare(strict_types=1);` on all non-view PHP files.
- New routes go in `public/index.php`. New DB changes go in a new Phinx migration — never edit the canonical migration.
- See [.claude/CLAUDE.md](.claude/CLAUDE.md) for project-specific conventions and footguns (load it before doing any non-trivial work).
- Run `composer test` and `composer cs:check` before opening a PR.

---

## License

[AGPL-3.0](LICENSE). Network use of modified versions requires publishing your source.

## Author

Russell Benzing · [me@russellbenzing.com](mailto:me@russellbenzing.com) · [@rbenzing](https://github.com/rbenzing)
