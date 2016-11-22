<?php
/**
 * Defines hooks for displaying lists of career paths.
 **/
if ( ! class_exists( 'UCF_Degree_Career_Paths_Common' ) ) {
	class UCF_Degree_Career_Paths_Common {
		public function display_career_paths( $items, $layout, $title, $display_type='default' ) {
			ob_start();

			// Display before
			if ( has_action( 'ucf_career_paths_display_' . $layout . '_before' ) ) {
				echo apply_filters( 'ucf_career_paths_display_' . $layout . '_before', $items, $title, $display_type );
			}

			// Display title
			if ( has_action( 'ucf_career_paths_display_' . $layout . '_title' ) ) {
				echo apply_filters( 'ucf_career_paths_display_' . $layout . '_title', $items, $title, $display_type );
			}

			// Display items
			if ( has_action( 'ucf_career_paths_display_' . $layout ) ) {
				echo apply_filters( 'ucf_career_paths_display_' . $layout, $items, $title, $display_type );
			}

			// Display after
			if ( has_action( 'ucf_career_paths_display_' . $layout . '_after' ) ) {
				echo apply_filters( 'ucf_career_paths_display_' . $layout . '_after', $items, $title, $display_type );
			}

			return ob_get_clean();
		}
	}
}

if ( ! function_exists( 'ucf_career_paths_display_classic_before' ) ) {
	function ucf_career_paths_display_classic_before( $items, $title, $display_type ) {
		ob_start();
	?>
		<div class="ucf-career-paths-wrapper">
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_career_paths_display_classic_before', 'ucf_career_paths_display_classic_before', 10, 3 );
}

if ( ! function_exists( 'ucf_career_paths_display_classic_title' ) ) {
	function ucf_career_paths_display_classic_title( $items, $title, $display_type ) {
		$formatted_title = $title;

		switch( $display_type ) {
			case 'widget':
				break;
			case 'default':
			default:
				$formatted_title = '<h2 class="ucf-career-paths-title">' . $title . '</h2>';
				break;
		}

		return $formatted_title;
	}

	add_filter( 'ucf_career_paths_display_classic_title', 'ucf_career_paths_display_classic_title', 10, 3 );
}

if ( ! function_exists( 'ucf_career_paths_display_classic' ) ) {
	function ucf_career_paths_display_classic( $items, $title, $display_type ) {
		ob_start();
	?>
		<ul class="ucf-career-paths-list">
	<?php foreach( $items as $item ) : ?>
			<li class="ucf-career-path-item"><?php echo $item->name; ?></li>
	<?php endforeach; ?>
		</ul>
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_career_paths_display_classic', 'ucf_career_paths_display_classic', 10, 3 );
}

if ( ! function_exists( 'ucf_career_paths_display_classic_after' ) ) {
	function ucf_career_paths_display_classic_after( $items, $title, $display_type ) {
		ob_start();
	?>
		</div>
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_career_paths_display_classic_after', 'ucf_career_paths_display_classic_after', 10, 3 );
}

?>
