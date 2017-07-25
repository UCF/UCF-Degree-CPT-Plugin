<?php
/**
 * Class for importing degrees
 */
class UCF_Degree_Importer {
	private
		$search_api,
		$additional_params,
		$catalog_api,
		$search_results,
		$result_count,
		$catalog_programs,
		$existing_posts = 0,
		$new_posts,
		$new_count = 0,
		$removed_count = 0,
		$updated_posts,
		$duplicate_count = 0;

	/**
	 * Constructor
	 * @author Jim Barnes
	 * @since 1.0.3
	 * @param $search_url string | The url of the UCF search service
	 * @param $catalog_url string | The url of the undergraduate catalog service
	 * @return UCF_Degree_Importer
	 **/
	public function __construct( $search_url, $catalog_url, $additional_params='' ) {
		$this->search_api = $search_url;
		$this->additional_params = $additional_params;
		$this->catalog_api = $catalog_url;
		$this->search_results = array();
		$this->catalog_programs = array();
		$this->existing_posts = array();
		$this->updated_posts = array();
		$this->new_posts = array();
	}

	/**
	 * Imports degrees into WordPress
	 * @author Jim Barnes
	 * @since 1.0.3
	 **/
	public function import() {
		try {
			$this->search_results = $this->fetch_degrees();
			$this->catalog_programs = $this->fetch_catalog_data();
			$this->existing_posts = $this->get_existing();

			$this->create_program_types();
			$this->process_degrees();
			$this->remove_remaining_existing();
			$this->publish_new_degrees();
		}
		catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * Returns a message with the current counts.
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return string | The success statistics
	 **/
	public function get_stats() {

	}

	/**
	 * Gets degrees from the search service
	 * @author Jim Barnes
	 * @since 1.0.3
	 * @return Array | The array of degree data
	 **/
	private function fetch_degrees() {
		$retval = null;

		$query = array(
			'use' => 'programSearch'
		);

		$url = $this->search_api . '?' . http_build_query( $query );

		if ( $this->additional_params ) {
			$url .= '&' . $this->additional_params;
		}

		$args = array(
			'timeout' => 15
		);

		$response = wp_remote_get( $url, $args );

		if ( is_array( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			$retval = json_decode( $response_body );

			if ( ! $retval ) {
				throw new Exception(
					'Failed to parse the search service json. ' .
					'Please make sure your search service url is correct.',
					2
				);
			}
		} else {
			throw new Exception(
				'Failed to connect to the search service. ' .
				'Please make sure your search service url is correct.',
				1
			);
		}

		if ( count( $retval->results ) === 0 ) {
			throw new Exception(
				'No results found from the search service. ' .
				'Please make sure your search service url is correct.',
				3
			);
		}

		$this->result_count = count( $retval->results );
		return $retval->results;
	}

	/**
	 * Gets all existing degree ids
	 * @author Jim Barnes
	 * @since 1.0.3
	 * @return Array<int> | An array of existing degree ids
	 **/
	private function get_existing() {
		$retval = array();

		$args = array(
			'post_type'      => 'degree',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids'
		);

		$posts = get_posts( $args );

		foreach( $posts as $key => $val ) {
			$retval[intval($val)] = intval( $val );
		}

		return $retval;
	}

	/**
	 * Processes the degrees
	 * @author Jim Barnes
	 * @since 1.0.3
	 **/
	private function process_degrees() {

	}
}
