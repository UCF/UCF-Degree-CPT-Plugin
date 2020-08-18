<?php
/**
 * Functions that define the degree list classic layout
 **/

if ( ! function_exists( 'ucf_degree_list_display_classic_before' ) ) {
	function ucf_degree_list_display_classic_before( $content, $items, $args, $grouped ) {
		ob_start();
	?>
		<div class="degree-list-wrapper <?php if ( $grouped ) { ?>degree-list-grouped-wrapper<?php } ?>">
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_classic_before', 'ucf_degree_list_display_classic_before', 10, 4 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic_title' ) ) {
	function ucf_degree_list_display_classic_title( $content, $items, $args, $grouped ) {
		$formatted_title = '';

		if ( $title = $args['title'] ) {
			$formatted_title = '<h2 class="ucf-degree-list-title">' . $title . '</h2>';
		}

		return $formatted_title;
	}

	add_filter( 'ucf_degree_list_display_classic_title', 'ucf_degree_list_display_classic_title', 10, 4 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic' ) ) {
	function ucf_degree_list_display_classic( $content, $items, $args, $grouped ) {
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
	function ucf_degree_list_display_classic_grouped( $content, $items, $args, $grouped ) {
		ob_start();
		foreach( $items as $item ) : // For each group
	?>
		<div class="degree-list-group">
			<h3 class="degree-list-heading"><?php echo $item['group_name']; ?></h3>
			<?php echo ucf_degree_list_display_classic( '', $item['posts'], $args, $grouped ); ?>
		</div>
	<?php
		endforeach;
		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_classic_grouped', 'ucf_degree_list_display_classic_grouped', 10, 4 );
}

if ( ! function_exists( 'ucf_degree_list_display_classic_after' ) ) {
	function ucf_degree_list_display_classic_after( $content, $items, $args, $grouped ) {
		ob_start();
	?>
		</div>
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_classic_after', 'ucf_degree_list_display_classic_after', 10, 4 );
}
