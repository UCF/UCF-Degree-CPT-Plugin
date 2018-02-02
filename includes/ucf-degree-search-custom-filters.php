<?php
/**
 * Provides custom WP_Query filters
 * for the degree search api
 **/
if ( ! class_exists( 'UCF_Degree_Search_Custom_Filters' ) ) {
	class UCF_Degree_Search_Custom_Filters {

		/**
		 * Processes order_by_taxonomy and additional options.
		 * @author Jim Barnes
		 * @since 1.0.2
		 * @param $orderby string | The current orderby clause
		 * @param $wp_query WP_Query | Global WP_Query reference
		 * @return string | The modified order_by clause.
		 **/
		public static function order_by_tax_orderby( $orderby, &$wp_query ) {
			global $wpdb;

			if ( UCF_Degree_Search_Custom_Filters::valid_taxonomy( $wp_query->get( 'order_by_taxonomy' ) ) ) {
				$taxonomy = $wp_query->get( 'order_by_taxonomy' );
				$field = $wp_query->get( 'order_by_taxonomy_field' );

				$defined_fields = array(
					'term_id',
					'name',
					'slug'
				);

				if ( ! in_array( $field, $defined_fields ) ) {
					$field = 'term_id';
				}

				// Subselect, so we have the taxonomy.field to order by
				$subselect = "
				(SELECT
					$wpdb->terms.{$field}
				FROM
					$wpdb->terms
				LEFT JOIN
					$wpdb->term_taxonomy
				ON
					$wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
				LEFT JOIN
					$wpdb->term_relationships
				ON
					$wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id
				WHERE
					$wpdb->term_taxonomy.taxonomy = '{$taxonomy}'
				AND
					$wpdb->term_relationships.object_id = $wpdb->posts.ID
				LIMIT
					0,1)";

				// Remove whitespace, tabs and new lines
				$subselect = preg_replace( '/\s+/', ' ', $subselect );

				$order_array = $wp_query->get( 'order_by_taxonomy_order' );

				// If there is a specific order, create CASE statement
				if ( $order_array ) {
					$retval = "CASE $subselect";

					$idx = 1;
					foreach( $order_array as $value ) {
						$retval .= " WHEN '{$value}' THEN {$idx}";
						$idx++;
					}
					$retval .= " ELSE {$idx}";
					$retval .= " END";
				} else {
					$retval = "$subselect";
				}

				$retval .= ", {$orderby}";

				return $retval;
			}

			return $orderby;
		}

		/**
		 * Determines if the taxonomy is valid
		 * @author Jim Barnes
		 * @since 1.0.2
		 * @param $taxonomy string | The taxonomy slug
		 * @return bool
		 **/
		public static function valid_taxonomy( $taxonomy ) {
			if ( $taxonomy === null ) { return false; }

			return taxonomy_exists( $taxonomy );
		}

		/**
		 * Custom where filter for filtering by career_paths
		 * @author Jim Barnes
		 * @since 2.0.2
		 * @param array $prepared_args The prepared query args
		 * @param WP_REST_Request $request The Rest request object
		 * @return array The modified prepared arguments
		 */
		public static function filter_by_career_paths( $prepared_args, $request) {
			if ( isset( $request['career_paths'] ) ) {
				$tax_query = array(
					'taxonomy' => 'career_paths',
					'field'    => 'name',
					'terms'    => esc_sql( $request['career_paths'] )
				);

				if ( ! isset( $prepared_args['tax_query'] ) ) {
					$prepared_args['tax_query'] = array( $tax_query );
				} else {
					$prepared_args['tax_query'][] = $tax_query;
				}
			}

			return $prepared_args;
		}
	}
}
