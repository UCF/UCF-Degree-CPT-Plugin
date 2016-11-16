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
			
		}
	}

	if ( ! shortcode_exists( 'career-paths' ) ) {
		add_shortcode( 'career-paths', array( 'UCF_Degree_Career_Paths_List_Shortcode', 'shortcode' ) );
	}
}
