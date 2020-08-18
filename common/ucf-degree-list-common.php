<?php
/**
 * Defines hooks for displaying lists of degrees.
 **/
if ( ! class_exists( 'UCF_Degree_List_Common' ) ) {
	class UCF_Degree_List_Common {
		public static function display_degrees( $items, $layout, $args, $grouped=false ) {
			ob_start();

			// Display before
			$layout_before = ucf_degree_list_display_classic_before( '', $items, $args, $grouped );
			if ( has_filter( 'ucf_degree_list_display_' . $layout . '_before' ) ) {
				$layout_before = apply_filters( 'ucf_degree_list_display_' . $layout . '_before', $layout_before, $items, $args, $grouped );
			}
			echo $layout_before;

			// Display title
			$layout_title = ucf_degree_list_display_classic_title( '', $items, $args, $grouped );
			if ( has_filter( 'ucf_degree_list_display_' . $layout . '_title' ) ) {
				$layout_title = apply_filters( 'ucf_degree_list_display_' . $layout . '_title', $layout_title, $items, $args, $grouped );
			}
			echo $layout_title;

			// Display items, grouped or ungrouped
			if ( !$grouped ) {
				$layout_content_ungrouped = ucf_degree_list_display_classic( '', $items, $args, $grouped );
				if ( has_filter( 'ucf_degree_list_display_' . $layout ) ) {
					$layout_content_ungrouped = apply_filters( 'ucf_degree_list_display_' . $layout, $layout_content_ungrouped, $items, $args, $grouped );
				}
				echo $layout_content_ungrouped;
			}
			else {
				$layout_content_grouped = ucf_degree_list_display_classic_grouped( '', $items, $args, $grouped );
				if ( has_filter( 'ucf_degree_list_display_' . $layout . '_grouped' ) ) {
					$layout_content_grouped = apply_filters( 'ucf_degree_list_display_' . $layout . '_grouped', $layout_content_grouped, $items, $args, $grouped );
				}
				echo $layout_content_grouped;
			}

			// Display after
			$layout_after = ucf_degree_list_display_classic_after( '', $items, $args, $grouped );
			if ( has_filter( 'ucf_degree_list_display_' . $layout . '_after' ) ) {
				$layout_after = apply_filters( 'ucf_degree_list_display_' . $layout . '_after', $layout_after, $items, $args, $grouped );
			}
			echo $layout_after;

			return ob_get_clean();
		}
	}
}
