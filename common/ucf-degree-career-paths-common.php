<?php
/**
 * Defines hooks for displaying lists of career paths.
 **/
if ( ! class_exists( 'UCF_Degree_Career_Paths_Common' ) ) {
	class UCF_Degree_Career_Paths_Common {
		public function display_career_paths( $items, $layout, $args ) {
			ob_start();

			// Display before
			$layout_before = ucf_career_paths_display_classic_before( '', $items, $args );
			if ( has_filter( 'ucf_career_paths_display_' . $layout . '_before' ) ) {
				$layout_before = apply_filters( 'ucf_career_paths_display_' . $layout . '_before', $layout_before, $items, $args );
			}
			echo $layout_before;

			// Display title
			$layout_title = ucf_career_paths_display_classic_title( '', $items, $args );
			if ( has_filter( 'ucf_career_paths_display_' . $layout . '_title' ) ) {
				$layout_title = apply_filters( 'ucf_career_paths_display_' . $layout . '_title', $layout_title, $items, $args );
			}
			echo $layout_title;

			// Display items
			$layout_content = ucf_career_paths_display_classic( '', $items, $args );
			if ( has_filter( 'ucf_career_paths_display_' . $layout ) ) {
				$layout_content = apply_filters( 'ucf_career_paths_display_' . $layout, $layout_content, $items, $args );
			}
			echo $layout_content;

			// Display after
			$layout_after = ucf_career_paths_display_classic_after( '', $items, $args );
			if ( has_filter( 'ucf_career_paths_display_' . $layout . '_after' ) ) {
				$layout_after = apply_filters( 'ucf_career_paths_display_' . $layout . '_after', $layout_after, $items, $args );
			}
			echo $layout_after;

			return ob_get_clean();
		}
	}
}

if ( ! function_exists( 'ucf_career_paths_display_classic_before' ) ) {
	function ucf_career_paths_display_classic_before( $content, $items, $args ) {
		ob_start();
	?>
		<div class="ucf-career-paths-wrapper">
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_career_paths_display_classic_before', 'ucf_career_paths_display_classic_before', 10, 3 );
}

if ( ! function_exists( 'ucf_career_paths_display_classic_title' ) ) {
	function ucf_career_paths_display_classic_title( $content, $items, $args ) {
		$formatted_title = '';

		if ( $title = $args['title'] ) {
			$formatted_title = '<h2 class="ucf-career-paths-title">' . $title . '</h2>';
		}

		return $formatted_title;
	}

	add_filter( 'ucf_career_paths_display_classic_title', 'ucf_career_paths_display_classic_title', 10, 3 );
}

if ( ! function_exists( 'ucf_career_paths_display_classic' ) ) {
	function ucf_career_paths_display_classic( $content, $items, $args ) {
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
	function ucf_career_paths_display_classic_after( $content, $items, $args ) {
		ob_start();
	?>
		</div>
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_career_paths_display_classic_after', 'ucf_career_paths_display_classic_after', 10, 3 );
}

?>
