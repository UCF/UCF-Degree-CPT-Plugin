<?php
/** 
 * Implements a simple API for degree data
 * Depends on the WP REST API plugin being
 * installed.
 **/
if ( ! class_exists( 'UCF_Degree_API' ) ) {
	class UCF_Degree_API {
		public static function register_rest_routes() {
			add_action( 'rest_api_init', array( 'UCF_Degree_API', 'register_fields' ) );
		}

		public static function add_rest_route_to_args( $args ) {
			$args['show_in_rest'] = true;
			$args['rest_base'] = 'degrees';
			$args['rest_controller_class'] = 'WP_REST_Posts_Controller';

			return $args;
		}

		public static function register_fields() {
			$taxonomies = UCF_Degree_PostType::taxonomies();

			foreach( $taxonomies as $tax ) {
				register_rest_field( 'degree',
					$tax,
					array(
						'get_callback'    => array( 'UCF_Degree_API', 'get_taxonomy_terms' ),
						'update_callback' => null,
						'schema'          => null
					)
				);
			}
		}

		public static function get_taxonomy_terms( $object, $tax_term, $request ) {
			$terms = wp_get_post_terms( $object['id'], $tax_term );
			return $terms;
		}
	}
}
