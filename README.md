# Aureo Project Management

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-AGPLv3-blue.svg)](LICENSE)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.4-blue.svg)](https://tailwindcss.com)

Aureo Project Management is a comprehensive project management application designed for modern agile teams. Built with PHP and featuring a clean, responsive interface powered by TailwindCSS, it provides everything you need to manage projects, tasks, sprints, and team collaboration effectively.

## 🚀 Features

### Core Project Management
- **Project Management**: Create and manage multiple projects with detailed tracking
- **Task Management**: Advanced task system with subtasks, priorities, and custom types (story, bug, task, epic)
- **Sprint Planning**: Full Scrum/Agile support with sprint management and backlog prioritization
- **Milestone Tracking**: Epic and milestone management with progress tracking
- **Time Tracking**: Built-in time tracking with billable hours and project costing

### Team Collaboration
- **User Management**: Comprehensive user system with profile management
- **Company Management**: Multi-company support with user associations
- **Role-Based Access Control**: Granular permission system with customizable roles
- **Activity Logging**: Track all user activities and system changes
- **Task Comments**: Collaborative task discussions with comment system

### Advanced Features
- **Templates**: Reusable templates for projects, tasks, milestones, and sprints
- **Dashboard Analytics**: Real-time project metrics and team performance insights
- **Security**: Enterprise-grade security with CSRF protection, rate limiting, and secure headers
- **Responsive Design**: Mobile-friendly interface that works on all devices
- **Settings Management**: Configurable application settings and preferences

## 🛠️ Technology Stack

### Backend
- **PHP 8.1+** - Core application logic with strict typing
- **MySQL** - Primary database with comprehensive relational schema
- **PDO** - Database abstraction layer with prepared statements
- **Custom MVC Architecture** - Clean separation of concerns
- **Composer** - Dependency management
- **PHPUnit** - Testing framework
- **PHP CS Fixer** - Code style enforcement (PSR-12)

### Frontend
- **TailwindCSS 3.4** - Utility-first CSS framework
- **PostCSS** - CSS processing with plugins
- **Responsive Design** - Mobile-first approach

### Security & Infrastructure
- **Argon2** - Password hashing
- **CSRF Protection** - Cross-site request forgery prevention
- **Rate Limiting** - API and request rate limiting
- **Security Headers** - Comprehensive security header implementation
- **Session Management** - Secure session handling

## 📋 Requirements

- **PHP 8.1 or higher** (with strict type support)
- **MySQL 5.7 or higher** (InnoDB engine required)
- **Composer** - For PHP dependency management
- **Node.js & NPM** - For frontend asset compilation
- **Web Server** - Apache/Nginx with PHP support and HTTPS (production)

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/rbenzing/aureo-project-management.git
cd aureo-project-management
```

### 2. Install PHP Dependencies
```bash
composer install
```

### 3. Install Node.js Dependencies
```bash
npm install
```

### 4. One-step Setup (Recommended)

Make sure `mysqld` is running locally, then:

```bash
composer setup
```

This interactive installer will:
- Create `.env` from `.env.example` if missing
- Prompt for DB host/port/user/password/name (defaults: `127.0.0.1`, `3306`, `root`, empty, `aureo_db` — i.e. a stock local MySQL)
- Test the connection and `CREATE DATABASE IF NOT EXISTS`
- Persist credentials to `.env`
- Run Phinx migrations
- Optionally import `sample-data.sql` (no `mysql` CLI required)

### 5. Manual Database Setup (alternative)

If you prefer to do it by hand:

```bash
cp .env.example .env       # then edit DB_* values

# Create the database (any one of these)
mysql -u root -p -e "CREATE DATABASE aureo_db;"
# ...or let composer setup handle it.

composer migrate           # run migrations
composer migrate:status    # verify
```

#### Legacy: Direct SQL Import
```bash
mysql -u root -p aureo_db < schema.sql
mysql -u root -p aureo_db < sample-data.sql   # optional sample data
```

### 6. Build Frontend Assets
```bash
# Build CSS assets
npm run build
```

### 7. Web Server Configuration
Point your web server document root to the `public/` directory.

#### Apache (.htaccess included)
The application includes `.htaccess` files for Apache configuration.

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/aureo-project-management/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## ⚙️ Configuration

### Environment Variables (.env)
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=aureo_db
DB_USER=your_username
DB_PASS=your_password

# Application Settings
APP_DEBUG=false
TIMEZONE=America/New_York
DOMAIN=your-domain.com
COMPANY=Your Company Name
SCHEME=https

# Email Configuration (for notifications)
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

### Default Admin User
After running the schema, a default admin user is created:
- **Email**: `admin@aureo.us`
- **Password**: `password` (change immediately after first login)

## 📁 Project Structure

```
aureo-project-management/
├── public/                 # Web server document root
│   ├── assets/            # Compiled CSS and static assets
│   └── index.php          # Application entry point
├── src/                   # Application source code
│   ├── Core/              # Core framework classes
│   │   ├── Config.php     # Configuration management
│   │   ├── Database.php   # Database connection and queries
│   │   └── Router.php     # URL routing system
│   ├── Controllers/       # MVC Controllers
│   ├── Models/           # Data models and business logic
│   ├── Views/            # HTML templates and views
│   ├── Middleware/       # Request middleware (Auth, CSRF, etc.)
│   ├── Services/         # Business services (Security, Email, etc.)
│   └── Utils/            # Utility classes and helpers
├── schema.sql            # Database schema
├── sample-data.sql       # Sample data for testing
├── composer.json         # PHP dependencies
├── package.json          # Node.js dependencies
├── tailwind.config.js    # TailwindCSS configuration
└── postcss.config.js     # PostCSS configuration
```

## 🎯 Usage

### Getting Started
1. **Access the Application**: Navigate to your configured domain
2. **Login**: Use the default admin credentials or register a new account
3. **Create a Company**: Set up your organization
4. **Add Team Members**: Invite users and assign roles
5. **Create Projects**: Start your first project with tasks and milestones

### Key Workflows

#### Project Management
1. **Create Project**: Define project scope, assign owner, set dates
2. **Add Milestones**: Break project into manageable milestones/epics
3. **Create Tasks**: Add detailed tasks with priorities and assignments
4. **Track Progress**: Monitor project health and team performance

#### Sprint Planning (Agile/Scrum)
1. **Product Backlog**: Create and prioritize user stories
2. **Sprint Planning**: Assign tasks to sprints with story points
3. **Sprint Execution**: Track daily progress and time spent
4. **Sprint Review**: Complete sprints and analyze velocity

#### Time Tracking
1. **Start Timer**: Begin tracking time on active tasks
2. **Log Hours**: Record billable and non-billable time
3. **Generate Reports**: Analyze time spent across projects
4. **Project Costing**: Calculate project profitability

## 🔐 Security Features

### Authentication & Authorization
- **Secure Login**: Argon2 password hashing with account activation
- **Role-Based Access**: Granular permissions for different user types
- **Session Security**: Secure session management with timeout
- **Password Reset**: Secure password reset via email tokens

### Application Security
- **CSRF Protection**: Cross-site request forgery prevention
- **Rate Limiting**: Prevent abuse with configurable rate limits
- **Input Validation**: Comprehensive input sanitization
- **Security Headers**: HSTS, CSP, and other security headers
- **Activity Logging**: Track all user actions for audit trails

### Data Protection
- **SQL Injection Prevention**: Prepared statements and parameterized queries
- **XSS Protection**: Output encoding and content security policy
- **File Upload Security**: Secure file handling (if implemented)
- **Environment Configuration**: Sensitive data in environment variables

## 📊 Database Schema

The application uses a comprehensive MySQL schema with the following key entities:

### Core Tables
- **users**: User accounts with authentication and profile data
- **companies**: Organization management with multi-tenancy support
- **roles**: Role definitions with hierarchical permissions
- **permissions**: Granular permission system

### Project Management
- **projects**: Project definitions with status and ownership
- **tasks**: Task management with subtasks and dependencies
- **milestones**: Epic and milestone tracking
- **sprints**: Sprint management for agile workflows

### Collaboration & Tracking
- **task_comments**: Task discussion and collaboration
- **time_entries**: Time tracking with billable hours
- **activity_logs**: Comprehensive audit trail
- **templates**: Reusable project and task templates

### System Tables
- **settings**: Application configuration (InnoDB)
- **sessions**: Session management
- **csrf_tokens**: CSRF protection tokens
- **rate_limits**: Database-persisted rate limiting

## 🔧 Development

### Local Development Setup
```bash
# Start PHP development server
composer start

# Watch for CSS changes
npm run build

# For development with auto-rebuild
npm run watch

# Run tests
composer test

# Run code style checks
composer cs:check

# Auto-fix code style issues
composer cs:fix
```

### Database Migrations

The application uses **Phinx** for database migrations, providing version control for your database schema.

#### Migration Commands
```bash
# Run all pending migrations
composer migrate

# Rollback the last migration
composer migrate:rollback

# Check migration status
composer migrate:status

# Create a new migration
composer migrate:create MyNewMigration
```

#### Creating a Migration
```bash
# Create a new migration file
composer migrate:create AddColumnToUsers

# Edit the generated file in db/migrations/
# Example: db/migrations/20231215123456_add_column_to_users.php
```

Example migration structure:
```php
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddColumnToUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE `users`
            ADD COLUMN `phone_verified` TINYINT(1) UNSIGNED DEFAULT 0
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE `users`
            DROP COLUMN `phone_verified`
        ");
    }
}
```

#### Migration Best Practices
- Always test migrations on a development database first
- Write both `up()` and `down()` methods for reversibility
- Use transactions for data migrations when possible
- Never modify existing migrations that have been deployed
- Keep migrations focused on a single change
- Use meaningful migration names

#### Important Notes
- All primary and foreign keys use **BIGINT UNSIGNED** for scalability
- The initial migration includes all core tables with seed data
- Migrations are tracked in the `phinxlog` table
- Configuration is in [phinx.php](phinx.php)

### Code Structure

#### MVC Architecture
- **Models**: Handle data logic and database interactions
- **Views**: PHP templates with embedded HTML/CSS
- **Controllers**: Handle HTTP requests and coordinate between models and views

#### Core Classes
- **Router**: Custom URL routing with parameter extraction
- **Database**: PDO wrapper with query logging and error handling
- **Config**: Environment-based configuration management
- **SecurityService**: Centralized security features

#### Middleware Stack
- **AuthMiddleware**: Authentication and authorization
- **CsrfMiddleware**: CSRF token validation
- **ActivityMiddleware**: User activity logging
- **SessionMiddleware**: Session management

### Extending the Application

#### Adding New Features
1. **Create Model**: Extend `BaseModel` for data operations
2. **Create Controller**: Handle HTTP requests and business logic
3. **Add Routes**: Register new routes in `public/index.php`
4. **Create Views**: Build PHP templates for UI
5. **Update Permissions**: Add new permissions if needed

#### Custom Permissions
```php
// Add to permissions table
INSERT INTO permissions (name, description) VALUES
('custom_feature', 'Access to custom feature');

// Assign to role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin' AND p.name = 'custom_feature';
```

## 🧪 Testing

### Automated Testing
The project uses PHPUnit for automated testing:
```bash
# Run all tests
composer test

# Run tests with coverage report
composer test:coverage

# View coverage report
open coverage/index.html
```

### Code Quality
Enforce PSR-12 coding standards with PHP CS Fixer:
```bash
# Check code style
composer cs:check

# Automatically fix code style issues
composer cs:fix
```

### Sample Data
The application includes comprehensive sample data for testing:
```bash
# Import sample data (includes users, projects, tasks, etc.)
mysql -u root -p aureo_db < sample-data.sql
```

### Test Accounts
After importing sample data, you can use these test accounts:
- **Admin**: `admin@aureo.us` / `password`
- **Manager**: Various manager accounts with different permissions
- **Developer**: Multiple developer accounts for testing team features

## 🚀 Deployment

### Production Deployment
1. **Server Requirements**: Ensure PHP 7.4+, MySQL 5.7+, and web server
2. **Environment**: Set `APP_DEBUG=false` in production
3. **Database**: Use production database credentials
4. **SSL**: Configure HTTPS with proper certificates
5. **Security**: Review and configure security settings
6. **Backups**: Implement regular database backups

### Performance Optimization
- **Database Indexing**: Schema includes optimized indexes
- **Query Optimization**: Efficient queries with proper joins
- **Asset Optimization**: Minified CSS in production
- **Caching**: Consider implementing Redis/Memcached for sessions

## 📝 API Documentation

### Authentication Endpoints
- `POST /login` - User authentication
- `POST /register` - User registration
- `GET /logout` - User logout
- `POST /forgot-password` - Password reset request

### Project Management Endpoints
- `GET /projects` - List projects
- `POST /projects/create` - Create new project
- `GET /projects/view/{id}` - View project details
- `POST /projects/update` - Update project

### Task Management Endpoints
- `GET /tasks` - List tasks with filtering
- `POST /tasks/create` - Create new task
- `POST /tasks/update` - Update task
- `POST /tasks/start-timer/{id}` - Start time tracking
- `POST /tasks/stop-timer/{id}` - Stop time tracking

## 🤝 Contributing

1. **Fork the Repository**
2. **Create Feature Branch**: `git checkout -b feature/amazing-feature`
3. **Commit Changes**: `git commit -m 'Add amazing feature'`
4. **Push to Branch**: `git push origin feature/amazing-feature`
5. **Open Pull Request**

### Development Guidelines
- Follow **PSR-12** coding standards (enforced by PHP CS Fixer)
- Follow **PSR-4** autoloading standards
- Use **strict typing** (`declare(strict_types=1)`) in all PHP files
- Write **PHPUnit tests** for new features
- Use meaningful commit messages
- Add comments for complex logic
- Run `composer cs:fix` before committing
- Update documentation as needed

### Security Guidelines
Before contributing, review [SECURITY.md](SECURITY.md) for security best practices:
- Never commit `.env` files
- Use prepared statements for all database queries
- Validate and sanitize all user input
- Follow password hashing guidelines (Argon2ID)
- Implement CSRF protection for state-changing operations

## 📄 License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

**Important:** The AGPL-3.0 license requires that if you modify this software and use it to provide a service over a network, you must make the complete source code of your modified version available to users of that service.

## 👨‍💻 Author

**Russell Benzing**
- Email: me@russellbenzing.com
- GitHub: [@rbenzing](https://github.com/rbenzing)

## 🙏 Acknowledgments

- **TailwindCSS** - For the excellent utility-first CSS framework
- **PHP Community** - For the robust ecosystem and best practices
- **Agile/Scrum Methodology** - For inspiring the project management features
- **Open Source Community** - For the tools and libraries that make this possible

## 📞 Support

For support, email me@russellbenzing.com or create an issue in the GitHub repository.

---

**Aureo Project Management** - Making project management simple and effective for teams of all sizes.
