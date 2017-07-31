<?php
/*
Plugin Name: UCF Degree Custom Post Type
Description: Provides a degree program custom post type, career paths and program type taxonomies and related meta fields.
Version: 1.0.2
Author: UCF Web Communications
License: GPL3
*/
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include_once 'includes/ucf-degree-wpcli.php';
	include_once 'importers/degree-importer.php';

	WP_CLI::add_command( 'degrees', 'Degrees' );
}

define( 'UCF_DEGREE__PLUGIN_URL', plugins_url( basename( dirname( __FILE__ ) ) ) );
define( 'UCF_DEGREE__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UCF_DEGREE__STATIC_URL', UCF_DEGREE__PLUGIN_URL . '/static' );
define( 'UCF_DEGREE__PLUGIN_FILE', __FILE__ );

include_once 'includes/ucf-degree-program-type-tax.php';
include_once 'includes/ucf-degree-career-path-tax.php';
include_once 'includes/ucf-degree-posttype.php';
include_once 'includes/ucf-degree-api.php';
include_once 'includes/ucf-degree-search-api.php';
include_once 'includes/ucf-degree-utils.php';
include_once 'includes/ucf-degree-search-custom-filters.php';
include_once 'admin/ucf-degree-admin.php';
include_once 'admin/ucf-degree-config.php';

include_once 'common/ucf-degree-list-common.php';
include_once 'common/ucf-degree-career-paths-common.php';
include_once 'common/ucf-degree-program-types-common.php';

include_once 'shortcodes/ucf-degree-list-shortcode.php';
include_once 'shortcodes/ucf-degree-career-paths-shortcode.php';

if ( ! function_exists( 'ucf_degree_plugin_activation' ) ) {
	function ucf_degree_plugin_activation() {
		UCF_Degree_ProgramType::register_programtype();
		UCF_Degree_CareerPath::register_careerpath();
		UCF_Degree_PostType::register_degree_posttype();
		UCF_Degree_Config::add_options();
		flush_rewrite_rules();
	}
}

if ( ! function_exists( 'ucf_degree_plugin_deactivation' ) ) {
	function ucf_degree_plugin_deactivation() {
		UCF_Degree_Config::delete_options();
		flush_rewrite_rules();
	}
}

register_activation_hook( UCF_DEGREE__PLUGIN_FILE, 'ucf_degree_plugin_activation' );
register_deactivation_hook( UCF_DEGREE__PLUGIN_FILE, 'ucf_degree_plugin_deactivation' );

add_action( 'plugins_loaded', function() {

	add_action( 'init', array( 'UCF_Degree_ProgramType', 'register_programtype'), 10, 0 );
	add_action( 'init', array( 'UCF_Degree_CareerPath', 'register_careerpath' ), 10, 0 );
	add_action( 'init', array( 'UCF_Degree_PostType', 'register_degree_posttype' ), 10, 0 );

	if ( UCF_Degree_Config::rest_api_enabled() && UCF_Degree_Config::get_option_or_default( 'rest_api' ) ) {
		add_action( 'ucf_degree_post_type_args', array( 'UCF_Degree_API', 'add_rest_route_to_args' ) );
		add_action( 'rest_api_init', array( 'UCF_Degree_API', 'register_rest_routes' ) );
		add_action( 'rest_api_init', array( 'UCF_Degree_Search_API', 'register_rest_routes' ) );

		add_action( 'posts_orderby', array( 'UCF_Degree_Search_Custom_Filters', 'order_by_tax_orderby' ), 15, 2 );
	}

	if ( ! shortcode_exists( 'career-paths' ) ) {
		add_shortcode( 'career-paths', array( 'UCF_Degree_Career_Paths_List_Shortcode', 'shortcode' ) );
	}

	add_action( 'admin_enqueue_scripts', array( 'UCF_Degree_Admin', 'enqueue_admin_scripts' ) );
} );

?>
