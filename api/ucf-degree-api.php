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
			$root = 'wp';
			$version = 'v2';

			if ( function_exists( 'relevanssi_do_query' ) ) {
				register_rest_route( "{$root}/{$version}", "/degrees/relevanssi", array(
					array(
						'method'              => WP_REST_Server::READABLE,
						'callback'            => array( 'UCF_Degree_API', 'get_relevanssi_results' ),
					)
				) );
			}

			self::register_fields();
			add_filter( 'rest_degree_query', array( 'UCF_Degree_API', 'add_arguments' ), 10, 2 );
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
				'degree_meta',
				array(
					'get_callback'    => array( 'UCF_Degree_API', 'get_post_meta' ),
					'update_callback' => null,
					'schema'          => null
				)
			);
		}

		/**
		 * Custom endpoint for querying degrees using relevanssi
		 * @author Jim Barnes
		 * @since 2.0.3
		 * @param WP_REST_Request $request The WP REST request object
		 * @return WP_REST_Response The prepared response
		 */
		public static function get_relevanssi_results( $request ) {
			$search        = isset( $request['search'] ) ? $request['search'] : null;
			$limit         = isset( $request['limit'] ) ? $request['limit'] : 10;
			$colleges      = isset( $request['colleges'] ) ? $request['colleges'] : null;
			$program_types = isset( $request['program_types'] ) ? $request['program_types'] : null;
			$interests     = isset( $request['interests'] ) ? $request['interests'] : null;
			$tags          = isset( $request['post_tag'] ) ? $request['post_tag'] : null;

			$args = array(
				'post_type'        => 'degree',
				'limit'            => $limit,
				'orderby'          => 'relevance',
				'suppress_filters' => false
			);

			if ( $search ) {
				$args['s'] = $search;
			}

			// Add program_typesto args, if they exist.
			if ( $program_types ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}

				$args['tax_query'][] = array(
					'taxonomy' => 'program_types',
					'field'    => 'slug',
					'terms'    => $program_types
				);
			}

			// Add colleges to args, if they exist
			if ( $colleges ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}

				$args['tax_query'][] = array(
					'taxonomy' => 'colleges',
					'field'    => 'slug',
					'terms'    => $colleges
				);
			}

			// Add interests to args, if they exist
			if ( $interests ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}

				$args['tax_query'][] = array(
					'taxonomy' => 'interests',
					'field'    => 'slug',
					'terms'    => $interests
				);
			}

			// Add post tags to args, if they exist
			if ( $interests ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}

				$args['tax_query'][] = array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => $tags
				);
			}

			if ( isset(  $args['tax_query'] ) && count( $args['tax_query'] ) > 1 ) {
				$args['tax_query']['relation'] = 'AND';
			}

			$query = new WP_Query( $args );

			// Don't call the normal query. Use relevansse query.
			relevanssi_do_query( $query );

			// Set object type to `degree` so additional meta/tax gets added
			$controller = new WP_REST_Posts_Controller( 'degree' );
			$retval = array();

			while( $query->have_posts() ) {
				$query->the_post(); // Set the post object
				$data     = $controller->prepare_item_for_response( $query->post, $request );
				$retval[] = $controller->prepare_response_for_collection( $data );
			}

			return new WP_REST_Response( $retval, 200 );
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
		 * Filters WP_Query arguments when querying degrees via the REST API.
		 *
		 * @since 3.0.2
		 * @param array $prepared_args Array of arguments for WP_Query.
		 * @param WP_REST_Request $request The current request.
		 */
		public static function add_arguments( $prepared_args, $request ) {
			if ( ! empty( $request['program_types'] ) ) {
				$program_type_arg = array(
					'taxonomy' => 'program_types',
					'field'    => 'slug',
					'terms'    => explode( ',', $request['program_types'] )
				);

				if ( ! isset( $prepared_args['tax_query'] ) ) {
					$prepared_args['tax_query'] = array(
						$program_type_arg
					);
				} else {
					$prepared_args['tax_query'][] = $program_type_arg;
				}
			}

			if ( ! empty( $request['colleges'] ) ) {
				$college_arg = array(
					'taxonomy' => 'colleges',
					'field'    => 'slug',
					'terms'    => explode( ',', $request['colleges'] )
				);

				if ( ! isset( $prepared_args['tax_query'] ) ) {
					$prepared_args['tax_query'] = array(
						$college_arg
					);
				} else {
					$prepared_args['tax_query'][] = $college_arg;
				}
			}

			if ( ! empty( $request['interests'] ) ) {
				$interests_arg = array(
					'taxonomy' => 'interests',
					'field'    => 'slug',
					'terms'    => explode( ',', $request['interests'] )
				);

				if ( ! isset( $prepared_args['tax_query'] ) ) {
					$prepared_args['tax_query'] = array(
						$interests_arg
					);
				} else {
					$prepared_args['tax_query'][] = $interests_arg;
				}
			}

			if ( ! empty( $request['post_tag'] ) ) {
				$tags_arg = array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => explode( ',', $request['post_tag'] )
				);

				if ( ! isset( $prepared_args['tax_query'] ) ) {
					$prepared_args['tax_query'] = array(
						$tags_arg
					);
				} else {
					$prepared_args['tax_query'][] = $tags_arg;
				}
			}

			if ( isset( $prepared_args['tax_query'] ) && count( $prepared_args['tax_query'] ) > 1 ) {
				$prepared_args['tax_query']['relation'] = 'AND';
			}

			return $prepared_args;
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
