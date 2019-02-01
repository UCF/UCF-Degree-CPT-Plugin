# UCF Degree Custom Post Type Plugin #

Provides a custom post type, taxonomies and other utilities for describing UCF degree programs.


## Description ##

Provides a custom post type, taxonomies and other utilities for describing UCF degree programs. Designed to leverage default WordPress templates and be overridden by post type specific templates.

For more information on what's included in this plugin and how to use it, please see the [project wiki](https://github.com/UCF/UCF-Degree-CPT-Plugin/wiki).


## Installation ##

### Manual Installation ###
1. Upload the plugin files (unzipped) to the `/wp-content/plugins` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress

### WP CLI Installation ###
1. `$ wp plugin install --activate https://github.com/UCF/UCF-Degree-CPT-Plugin/archive/master.zip`.  See [WP-CLI Docs](http://wp-cli.org/commands/plugin/install/) for more command options.

### Setup & Usage ###
See the [project wiki](https://github.com/UCF/UCF-Degree-CPT-Plugin/wiki) for setup and usage instructions.


## Changelog ##

### 3.2.1. ###
Bug Fixes:
- Updated `get_parent_program_id()` to return the program's parent ID from `$this->program->parent_program->id` instead of fetching per-parent API results.  See https://github.com/UCF/Search-Service-Django/pull/28.
- Increased API fetch timeout from 5 seconds to 10
- Added check in `UCF_Degree_Common::return_verified_result()` to ensure `$results` is an array before attempting to loop through it.  Resolves #83 .

### 3.2.0 ###
Enhancements:
* Updated the degree importer to get the tuition data from the `program` object in the search service (recently added).
* Adds a filter for adjusting the tuition string.
* Adds the `degree_tuition_skip` field which, when checked, will add a `TuitionOverride` object to the search service via an API call.
* Adds admin messages for letting the user know if the API call is successful.

### 3.1.0 ###
Enhancements:
* Added the "Areas of Interest" (`interests`) taxonomy for Degrees
* Added API endpoints for interests and post tags
* Added an importer for interests
* Added a hook for modifying grouped degree lists by taxonomy (`ucf_degree_group_posts_by_tax`)
* Added a hook for modifying the sort order of grouped degree lists outputted by the [degree-list] shortcode (`ucf_degree_list_sort_grouped_degrees`)

Bug Fixes:
* Added missing quote to class attribute on the `ucf-degree-list-title` heading.
* Fixed an incorrect function name in `function_exists()` call for `ucf_degree_group_posts_by_tax`.

### 3.0.2 ###
Enhancements:
* Adds the `program_types` and `colleges` GET parameters to the `/wp/v2/degrees/` endpoint.
* Adds very simple college schema to each result in the `ucf-degree-search/v1/degrees/` endpoint.

### 3.0.1 ###
Enhancements:
* Updated profile and description type fetches to cache failed responses for 2 minutes, to avoid subsequent external requests to the API on every admin page load
* Added default empty option to profile and description type dropdowns on the plugin options page for improved clarity when an option hasn't been selected

### 3.0.0 ###
Enhancements:
* Refactored the degree import script for compatibility with the new UCF Search Service.  Note that the updated Search Service requires an API key for any access; you will not be able to import fresh degree data until obtaining an API key.
* The default set of Program Type terms has been updated.  Degrees are now explicitly assigned both a parent and child Program Type term, e.g. "Undergraduate Program" AND "Bachelor" (previously, only the child term was explicitly assigned).
* Degree subplans are now saved as separate degree posts.  Subplans are saved as direct children of their parent degree program (are hierarchical).
* Removed the following degree meta data: `degree_type_id`, `degree_description`
* Added the following degree meta data: `degree_api_id`, `degree_plan_code`, `degree_subplan_code`, `degree_name_short`
* Updated the Degree Search API REST controller to organize results with plan/subplan hierarchy, and to add support for the updated Program Type default terms + their expected sort order.
* Unique site-specific degree profile URLs and descriptions (degree `post_content` values) can now be written back up to the UCF Search Service whenever a degree is modified.

**Please note that this version of the plugin is not compatible with previous plugin versions' imported degree data.**  If you require use of the degree import script, we recommend deleting all existing Program Type and Department terms (if applicable) before running a fresh degree import to ensure that all stale degrees are removed and new degrees are imported successfully.

### 2.0.3 ###
Enhancements:
* Adds an endpoint for retrieving degree-specific Relevanssi results.

### 2.0.2 ###
Bug Fixes/Enhancements:
* Added improvements to how degrees are matched with undergraduate catalog URLs in the degree importer

### 2.0.1 ###
Enhancements:
* Added fullname and plural to degree-search API colleges and program-types

Bug Fixes:
* Fixed notices

### 2.0.0 ###
Enhancements:
* Converts layout actions for degree lists and career path lists to filters, and consolidates arguments passed to degree list layout functions.  Please note this change is not backward-compatible with layouts registered using hooks provided by older versions of the plugin.
* Changed the generic class name "Degrees" to "UCF_Degree_Commands"
* Removed anonymous function calls for improved support with older versions of PHP
* Fixed plugin activation and deactivation hooks being referenced outside of the if-statements that check whether or not the functions exist yet.

### 1.1.0 ###
Bug Fixes:
* Fixed bug with output of `UCF_Degree_PostType::taxonomies()` still returning nonexistent taxonomies
* Fixed calls to `$wpdb->prepare` to suppress warnings

Enhancements:
* Adds logic to import degrees from the search service
* Adds a configuration option for filtering degrees from the search service (i.e. by college)
* Adds an option to schedule the import via wp-cron (the actual logic for adding the wp-cron task still needs to be written)
* Adds some hooks that should allow the import process to be extended for a specific site's needs.
* Added the helper method `UCF_Degree_Program_Types_Common::get_name_or_alias()`, which returns a program type's alias, if available, or name.

### 1.0.2 ###
Bug Fixes:
* Fixed rewrite rule issues on plugin activation/deactivation.

Enhancements:
* Added a custom REST route for the Angular degree search.
* The degree-search api now orders the results by program_types using the new `order_by-taxonomy`, `order_by_taxonomy_field` and `order_by_taxonomy_order` fields.

### 1.0.1 ###
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
