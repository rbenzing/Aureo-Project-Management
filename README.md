# Aureo Project Management

<div align="center">

[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind%20CSS-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Composer](https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white)](https://getcomposer.org/)
[![License: AGPL v3+](https://img.shields.io/badge/License-AGPL%20v3%2B-blue.svg?style=for-the-badge)](./LICENSE)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-FFDD00?style=for-the-badge&logo=buymeacoffee&logoColor=black)](https://buymeacoffee.com/russellbenzing)

**A self-hosted project, sprint, and time-tracking application for agile teams**

🏠 **Self-Hosted** • 🧩 **No Framework** • 🔍 **Small and Inspectable**

[Features](#-key-features) • [Quick Start](#-quick-start) • [Documentation](#-documentation) • [License](#-license)

</div>

---

## ✨ Key Features

### 📋 Delivery Management
- **📊 Dashboard**: Workload, status, and progress at a glance
- **📁 Projects**: Table, chart, pivot, and Gantt views over the same project data
- **✅ Tasks**: Subtasks, hierarchical task tables, backlog, and per-project filtering
- **🏃 Sprints**: Sprint board, planning with drag-and-drop assignment, and burn-down stats
- **🎯 Milestones**: Epics and milestones with timeline and card views
- **📝 Templates**: Reusable project, task, and sprint templates
- **⏱️ Time Tracking**: Per-task start/stop timers, a floating timer, and time entry management

### 🏢 Organization & Access
- **🏛️ Companies**: Multi-company records with users and projects scoped underneath
- **👥 Users & Profiles**: Registration, activation, password reset, and profile management
- **🔑 Roles & Permissions**: 55 granular permissions composed into roles
- **🕓 Activity Log**: Filterable audit trail of user activity across the app
- **⭐ Favorites**: Pin the projects, tasks, and sprints you work in daily
- **🔎 Global Search**: Indexed full-text search with a keyboard-driven command palette

### 🔒 Security & Privacy
- **🛡️ CSRF Protection**: Mandatory token validation on every state-changing request
- **🚦 Rate Limiting**: Database-persisted throttling that survives session resets
- **🔐 Argon2id Hashing**: Modern password storage, with a bcrypt fallback on hosts built without libargon2
- **🧼 Parameterized SQL**: PDO prepared statements everywhere — no string-built queries
- **🏠 Self-Hosted**: Your database, your server, no third-party data sharing

## 🛠️ Tech Stack

| Component | Technology |
|-----------|-----------|
| **Language** | PHP 8.2+ with `declare(strict_types=1)` |
| **Architecture** | Custom MVC — no Laravel, Symfony, Eloquent, or Blade |
| **Database** | MySQL 8.0+ over raw PDO, with soft deletes |
| **Migrations** | Phinx (the canonical migration is the schema of record) |
| **DI** | PHP-DI container wired in `config/container.php` |
| **Views** | Plain PHP templates — no templating engine |
| **UI** | Tailwind CSS 3.4 + PostCSS, vanilla JavaScript |
| **Email** | PHPMailer over SMTP |
| **Quality** | PHPUnit 10 + PHP-CS-Fixer (PSR-12) |

## 🚀 Quick Start

**Prerequisites:** PHP 8.2+, MySQL 8.0+, Composer, Node.js + npm, and a running local `mysqld`.

```bash
# Clone the repository
git clone https://github.com/rbenzing/Aureo-Project-Management.git
cd Aureo-Project-Management

# Install PHP + npm dependencies and build CSS
composer install

# Interactive installer: DB credentials, migrations, admin password, sample data
php bin/setup.php

# Serve the app
composer start
```

Access your app at `http://localhost:8000` and log in:

| Email | Password |
|---|---|
| `admin@aureo.us` | `password` |

The admin user is seeded with **all 55 permissions**. Change the password from Settings → Profile after first login.

> **Why `php bin/setup.php` and not `composer setup`?** Composer pipes STDIN through its process
> wrapper, which breaks interactive prompts. Run the setup script directly so it can talk to your
> terminal. `composer setup` still works non-interactively — it passes `--yes` and accepts every default.

### 🔧 What the installer does

1. Copies `.env.example` → `.env` if missing.
2. Prompts for MySQL host/port/user/password/database (stock XAMPP defaults work as-is).
3. Connects, creates the database if needed, writes credentials to `.env`.
4. Runs Phinx migrations — schema, admin user, and the 55 permissions.
5. Prompts for the admin password (defaults to `password`).
6. Optionally imports `sample-data.sql`: 5 companies, 25 users, 50 projects, 5000 tasks, 50 sprints.
7. Writes `config/installed.lock`, which disables the web installer. Deleting it re-opens an
   unauthenticated route that can rewrite your configuration — only do that if you intend to
   reinstall from scratch.

Re-run it any time to reconfigure. Re-running does **not** delete `config/installed.lock` for you;
remove it first if you actually want the web installer available again.

### ✅ Quality Gates

```bash
composer cs:check   # PSR-12 lint (dry run + diff)
composer cs:fix     # auto-fix code style
composer test       # PHPUnit suite
composer audit      # known vulnerabilities in dependencies
composer preflight  # check this host can run Aureo (see below)
npm run build       # rebuild Tailwind CSS
```

### 🔎 Preflight

Before deploying to an unfamiliar host, ask it whether it can actually run Aureo:

```bash
composer preflight                              # environment checks only
php bin/preflight.php --url=https://example.com # also probe what the server hands out
```

It checks the PHP version and extensions, that `log/` and `var/cache/` are writable, that a
configuration file can be written somewhere, and which deployment layout is in effect. With
`--url` it additionally asks the running site for `/.env`, `/config/config.php`, `/log/aureo.log`
and `/.git/config` — any of which returning `200` is a credential or source disclosure.

Exit codes: `0` all clear (warnings allowed), `1` at least one failure, `2` could not run.

A path the probe cannot reach at all is reported as *unverified*, never as safe. Hosts that block
loopback HTTP cannot self-check, and you should confirm those paths by hand before going live.

## ⚙️ Configuration

### Environment Variables

`bin/setup.php` writes a working `.env` for local development. Key values:

```env
# Database
DB_HOST=127.0.0.1:3306
DB_NAME=aureo_db
DB_USERNAME=root
DB_PASSWORD=              # REQUIRED (non-empty) when APP_ENV=production

# Application
APP_ENV=local             # local | development | testing | production
APP_DEBUG=true            # MUST be false in production
APP_SCHEME=http           # set to https in production

# Session
SESSION_SECURE=false      # set to true in production (requires HTTPS)
```

For SMTP and the remaining options, see [`.env.example`](./.env.example).

> **Local HTTP development:** keep `SESSION_SECURE=false` and `APP_SCHEME=http`, or session
> cookies and CSRF tokens fail silently.

### Common Commands

```bash
composer start              # PHP dev server at http://localhost:8000
composer pma                # phpMyAdmin at http://localhost:8081
composer migrate            # apply pending Phinx migrations
composer migrate:status     # show migration state
composer migrate:rollback   # undo the last migration
composer migrate:create Foo # scaffold a new migration in db/migrations/
npm run watch               # rebuild CSS on change
```

## 🏗️ Project Layout

```
public/             # web root — point your server here
  index.php         # entry point and route registry
src/
  Controllers/      # HTTP handlers; extend BaseController
  Models/           # PDO models; extend BaseModel
  Repositories/     # query objects for the heavier read paths
  Services/         # business logic and infrastructure
  Events/Listeners/ # in-process domain events
  Http/Requests/    # form request validation objects
  Middleware/       # Auth, CSRF, Activity, Session
  Views/            # plain PHP templates (no engine)
  Enums/            # backed enums (string and int)
  Core/             # Router, Config, Database, Response
config/             # DI container wiring
db/migrations/      # Phinx migrations (canonical schema lives here)
bin/                # setup.php, install.php, pma.php, reindex-search.php
log/                # application log: log/aureo.log
```

Full design rationale and request lifecycle: [ARCHITECTURE.md](./docs/ARCHITECTURE.md).

## 🚢 Deployment

1. Point the web server's document root at `public/` — recommended, and the only layout where
   nothing but the front controller and static assets is web-reachable.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_SCHEME=https`, `SESSION_SECURE=true`.
3. Provide a non-empty `DB_PASSWORD` — production refuses to boot without it.
4. Run `composer install --no-dev --optimize-autoloader`. `public/assets/css/styles.css` ships in
   the repository, so `npm run build` is only needed if you're changing the stylesheet.
5. Configure HTTPS. [SECURITY.md](./docs/SECURITY.md) covers headers and cookie posture.

Hosts that don't allow a `public/`-only document root can instead point it at the application
root (the "drop-in" layout), via a five-rung configuration chain and a shared mount-point
resolver. See
[Deployment layouts](./docs/DEPLOYMENT.md#deployment-layouts) and
[Configuration sources](./docs/DEPLOYMENT.md#configuration-sources) in DEPLOYMENT.md.

**Both supported layouts mount the app at the domain root.** Routes and links are root-absolute
throughout, so installing under a subdirectory (`https://host/aureo/`) is not supported — see
[Known issues](./docs/DEPLOYMENT.md#known-issues). Whichever layout you use, **never extract a
`git clone` at a document root** — a web-reachable `.git/` directory discloses the full source
history.

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
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Apache works with the bundled `public/.htaccess`.

## 📚 Documentation

- **[Architecture](./docs/ARCHITECTURE.md)**: Request lifecycle, layers, and the conventions that hold it together
- **[Deployment](./docs/DEPLOYMENT.md)**: Deployment layouts, configuration sources, web server setup, and operations
- **[Contributing](./CONTRIBUTING.md)**: Development workflow, coding standards, and PR expectations
- **[Security](./docs/SECURITY.md)**: Security features, production checklist, and vulnerability reporting
- **[Agent Guidance](./.claude/CLAUDE.md)**: Project-specific footguns — read before non-trivial work

## 🩺 Troubleshooting

**White screen after install.** Tail `log/aureo.log` — that is the FIRST place to look on any 500 or blank page.

**`composer setup` hangs or instantly accepts defaults.** Composer pipes STDIN. Use `php bin/setup.php` directly.

**`Cannot redeclare X::tryfrom()`.** PHP opcache is serving a stale enum. Restart `composer start`.

**`Unknown table 'u'` in a query.** The model's `queryBuilder` call is missing `'alias' => 'u'`.

**Session/CSRF silently fails on `http://localhost`.** Set `SESSION_SECURE=false` and `APP_SCHEME=http`.

**XAMPP `mysql.exe` errors with `caching_sha2_password could not be loaded`.** Use PDO from PHP scripts
or phpMyAdmin (`composer pma`) instead of the CLI client.

## 📄 License

Aureo Project Management is free software: you can redistribute it and/or modify it under the terms of
the **GNU Affero General Public License** as published by the Free Software Foundation, either
version 3 of the License, or (at your option) any later version.

It is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
[LICENSE](./LICENSE) for details.

> **What AGPL means for a self-hosted app:** if you modify Aureo and let others use it over a
> network, you must offer those users the source of your modified version. Running it privately
> for your own team places no obligation on you.

SPDX identifier: `AGPL-3.0-or-later`
Copyright © 2025 Russell Benzing

---

## 👤 About the Author

Aureo is built by **[Russell Benzing](https://github.com/rbenzing)**.

It's developed with heavy use of AI coding assistants — architecture, implementation, tests and
this documentation. That's a deliberate choice, and worth stating plainly: it means the project
moves quickly, and it means every change still gets linted, tested and reviewed before it lands.
The full history is public; judge the code, not the tooling.

It's deliberately framework-free. A custom MVC core, raw PDO, plain PHP views and vanilla JS mean
the whole application is small enough to read end to end — no magic to reverse-engineer when
something breaks at 2am, and no upgrade treadmill imposed by someone else's release cycle.

It's released free and open source under the AGPL so that teams can run their own project tracking
on their own hardware, and own their data outright rather than rent access to it. There's no hosted
tier, no telemetry, and nothing held back for a paid version.

Bug reports, feature requests and pull requests are all welcome — see
[CONTRIBUTING.md](./CONTRIBUTING.md).

---

## 💬 Support & Community

Found a bug? Have a feature request? Please open an
[issue](https://github.com/rbenzing/Aureo-Project-Management/issues).

Found a security vulnerability? **Do not open a public issue** — see
[SECURITY.md](./docs/SECURITY.md) for private disclosure.

If Aureo is useful to you, you can support its development:

<a href="https://buymeacoffee.com/russellbenzing" target="_blank"><img src="https://img.shields.io/badge/Buy%20Me%20A%20Coffee-FFDD00?style=for-the-badge&logo=buymeacoffee&logoColor=black" alt="Buy Me A Coffee" /></a>

---

<div align="center">

**🏠 Self-hosted • 🔒 Secure • 🧩 Framework-free**

*Built for teams who want to own their project data, not rent access to it.*

</div>
