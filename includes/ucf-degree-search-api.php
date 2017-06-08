<?php
/**
 * Register the routes for the custom Degree Search api
 */
class UCF_Degree_Search_API extends WP_REST_Controller {
	public static
		$order = array(
			'Undergraduate Degree',
			'Minor',
			'Certificate',
			'Graduate Degree',
			'Articulated Degree',
			'Accelerated Degree'
		);

	/**
	 * Registers the rest routes for the degree search api
	 * @author Jim Barnes
	 * @since 1.0.2
	 **/
	public static function register_rest_routes() {
		$root    = 'ucf-degree-search';
		$version = 'v1';
		$base    = 'degrees';

		register_rest_route( "{$root}/{$version}", "/{$base}", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_degrees' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions'),
				'args'                => array( 'UCF_Degree_Search_API', 'get_degrees_args' )
			)
		) );
	}

	/**
	 * Callback for the /degrees endpoint
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $request WP_REST_Request object | Contains get params
	 * @return WP_REST_Response
	 **/
	public static function get_degrees( $request ) {
		$search = $request['search'];
		$colleges = $request['colleges'];
		$program_types = $request['program_types'];
		$page = $request['page'] ? $request['page'] : 1;

		$count = 0;

		$retval = array();

		$args = array(
			'post_type'               => 'degree',
			'posts_per_page'          => 100,
			'paged'                   => $page,
			'orderby'                 => 'post_title',
			'order'                   => 'ASC',
			'order_by_taxonomy'       => 'program_types',
			'order_by_taxonomy_field' => 'name',
			'order_by_taxonomy_order' => self::$order,
			'suppress_filters'        => false
		);

		if ( $search ) {
			$args['s'] = $search;
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

		$query = new WP_Query( $args );
		$number_pages = $query->max_num_pages;
		$total_posts = $query->found_posts;

		$posts = $query->posts;

		foreach( $posts as $post ) {
			$program_type = wp_get_post_terms( $post->ID, 'program_types' );
			$program_type = is_array( $program_type ) ? $program_type[0] : null;

			if ( ! isset( $retval[$program_type->slug] ) ) {
				$alias = get_term_meta( $program_type->term_id, 'program_types_alias', true );
				$alias = $alias ? $alias : $program_type->name;

				$retval[$program_type->slug] = array(
					'alias'   => $alias,
					'count'   => 0,
					'degrees' => array()
				);
			}

			$retval[$program_type->slug]['degrees'][] = self::prepare_degree_for_response( $post, $request );
			$retval[$program_type->slug]['count']++;
			$count++;
		}

		$retval = self::prepare_response( $retval, $count, $page, $number_pages, $total_posts );

		return new WP_REST_Response( $retval, 200 );
	}

	/**
	 * Prepares each degree for response
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $post WP_Post | The post object to format
	 * @param $request WP_REST_Request | The request option
	 * @return Array | The formatted post
	 **/
	public static function prepare_degree_for_response( $post, $request ) {
		$permalink = get_permalink( $post );
		$hours = get_post_meta( $post->ID, 'degree_hours', true );
		$terms = wp_get_post_terms( $post->ID, 'program_types' );
		$term = is_array( $terms ) ? $terms[0]->slug : null;

		$retval = array(
			'title' => $post->post_title,
			'url'   => $permalink,
			'hours' => $hours,
			'type'  => $term
		);

		return $retval;
	}

	/**
	 * Get permissions callback. Read only, so just return true.
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	public static function get_permissions() {
		return true;
	}

	/**
	 * Defines the args available for the /degrees/ endpoint
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @return array | The array of args
	 **/
	public static function get_degrees_args() {
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

	/**
	 * Prepares the response object
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $results Array | The array of degrees grouped by type
	 * @param $count int | The count of degrees
	 * @param $page int | The current page
	 * @param $number_pages int | The total number of pages.
	 * @param $total_posts int | The total number of posts.
	 * @return Array | The response array
	 **/
	public static function prepare_response( $results, $count, $page, $number_pages, $total_posts ) {
		$start = (((int)$page - 1) * 100) + 1;
		$end = $start + $count - 1;

		$retval = array(
			'count'       => $count,
			'totalPosts'  => (int)$total_posts,
			'startIndex'  => $start,
			'endIndex'    => $end,
			'currentPage' => (int)$page,
			'totalPages'  => $number_pages,
			'types'       => array()
		);

		foreach( $results as $type => $data ) {
			if ( $data['count'] !== 0 ) {
				$retval['types'][] = $results[$type];
			}
		}

		return $retval;
	}

	/**
	 * Custom sanitation callback for colleges/program_types
	 * Allows for string or array of string
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $array string|Array | The string or array to sanitize
	 * @return string|array | The string or array sanitized
	 **/
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
