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
			self::register_fields();
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

			register_rest_field( 'degree',
				'thumbnail',
				array(
					'get_callback'    => array( 'UCF_Degree_API', 'get_degree_thumbnail' ),
					'update_callback' => null,
					'schema'          => null
				)
			);

			register_rest_field( 'degree',
				'meta',
				array(
					'get_callback'    => array( 'UCF_Degree_API', 'get_post_meta' ),
					'update_callback' => null,
					'schema'          => null
				)
			);
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
		 * Gets a post thumbnail src url
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @param $object array | The data object that will be returned to the Rest API
		 * @param $field_name string | The field name
		 * @param $request array | The request object, which includes get and post parameters
		 * @return mixed | In this case a thumbnail src url.
		 **/
		public static function get_degree_thumbnail( $object, $field_name, $request ) {
			$retval = null;
			$thumbnail_id = get_post_thumbnail_id( $object['id'] );
			if ( $thumbnail_id ) {
				$thumbnail = wp_get_attachment_image_src( $thumbnail_id );
				$retval = isset( $thumbnail[0] ) ? $thumbnail[0] : null; 
			}

			return $retval;
		}

		/**
		 * Adds the postmeta to the `meta` field.
		 * @author Jim Barnes
		 * @since 1.0.1
		 * @param $object array | The data object that will be returned to the Rest API
		 * @param $field_name string | The field name
		 * @param $request array | The request object, which includes get and post parameters
		 * @return mixed | In this case an array of postmeta.
		 **/
		public static function get_post_meta( $object, $field_name, $request ) {
			$retval = array();
			$postmeta = get_post_meta( $object['id'] );
			
			foreach( $postmeta as $key => $val ) {
				if ( substr( $key, 0, 6 ) === 'degree' ) {
					$retval[$key] = $val[0];
				}
			}

			return $retval;
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
			if ( isset( $data->data['tags'] ) ) {
				unset( $data->data['tags'] );
			}
			return $data;
		}
	}
}

?>
