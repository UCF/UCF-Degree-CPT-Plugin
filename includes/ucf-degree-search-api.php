<?php
/**
 * Register the routes for the custom Degree Search api
 */
class UCF_Degree_Search_API extends WP_REST_Controller {
	public static function register_route() {
		$root    = 'ucf-degree-search';
		$version = 'v1';
		$base    = 'degrees';

		register_rest_route( "{$root}/{$version}", "/{$base}", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_degrees' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions'),
				'args'                => array( 'UCF_Degree_Search_API', 'get_args' )
			)
		) );
	}

	public static function get_degrees( $request ) {
		$search = $request['search'];
		$colleges = $request['colleges'];
		$program_types = $request['program_types'];

		$retval = array();

		$args = array(
			'post_type'      => 'degree',
			'posts_per_page' => -1,
			'order'          => 'ASC',
			'orerby'         => 'post_title'
		);

		if ( $search ) {
			$args['s'] = $search;
		}

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

		$posts = get_posts( $args );

		foreach( $posts as $post ) {
			$retval[] = self::perpare_degree_for_response( $post, $request );
		}

		return new WP_REST_Response( $retval, 200 );
	}

	public static function perpare_degree_for_response( $post, $request ) {
		$retval = array(
			'title' => $post->post_title,
			'url'   => get_permalink( $post ),
			'hours' => get_post_meta( $post->ID, 'degree_hours', true )
		);

		return $retval;
	}

	public static function get_permissions() {
		return true;
	}

	public static function get_args() {
		return array(
			array(
				'search' => array(
					'default'           => false,
					'sanitize_callback' => 'sanitize_text_field'
				),
				'colleges' => array(
					'default'           => false,
					'sanitize_callback' => array( 'UCF_Degree_Search_API', 'sanitize_array' )
				),
				'program_types' => array(
					'default'           => false,
					'sanitize_callback' => array( 'UCF_Degree_Search_API', 'sanitize_array' )
				)
			)
		);
	}

	public static function sanitize_array( $array ) {
		if ( is_array( $array ) ) {
			$valid = false;

			foreach( $array as $key => $val ) {
				$array[$key] = sanitize_text_field( $val );
			}
		}

		if ( is_string( $array ) ) {
			$array = sanitize_text_field( $array );
		}

		return $array;
	}
}
