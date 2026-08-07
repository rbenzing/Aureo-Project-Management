# Security Guidelines

## Overview

This document outlines the security features, configuration, and best practices for the Aureo Project Management application.

## Critical Security Requirements

### 1. Environment Configuration

**NEVER commit `.env` files to version control.**

The `.env` file contains sensitive credentials and must be kept secure:

```bash
# .env should be in .gitignore (already configured)
# Use .env.example as a template
cp .env.example .env
```

### 2. Production Environment Setup

When deploying to production, ensure the following environment variables are properly configured:

```env
# Application
APP_ENV=production
APP_DEBUG=false          # CRITICAL: Must be false in production
APP_SCHEME=https         # Use HTTPS in production

# Database
DB_PASSWORD=<strong-password>  # REQUIRED in production

# Session Security
SESSION_SECURE=true      # Requires HTTPS
SESSION_HTTP_ONLY=true

# Security
PASSWORD_PEPPER=<random-string>  # Reserved for future use - read by nothing today
CSRF_TOKEN_EXPIRY=3600
```

### 3. HTTPS Requirement

**Production deployments MUST use HTTPS** to protect:
- Session cookies (when `SESSION_SECURE=true`)
- CSRF tokens
- User credentials
- Sensitive data in transit

### 4. Password Security

Passwords are hashed using **Argon2id**, the current recommended algorithm, via
`App\Utils\PasswordHasher` — the single place the application chooses an algorithm:

- Algorithm: `PASSWORD_ARGON2ID`
- Memory cost: 65536 KB
- Time cost: 4 iterations
- Parallelism: 1 thread

Those costs are PHP's own defaults for Argon2id; the application passes no options array, so they
follow the runtime rather than being configured here.

**Fallback on hosts without libargon2.** Argon2id is a compile-time option, and `password_hash()`
throws a `ValueError` — not a warning — when asked for an algorithm the build does not provide.
Naming it unconditionally therefore made registration, password reset, administrator user
creation and the install migration all fatal on such a host. `PasswordHasher::algorithm()` asks
`password_algos()` what is actually available and falls back to `PASSWORD_DEFAULT` (bcrypt on PHP
8.2) only where Argon2id is absent.

This is not a downgrade of any working installation: every host that runs Aureo today has
Argon2id and continues to use it. `password_verify()` reads the algorithm from the hash itself, so
stored hashes keep verifying and a database may safely hold a mixture of both — which happens
after a restore onto a differently-built host. Note that bcrypt truncates at 72 bytes.

**Never:**
- Store plaintext passwords
- Use weak hashing (MD5, SHA1)
- Implement custom crypto
- Weaken hashing parameters

## Security Features

### CSRF Protection

Cross-Site Request Forgery (CSRF) protection is **mandatory** for all state-changing operations.

**Configuration:**
```env
# In .env (configured via settings table)
csrf_protection_enabled=1
csrf_ajax_protection=1
csrf_token_lifetime=3600
```

**How It Works:**
1. Tokens generated per session
2. Validated on all POST/PUT/DELETE/PATCH requests
3. Auto-rotated on expiry
4. Stored in database with expiration tracking

**Implementation:**
- Middleware: `App\Middleware\CsrfMiddleware`
- Tokens stored in `csrf_tokens` table
- Automatic validation via middleware stack

### Rate Limiting

Database-persisted rate limiting prevents abuse and brute-force attacks.

**Configuration:**
```env
# Settings table: security.rate_limit_attempts
rate_limit_attempts=60  # Max attempts per window
```

**Features:**
- Persistent across sessions (database-backed)
- Per-identifier tracking (IP, user ID, etc.)
- Configurable time windows
- Automatic cleanup of expired records

**Table:** `rate_limits`

**Usage:**
```php
$securityService = SecurityService::getInstance();
if (!$securityService->checkRateLimit($identifier, 'login', 300)) {
    // Rate limit exceeded
}
```

### Input Validation & Sanitization

**All external input is validated** before processing.

**Rules:**
1. Validate all `$_GET`, `$_POST`, `$_SERVER` data
2. Use parameterized queries (PDO) for database operations
3. Sanitize output with `htmlspecialchars()` in views
4. Enforce input size limits (default: 1MB)

**Validator:** `App\Utils\Validator`

**Example:**
```php
$validator = new Validator($data);
$validator->required('email')->email('email');

if (!$validator->validate()) {
    // Handle validation errors
}
```

### SQL Injection Prevention

**Protection Layers:**
1. **Prepared Statements**: All queries use PDO with parameter binding
2. **Column/Table Validation**: Regex validation for identifiers (`/^[a-zA-Z0-9_]+$/`)
3. **Backtick Escaping**: Table and column names wrapped in backticks
4. **Parameter Sanitization**: Sensitive params redacted in error logs

**Example:**
```php
$query = "SELECT * FROM `users` WHERE `email` = :email";
$stmt = $db->executeQuery($query, [':email' => $email]);
```

### XSS Prevention

**All output is escaped** to prevent Cross-Site Scripting (XSS).

**Rules:**
1. Use `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` in views
2. Sanitize user-generated content before display
3. Set Content Security Policy headers

**CSP Headers:**
```php
// Set in public/index.php
Content-Security-Policy: default-src 'self';
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
```

### Session Security

**Configuration:**
```env
SESSION_SECURE=true       # Cookie only sent over HTTPS
SESSION_HTTP_ONLY=true    # Cookie not accessible via JavaScript
SESSION_SAMESITE=Lax      # CSRF protection
```

**Features:**
- Automatic regeneration on login
- Timeout tracking
- IP and User-Agent validation (optional)
- Database-backed session storage

**Table:** `sessions`

### Password Reset & Email Security

**Token-based password reset** with expiration:

1. Tokens are cryptographically random (32 bytes)
2. Expiration enforced (24 hours default)
3. Single-use tokens (invalidated after reset)
4. Rate-limited to prevent abuse

**SMTP Configuration:**
- Use Config methods instead of direct `$_ENV` access
- Credentials stored in `.env`, never in code
- TLS/SSL encryption required

## Security Checklist for Production

### Pre-Deployment

- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Use `APP_SCHEME=https`
- [ ] Rotate all SMTP credentials
- [ ] Set strong database password
- [ ] Configure `SESSION_SECURE=true`
- [ ] Review `.gitignore` excludes `.env`
- [ ] Remove any test/development credentials

### Post-Deployment

- [ ] Verify HTTPS is working
- [ ] Test CSRF protection
- [ ] Verify rate limiting
- [ ] Check error logs for sensitive data leaks
- [ ] Review security headers in responses
- [ ] Run security scan (OWASP ZAP, etc.)
- [ ] Monitor activity logs for anomalies

### Ongoing

- [ ] Regular dependency updates (`composer update`)
- [ ] Monitor error logs daily
- [ ] Review rate limit violations
- [ ] Rotate passwords quarterly
- [ ] Backup database regularly
- [ ] Review user permissions
- [ ] Audit activity logs

## Reporting Security Issues

If you discover a security vulnerability, please email:

**Email:** [me@russellbenzing.com](mailto:me@russellbenzing.com)

**Do NOT:**
- Open public GitHub issues for security vulnerabilities
- Disclose vulnerabilities publicly before patch is released
- Exploit vulnerabilities beyond proof-of-concept

## Security Tools

### Code Quality & Linting

```bash
# Check PSR-12 compliance
composer cs:check

# Auto-fix code style issues
composer cs:fix
```

### Testing

```bash
# Run test suite
composer test

# Run with coverage
composer test:coverage
```

### Dependencies

```bash
# Check for known vulnerabilities in dependencies
composer audit
```

## Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)

---

**Last Updated:** December 2025
**Security Contact:** me@russellbenzing.com
