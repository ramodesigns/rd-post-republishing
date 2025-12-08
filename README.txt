=== RD - Post Republishing ===
Contributors: paulramotowski
Donate link: https://www.paulramotowski.com/
Tags: republishing, seo, posts, automation, cron, content
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically republish old posts to boost SEO by updating their publish dates, making evergreen content appear fresh in feeds and search results.

== Description ==

**RD - Post Republishing** is a powerful WordPress plugin that helps you maximize the value of your existing content by automatically republishing older posts with updated timestamps. This SEO strategy can help boost traffic to your evergreen content.

= Key Features =

* **Automatic Republishing** - Schedule daily republishing via WP Cron
* **Smart Post Selection** - Republishes oldest eligible posts first
* **Configurable Quota** - Set daily limits by number or percentage
* **Time Randomization** - Natural publish times within your configured hours
* **Category Filtering** - Whitelist or blacklist categories
* **Multiple Post Types** - Support for posts and custom post types
* **REST API** - Trigger republishing programmatically
* **WP-CLI Support** - Command-line interface for developers
* **Detailed History** - Track all republishing activity
* **Audit Logging** - Monitor settings changes
* **Cache Integration** - Automatic cache clearing (WP Rocket, W3TC, etc.)

= How It Works =

1. Configure which post types to republish
2. Set your daily quota (e.g., 5 posts per day)
3. Define the time window for republishing
4. Set the minimum age for eligible posts
5. The plugin automatically selects and republishes posts daily

= Requirements =

* WordPress 6.6 or higher
* PHP 8.2 or higher
* MySQL 5.7+ or MariaDB 10.3+

= Developer Features =

* Modern PHP 8.2 codebase with strict typing
* PSR-4 autoloading via Composer
* Comprehensive hook system for extensibility
* REST API with Application Password authentication
* WP-CLI commands for automation
* PHPStan level 8 compatible
* WordPress Coding Standards compliant

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New in your WordPress admin
2. Search for "RD - Post Republishing"
3. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Go to Plugins > Add New > Upload Plugin
3. Choose the zip file and click "Install Now"
4. Activate the plugin

= Via Composer (for developers) =

`composer require ramodesigns/rd-post-republishing`

= After Installation =

1. Go to Settings > Post Republishing
2. Configure your republishing preferences
3. The plugin will start republishing based on your WP Cron schedule

== Frequently Asked Questions ==

= Does this affect my original post content? =

No. The plugin only updates the post's publish date and modified date. Your content, categories, tags, and all other data remain unchanged.

= How often does republishing occur? =

By default, the plugin runs once daily via WP Cron. You can also trigger it manually or via the REST API.

= Will posts be republished multiple times? =

Each post is only republished once per day. The plugin tracks which posts have been republished to prevent duplicates.

= Can I exclude certain posts? =

Yes! You can use category filtering (whitelist or blacklist) to control which posts are eligible. You can also add the `wpr_exclude_post` post meta with a value of `1` to exclude specific posts.

= Does it work with WP Rocket / W3 Total Cache? =

Yes. The plugin automatically clears caches for WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache, and Autoptimize when posts are republished.

= Can I use this with custom post types? =

Yes. Any public post type can be enabled in the settings.

= What is the minimum post age? =

The default is 30 days, but you can configure this between 7 and 180 days.

= How do I trigger republishing via the API? =

Send a POST request to `/wp-json/republish/v1/trigger` with Application Password authentication. Rate limiting applies (default: 1 request per day).

= Is there a WP-CLI command? =

Yes! Use `wp wpr run` to execute republishing, `wp wpr dry-run` for a preview, and `wp wpr status` to check configuration.

== Screenshots ==

1. Overview dashboard showing republishing statistics
2. Schedule tab with upcoming posts preview
3. Settings configuration page
4. History and audit log viewer

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic post republishing with configurable schedule
* REST API endpoint with rate limiting
* WP-CLI commands
* Cache clearing integration
* Comprehensive admin interface
* Audit logging and history tracking
* PHPStan level 8 and WordPress Coding Standards compliant

== Upgrade Notice ==

= 1.0.0 =
Initial release of RD - Post Republishing.

== Developer Documentation ==

= Hooks and Filters =

**Actions:**

* `wpr_before_republish` - Fires before a post is republished
* `wpr_after_republish` - Fires after successful republishing
* `wpr_republish_failed` - Fires when republishing fails
* `wpr_post_republished` - Fires after each post in a batch
* `wpr_daily_batch_complete` - Fires after daily batch completes

**Filters:**

* `wpr_required_cap` - Filter the required capability (default: `manage_options`)
* `wpr_cache_clear_results` - Filter cache clearing results
* `wpr_eligible_posts` - Filter the list of eligible posts

= REST API =

**Endpoint:** `POST /wp-json/republish/v1/trigger`

**Authentication:** Application Password (Basic Auth)

**Parameters:**
* `force` (boolean) - Bypass daily check (debug mode only)

**Response:**
```json
{
  "success": true,
  "message": "Republished 5 posts successfully.",
  "posts": [...],
  "total": 5
}
```

= WP-CLI Commands =

* `wp wpr run` - Execute republishing batch
* `wp wpr dry-run` - Preview without changes
* `wp wpr status` - Show current configuration
* `wp wpr history` - View republishing history
* `wp wpr cleanup` - Purge old records
* `wp wpr reschedule` - Reset cron events
* `wp wpr db status` - Check database migration status
* `wp wpr db migrate` - Run pending migrations

== Privacy Policy ==

This plugin stores the following data:

* Republishing history (post IDs, dates, status)
* Audit logs (user IDs, settings changes)
* API request logs (IP addresses for rate limiting)

All data is stored in your WordPress database and is not transmitted to external services. Data is automatically purged after 365 days by default.
