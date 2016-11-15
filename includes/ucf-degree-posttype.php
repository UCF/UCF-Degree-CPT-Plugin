<?php
/**
 * Handles the registration of the degree custom post type.
 * @author Jim Barnes
 * @since 0.0.1
 **/
if ( ! class_exists( 'UCF_Degree_PostType' ) ) {
	class UCF_Degree_PostType {
		public static function register_degree_posttype() {
			register_post_type( 'degree', self::args() );
		}

		public static function labels() {
			return array(
				'name'                  => _x( 'Degrees', 'Post Type General Name', 'ucf_degree' ),
				'singular_name'         => _x( 'Degree', 'Post Type Singular Name', 'ucf_degree' ),
				'menu_name'             => __( 'Degrees', 'ucf_degree' ),
				'name_admin_bar'        => __( 'Degree', 'ucf_degree' ),
				'archives'              => __( 'Degree Archives', 'ucf_degree' ),
				'parent_item_colon'     => __( 'Parent Degree:', 'ucf_degree' ),
				'all_items'             => __( 'All Degrees', 'ucf_degree' ),
				'add_new_item'          => __( 'Add New Degree', 'ucf_degree' ),
				'add_new'               => __( 'Add New', 'ucf_degree' ),
				'new_item'              => __( 'New Degree', 'ucf_degree' ),
				'edit_item'             => __( 'Edit Degree', 'ucf_degree' ),
				'update_item'           => __( 'Update Degree', 'ucf_degree' ),
				'view_item'             => __( 'View Degree', 'ucf_degree' ),
				'search_items'          => __( 'Search Degrees', 'ucf_degree' ),
				'not_found'             => __( 'Not found', 'ucf_degree' ),
				'not_found_in_trash'    => __( 'Not found in Trash', 'ucf_degree' ),
				'featured_image'        => __( 'Featured Image', 'ucf_degree' ),
				'set_featured_image'    => __( 'Set featured image', 'ucf_degree' ),
				'remove_featured_image' => __( 'Remove featured image', 'ucf_degree' ),
				'use_featured_image'    => __( 'Use as featured image', 'ucf_degree' ),
				'insert_into_item'      => __( 'Insert into degree', 'ucf_degree' ),
				'uploaded_to_this_item' => __( 'Uploaded to this degree', 'ucf_degree' ),
				'items_list'            => __( 'Degrees list', 'ucf_degree' ),
				'items_list_navigation' => __( 'Degrees list navigation', 'ucf_degree' ),
				'filter_items_list'     => __( 'Filter degrees list', 'ucf_degree' ),
			);
		}

		public static function args() {
			return array(
				'label'                 => __( 'Degree', 'ucf_degree' ),
				'description'           => __( 'Degree Programs', 'ucf_degree' ),
				'labels'                => self::labels(),
				'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', ),
				'taxonomies'            => self::taxonomies(),
				'hierarchical'          => false,
				'public'                => true,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'menu_position'         => 5,
				'menu_icon'             => 'dashicons-welcome-learn-more',
				'show_in_admin_bar'     => true,
				'show_in_nav_menus'     => true,
				'can_export'            => true,
				'has_archive'           => true,		
				'exclude_from_search'   => false,
				'publicly_queryable'    => true,
				'capability_type'       => 'post',
			);
		}

		public static function taxonomies() {
			$retval = array(
				'post_tag',
				'program_types',
				'colleges',
				'career_paths'
			);

			$retval = apply_filters( 'ucf_degree_taxonomies', $retval );

			foreach( $retval as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					unset( $retval[$taxonomy] );
				}
			}

			return $retval;
		}
	}
}
