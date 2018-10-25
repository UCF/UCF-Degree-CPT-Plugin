<?php
/**
 * Handles the registration of the Interest taxonomy
 * @author Jim Barnes
 * @since 3.1.0
 */
if ( ! class_exists( 'UCF_Degree_Interest' ) ) {
    class UCF_Degree_Interest {
        public static
            $labels = array(
                'singular' => 'Area of Interest',
                'plural'   => 'Areas of Interest',
                'slug'     => 'interest'
            );

        public static function register_interest() {
            $labels = apply_filters( 'ucf_degree_interests_labels', self::$labels );

            register_taxonomy( $labels['slug'], array( 'degree' ), self::args( $labels ) );
        }

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
				'items_list_navigation'      => __( $plural . 'list navigation', 'ucf_degree' ),
			);
        }

        public static function args( $labels ) {
            return array(
				'labels'                     => self::labels( $labels ),
				'hierarchical'               => false,
				'public'                     => true,
				'show_ui'                    => true,
				'show_admin_column'          => true,
				'show_in_nav_menus'          => true,
				'show_tagcloud'              => true,
			);
        }
    }
}