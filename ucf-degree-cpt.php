<?php
/*
Plugin Name: UCF Degree Custom Post Type
Description: Provides a degree program custom post type, career paths and program type taxonomies and related meta fields.
Version: 0.0.1
Author: UCF Web Communications
License: GPL3
*/
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'plugins_loaded', function() {
	define( 'UCF_DEGREE__PLUGIN_URL', plugins_url( basename( dirname( __FILE__ ) ) ) );
	define( 'UCF_DEGREE__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'UCF_DEGREE__STATIC_URL', UCF_DEGREE__PLUGIN_URL . '/static' );

	include_once 'includes/ucf-degree-program-type-tax.php';
	include_once 'includes/ucf-degree-career-path-tax.php';
	include_once 'includes/ucf-degree-posttype.php';
	include_once 'admin/ucf-degree-admin.php';

	add_action( 'init', array( 'UCF_Degree_ProgramType', 'register_programtype'), 0 );
	add_action( 'init', array( 'UCF_Degree_CareerPath', 'register_careerpath' ), 0 );
	add_action( 'init', array( 'UCF_Degree_PostType', 'register_degree_posttype' ), 0 );

	add_action( 'admin_enqueue_scripts', array( 'UCF_Degree_Admin', 'enqueue_admin_scripts' ) );

} );

?>
