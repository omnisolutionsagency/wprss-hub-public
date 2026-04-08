=== WPRSS Hub ===
Contributors: wprsshub
Tags: rss, aggregator, management, multisite, dashboard
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Central dashboard to manage WP RSS Aggregator across multiple remote WordPress sites.

== Description ==

WPRSS Hub provides a single admin dashboard installed on a dedicated WordPress site to manage WP RSS Aggregator across up to 5 remote WordPress sites. It connects to remote sites via the WordPress REST API (WP Application Passwords) and WP-CLI over SSH.

**Features:**

* Register and manage multiple remote WordPress sites
* Push and sync RSS feeds across all sites from one place
* Mirror feeds from a source site to target sites
* Manage WPRSS settings across all sites with a grid view
* Job queue for multi-site operations with retry support
* Real-time health monitoring bar
* Full action logging with filters and pagination

== Installation ==

1. Upload the `wprss-hub` folder to `/wp-content/plugins/` on your hub site.
2. Activate the plugin through the 'Plugins' menu.
3. Install `companion/wprss-hub-remote.php` on each remote site as a separate plugin.
4. Create WP Application Passwords on each remote site for the hub to use.
5. Register your remote sites under WPRSS Hub > Sites.

**Requirements:**

* PHP 7.4+ with the Sodium extension enabled
* WordPress 6.0+
* WP RSS Aggregator installed on all remote sites
* SSH key-based auth configured from hub server to remote servers (for WP-CLI features)

== Frequently Asked Questions ==

= Do I need the companion plugin on remote sites? =

Yes. Install `wprss-hub-remote.php` on each remote site for health monitoring, settings management, and force-fetch functionality.

= Does this require WooCommerce? =

No. If WooCommerce (Action Scheduler) is available, it is used for background job processing. Otherwise, WP-Cron is used as a fallback.

= What happens if a remote site is unreachable? =

The hub continues processing other sites. The unreachable site is marked as failed in the health bar, and the error is logged.

== Changelog ==

= 1.0.0 =
* Initial release.
