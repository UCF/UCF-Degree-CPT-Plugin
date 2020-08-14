<?php
/**
 * Defines a twocol layout for the [ucf-degree-list] shortcode
 */

if ( ! function_exists( 'ucf_degree_list_display_twocol_grouped' ) ) {
	function ucf_degree_list_display_twocol_grouped( $content, $items, $args, $grouped ) {
		$item_count = 0;

		if ( $items ) {
			foreach( $items as $group ) {
				$item_count += count( $group['posts'] );
			}
		}

		// Figure out where we're going to split the columns
		$col_split = ceil( $item_count / 2 );
		$col_index = 0;
		$split = false;

		// Reset item count variable
		// We're going to use it to keep track of where we are now.
		$item_count = 0;

		ob_start();

		if ( $items ):
			foreach( $items as $index => $group ) :
				$item_count += count( $group['posts'] );


				if ( $index === 0 ) :
			?>
				<div class="row">
					<div class="col-lg-6">
			<?php elseif ( $col_index === 1 && $split === false ) : $split = true; ?>
					</div>

					<div class="col-lg-6">
			<?php endif;  ?>

			<div class="degree-list-group">
				<h3 class="degree-list-heading"><?php echo $group['group_name']; ?></h3>
				<?php echo ucf_degree_list_display_classic( '', $group['posts'], $args, $grouped ); ?>
			</div>
		<?php
			// If we're over our split point,
			// move onto the next column.
			if ( $item_count > $col_split ) :
				$col_index = 1;
			endif;

			endforeach;
		?>
		</div></div>
		<?php
		else:
			echo '<p>No results found.</p>';
		endif;

		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_twocol_grouped', 'ucf_degree_list_display_twocol_grouped', 10, 4 );
}
