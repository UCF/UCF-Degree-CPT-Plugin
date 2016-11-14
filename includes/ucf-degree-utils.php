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
		$meta = get_post_meta( $post->ID );
		$post->meta = ucf_degree_reduce_meta_values( $meta );
		return apply_filters( 'ucf_degree_append_meta', $post );
	}
}

if ( ! function_exists( 'ucf_degree_group_by_tax_term' ) ) {
	function ucf_degree_group_posts_by_tax( $taxonomy_slug, $posts, $order='ASC' ) {
		$retval = array();

		foreach( $posts as $post ) {
			$post_terms = wp_get_post_terms( $post->ID, $taxonomy_slug );
			
			foreach( $post_terms as $term ) {
				if ( ! is_array( $retval[$term->term_id] ) ) {
					$retval[$term->term_id] = array(
						'term'  => array(
							'name'  => $term->name,
							'meta' => ucf_degree_reduce_meta_values( get_term_meta( $term->term_id, null ) ),
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
			if ( is_array( $value ) && count( $value) === 1 ) {
				$retval[$key] = $value[0];
			} else {
				$retval[$key] = $value;
			}
		}

		return $retval;
	}
}

?>
