=== UCF Degree Custom Post Type Plugin ===
Contributors: ucfwebcom
Tags: ucf, degree
Requires at least: 4.5.3
Tested up to: 4.7.3
Stable tag: 1.0.2
License: GPLv3 or later
License URI: http://www.gnu.org/copyleft/gpl-3.0.html

Provides a custom post type, taxonomies and help functions for describing degree programs.


== Description ==

Provides a custom post type, taxonomies and help functions for describing degree programs. Designed to leverage default WordPress templates and be overridden by post type specific templates.


== Installation ==

= Manual Installation =
1. Upload the plugin files (unzipped) to the `/wp-content/plugins` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress

= WP CLI Installation =
1. `$ wp plugin install --activate https://github.com/UCF/UCF-Degree-CPT-Plugin/archive/master.zip`.  See [WP-CLI Docs](http://wp-cli.org/commands/plugin/install/) for more command options.


== Changelog ==

=== 1.0.2 ===
Bug Fixes:
* Fixed rewrite rule issues on plugin activation/deactivation.

Enhancements:
* Added a custom REST route for the Angular degree search.
* The degree-search api now orders the results by program_types using the new `order_by-taxonomy`, `order_by_taxonomy_field` and `order_by_taxonomy_order` fields.

=== 1.0.1 ===
Bug Fixes:
* Updated to detect if the now built-in REST API is available.

Enhancements:
* Added postmeta to the meta field of the api.

== Upgrade Notice ==

n/a


== Installation Requirements ==

None


== Development & Contributing ==

NOTE: this plugin's readme.md file is automatically generated.  Please only make modifications to the readme.txt file, and make sure the `gulp readme` command has been run before committing readme changes.

= Wishlist/TODOs =
* Provide simple default templates that can be overridden in the theme.
