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
	 * [--api_base_url=<api_base>]
	 * : The base URL of the Search Service you want to pull from. The "Search Service Base URL" plugin option is used by default.
	 *
	 * [--api_key=<api_key>]
	 * : The API key to query against the Search Service with. The "Search Service API Key" plugin option is used by default.
	 *
	 * [--enable_search_writebacks=<enable_search_writebacks>]
     * : If enabled, data will be written back to the Search Service when each degree is imported. Disabled by default.
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
	 * $ wp degrees import --api_base_url="https://search.cm.ucf.edu/api/v1/" --api_key="xxxxxxxxxxxxxxxxx"
	 *
	 * @when after_wp_load
	 */
	public function import( $args, $assoc_args ) {
		$api_base_url    = isset( $assoc_args['api_base_url'] ) && !empty( $assoc_args['api_base_url'] ) ? trim( $assoc_args['api_base_url'] ) : trim( UCF_Degree_Config::get_option_or_default( 'ucf_degree_api_base_url' ) );
		$api_key         = isset( $assoc_args['api_key'] ) && !empty( $assoc_args['api_key'] ) ? trim( $assoc_args['api_key'] ) : trim( UCF_Degree_Config::get_option_or_default( 'ucf_degree_api_key' ) );
		$do_writebacks   = isset( $assoc_args['enable_search_writebacks'] ) ? filter_var( $assoc_args['enable_search_writebacks'], FILTER_VALIDATE_BOOLEAN ) : false;
		$additional_args = UCF_Degree_Config::get_option_or_default( 'search_filter' );

		if ( empty( $api_base_url ) ) {
			WP_CLI::error( 'Search Service API Base URL is required to run the degree importer.' );
		}

		if ( empty( $api_key ) ) {
			WP_CLI::error( 'Search Service API Key is required to run the degree importer.' );
		}

		// Do import
		$import = new UCF_Degree_Importer( $api_base_url, $api_key, $do_writebacks, $additional_args );
		try {
			$import->import();
		}
		catch( Exception $e ) {
			WP_CLI::error( $e->getMessage(), $e->getCode() );
		}
		WP_CLI::success( $import->get_stats() );
	}

	/**
	 * Imports interests from a JSON file
	 *
	 * ## OPTIONS
	 *
	 * <filepath>
	 * : The filepath to a valid JSON file containing interests and assigned program names.
	 *
	 * ## EXAMPLES
	 *
	 * # Imports interests from a file called interests.json in a home directory
	 * $ wp degrees import_intersts ~/interests.json
	 *
	 * @when after_wp_load
	 */
	public function import_interests( $args, $assoc_args ) {
		list( $filepath ) = $args;

		if ( empty( $filepath ) ) {
			WP_CLI::error( 'A filepath to a valid JSON file must be provided.' );
		}

		$import = new UCF_Degree_Interests_Importer( $filepath );
		try {
			$import->import();
			WP_CLI::success( $import->get_stats() );
		}
		catch( Exception $e ) {
			WP_CLI::error( $e->getMessage(), $e->getCode() );
		}
	}
}
