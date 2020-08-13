<?php
/**
 * Handles the registration of the Program type taxonomy
 * @author Jim Barnes
 * @since 0.0.1
 **/
if ( ! class_exists( 'UCF_Degree_CareerPath' ) ) {
	class UCF_Degree_CareerPath {
		public static
			$labels = array(
				'singular' => 'Career Path',
				'plural'   => 'Career Paths',
				'slug'     => 'career_paths'
			);

		/**
		 * Registers the `career_paths` custom taxonomy.
		 * @author Jim Barnes
		 * @since 0.0.1
		 */
		public static function register_careerpath() {
			$labels = apply_filters( 'ucf_degree_career_paths_labels', self::$labels );

			register_taxonomy( $labels['slug'], array( 'degree' ), self::args( $labels ) );
		}

		/**
		 * Retrieves the array of labels use in
		 * registering the custom taxonomy
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @param array $labels The array of singular, plural and slug labels
		 * @return array
		 */
		public static function labels( $labels ) {
			$singular = $labels['singular'];
			$plural   = $labels['plural'];

			return array(
				'name'                       => _x( $plural, 'Taxonomy General Name', 'ucf_degree' ),
				'singular_name'              => _x( $singular, 'Taxonomy Singular Name', 'ucf_degree' ),
				'menu_name'                  => __( $plural, 'ucf_degree' ),
				'all_items'                  => __( 'All ' . $plural, 'ucf_degree' ),
				'parent_item'                => __( 'Parent ' . $singular, 'ucf_degree' ),
				'parent_item_colon'          => __( 'Parent ' . $singular . ':', 'ucf_degree' ),
				'new_item_name'              => __( 'New ' . $singular . ' Name', 'ucf_degree' ),
				'add_new_item'               => __( 'Add New ' . $singular, 'ucf_degree' ),
				'edit_item'                  => __( 'Edit ' . $singular, 'ucf_degree' ),
				'update_item'                => __( 'Update ' . $singular, 'ucf_degree' ),
				'view_item'                  => __( 'View ' . $singular, 'ucf_degree' ),
				'separate_items_with_commas' => __( 'Separate ' . $plural . 'with commas', 'ucf_degree' ),
				'add_or_remove_items'        => __( 'Add or remove ' . $plural, 'ucf_degree' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'ucf_degree' ),
				'popular_items'              => __( 'Popular ' . $plural, 'ucf_degree' ),
				'search_items'               => __( 'Search ' . $plural, 'ucf_degree' ),
				'not_found'                  => __( 'Not Found', 'ucf_degree' ),
				'no_terms'                   => __( 'No items', 'ucf_degree' ),
				'items_list'                 => __( $plural . ' list', 'ucf_degree' ),
				'items_list_navigation'      => __( $plural . ' list navigation', 'ucf_degree' ),
			);
		}

		/**
		 * Returns the args array for registering
		 * the custom taxonomy
		 * @author Jim Barnes
		 * @since 3.1.0
		 * @param array $labels The array of singular, plural and slug labels
		 * @return array
		 */
		public static function args( $labels ) {
			$retval =  array(
				'labels'            => self::labels( $labels ),
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => false,
				'show_in_nav_menus' => true,
				'show_tagcloud'     => true,
			);

			$retval = apply_filters( 'ucf_degree_career_paths_taxonomy_args', $retval );

			return $retval;
		}
	}
}

?>
