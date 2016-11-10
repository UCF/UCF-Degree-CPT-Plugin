<?php
/** 
 * Handles the registration of the Program type taxonomy
 * @author Jim Barnes
 * @since 0.0.1
 **/
if ( ! class_exists( 'UCF_Degree_ProgramType' ) ) {
	class UCF_Degree_ProgramType {
		public static function register_programtype() {
			register_taxonomy( 'program_types', array( 'degree' ), self::args() );
		}

		public static function labels() {
			return array(
				'name'                       => _x( 'Program Types', 'Taxonomy General Name', 'ucf_degree' ),
				'singular_name'              => _x( 'Program Type', 'Taxonomy Singular Name', 'ucf_degree' ),
				'menu_name'                  => __( 'Program Types', 'ucf_degree' ),
				'all_items'                  => __( 'All Program Types', 'ucf_degree' ),
				'parent_item'                => __( 'Parent Program Type', 'ucf_degree' ),
				'parent_item_colon'          => __( 'Parent Program Type:', 'ucf_degree' ),
				'new_item_name'              => __( 'New Program Type Name', 'ucf_degree' ),
				'add_new_item'               => __( 'Add New Program Type', 'ucf_degree' ),
				'edit_item'                  => __( 'Edit Program Type', 'ucf_degree' ),
				'update_item'                => __( 'Update Program Type', 'ucf_degree' ),
				'view_item'                  => __( 'View Program Type', 'ucf_degree' ),
				'separate_items_with_commas' => __( 'Separate program types with commas', 'ucf_degree' ),
				'add_or_remove_items'        => __( 'Add or remove program types', 'ucf_degree' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'ucf_degree' ),
				'popular_items'              => __( 'Popular Program Types', 'ucf_degree' ),
				'search_items'               => __( 'Search Program Types', 'ucf_degree' ),
				'not_found'                  => __( 'Not Found', 'ucf_degree' ),
				'no_terms'                   => __( 'No program types', 'ucf_degree' ),
				'items_list'                 => __( 'Program Types list', 'ucf_degree' ),
				'items_list_navigation'      => __( 'Program types list navigation', 'ucf_degree' ),
			);
		}

		public static function args() {
			return array(
				'labels'                     => self::labels(),
				'hierarchical'               => true,
				'public'                     => true,
				'show_ui'                    => true,
				'show_admin_column'          => true,
				'show_in_nav_menus'          => true,
				'show_tagcloud'              => true,
			);
		}
	}
}
