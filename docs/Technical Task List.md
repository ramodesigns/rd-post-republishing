# RD Post Republishing - Technical Task List

This document outlines all development tasks required to implement the WordPress Post Republishing Plugin as specified in the project documentation.

---

## Phase 1: Foundation & Architecture Refactoring

### 1.1 PHP 8.2 & Modern Standards Setup
- [ ] Add `declare(strict_types=1);` to all PHP files
- [ ] Implement typed properties throughout all classes
- [ ] Use constructor property promotion where applicable
- [ ] Add return type declarations to all methods
- [ ] Use union types and nullable types appropriately
- [ ] Implement readonly properties for immutable data

### 1.2 Namespace & Autoloading Setup
- [ ] Create `composer.json` with PSR-4 autoloading configuration
- [ ] Define namespace root: `WPR\Republisher`
- [ ] Restructure classes to use proper namespaces
- [ ] Add `vendor/autoload.php` loading with `file_exists` guard
- [ ] Update main plugin file to use autoloader

### 1.3 Prefix & Naming Convention Alignment
- [ ] Change hook/filter prefix from `rd_` to `wpr_`
- [ ] Update database table prefix to `{$wpdb->prefix}wpr_`
- [ ] Align text-domain usage (`rd-post-republishing`)
- [ ] Update all class names to follow new naming convention

### 1.4 File Structure Reorganization
```
rd-post-republishing/
├── rd-post-republishing.php (Main plugin file)
├── composer.json
├── includes/
│   ├── Core/
│   │   ├── Plugin.php (Main orchestrator)
│   │   ├── Loader.php (Hook registration)
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   └── I18n.php
│   ├── Republisher/
│   │   ├── Engine.php (Post republishing logic)
│   │   ├── Query.php (Post selection queries)
│   │   └── Cache.php (Cache clearing)
│   ├── Scheduler/
│   │   ├── Cron.php (WP Cron management)
│   │   └── Retry.php (Failed post retry logic)
│   ├── Database/
│   │   ├── Schema.php (Table creation/migration)
│   │   ├── Repository.php (Data access layer)
│   │   └── Maintenance.php (Cleanup operations)
│   ├── Api/
│   │   ├── RestController.php (REST API endpoints)
│   │   └── RateLimiter.php (API rate limiting)
│   ├── Admin/
│   │   ├── Menu.php (Admin menu registration)
│   │   ├── Settings.php (Settings API integration)
│   │   ├── Ajax.php (AJAX handlers)
│   │   └── Views/ (Tab templates)
│   └── Logger/
│       ├── Logger.php (Logging interface)
│       └── AuditTrail.php (Admin change tracking)
├── admin/
│   ├── css/
│   ├── js/
│   └── views/
├── cli/
│   └── Commands.php (WP-CLI commands)
└── languages/
```

---

## Phase 2: Database Layer

### 2.1 Database Schema Creation
- [ ] Create `{prefix}wpr_settings` table for plugin configuration
- [ ] Create `{prefix}wpr_history` table for republishing history
- [ ] Create `{prefix}wpr_audit` table for admin audit logs
- [ ] Create `{prefix}wpr_api_log` table for API rate limiting
- [ ] Add proper indexes for all queryable columns
- [ ] Use `dbDelta()` for safe schema creation

### 2.2 Activation Logic
- [ ] Implement database table creation on activation
- [ ] Set default option values on first activation
- [ ] Store `wpr_db_version` option for migration tracking
- [ ] Schedule initial WP Cron event

### 2.3 Deactivation Logic
- [ ] Clear all scheduled WP Cron events
- [ ] Optionally retain data (with admin setting)

### 2.4 Uninstall Logic
- [ ] Drop all custom database tables
- [ ] Delete all plugin options from `wp_options`
- [ ] Clean up any transients

### 2.5 Migration System
- [ ] Implement version comparison on plugin update
- [ ] Create incremental migration runner
- [ ] Add schema upgrade path for future versions

---

## Phase 3: Core Republishing Engine

### 3.1 Post Selection Query Class
- [ ] Implement `get_eligible_posts()` method with:
  - Post type filtering (configurable)
  - Minimum age threshold (7-180 days)
  - Oldest-first ordering
  - Exclusion of already-republished-today posts
  - Category whitelist/blacklist filtering
- [ ] Handle cross-post-type priority (absolute oldest first)
- [ ] Implement percentage-based quota calculation
- [ ] Cap at 50 posts maximum per day

### 3.2 Republishing Engine Class
- [ ] Implement single post republishing with:
  - `post_date` update to randomized time
  - `post_date_gmt` calculation
  - `post_modified` update to current time
  - `post_modified_gmt` update
- [ ] Generate random publish times within configured hours
- [ ] Support chronological order preservation option
- [ ] Trigger WordPress core publication hooks
- [ ] Return detailed result array (success/failure, timestamps)

### 3.3 Time Randomization
- [ ] Use `wp_timezone()` for site timezone awareness
- [ ] Generate random hour within configured start/end range
- [ ] Handle daylight saving time edge cases
- [ ] Generate random minutes/seconds for natural distribution

### 3.4 Custom Hooks
- [ ] Implement `wpr_before_republish` action
- [ ] Implement `wpr_after_republish` action
- [ ] Implement `wpr_republish_failed` action
- [ ] Document hook parameters for developers

---

## Phase 4: Cache Integration

### 4.1 WordPress Core Cache Clearing
- [ ] Call `clean_post_cache()` after republishing
- [ ] Call `wp_cache_delete()` for posts cache group
- [ ] Clear related taxonomy caches

### 4.2 Third-Party Cache Plugin Support
- [ ] WP Rocket integration (`rocket_clean_post()`)
- [ ] W3 Total Cache integration (`w3tc_pgcache_flush_post()`)
- [ ] WP Super Cache integration (`wp_cache_post_change()`)
- [ ] LiteSpeed Cache integration
- [ ] Add extensible filter for custom cache clearing

---

## Phase 5: Scheduling System

### 5.1 WP Cron Integration
- [ ] Register custom cron schedule interval
- [ ] Schedule daily republishing event on activation
- [ ] Implement main cron callback handler
- [ ] Handle `DISABLE_WP_CRON` detection
- [ ] Handle `ALTERNATE_WP_CRON` detection

### 5.2 Retry Mechanism
- [ ] Track failed republishing attempts
- [ ] Schedule retry event 30 minutes after failure
- [ ] Implement selective retry (failed posts only)
- [ ] Log all retry attempts

### 5.3 Concurrent Execution Prevention
- [ ] Implement mutex/lock using transients
- [ ] Prevent overlapping cron/API executions
- [ ] Add lock timeout fallback

### 5.4 Database Maintenance Cron
- [ ] Schedule daily cleanup event
- [ ] Implement 365-day retention policy for history
- [ ] Implement 365-day retention policy for audit logs
- [ ] Implement 365-day retention policy for API logs

---

## Phase 6: REST API

### 6.1 Endpoint Registration
- [ ] Register `/wp-json/republish/v1/trigger` endpoint
- [ ] Set method to POST
- [ ] Configure permission callback

### 6.2 Authentication & Authorization
- [ ] Implement WordPress Application Password support
- [ ] Verify `manage_options` capability
- [ ] Add capability filter: `apply_filters('wpr_required_cap', 'manage_options')`

### 6.3 Rate Limiting
- [ ] Implement configurable rate limiting (default: 1/day)
- [ ] Store API call timestamps in database
- [ ] Return appropriate error on rate limit exceeded
- [ ] Allow 1/second minimum for testing mode

### 6.4 Response Handling
- [ ] Implement debug mode response (detailed JSON)
- [ ] Implement production mode response (simple boolean)
- [ ] Handle empty response (quota already met)
- [ ] Include proper HTTP status codes

### 6.5 Force Parameter
- [ ] Add `force` parameter to bypass daily check
- [ ] Only enable when `debug_mode` is true
- [ ] Log all force-triggered republishing

---

## Phase 7: Admin Interface

### 7.1 Menu Registration
- [ ] Add settings page under Settings menu
- [ ] Set capability requirement to `manage_options`
- [ ] Register admin page callback

### 7.2 Tab 1: Overview Dashboard
- [ ] Display total published posts count by type
- [ ] Show custom post type counts
- [ ] Display recent republishing activity summary (last 7 days)
- [ ] Add system status indicators:
  - WP Cron status
  - Next scheduled run
  - Last run status
  - Database table status

### 7.3 Tab 2: Republishing Schedule
- [ ] Show 7-day outlook (today + 6 future days)
- [ ] Display today's posts with pending/completed status
- [ ] Show timestamps for completed republishing
- [ ] Preview post selection with titles and original dates
- [ ] Implement real-time status updates via AJAX

### 7.4 Tab 3: Settings
- [ ] **Post Type Selection**: Toggles for standard posts + detected CPTs
- [ ] **Daily Quota**:
  - Radio for number vs percentage mode
  - Number input (1-50)
  - Percentage input with live calculation display
- [ ] **Time Configuration**: Start/end hour dropdowns (0-23)
- [ ] **Minimum Age**: Slider/input for days (7-180, default 30)
- [ ] **Order Preservation**: Toggle for chronological sequence
- [ ] **Category Filtering**:
  - None/whitelist/blacklist radio
  - Category multi-select
- [ ] **Advanced Options**:
  - WP Cron enable/disable toggle
  - API rate limit configuration
  - Debug mode toggle
  - Dry-run mode toggle

### 7.5 Tab 4: History & Logs
- [ ] Republishing history table with:
  - Post title (linked)
  - Post type
  - Original date
  - Republish date
  - Status (success/failed/retrying)
  - Trigger type (cron/api/manual)
- [ ] Add filtering by status, date range, post type
- [ ] Implement pagination (50 rows per page)
- [ ] Audit log table for admin changes
- [ ] Export functionality (CSV) for both tables

### 7.6 Settings API Integration
- [ ] Register settings sections
- [ ] Register individual settings fields
- [ ] Implement validation callback
- [ ] Handle settings save with audit logging

### 7.7 AJAX Handlers
- [ ] Implement dry-run AJAX endpoint
- [ ] Implement manual trigger AJAX endpoint
- [ ] Add nonce verification for all AJAX calls
- [ ] Return proper JSON responses

### 7.8 Admin Assets
- [ ] Create admin CSS with WordPress component styling
- [ ] Create admin JavaScript for:
  - Tab switching
  - AJAX operations
  - Live quota calculation
  - Form validation
- [ ] Use Dashicons for icons
- [ ] Ensure accessibility (labels, ARIA attributes)

---

## Phase 8: Security Implementation

### 8.1 Nonce Verification
- [ ] Add `wp_nonce_field()` to all admin forms
- [ ] Verify with `check_admin_referer()` on submission
- [ ] Use `check_ajax_referer()` for AJAX requests

### 8.2 Input Sanitization
- [ ] Sanitize all text inputs with `sanitize_text_field()`
- [ ] Validate post types against registered types
- [ ] Validate numeric inputs with `absint()`
- [ ] Validate enum values against allowed options

### 8.3 Output Escaping
- [ ] Use `esc_html()` for text output
- [ ] Use `esc_attr()` for HTML attributes
- [ ] Use `wp_kses_post()` for post content
- [ ] Use `esc_url()` for URLs

### 8.4 Capability Checks
- [ ] Verify `manage_options` on all admin operations
- [ ] Add filterable capability for extensibility
- [ ] Check capabilities before any data modification

### 8.5 SQL Security
- [ ] Use `$wpdb->prepare()` for all queries with variables
- [ ] Validate table names before use
- [ ] Use parameterized queries exclusively

---

## Phase 9: Logging & Debugging

### 9.1 History Logging
- [ ] Log every republishing attempt with:
  - Post ID, post type
  - Original date, new date
  - Status, error message
  - Execution time
  - Trigger source

### 9.2 Audit Trail
- [ ] Log all settings changes with:
  - User ID
  - Action type
  - Old value, new value
  - Timestamp
  - IP address, user agent

### 9.3 Debug Mode
- [ ] Enhanced API response details
- [ ] Verbose logging to `error_log()` when `WP_DEBUG_LOG` true
- [ ] Admin visibility into process internals

### 9.4 Dry-Run Mode
- [ ] Complete republishing simulation
- [ ] Return detailed preview of what would change
- [ ] No actual database modifications

---

## Phase 10: WP-CLI Integration

### 10.1 Command Registration
- [ ] Register `wp wpr republish run` command
- [ ] Register `wp wpr republish dry-run` command
- [ ] Register `wp wpr republish cleanup` command

### 10.2 Command Implementation
- [ ] `run`: Execute today's republishing batch
- [ ] `dry-run`: Show forecast without changes
- [ ] `cleanup`: Force 365-day purge of old records

---

## Phase 11: Internationalization

### 11.1 String Wrapping
- [ ] Wrap all UI strings with `__()` or `_e()`
- [ ] Use text-domain `rd-post-republishing` consistently
- [ ] Handle plural forms with `_n()`

### 11.2 Date/Time Localization
- [ ] Use `wp_date()` for all date/time display
- [ ] Respect site timezone and locale settings

### 11.3 POT File
- [ ] Generate updated `rd-post-republishing.pot` file
- [ ] Include all translatable strings

---

## Phase 12: Performance Optimization

### 12.1 Query Optimization
- [ ] Cache settings in class property
- [ ] Use `wp_cache_set()` with `wpr_settings` group
- [ ] Ensure covering indexes on frequently queried columns
- [ ] Use LIMIT queries for post selection

### 12.2 Batch Processing
- [ ] Wrap history inserts in database transaction
- [ ] Process posts in batches if count > 10
- [ ] Implement memory-efficient iteration

### 12.3 Asset Loading
- [ ] Only enqueue admin assets on plugin pages
- [ ] Minify production CSS/JS
- [ ] Use WordPress script dependencies correctly

---

## Phase 13: Testing & Quality Assurance

### 13.1 Static Analysis Setup
- [ ] Configure PHPStan at level 8
- [ ] Configure PHPCS with WordPress standard
- [ ] Add pre-commit hooks for linting

### 13.2 Unit Tests
- [ ] Set up PHPUnit 10 with Brain Monkey
- [ ] Test post selection query logic
- [ ] Test republishing engine
- [ ] Test time randomization
- [ ] Test rate limiting

### 13.3 Integration Tests
- [ ] Test activation/deactivation flow
- [ ] Test WP Cron execution
- [ ] Test REST API endpoints
- [ ] Test admin settings save

### 13.4 GitHub Actions CI
- [ ] PHPStan static analysis workflow
- [ ] PHPCS coding standards workflow
- [ ] PHPUnit test workflow

---

## Phase 14: Documentation & Release

### 14.1 Code Documentation
- [ ] Add PHPDoc blocks to all classes and methods
- [ ] Document all hooks and filters
- [ ] Include inline comments for complex logic

### 14.2 User Documentation
- [ ] Update README.txt with installation instructions
- [ ] Document all settings and their effects
- [ ] Add troubleshooting section
- [ ] Include changelog

### 14.3 Release Preparation
- [ ] Verify WordPress.org plugin guidelines compliance
- [ ] Test on WordPress 6.6+ with PHP 8.2+
- [ ] Create release build script
- [ ] Tag version 1.0.0

---

## Summary: Task Count by Phase

| Phase | Description | Task Count |
|-------|-------------|------------|
| 1 | Foundation & Architecture | 18 |
| 2 | Database Layer | 14 |
| 3 | Core Republishing Engine | 15 |
| 4 | Cache Integration | 6 |
| 5 | Scheduling System | 12 |
| 6 | REST API | 14 |
| 7 | Admin Interface | 35 |
| 8 | Security | 14 |
| 9 | Logging & Debugging | 11 |
| 10 | WP-CLI | 5 |
| 11 | Internationalization | 5 |
| 12 | Performance | 8 |
| 13 | Testing & QA | 12 |
| 14 | Documentation | 8 |
| **Total** | | **~177 tasks** |

---

## Recommended Implementation Order

1. **Phase 1**: Foundation (must be done first - sets up modern PHP structure)
2. **Phase 2**: Database Layer (required for all data operations)
3. **Phase 3**: Core Engine (the main functionality)
4. **Phase 5**: Scheduling (makes the plugin work automatically)
5. **Phase 4**: Cache Integration (ensures changes are visible)
6. **Phase 8**: Security (critical for any user-facing features)
7. **Phase 7**: Admin Interface (allows configuration)
8. **Phase 6**: REST API (alternative trigger method)
9. **Phase 9**: Logging (audit and debugging support)
10. **Phase 11**: i18n (translation readiness)
11. **Phase 10**: WP-CLI (power user features)
12. **Phase 12**: Performance (optimization pass)
13. **Phase 13**: Testing (quality assurance)
14. **Phase 14**: Documentation & Release
