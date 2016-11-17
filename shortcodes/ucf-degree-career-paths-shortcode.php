<?php
/**
 * Defined the career paths list shortcode
 **/
if ( ! class_exists( 'UCF_Degree_Career_Paths_List_Shortcode' ) ) {
	class UCF_Degree_Career_Paths_List_Shortcode {
		/**
			* Displays a list of career paths
			* @author Jim Barnes
			* @since 0.0.1
			* @param $atts array | An array of attributes
			* @return string | The html output of the shortcode.
			**/ 
		public static function shortcode( $atts, $content='' ) {
			$atts = shortcode_atts( array (
				'post_type' => 'degree',
				'post_slug' => null,
				'layout'    => 'classic',
				'title'     => null
			), $atts);

			$_post = null;

			if ( $atts['post_slug'] ) {
				$args = array(
					'name'        => $atts['post_slug'],
					'post_type'   => $atts['post_type'],
					'numberposts' => 1
				);

				$posts = get_posts( $args );

				$_post = is_array( $posts ) ? $posts[0] : null;
			}

			if ( ! $_post ) {
				global $post;
				$_post = $post;
			}

			if ( $_post ) {
				$items = wp_get_post_terms( $_post->ID, 'career_paths' );
				return UCF_Degree_Career_Paths_Common::display_career_paths( $items, $atts['layout'], $atts['title'] );
			}
		}
	}

	if ( ! shortcode_exists( 'career-paths' ) ) {
		add_shortcode( 'career-paths', array( 'UCF_Degree_Career_Paths_List_Shortcode', 'shortcode' ) );
	}
}
