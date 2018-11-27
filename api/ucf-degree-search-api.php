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

		register_rest_route( "{$root}/{$version}", "/interests", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_interests' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions' ),
				'args'                => array( 'UCF_Degree_Search_API', 'get_interests_args' )
			)
		) );

		register_rest_route( "{$root}/{$version}", "/tags", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'UCF_Degree_Search_API', 'get_tags' ),
				'permission_callback' => array( 'UCF_Degree_Search_API', 'get_permissions' ),
				'args'                => array( 'UCF_Degree_Search_API', 'get_tags_args' )
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
		$interests = $request['interests'];
		$tags = $request['post_tag'];
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

		if ( $tags ) {
			if ( ! isset( $args['tax_query'] ) ) {
				$args['tax_query'] = array();
			}

			$args['tax_query'][] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'slug',
				'terms'    => $tags
			);
		}

		$query = new WP_Query( $args );
		$number_pages = $query->max_num_pages;
		$total_posts = $query->found_posts;

		$posts = $query->posts;

		foreach( $posts as $post ) {
			$program_types = wp_get_post_terms( $post->ID, 'program_types' );
			$program_types = ( is_array( $program_types ) ) ? $program_types : null;

			$program_type = null;

			if ( count( $program_types ) > 1 ) {
				foreach( $program_types as $pt ) {
					if ( $pt->parent !== 0 ) {
						$program_type = $pt;
						break;
					}
				}
			} else if ( count( $program_types ) > 0 ) {
				$program_type = $program_types[0];
			}

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
		$name_short = get_post_meta( $post->ID, 'degree_name_short', true );
		$hours = get_post_meta( $post->ID, 'degree_hours', true );
		$terms = wp_get_post_terms( $post->ID, 'program_types' );
		$term = is_array( $terms ) ? $terms[0]->slug : null;
		$colleges = self::get_college_terms( $post, $request );

		$retval = array(
			'title'     => $post->post_title,
			'nameShort' => $name_short,
			'url'       => $permalink,
			'hours'     => $hours,
			'type'      => $term,
			'colleges'  => $colleges,
			'subplans'  => array()
		);

		return $retval;
	}

	/**
	 * Retrieves a simplified array of college terms
	 *
	 * @param WP_Post $post The post object
	 * @param WP_REST_Request $request The request object
	 * @return array The array of colleges.
	 */
	public static function get_college_terms( $post, $request ) {
		$colleges = wp_get_post_terms( $post->ID, 'colleges' );

		if ( is_wp_error( $colleges ) ) {
			return array();
		}

		$retval = array();

		foreach( $colleges as $college ) {
			$retval[] = array(
				'name' => $college->name,
				'slug' => $college->slug
			);
		}

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

		foreach( $terms as $term ) {
			if ( isset( $retval[$term->term_id] ) ) continue;

			if ( $term->count === 0 ) { continue; } // Throw out empty program_types.

			$alias = get_term_meta( $term->term_id, 'program_types_alias', true );
			$alias = $alias ? $alias : $term->name;

			if ( $term->parent === 0 ) {
				$retval[$term->term_id] = array(
					'name'     => $alias,
					'plural'   => $term->name . 's',
					'slug'     => $term->slug,
					'count'    => $term->count,
					'children' => array()
				);
			} else {
				if ( ! isset( $retval[$term->parent] ) ) {
					$parent = get_term( $term->parent );

					$parent_alias = get_term_meta( $parent->term_id, 'program_types_alias', true );
					$parent_alias = $parent_alias ? $parent_alias : $parent->name;

					$retval[$parent->term_id] = array(
						'name'     => $parent_alias,
						'plural'   => $parent->name . 's',
						'slug'     => $parent->slug,
						'count'    => $parent->count,
						'children' => array(
							$term->term_id => array(
								'name'     => $alias,
								'plural'   => $term->name . 's',
								'slug'     => $term->slug,
								'count'    => $term->count,
								'children' => array()
							)
						)
					);
				} else {
					$retval[$term->parent]['children'][$term->term_id] = array(
						'name'     => $alias,
						'plural'   => $term->name . 's',
						'slug'     => $term->slug,
						'count'    => $term->count,
						'children' => array()
					);
				}
			}
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
	 * Callback for the /interests endpoint
	 * @author Jim Barnes
	 * @since 3.1.0
	 * @param WP_REST_Request $request | Contains get parameters
	 * @return WP_REST_Response
	 */
	public static function get_interests( $request ) {
		$search        = $request['search'];
		$program_types = $request['program_types'];
		$colleges      = $request['colleges'];
		$limit         = $request['limit'];

		$terms         = array();

		/**
		 * The `$search` parameter cannot be used in
		 * conjunction with the `program_types` or `colleges`
		 * arguments. If one of those two are provided
		 * the search parameter will be disregarded.
		 */
		if ( ! empty( $program_types ) || ! empty( $colleges ) ) {
			$args = array(
				'post_type'      => 'degree',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array()
			);

			if ( ! empty( $program_types ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'program_types',
					'field'    => 'slug',
					'terms'    => explode( ',', $program_types )
				);
			}

			if ( ! empty( $colleges ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'colleges',
					'field'    => 'slug',
					'terms'    => explode( ',', $colleges )
				);
			}

			if ( count( $args['tax_query'] ) > 1 ) {
				$args['tax_query']['relationship'] = 'AND';
			}

			$post_ids = get_posts( $args );

			$terms = wp_get_object_terms( $post_ids, 'interests' );
		} else {
			$args = array(
				'taxonomy' => 'interests'
			);

			if ( $search ) {
				$args['name__like'] = $search;
			}

			$terms = get_terms( $args );
		}

		$retval = self::prepare_interests_for_response( $terms );

		return new WP_REST_Response( $retval, 200 );
	}

	/**
	 * Prepares a term array for JSON response
	 * @author Jim Barnes
	 * @since 3.1.0
	 * @param array $terms | The term array
	 * @param array The response array
	 */
	private static function prepare_interests_for_response( $terms ) {
		$retval = array();

		foreach( $terms as $term ) {
			$interest = array(
				'id'           => abs( $term->term_id ),
				'name'         => $term->name,
				'display_text' => get_term_meta( $term->term_id, 'interests_display_text', true ),
				'slug'         => $term->slug
			);

			$retval[] = $interest;
		}

		return $retval;
	}

	/**
	 * Defines the args for the /interests endpoint
	 * @author Jim Barnes
	 * @since 3.1.0
	 * @return array
	 */
	public static function get_interests_args() {
		return array(
			array(
				'search' => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field'
				),
				'program_types' => array(
					'default'           => false,
					'sanitize_callback' => array( 'UCF_Degree_Search_API', 'sanitize_array' )
				),
				'colleges'      => array(
					'default'           => false,
					'sanitize_callback' => array( 'UCF_Degree_Search_API', 'sanitize_array' )
				)
			)
		);
	}

	/**
	 * Callback for the /tags endpoint
	 * @author Jo Dickson
	 * @since 3.1.0
	 * @param WP_REST_Request $request | Contains get parameters
	 * @return WP_REST_Response
	 */
	public static function get_tags( $request ) {
		$search        = $request['search'];
		$program_types = $request['program_types'];
		$colleges      = $request['colleges'];
		$limit         = $request['limit'];

		$terms         = array();

		/**
		 * The `$search` parameter cannot be used in
		 * conjunction with the `program_types` or `colleges`
		 * arguments. If one of those two are provided
		 * the search parameter will be disregarded.
		 */
		if ( ! empty( $program_types ) || ! empty( $colleges ) ) {
			$args = array(
				'post_type'      => 'degree',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array()
			);

			if ( ! empty( $program_types ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'program_types',
					'field'    => 'slug',
					'terms'    => explode( ',', $program_types )
				);
			}

			if ( ! empty( $colleges ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'colleges',
					'field'    => 'slug',
					'terms'    => explode( ',', $colleges )
				);
			}

			if ( count( $args['tax_query'] ) > 1 ) {
				$args['tax_query']['relationship'] = 'AND';
			}

			$post_ids = get_posts( $args );

			$terms = wp_get_object_terms( $post_ids, 'post_tag' );
		} else {
			$args = array(
				'taxonomy' => 'post_tag'
			);

			if ( $search ) {
				$args['name__like'] = $search;
			}

			$terms = get_terms( $args );
		}

		$retval = self::prepare_tags_for_response( $terms );

		return new WP_REST_Response( $retval, 200 );
	}

	/**
	 * Prepares a tag term array for JSON response
	 * @author Jo Dickson
	 * @since 3.1.0
	 * @param array $terms | The term array
	 * @param array The response array
	 */
	private static function prepare_tags_for_response( $terms ) {
		$retval = array();

		foreach( $terms as $term ) {
			$tag = array(
				'id'   => abs( $term->term_id ),
				'name' => $term->name,
				'slug' => $term->slug
			);

			$retval[] = $tag;
		}

		return $retval;
	}

	/**
	 * Defines the args for the /tags endpoint
	 * @author Jo Dickson
	 * @since 3.1.0
	 * @return array
	 */
	public static function get_tags_args() {
		return get_interests_args();
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
