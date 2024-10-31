=== Sales Health Monitor for WooCommerce ===
Contributors: itgoldman
Tags: woocommerce, orders, monitoring, notifications, e-commerce
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 0.9.1
Requires PHP: 5.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Effortlessly monitor your WooCommerce store's performance and receive timely email alerts when your sales fall below defined thresholds.

== Description ==

Sales Health Monitor for WooCommerce is an essential tool for store owners seeking to keep a close eye on their sales performance. This plugin allows you to set expectations for your order volume and receive alerts if your sales don't meet those expectations. An external server monitors your website and sends email notifications if the order count falls below your specified limits.

Key features:

* Set a custom threshold for expected number of orders in a specified time frame
* Receive email notifications when sales fall below your threshold
* Easy-to-use settings page in the WordPress admin area
* Seamless integration with WooCommerce
* Hourly monitoring by an external server

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/sales-health-monitor-for-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Sales Health Monitor for WooCommerce menu item in the WordPress admin area to configure the plugin settings

== Privacy ==

Sales Health Monitor for WooCommerce is designed with your privacy in mind. The plugin only shares the site URL and aggregated order data (number of orders within a time frame) with the external monitoring service at https://sales-health-monitor.itgoldman.com. No personal customer information or order details are transmitted.

== Frequently Asked Questions ==

= How does the plugin monitor orders? =

The plugin allows you to set expectations for your order volume. You specify how many orders (X) you expect within a certain number of hours (Y). For example, you might expect at least 10 orders every 12 hours.

= How often does the plugin check for new orders? =

An external service, located at https://sales-health-monitor.itgoldman.com, monitors your website and checks for new orders every hour. If the number of new orders within your specified time frame falls below your set threshold, an email notification is sent.

= Is my privacy secured? =

Yes. Only the minimum necessary data is transmitted: your site's URL and the aggregated number of orders. No sensitive customer or order details are shared.

= Can I change the email address for notifications? =

Yes, you can set any email address for notifications in the plugin settings page.

= Is the monitoring done on my WordPress site? =

No, the actual monitoring is performed by an external server. Your WordPress site provides the necessary information to this server, which then performs the hourly checks and sends notifications if needed.

= How do I enable or disable the service? =

You can deactivate the plugin anytime, or you can pause monitoring by unchecking the "Enable Monitoring" checkbox.

== Disclaimer ==

Sales Health Monitor for WooCommerce is provided as-is, without any guarantees or warranties of any kind. While we strive to ensure the plugin operates efficiently and reliably, we do not take responsibility for any issues that may arise in mission-critical environments, such as e-commerce stores. Users are advised to thoroughly test the plugin in their environments before relying on it for business-critical operations. Use of this plugin is at your own risk.

== Screenshots ==

1. Sales Health Monitor for WooCommerce settings page

== Changelog ==

= 0.9.1 =
* Improved monitoring performance
* Bug fixes and stability improvements

= 0.9.0 =
* Initial release

