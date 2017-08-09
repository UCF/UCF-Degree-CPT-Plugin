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
	 * <catalog_url>
	 * : The url of the undergraduate catalog. (Required)
	 *
	 * ## EXAMPLES
	 *
	 * # Imports degrees from the dev search service.
	 * $ wp degrees import https://searchdev.smca.ucf.edu/service.php http://catalog.ucf.edu/feed/
	 *
	 * @when after_wp_load
	 */
	public function import( $args, $assoc_args ) {
		$search_url  = $args[0];
		$catalog_url = $args[1];

		$additional_args = UCF_Degree_Config::get_option_or_default( 'search_filter' );

		$import = new UCF_Degree_Importer( $search_url, $catalog_url, $additional_args );
		try {
			$import->import();
		}
		catch( Exception $e ) {
			WP_CLI::error( $e->getMessage(), $e->getCode() );
		}
		WP_CLI::success( $import->get_stats() );
	}
}
