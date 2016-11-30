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
				'title'         => 'Degrees',
				'groupby'       => null,
				'groupby_field' => null,
				'filter_by_tax' => null,
				'terms'         => null
			), $atts );

			$args = array(
				'post_type'      => 'degree',
				'posts_per_page' => -1,
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

			$posts = get_posts( $args );

			if ( $atts['groupby'] ) {
				$posts = ucf_degree_group_posts_by_tax( $atts['groupby'], $posts );
			}

			$grouped = ! empty( $atts['groupby'] ) ? true : false;

			ob_start();
			echo UCF_Degree_List_Common::display_degrees( $posts, 'classic', $atts['title'], 'default', $grouped, $atts['groupby_field'] );
			return ob_get_clean();
		}
	}

	if ( ! shortcode_exists( 'degree-list' ) ) {
		add_shortcode( 'degree-list', array( 'UCF_Degree_List_Shortcode', 'shortcode' ) );
	}
}

?>
