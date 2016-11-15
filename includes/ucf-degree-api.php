<?php
/** 
 * Implements a simple API for degree data
 * Depends on the WP REST API plugin being
 * installed.
 **/
if ( ! class_exists( 'UCF_Degree_API' ) ) {
	class UCF_Degree_API {

		/**
		 * Main entry point for Rest API functionality
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @return void
		 **/
		public static function register_rest_routes() {
			add_action( 'rest_api_init', array( 'UCF_Degree_API', 'register_fields' ) );
			add_action( 'rest_prepare_degree', array( 'UCF_Degree_API', 'remove_tags' ), 10, 3 );
		}

		/**
		 * Registers the rest route by hooking into the custom post type args
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @param $args array | The arguments that will be passed to the register_post_type function.
		 * @return array
		 **/
		public static function add_rest_route_to_args( $args ) {
			$args['show_in_rest'] = true;
			$args['rest_base'] = 'degrees';
			$args['rest_controller_class'] = 'WP_REST_Posts_Controller';

			return $args;
		}

		/**
		 * Registers additional fields to the Rest API object
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @return void
		 **/
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

		/**
		 * Gets all terms set to the post for the provided taxonomy
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @param $object array | The data object that will be returned to the Rest API
		 * @param $tax string | The field name, which is this case should be a taxonomy slug
		 * @param $request array | The request object, which includes get and post parameters
		 * @return mixed | In this case a WP_Term object.
		 **/
		public static function get_taxonomy_terms( $object, $tax, $request ) {
			$terms = wp_get_post_terms( $object['id'], $tax );
			foreach( $terms as $term ) {
				$term->meta = ucf_degree_reduce_meta_values( get_term_meta( $term->term_id ) );
			}
			return $terms;
		}

		/**
		 * Removes the 'tags' array, since it is included as 'post_tag'
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @param $data Object | The data object
		 * @param $post WP_POST | The post object
		 * @param $request Object | The request object
		 * @return Object
		 **/ 
		public static function remove_tags( $data, $post, $request ) {
			unset( $data->data['tags'] );
			return $data;
		}
	}
}
