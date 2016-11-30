<?php
/**
 * Defines hooks for displaying lists of degrees.
 **/
if ( ! class_exists( 'UCF_Degree_List_Common' ) ) {
	class UCF_Degree_List_Common {
		public function display_degrees( $items, $layout, $title, $display_type='default', $grouped=false, $groupby_field=null ) {
			ob_start();

			// Display before
			if ( has_action( 'ucf_degree_list_display_' . $layout . '_before' ) ) {
				do_action( 'ucf_degree_list_display_' . $layout . '_before', $items, $title, $display_type );
			}

			// Display title
			if ( has_action( 'ucf_degree_list_display_' . $layout . '_title' ) ) {
				do_action( 'ucf_degree_list_display_' . $layout . '_title', $items, $title, $display_type );
			}

			// Display items ungrouped
			if ( has_action( 'ucf_degree_list_display_' . $layout ) && ! $grouped ) {
				do_action( 'ucf_degree_list_display_' . $layout, $items, $title, $display_type );
			}

			// Display items grouped
			if ( has_action( 'ucf_degree_list_display_' . $layout . '_grouped' ) && $grouped ) {
				do_action( 'ucf_degree_list_display_' . $layout . '_grouped', $items, $title, $display_type, $groupby_field );
			}

			// Display after
			if ( has_action( 'ucf_degree_list_display_' . $layout . '_after' ) ) {
				do_action( 'ucf_degree_list_display_' . $layout . '_after', $items, $title, $display_type );
			}

			return ob_get_clean();
		}
	}
}

if ( ! function_exists( 'ucf_degree_list_display_classic_before' ) ) {
	function ucf_degree_list_display_classic_before( $items, $title, $display_type ) {
		ob_start();
	?>
		<div class="degree-list-wrapper">
	<?php
		echo ob_get_clean();
	}

	add_action( 'ucf_degree_list_display_classic_before', 'ucf_degree_list_display_classic_before', 10, 3 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic_title' ) ) {
	function ucf_degree_list_display_classic_title( $items, $title, $display_type ) {
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

		echo $formatted_title;
	}

	add_action( 'ucf_degree_list_display_classic_title', 'ucf_degree_list_display_classic_title', 10, 3 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic' ) ) {
	function ucf_degree_list_display_classic( $items, $title, $display_type ) {
		ob_start();
	?>
		<ul class="degree-list">
	<?php foreach( $items as $item ) : ?>
			<li class="degree-list-program"><a href="<?php echo get_permalink( $item->ID ); ?>"><?php echo $item->post_title; ?></a></li>
	<?php endforeach; ?>
		</ul>
	<?php
		echo ob_get_clean();
	}

	add_action( 'ucf_degree_list_display_classic', 'ucf_degree_list_display_classic', 10, 3 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic_grouped' ) ) {
	function ucf_degree_list_display_classic_grouped( $items, $title, $display_type, $groupby_field ) {
		ob_start();
		foreach( $items as $item ) : // For each group
			$heading = ( ! empty( $groupby_field ) && isset( $item['term']['meta'][$groupby_field] ) )
				? $item['term']['meta'][$groupby_field]
				: $item['term']['name'];
	?>
		<div class="degree-list-group">
			<h3 class="degree-list-heading"><?php echo $heading; ?></h3>
			<?php echo ucf_degree_list_display_classic( $item['posts'], $title, $display_type ); ?>
		</div>
	<?php
		endforeach;
		echo ob_get_clean();
	}

	add_action( 'ucf_degree_list_display_classic_grouped', 'ucf_degree_list_display_classic_grouped', 10, 4 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic_after' ) ) {
	function ucf_degree_list_display_classic_after( $items, $title, $display_type ) {
		ob_start();
	?>
		</div>
	<?php
		echo ob_get_clean();
	}

	add_action( 'ucf_degree_list_display_classic_after', 'ucf_degree_list_display_classic_after', 10, 3 );
}
