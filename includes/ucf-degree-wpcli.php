<?php
/**
 * Commands for creating and upgrading degrees
 **/
class UCF_Degree_Commands extends WP_CLI_Command {
	/**
	 * Imports degrees from the search service.
	 *
	 * ## OPTIONS
	 *
	 * <search_url>
	 * : The url of the search service you want to pull from. (Required)
	 *
	 * <api_key>
	 * : The API key to query against the search service with. (Required)
	 *
	 * [--enable_search_writebacks=<type>]
     * : If enabled, data will be written back to the search service when each degree is imported if the UCF Search Service Hook plugin is activated. Disabled by default.
     * ---
     * default: false
     * options:
     *   - true
     *   - false
     * ---
	 *
	 * ## EXAMPLES
	 *
	 * # Imports degrees from the production search service.
	 * $ wp degrees import https://search.cm.ucf.edu/ xxxxxxxxxxxxxxxxx
	 *
	 * @when after_wp_load
	 */
	public function import( $args, $assoc_args ) {
		$search_url      = $args[0];
		$api_key         = $args[1];
		$do_writebacks   = filter_var( $assoc_args['enable_search_writebacks'], FILTER_VALIDATE_BOOLEAN );
		$additional_args = UCF_Degree_Config::get_option_or_default( 'search_filter' );

		// Do import
		$import = new UCF_Degree_Importer( $search_url, $api_key, $do_writebacks, $additional_args );
		try {
			$import->import();
		}
		catch( Exception $e ) {
			WP_CLI::error( $e->getMessage(), $e->getCode() );
		}
		WP_CLI::success( $import->get_stats() );
	}
}
