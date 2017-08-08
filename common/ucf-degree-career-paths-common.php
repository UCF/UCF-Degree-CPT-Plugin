<?php
/**
 * Defines hooks for displaying lists of career paths.
 **/
if ( ! class_exists( 'UCF_Degree_Career_Paths_Common' ) ) {
	class UCF_Degree_Career_Paths_Common {
		public function display_career_paths( $items, $layout, $title, $display_type='default' ) {
			ob_start();

			// Display before
			$layout_before = ucf_career_paths_display_classic_before( '', $items, $title, $display_type );
			if ( has_filter( 'ucf_career_paths_display_' . $layout . '_before' ) ) {
				$layout_before = apply_filters( 'ucf_career_paths_display_' . $layout . '_before', $layout_before, $items, $title, $display_type );
			}
			echo $layout_before;

			// Display title
			$layout_title = ucf_career_paths_display_classic_title( '', $items, $title, $display_type );
			if ( has_filter( 'ucf_career_paths_display_' . $layout . '_title' ) ) {
				$layout_title = apply_filters( 'ucf_career_paths_display_' . $layout . '_title', $layout_title, $items, $title, $display_type );
			}
			echo $layout_title;

			// Display items
			$layout_content = ucf_career_paths_display_classic( '', $items, $title, $display_type );
			if ( has_filter( 'ucf_career_paths_display_' . $layout ) ) {
				$layout_content = apply_filters( 'ucf_career_paths_display_' . $layout, $layout_content, $items, $title, $display_type );
			}
			echo $layout_content;

			// Display after
			$layout_after = ucf_career_paths_display_classic_after( '', $items, $title, $display_type );
			if ( has_filter( 'ucf_career_paths_display_' . $layout . '_after' ) ) {
				$layout_after = apply_filters( 'ucf_career_paths_display_' . $layout . '_after', $layout_after, $items, $title, $display_type );
			}
			echo $layout_after;

			return ob_get_clean();
		}
	}
}

if ( ! function_exists( 'ucf_career_paths_display_classic_before' ) ) {
	function ucf_career_paths_display_classic_before( $content, $items, $title, $display_type ) {
		ob_start();
	?>
		<div class="ucf-career-paths-wrapper">
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_career_paths_display_classic_before', 'ucf_career_paths_display_classic_before', 10, 4 );
}

if ( ! function_exists( 'ucf_career_paths_display_classic_title' ) ) {
	function ucf_career_paths_display_classic_title( $content, $items, $title, $display_type ) {
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

	add_filter( 'ucf_career_paths_display_classic_title', 'ucf_career_paths_display_classic_title', 10, 4 );
}

if ( ! function_exists( 'ucf_career_paths_display_classic' ) ) {
	function ucf_career_paths_display_classic( $content, $items, $title, $display_type ) {
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

	add_filter( 'ucf_career_paths_display_classic', 'ucf_career_paths_display_classic', 10, 4 );
}

if ( ! function_exists( 'ucf_career_paths_display_classic_after' ) ) {
	function ucf_career_paths_display_classic_after( $content, $items, $title, $display_type ) {
		ob_start();
	?>
		</div>
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_career_paths_display_classic_after', 'ucf_career_paths_display_classic_after', 10, 4 );
}

?>
