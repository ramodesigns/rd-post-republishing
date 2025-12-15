# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Nothing yet

## [1.0.0] - 2024-12-08

### Added

#### Core Features
- Automatic post republishing with configurable daily quota
- Smart post selection prioritizing oldest eligible posts
- Time randomization within configured publishing hours
- Support for standard posts and custom post types
- Category filtering with whitelist/blacklist options
- Minimum post age configuration (7-180 days)
- Chronological order preservation option
- Dry-run mode for testing without making changes

#### Scheduling System
- WP Cron integration for automated daily republishing
- Configurable start and end hours for publishing window
- Retry mechanism with exponential backoff for failed posts
- Concurrent execution prevention using mutex locks
- Database maintenance cron for automatic cleanup

#### REST API
- POST endpoint at `/wp-json/republish/v1/trigger`
- Application Password authentication support
- Configurable rate limiting (default: 1 request per day)
- Debug mode for detailed API responses
- Force parameter to bypass daily check (debug mode only)

#### WP-CLI Commands
- `wp wpr run` - Execute republishing batch
- `wp wpr dry-run` - Preview without changes
- `wp wpr status` - Show current configuration
- `wp wpr history` - View republishing history
- `wp wpr cleanup` - Purge old records
- `wp wpr reschedule` - Reset cron events
- `wp wpr db status` - Check database migration status
- `wp wpr db migrate` - Run pending migrations

#### Admin Interface
- Tabbed settings page (Overview, Schedule, Settings, Logs)
- Real-time republishing preview
- Manual trigger and dry-run buttons
- Comprehensive settings with validation
- History table with filtering and pagination
- Audit log for settings changes
- CSV export for history and audit data

#### Cache Integration
- WordPress core cache clearing
- WP Rocket integration
- W3 Total Cache integration
- WP Super Cache integration
- LiteSpeed Cache integration
- WP Fastest Cache integration
- Autoptimize integration
- Extensible cache clearing via filters

#### Database
- Custom tables for history, audit logs, and API logs
- Version-based migration system
- 365-day automatic data retention
- Optimized queries with proper indexing

#### Logging & Debugging
- Detailed republishing history
- Settings change audit trail
- Debug mode with verbose logging
- WP Cron status monitoring
- Admin notices for cron issues

#### Developer Features
- Modern PHP 8.2 codebase with strict typing
- PSR-4 autoloading via Composer
- Comprehensive action and filter hooks
- PHPStan level 8 compatible
- WordPress Coding Standards compliant
- GitHub Actions CI pipeline

#### Internationalization
- Full i18n support
- POT file for translations
- Proper text domain usage

### Security
- Nonce verification on all forms
- Capability checks (manage_options)
- Input sanitization
- Output escaping
- Prepared SQL statements
- Rate limiting on API endpoints

---

## Version History

| Version | Date | Description |
|---------|------|-------------|
| 1.0.0 | 2024-12-08 | Initial release |

---

## Upgrade Notes

### Upgrading to 1.0.0

This is the initial release. No upgrade steps required.

---

## Development

### Running Static Analysis

```bash
# Install dependencies
composer install

# Run PHPStan
composer phpstan

# Run PHPCS
composer phpcs

# Fix PHPCS issues automatically
composer phpcbf
```

### Running Tests

```bash
composer test
```

---

## Links

- [GitHub Repository](https://github.com/ramodesigns/rd-post-republishing)
- [Issue Tracker](https://github.com/ramodesigns/rd-post-republishing/issues)
- [Author Website](https://www.paulramotowski.com)
