<?php
/**
 * Defines the degree-list shortcode
 **/
if ( ! class_exists( 'UCF_Degree_List_Shortcode' ) ) {
	class UCF_Degree_List_Shortcode {
		/**
		* Displays a list of degrees
		* @author Jim Barnes
		* @since 0.0.1
		* @param $atts array | An array of attributes
		* @return string | The html output of the shortcode.
		**/
		public static function shortcode( $atts ) {
			$atts = shortcode_atts( array(
				'layout'        => 'classic',
				'title'         => 'Degrees',
				'groupby'       => null,
				'groupby_field' => null,
				'filter_by_tax' => null,
				'terms'         => null
			), $atts, 'degree-list' );

			$args = array(
				'post_type'      => 'degree',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC'
			);

			if ( $atts['filter_by_tax'] && $atts['terms'] ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => $atts['filter_by_tax'],
						'field'    => 'slug',
						'terms'    => explode( ',', $atts['terms'] )
					)
				);
			}

			$items = get_posts( $args );

			$grouped = ! empty( $atts['groupby'] ) ? true : false;

			if ( $grouped ) {
				$items = ucf_degree_group_posts_by_tax( $atts['groupby'], $items );

				foreach ( $items as $key => $item ) {
					$items[$key]['group_name'] = ( ! empty( $atts['groupby_field'] ) && isset( $item['term']['meta'][$atts['groupby_field']] ) )
						? $item['term']['meta'][$atts['groupby_field']]
						: $item['term']['name'];
				}

				usort( $items, array( 'UCF_Degree_List_Shortcode', 'sort_grouped_degrees' ) );
				$items = apply_filters( 'ucf_degree_list_sort_grouped_degrees', $items );
			}

			ob_start();
			echo UCF_Degree_List_Common::display_degrees( $items, $atts['layout'], $atts, $grouped );
			return ob_get_clean();
		}

		public static function sort_grouped_degrees( $a, $b ) {
			return strcmp( $a['group_name'], $b['group_name'] );
		}
	}

	if ( ! shortcode_exists( 'degree-list' ) ) {
		add_shortcode( 'degree-list', array( 'UCF_Degree_List_Shortcode', 'shortcode' ) );
	}
}

?>
