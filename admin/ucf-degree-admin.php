<?php
/**
 * Responsible for wp-admin related functionality
 **/
class UCF_Degree_Admin {
	public static function enqueue_admin_scripts() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'ucf-degree-admin', UCF_DEGREE__STATIC_URL . '/js/ucf-degree-admin.min.js', array( 'wp-color-picker' ), false, true );
	}
}
