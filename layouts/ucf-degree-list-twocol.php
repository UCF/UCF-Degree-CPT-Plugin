<?php
/**
 * Functions that define the degree list two-column layout
 **/

if ( ! function_exists( 'ucf_degree_list_display_twocol' ) ) {
	function ucf_degree_list_display_twocol( $content, $items, $args, $grouped ) {
		ob_start();
	?>
		<ul class="degree-list-twocol">
	<?php foreach( $items as $item ) : ?>
			<li class="degree-list-program"><a href="<?php echo get_permalink( $item->ID ); ?>"><?php echo $item->post_title; ?></a></li>
	<?php endforeach; ?>
		</ul>
	<?php
		return ob_get_clean();
	}

	add_filter( 'ucf_degree_list_display_twocol', 'ucf_degree_list_display_twocol', 10, 4 );
}

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

/**
 * Enqueues twocol assets if degree-list shortcode layout attr is
 * set to 'twocol' and the groupby attr is empty.
 *
 * @since 3.2.6
 * @author Cadie Stockman
 * @param string $output Shortcode output.
 * @param string $tag Shortcode name.
 * @param array|string $attr Shortcode attributes array or empty string.
 * @return string
 */
if ( ! function_exists( 'ucf_degree_list_enqueue_twocol_assets' ) ) {
	function ucf_degree_list_enqueue_twocol_assets( $output, $tag, $attr) {
		if ( $tag !== 'degree-list' ) {
			return $output;
		}

		if ( isset( $attr['layout'] ) && $attr['layout'] === 'twocol' && empty( $attr['groupby'] ) ) {
			$plugin_data = get_plugin_data( UCF_DEGREE__PLUGIN_FILE, false, false );
			$version     = $plugin_data['Version'];

			wp_enqueue_style( 'ucf_degree_cpt_css', plugins_url( 'static/css/ucf-degree-list-twocol.min.css', UCF_DEGREE__PLUGIN_FILE ), false, $version, 'all' );

			return $output;
		}

		return $output;
	}

	add_filter( 'do_shortcode_tag','ucf_degree_list_enqueue_twocol_assets', 10, 3 );
}
