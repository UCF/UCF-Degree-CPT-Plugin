<?php
/**
 * Defines hooks for displaying lists of degrees.
 **/
if ( ! class_exists( 'UCF_Degree_List_Common' ) ) {
	class UCF_Degree_List_Common {
		public function display_degrees( $items, $layout, $title, $display_type='default', $grouped=false, $groupby_field=null ) {
			ob_start();

			// Display before
			$layout_before = ucf_degree_list_display_classic_before( '', $items, $title, $display_type, $grouped );
			if ( has_filter( 'ucf_degree_list_display_' . $layout . '_before' ) ) {
				$layout_before = apply_filters( 'ucf_degree_list_display_' . $layout . '_before', $layout_before, $items, $title, $display_type, $grouped );
			}
			echo $layout_before;

			// Display title
			$layout_title = ucf_degree_list_display_classic_title( '', $items, $title, $display_type );
			if ( has_filter( 'ucf_degree_list_display_' . $layout . '_title' ) ) {
				$layout_title = apply_filters( 'ucf_degree_list_display_' . $layout . '_title', $layout_title, $items, $title, $display_type );
			}
			echo $layout_title;

			// Display items, grouped or ungrouped
			if ( !$grouped ) {
				$layout_content_ungrouped = ucf_degree_list_display_classic( '', $items, $title, $display_type );
				if ( has_filter( 'ucf_degree_list_display_' . $layout ) ) {
					$layout_content_ungrouped = apply_filters( 'ucf_degree_list_display_' . $layout, $layout_content_ungrouped, $items, $title, $display_type );
				}
				echo $layout_content_ungrouped;
			}
			else {
				$layout_content_grouped = ucf_degree_list_display_classic_grouped( '', $items, $title, $display_type, $groupby_field );
				if ( has_filter( 'ucf_degree_list_display_' . $layout . '_grouped' ) && $grouped ) {
					$layout_content_grouped = apply_filters( 'ucf_degree_list_display_' . $layout . '_grouped', $layout_content_grouped, $items, $title, $display_type, $groupby_field );
				}
				echo $layout_content_grouped;
			}

			// Display after
			$layout_after = ucf_degree_list_display_classic_after( '', $items, $title, $display_type );
			if ( has_filter( 'ucf_degree_list_display_' . $layout . '_after' ) ) {
				$layout_after = apply_filters( 'ucf_degree_list_display_' . $layout . '_after', $layout_after, $items, $title, $display_type );
			}
			echo $layout_after;

			return ob_get_clean();
		}
	}
}

if ( ! function_exists( 'ucf_degree_list_display_classic_before' ) ) {
	function ucf_degree_list_display_classic_before( $content, $items, $title, $display_type, $grouped ) {
		ob_start();
	?>
		<div class="degree-list-wrapper <?php if ( $grouped ) { ?>degree-list-grouped-wrapper<?php } ?>">
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_classic_before', 'ucf_degree_list_display_classic_before', 10, 5 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic_title' ) ) {
	function ucf_degree_list_display_classic_title( $content, $items, $title, $display_type ) {
		$formatted_title = $title;

		switch( $display_type ) {
			case 'widget':
				break;
			case 'default':
			default:
				if ( $title ) {
					$formatted_title = '<h2 class="ucf-degree-list-title">' . $title . '</h2>';
				}
				break;
		}

		return $formatted_title;
	}

	add_filter( 'ucf_degree_list_display_classic_title', 'ucf_degree_list_display_classic_title', 10, 4 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic' ) ) {
	function ucf_degree_list_display_classic( $content, $items, $title, $display_type ) {
		ob_start();
	?>
		<ul class="degree-list">
	<?php foreach( $items as $item ) : ?>
			<li class="degree-list-program"><a href="<?php echo get_permalink( $item->ID ); ?>"><?php echo $item->post_title; ?></a></li>
	<?php endforeach; ?>
		</ul>
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_classic', 'ucf_degree_list_display_classic', 10, 4 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic_grouped' ) ) {
	function ucf_degree_list_display_classic_grouped( $content, $items, $title, $display_type, $groupby_field ) {
		ob_start();
		foreach( $items as $item ) : // For each group
	?>
		<div class="degree-list-group">
			<h3 class="degree-list-heading"><?php echo $item['group_name']; ?></h3>
			<?php echo ucf_degree_list_display_classic( '', $item['posts'], $title, $display_type ); ?>
		</div>
	<?php
		endforeach;
		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_classic_grouped', 'ucf_degree_list_display_classic_grouped', 10, 5 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic_after' ) ) {
	function ucf_degree_list_display_classic_after( $content, $items, $title, $display_type ) {
		ob_start();
	?>
		</div>
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_classic_after', 'ucf_degree_list_display_classic_after', 10, 4 );
}
