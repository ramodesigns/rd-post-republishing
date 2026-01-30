=== RD - Post Republishing ===
Contributors: paulramotowski
Donate link: https://www.paulramotowski.com/
Tags: republish, posts, automation, seo
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

RD - Post Republishing is a powerful tool designed to help WordPress site owners keep their content fresh by automatically republishing old posts.

== Description ==

RD - Post Republishing automates the process of updating the publish date of older posts to the current time, helping to bring past content back to the top of your blog feed and potentially improving SEO by signaling that content is up-to-date.

Key Features:
* **Automated Republishing**: Automatically finds and updates the dates of older posts.
* **REST API Integration**: Provides endpoints to trigger the republishing process from external services or cron jobs.
* **History Tracking**: Keep track of which posts were republished and when.
* **Detailed Logging**: Comprehensive logs of all plugin activities for easy troubleshooting.
* **Customizable Settings**: Configure the republishing logic to suit your site's needs.

== Installation ==

1. Upload the `rd-post-republishing` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your settings under the 'Post Republisher' menu.

== REST API Endpoints ==

The plugin provides several REST API endpoints:
* `GET /wp-json/postmetadata/v1/process/trigger`: Triggers the republishing process (protected).
* `GET /wp-json/postmetadata/v1/process/triggerpublic`: Triggers the republishing process (requires Debug mode).
* `GET /wp-json/postmetadata/v1/process/validate`: Validates the prerequisites for republishing (protected).
* `GET /wp-json/postmetadata/v1/process/validatepublic`: Validates the prerequisites for republishing (requires Debug mode).

Note: Public endpoints (`*public`) are restricted by default. They can be accessed in two ways:
1. **Debug Mode**: Enable "Debug" mode in the plugin settings. This provides temporary access for 12 hours.
2. **Cron Secret Token**: Generate a secret token in the plugin settings and append it to your request as a query parameter (e.g., `?token=YOUR_TOKEN`). This is the recommended method for automated server-side cron jobs.

== Changelog ==

= 1.0.1 =
* Updated database schema to support longer access tokens.
* Improved update process to handle manual "override" installations.

= 1.0.0 =
* Initial release.
* Automated post republishing service.
* REST API endpoints for process triggering and validation.
* Admin interface for settings, history, and logs.