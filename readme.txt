=== Easy Storage Usage Check ===
Contributors: itsaShane
Tags: storage, usage, report, delete, csv
Requires at least: 5.0
Tested up to: 5.9
Requires PHP: 7.2
Stable tag: 1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Calculates storage usage, displays the largest directories and files, and allows exporting results to CSV and deleting files.

== Description ==

This plugin calculates storage usage, displays the total storage used, the 10 largest directories, and the 50 largest files. It allows deletion of selected files, clearing the results, and exporting the data to a CSV file.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/easy-storage-usage-check` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Tools -> Storage Usage Check screen to view and manage storage usage.

== Frequently Asked Questions ==

= Can this plugin delete core WordPress files? =

No, for security reasons, the plugin cannot delete core WordPress files critical to its functioning.

= Can this plugin delete files in the _wpeprivate directory? =

No, files located in the _wpeprivate directory must be deleted by WP Engine staff.

== Changelog ==

= 1.4 =
* Added nonce verification for enhanced security.
* Fixed text domain to match the plugin slug.
* Updated author URI.

= 1.3 =
* Improved performance of storage calculation.
* Added functionality to export results to CSV.

= 1.2 =
* Added ability to delete selected files.
* Added functionality to clear results.

= 1.1 =
* Improved UI for better user experience.
* Fixed minor bugs.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.4 =
Added nonce verification for enhanced security and fixed text domain.

== Screenshots ==

1. Plugin settings page showing storage usage report.
2. Storage usage report with options to delete selected files.
3. Confirmation dialog for file deletion.

== License ==

This plugin is licensed under the GPLv2 or later.
