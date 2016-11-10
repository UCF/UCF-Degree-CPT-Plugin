<?php
/**
 * Utility functions
 **/
function ucf_degree_append_meta( $post ) {
	$meta = get_post_meta( $post->ID );
	$post->meta = $meta;
	return apply_filters( 'ucf_degree_append_meta', $post );
}

?>
