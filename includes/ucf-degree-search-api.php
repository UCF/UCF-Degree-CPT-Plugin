<?php
/**
 * Register the routes for the custom Degree Search api
 */
class UCF_Degree_Search_API extends WP_REST_Controller {
	public static
		$order = array(
			'Bachelor',
			'Minor',
			'Master',
			'Specialist',
			'Doctorate',
			'Graduate Certificate',
			'Undergraduate Certificate'
		);

	/**
	 * Registers the rest routes for the degree search api
	 * @author Jim Barnes
	 * @since 1.0.2
	 **/
	public static function register_rest_routes() {
		$root    = 'ucf-degree-search';
		$version = 'v1';

		register_rest_route( "{$root}/{$version}", "/degrees", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_degrees' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions'),
				'args'                => array( 'UCF_Degree_Search_API', 'get_degrees_args' )
			)
		) );

		register_rest_route( "{$root}/{$version}", "/program-types", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_program_types' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions' ),
				'args'                => array( 'UCF_Degree_Search_API', 'get_program_types_args' )
			)
		) );

		register_rest_route( "{$root}/{$version}", "/program-types/counts", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_program_types_counts' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions' ),
				'args'                => array( 'UCF_Degree_Search_API', 'get_program_types_counts_args' )
			)
		) );

		register_rest_route( "{$root}/{$version}", "/colleges", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_colleges' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions' ),
				'args'                => array( 'UCF_Degree_Search_API', 'get_colleges_args' )
			)
		) );

		register_rest_route( "{$root}/{$version}", "/colleges/counts", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_colleges_counts' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions' ),
				'args'                => array( 'UCF_Degree_Search_API', 'get_colleges_counts_args' )
			)
		) );
	}

	/**
	 * Callback for the /degrees endpoint
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param WP_REST_Request $request | Contains get params
	 * @return WP_REST_Response
	 **/
	public static function get_degrees( $request ) {
		$search = $request['search'];
		$colleges = $request['colleges'];
		$program_types = $request['program_types'];
		$page = $request['page'] ? $request['page'] : 1;
		$limit = $request['limit'] ? $request['limit'] : 100;

		$count = 0;

		$retval = array();

		$args = array(
			'post_type'               => 'degree',
			'posts_per_page'          => $limit,
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

			$retval[$program_type->slug]['degrees'][] = $post;
			$retval[$program_type->slug]['count']++;
			$count++;
		}

		$retval = self::organize_results( $retval, $request );

		$retval = self::prepare_response( $retval, $count, $page, $number_pages, $total_posts, $limit );

		return new WP_REST_Response( $retval, 200 );
	}

	/**
	 * Organize results based on subplans
	 * @param array $results | The results array.
	 * @param WP_REST_Request $request | The request object.
	 * @return array The reorganized results.
	 */
	public static function organize_results( $results, $request ) {
		$retval = $results;

		foreach( $results as $key => $program_type ) {
			$retval[$key]['degrees'] = array();
			foreach( $program_type['degrees'] as $degree ) {

				$children = get_children( array(
					'post_parent' => $degree->ID,
					'post_type'   => 'degree',
					'post_status' => 'publish',
					'numberposts' => -1
				) );

				if ( isset( $retval[$key][$degree->ID] ) ) {
					continue;
				}

				if ( $degree->post_parent === 0 && count( $children ) === 0 ) {
					$retval[$key]['degrees'][$degree->ID] = self::prepare_degree_for_response( $degree, $request );
				}

				if ( count( $children ) ) {
					$parent_degree = self::prepare_degree_for_response( $degree, $request );

					foreach( $children as $child ) {
						$parent_degree['subplans'][$child->ID] = self::prepare_degree_for_response( $child, $request );
					}

					$retval[$key]['degrees'][$degree->ID] = $parent_degree;
				}

				if ( $degree->post_parent !== 0 ) {
					if ( ! isset( $retval[$key]['degrees'][$degree->post_parent] ) ) {
						$parent = get_post( $degree->post_parent );
						$retval[$key]['degrees'][$degree->post_parent] = self::prepare_degree_for_response( $parent, $request );
					}

					$retval[$key]['degrees'][$degree->post_parent]['subplans'][$degree->ID] = self::prepare_degree_for_response( $degree, $request );
				}
			}

			// Remove post ids from results
			foreach( $retval[$key]['degrees'] as $i => $degree ) {
				$retval[$key]['degrees'][$i]['subplans'] = array_values( $degree['subplans'] );
			}

			$retval[$key]['degrees'] = array_values( $retval[$key]['degrees'] );
		}

		return array_values( $retval );
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
			'title'    => $post->post_title,
			'url'      => $permalink,
			'hours'    => $hours,
			'type'     => $term,
			'subplans' => array()
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
				),
				'limit'         => array(
					'default'           => false,
					'sanitize_callback' => 'absint'
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
	 * @param $limit int | The limit of posts per page
	 * @return Array | The response array
	 **/
	public static function prepare_response( $results, $count, $page, $number_pages, $total_posts, $limit ) {
		$start = (((int)$page - 1) * $limit) + 1;
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
			foreach( $array as $key => $val ) {
				$array[$key] = sanitize_text_field( $val );
			}
		}

		if ( is_string( $array ) ) {
			$array = sanitize_text_field( $array );
		}

		return $array;
	}

	/**
	 * Returns an array of program_types
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $request WP_REST_Request | The request object
	 * @return WP_REST_Response | The response object
	 **/
	public static function get_program_types( $request ) {
		$retval = array();

		$args = array(
			'taxonomy'   => 'program_types',
			'hide_empty' => true
		);

		$terms = get_terms( $args );

		// Sort our program types by our custom order.
		usort( $terms, array( 'UCF_Degree_Search_API', 'custom_program_types_order' ) );

		foreach( $terms as $term ) {
			if ( $term->count === 0 ) { continue; } // Throw out empty program_types.

			$alias = get_term_meta( $term->term_id, 'program_types_alias', true );
			$alias = $alias ? $alias : $term->name;

			$retval[] = array(
				'name'     => $alias,
				'plural'   => $term->name . 's',
				'slug'     => $term->slug,
				'count'    => $term->count
			);
		}

		$retval = array_values( $retval );

		foreach( $retval as $i => $child ) {
			$retval[$i]['children'] = array_values( $retval[$i]['children'] );
		}

		return new WP_REST_Response( $retval, 200 );
	}

	/**
	 * Returns args for the get_program_types callback
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @return array
	 **/
	public static function get_program_types_args() {
		return array();
	}

	/**
	 * Returns the counts of program_types based on search query
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $request WP_REST_Request | The request object.
	 * @return WP_REST_Response | The response object.
	 **/
	public static function get_program_types_counts( $request ) {
		$s = $request['search'];
		$colleges = $request['colleges'];

		$args = array(
			'post_type'      => 'degree',
			'posts_per_page' => -1,
			's'              => $s,
			'fields'         => 'ids'
		);

		if ( $colleges ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'colleges',
					'field'    => 'slug',
					'terms'    => $colleges
				)
			);
		}

		$query = new WP_Query( $args );

		$retval = array();

		$retval['all'] = $query->found_posts;

		foreach( $query->posts as $post ) {
			$terms = wp_get_post_terms( $post, 'program_types' );
			$term = is_array( $terms ) ? $terms[0]->slug : null;

			if ( ! $term ) continue;

			if ( ! isset( $retval[$term] ) ) {
				$retval[$term] = 0;
			}

			$retval[$term]++;
		}

		return new WP_REST_Response( $retval, 200 );
	}

	/**
	 * Returns the `get_program_types_counts` args
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @return Array
	 **/
	public static function get_program_types_counts_args() {
		return array(
			array(
				'search'  => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field'
				),
				'colleges' => array(
					'default'           => false,
					'sanitize_callback' => array( 'UCF_Degree_Search_API', 'sanitize_array' )
				)
			)
		);
	}

	/**
	 * Returns an array of colleges
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $request WP_REST_Request | The request object
	 * @return WP_REST_Response | The response object
	 **/
	public static function get_colleges( $request ) {
		$retval = array();

		$args = array(
			'taxonomy'   => 'colleges',
			'hide_empty' => true
		);

		$terms = get_terms( $args );

		foreach( $terms as $term ) {
			if ( $term->count === 0 ) { continue; } // Throw out empty program_types.

			$alias = get_term_meta( $term->term_id, 'colleges_alias', true );
			$alias = $alias ? $alias : $term->name;

			$retval[] = array(
				'name'     => $alias,
				'fullname' => $term->name,
				'slug'     => $term->slug,
				'count'    => $term->count
			);
		}

		return new WP_REST_Response( $retval, 200 );
	}

	/**
	 * Returns the allowable args for get_colleges
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @return array
	 **/
	public static function get_colleges_args() {
		return array();
	}

	/**
	 * Returns the counts of colleges based on search query
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $request WP_REST_Request | The request object.
	 * @return WP_REST_Response | The response object.
	 **/
	public static function get_colleges_counts( $request ) {
		$s = $request['search'];
		$program_types = $request['program_types'];

		$args = array(
			'post_type'      => 'degree',
			'posts_per_page' => -1,
			's'              => $s,
			'fields'         => 'ids',
		);

		if ( $program_types ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'program_types',
					'field'    => 'slug',
					'terms'    => $program_types
				)
			);
		}

		$query = new WP_Query( $args );

		$retval = array();

		$retval['all'] = $query->found_posts;

		foreach( $query->posts as $post ) {
			$terms = wp_get_post_terms( $post, 'colleges' );
			$term = is_array( $terms ) ? $terms[0]->slug : null;

			if ( ! $term ) continue;

			if ( ! isset( $retval[$term] ) ) {
				$retval[$term] = 0;
			}

			$retval[$term]++;
		}

		return new WP_REST_Response( $retval, 200 );
	}

	/**
	 * Returns the `get_program_types_counts` args
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @return Array
	 **/
	public static function get_colleges_counts_args() {
		return array(
			array(
				'search' => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field'
				),
				'program_types' => array(
					'default'           => false,
					'sanitize_callback' => array( 'UCF_Degree_Search_API', 'sanitize_array' )
				)
			)
		);
	}

	/**
	 * Custom sorter for program_types
	 * @author Jim Barnes
	 * @since 1.0.2
	 * @param $a Object | Element `a` to compare
	 * @param $b Object | Element `b` to compare
	 * @return int | Returns order priority
	 **/
	public static function custom_program_types_order( $a, $b ) {
		$order = UCF_Degree_Search_API::$order;

		foreach( $order as $value ) {
			if ( $a->name === $value ) {
				return 0;
				break;
			}

			if ( $b->name === $value ) {
				return 1;
				break;
			}
		}
	}


}
