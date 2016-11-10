<?php
/** 
 * Handles the registration of the Program type taxonomy
 * @author Jim Barnes
 * @since 0.0.1
 **/
if ( ! class_exists( 'UCF_Degree_CareerPath' ) ) {
	class UCF_Degree_CareerPath {
		public static function register_careerpath() {
			register_taxonomy( 'career_paths', array( 'degree' ), self::args() );
		}

		public static function labels() {
			return array(
				'name'                       => _x( 'Career Paths', 'Taxonomy General Name', 'ucf_degree' ),
				'singular_name'              => _x( 'Career Path', 'Taxonomy Singular Name', 'ucf_degree' ),
				'menu_name'                  => __( 'Career Paths', 'ucf_degree' ),
				'all_items'                  => __( 'All Career Paths', 'ucf_degree' ),
				'parent_item'                => __( 'Parent Career Path', 'ucf_degree' ),
				'parent_item_colon'          => __( 'Parent Career Path:', 'ucf_degree' ),
				'new_item_name'              => __( 'New Career Path Name', 'ucf_degree' ),
				'add_new_item'               => __( 'Add New Career Path', 'ucf_degree' ),
				'edit_item'                  => __( 'Edit Career Path', 'ucf_degree' ),
				'update_item'                => __( 'Update Career Path', 'ucf_degree' ),
				'view_item'                  => __( 'View Career Path', 'ucf_degree' ),
				'separate_items_with_commas' => __( 'Separate career paths with commas', 'ucf_degree' ),
				'add_or_remove_items'        => __( 'Add or remove career paths', 'ucf_degree' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'ucf_degree' ),
				'popular_items'              => __( 'Popular career paths', 'ucf_degree' ),
				'search_items'               => __( 'Search Career Paths', 'ucf_degree' ),
				'not_found'                  => __( 'Not Found', 'ucf_degree' ),
				'no_terms'                   => __( 'No items', 'ucf_degree' ),
				'items_list'                 => __( 'Career Paths list', 'ucf_degree' ),
				'items_list_navigation'      => __( 'Career Paths list navigation', 'ucf_degree' ),
			);
		}

		public static function args() {
			return array(
				'labels'                     => self::labels(),
				'hierarchical'               => false,
				'public'                     => true,
				'show_ui'                    => true,
				'show_admin_column'          => true,
				'show_in_nav_menus'          => true,
				'show_tagcloud'              => true,
			);
		}
	}

	add_action( 'init', array( 'UCF_Degree_CareerPath', 'register_careerpath' ), 0 );
}
