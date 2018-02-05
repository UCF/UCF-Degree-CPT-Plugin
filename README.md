# UCF Degree Custom Post Type Plugin #

Provides a custom post type, taxonomies and help functions for describing degree programs.


## Description ##

Provides a custom post type, taxonomies and help functions for describing degree programs. Designed to leverage default WordPress templates and be overridden by post type specific templates.


## Installation ##

### Manual Installation ###
1. Upload the plugin files (unzipped) to the `/wp-content/plugins` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress

### WP CLI Installation ###
1. `$ wp plugin install --activate https://github.com/UCF/UCF-Degree-CPT-Plugin/archive/master.zip`.  See [WP-CLI Docs](http://wp-cli.org/commands/plugin/install/) for more command options.


## Changelog ##

# 2.0.3 #
Enhancements:
* Adds an endpoint for retrieving degree specific relevanssi results.

# 2.0.2 #
Bug Fixes/Enhancements:
* Added improvements to how degrees are matched with undergraduate catalog URLs in the degree importer

# 2.0.1 #
Enhancements:
* Added fullname and plural to degree-search API colleges and program-types

Bug Fixes:
* Fixed notices

# 2.0.0 #
Enhancements:
* Converts layout actions for degree lists and career path lists to filters, and consolidates arguments passed to degree list layout functions.  Please note this change is not backward-compatible with layouts registered using hooks provided by older versions of the plugin.
* Changed the generic class name "Degrees" to "UCF_Degree_Commands"
* Removed anonymous function calls for improved support with older versions of PHP
* Fixed plugin activation and deactivation hooks being referenced outside of the if-statements that check whether or not the functions exist yet.

# 1.1.0 #
Bug Fixes:
* Fixed bug with output of `UCF_Degree_PostType::taxonomies()` still returning nonexistent taxonomies
* Fixed calls to `$wpdb->prepare` to suppress warnings

Enhancements:
* Adds logic to import degrees from the search service
* Adds a configuration option for filtering degrees from the search service (i.e. by college)
* Adds an option to schedule the import via wp-cron (the actual logic for adding the wp-cron task still needs to be written)
* Adds some hooks that should allow the import process to be extended for a specific site's needs.
* Added the helper method `UCF_Degree_Program_Types_Common::get_name_or_alias()`, which returns a program type's alias, if available, or name.

# 1.0.2 #
Bug Fixes:
* Fixed rewrite rule issues on plugin activation/deactivation.

Enhancements:
* Added a custom REST route for the Angular degree search.
* The degree-search api now orders the results by program_types using the new `order_by-taxonomy`, `order_by_taxonomy_field` and `order_by_taxonomy_order` fields.

# 1.0.1 #
Bug Fixes:
* Updated to detect if the now built-in REST API is available.

Enhancements:
* Added postmeta to the meta field of the api.

## Upgrade Notice ##

n/a


## Installation Requirements ##

None


## Development & Contributing ##

NOTE: this plugin's readme.md file is automatically generated.  Please only make modifications to the readme.txt file, and make sure the `gulp readme` command has been run before committing readme changes.

### Wishlist/TODOs ###
* Provide simple default templates that can be overridden in the theme.
