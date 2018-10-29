<?php
/**
 * Utility functions
 **/
if ( ! function_exists( 'ucf_degree_append_meta' ) ) {
	/**
	 * Appends the meta data of the degree to the $post object.
	 * @author Jim Barnes
	 * @since 0.0.1
	 * @param $post WP_POST | The WP_POST object
	 * @param WP_POST | The WP_POST object with the additional `meta` array attached as a field.
	 **/
	function ucf_degree_append_meta( $post ) {
		// Post meta
		$meta = get_post_meta( $post->ID );
		$post->meta = ucf_degree_reduce_meta_values( $meta );

		// Taxonomies + terms
		$taxonomies = UCF_Degree_PostType::taxonomies();
		$terms_by_tax = array_fill_keys( $taxonomies, array() );

		foreach ( $taxonomies as $tax ) {
			$terms = wp_get_post_terms( $post->ID, $tax );
			if ( !is_wp_error( $terms ) ) {
				$terms_by_tax[$tax] = $terms;
			}
		}

		$post->taxonomies = $terms_by_tax;

		return apply_filters( 'ucf_degree_append_meta', $post );
	}
}

if ( ! function_exists( 'ucf_degree_group_by_tax_term' ) ) {
	function ucf_degree_group_posts_by_tax( $taxonomy_slug, $posts ) {
		$retval = array();

		foreach( $posts as $post ) {
			$post_terms = wp_get_post_terms( $post->ID, $taxonomy_slug );

			foreach( $post_terms as $term ) {
				if ( ! isset( $retval[$term->term_id] ) || ! is_array( $retval[$term->term_id] ) ) {
					$retval[$term->term_id] = array(
						'term'  => array(
							'name'  => $term->name,
							'meta' => ucf_degree_reduce_meta_values( get_term_meta( $term->term_id ) ),
						),
						'posts' => array()
					);
				}

				$retval[$term->term_id]['posts'][] = $post;
			}
		}

		return $retval;
	}
}

if ( ! function_exists( 'ucf_degree_reduce_meta_values' ) ) {
	/**
	 * Converts all single index arrays to values
	 * @author Jim Barnes
	 * @since 0.0.1
	 * @param $meta_array array | Array of meta values
	 * @return array
	 **/
	function ucf_degree_reduce_meta_values( $meta_array ) {
		$retval = $meta_array;

		foreach( $meta_array as $key=>$value ) {
			if ( is_array( $value ) && count( $value ) === 1 ) {
				$retval[$key] = $value[0];
			} else {
				$retval[$key] = $value;
			}
		}

		return $retval;
	}
}

if ( ! function_exists( 'ucf_degree_search_join_filter' ) ) {
	/**
	* Joins term and meta tables to the default query when the degree_search query param is preesnt.
	* @author Jim Barnes
	* @since 0.0.1
	* @param $join string | The join string to be modified
	* @param $wp_query WP_Query passed by reference
	**/
	function ucf_degree_search_join_filter( $join, &$wp_query ) {
		global $wpdb;

		if ( isset( $wp_query->query['degree_search'] ) && $wp_query->query_vars['post_type'] === 'degree' ) {
			$join .= " LEFT JOIN $wpdb->term_relationships as wtr ON ($wpdb->posts.ID = wtr.object_id)";
			$join .= " LEFT JOIN $wpdb->term_taxonomy as wtt ON (wtr.term_taxonomy_id = wtt.term_taxonomy_id)";
			$join .= " LEFT JOIN $wpdb->terms as wt ON (wtt.term_id = wt.term_id)";
			$join .= " left join $wpdb->postmeta as wpm ON ($wpdb->posts.ID = wpm.post_id)";
		}

		return $join;
	}

	add_filter( 'posts_join', 'ucf_degree_search_join_filter', 10, 2 );
}

if ( ! function_exists( 'ucf_degree_search_where_filter' ) ) {
	/**
	 * Modifies the where clause on the default query to search postmeta, terms and post_title like the degree_search keyword.
	 * @author Jim Barnes
	 * @since 0.0.1
	 * @param $where string | The where string to be modified
	 * @param $wp_query WP_Query passed by reference
	 **/
	function ucf_degree_search_where_filter( $where, &$wp_query ) {
		global $wpdb;

		if ( isset( $wp_query->query['degree_search'] ) && $wp_query->query_vars['post_type'] === 'degree' ) {
			$s = $wp_query->query['degree_search'];
			$where .= " AND (";
			$where .= $wpdb->prepare( " lower($wpdb->posts.post_title) LIKE %s OR", '%' . $s . '%' );
			$where .= $wpdb->prepare( " lower(wt.name) LIKE %s OR", '%' . $s . '%' );
			$where .= $wpdb->prepare( " lower(wpm.meta_value) LIKE %s)", '%'. $s . '%' );
		}

		return $where;
	}

	add_filter( 'posts_where', 'ucf_degree_search_where_filter', 10, 2 );
}

if ( ! function_exists( 'ucf_degree_search_groupby_filter' ) ) {
	/**
	 * Modifies the groupby clause to group by posts.ID because of the left joins above.
	 * @author Jim Barnes
	 * @since 0.0.1
	 * @param $groupby string | The groupby string to be modified
	 * @param $wp_query WP_Query passed by reference
	 **/
	function ucf_degree_search_groupby_filter( $groupby, &$wp_query ) {
		global $wpdb;

		if ( isset( $wp_query->query['degree_search'] ) && $wp_query->query_vars['post_type'] === 'degree' ) {
			$groupby = "$wpdb->posts.ID";
		}

		return $groupby;
	}

	add_filter( 'posts_groupby', 'ucf_degree_search_groupby_filter', 10, 2 );
}

if ( ! function_exists( 'ucf_degree_valid_query_vars' ) ) {
	/**
	 * Adds 'degree_search' to the array of allowed rest query variables
	 * @author Jim Barnes
	 * @since 0.0.1
	 * @param $valid_vars array<string> The current array of valid query vars
	 **/
	function ucf_degree_valid_query_vars( $valid_vars ) {
		$valid_vars[] = 'degree_search';
		return $valid_vars;
	}

	add_filter( 'rest_query_vars', 'ucf_degree_valid_query_vars', 10, 2 );
}

if ( ! function_exists( 'ucf_degree_add_query_args' ) ) {
	/**
	 * Adds the 'degree_search' variable to the args if it is defined
	 * @author Jim Barnes
	 * @since 0.0.1
	 * @param $args array The args for the request
	 * @param $request The http request object
	 **/
	function ucf_degree_add_query_args( $args, $request ) {
		if ( isset( $request['degree_search'] ) ) {
			$args['degree_search'] = $request['degree_search'];
		}

		return $args;
	}

	add_filter( 'rest_degree_query', 'ucf_degree_add_query_args', 10, 2 );

	/**
	 * Capitalizes a title, excluding specific articles
	 * @author Jim Barnes
	 * @since 3.1.0
	 * @param string $title The title to capitalize
	 * @return string
	 */
	function ucf_degree_capitalize_title( $title ) {
		$title_array = explode( ' ', $title );

		$small_words = array( 'of','a','the','and','an','or','nor','but','is','if','then','else','when', 'at','from','by','on','off','for','in','out','over','to','into','with' );

		$retval = array();

		foreach( $title_array as $title_word ) {
			if ( ! in_array( $title_word, $small_words ) ) {
				$retval[] = ucwords( $title_word );
			} else {
				$retval[] = $title_word;
			}
		}

		return implode( ' ', $retval );
	}
}

?>
