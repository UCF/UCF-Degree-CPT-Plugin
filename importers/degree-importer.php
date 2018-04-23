<?php
/**
 * Class for importing degrees
 */
class UCF_Degree_Importer {
	private
		$search_api,
		$additional_params,
		$search_results,
		$result_count,
		$existing_posts = 0,
		$new_posts,
		$new_count = 0,
		$existing_count = 0,
		$removed_count = 0,
		$updated_posts,
		$duplicate_count = 0,
		$program_types = array(
			'Undergraduate Program' => array(
				'Bachelor',
				'Minor',
				'Undergraduate Certificate'
			),
			'Graduate Program' => array(
				'Master',
				'Specialist',
				'Doctorate',
				'Graduate Certificate'
			),
			'Professional Program'
		); // Array of default program_types

	/**
	 * Constructor
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param string $search_url | The url of the UCF search service
	 * @param string $api_key | The API key to query against the search service with
	 * @param bool $do_writebacks | Whether or not writebacks to the search service should be enabled during the import process
	 * @return UCF_Degree_Importer
	 **/
	public function __construct( $search_url, $api_key, $do_writebacks, $additional_params='' ) {
		$this->search_api = substr( $search_url, -1 ) === '/' ? $search_url : $search_url . '/';
		$this->additional_params = $additional_params;
		$this->api_key = $api_key;
		$this->do_writebacks = $do_writebacks;
		$this->search_results = array();
		$this->existing_posts = array();
		$this->updated_posts = array();
		$this->new_posts = array();
	}

	/**
	 * Imports degrees into WordPress
	 * @author Jim Barnes
	 * @since 1.1.0
	 **/
	public function import() {
		try {
			$this->maybe_enable_search_writebacks();

			$this->search_results = $this->fetch_degrees();
			$this->existing_posts = $this->get_existing();

			$this->create_program_types();
			$this->process_degrees();
			$this->remove_remaining_existing();

			// Publish new degree posts once stale posts have been removed to
			// ensure post slugs are generated without undesirable increments
			// (e.g. my-degree-2)
			$this->publish_new_degrees();

			// Reset writeback hook updates
			$this->maybe_reset_search_writebacks();
		}
		catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * Enables writebacks to the search service during the import process.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 */
	private function maybe_enable_search_writebacks() {
		if ( $this->do_writebacks && class_exists( 'UCF_Search_Service_Common' ) ) {
			add_action( 'save_post', array( 'UCF_Search_Service_Common', 'on_save_post' ), 99, 1 );
		}
	}

	/**
	 * Resets search service writeback hook settings.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 */
	private function maybe_reset_search_writebacks() {
		// Reset writeback hook updates
		if ( $this->do_writebacks && class_exists( 'UCF_Search_Service_Common' ) ) {
			remove_action( 'save_post', array( 'UCF_Search_Service_Common', 'on_save_post' ), 99 );
		}
	}

	/**
	 * Returns a message with the current counts.
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return string | The success statistics
	 **/
	public function get_stats() {
		$degree_total = $this->new_count + $this->existing_count - $this->removed_count;
		return
"
Finished importing degrees.
Total Processed : {$this->result_count}
New             : {$this->new_count}
Updated         : {$this->existing_count}
Removed         : {$this->removed_count}
Duplicates      : {$this->duplicate_count}
Degree Total    : {$degree_total}
";
	}

	/**
	 * Gets degrees from the search service
	 *
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @return Array | The array of degree data
	 **/
	private function fetch_degrees() {
		$retval = null;

		$query = array(
			'key' => $this->api_key
		);

		$url = $this->search_api . 'api/v1/programs/?' . http_build_query( $query );

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

		$this->result_count = $retval->count;
		return $retval->results;
	}

	/**
	 * Gets all existing degree ids
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @return Array<int> | An array of existing degree ids
	 **/
	private function get_existing() {
		$retval = array();

		$args = array(
			'post_type'      => 'degree',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'degree_import_ignore',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key'     => 'degree_import_ignore',
					'value'   => 'on',
					'compare' => '!='
				)
			)
		);

		if ( has_filter( 'ucf_degree_get_existing_args' ) ) {
			$args = apply_filters( 'ucf_degree_get_existing_args', $args );
		}

		$posts = get_posts( $args );

		foreach( $posts as $key => $val ) {
			$retval[intval($val)] = intval( $val );
		}

		return $retval;
	}

	/**
	 * Creates the default program types
	 * @author Jim Barnes
	 * @since 1.1.0
	 **/
	private function create_program_types() {
		$created = False;

		foreach( $this->program_types as $key => $val ) {
			// Handle parent + children program type association
			if ( is_array( $val ) ) {
				if ( ! term_exists( $key, 'program_types' ) ) {
					$parent = wp_insert_term( $key, 'program_types' );
					foreach( $val as $program_type ) {
						wp_insert_term(
							$program_type,
							'program_types',
							array(
								'parent' => $parent['term_id']
							)
						);
					}
					$created = true;
				}
			}
			// Handle a single program type, no children
			else {
				if ( ! term_exists( $val, 'program_types' ) ) {
					$program_type = wp_insert_term( $val, 'program_types' );
					$created = true;
				}
			}
		}

		if ( $created ) {
			// Force a purge of any cached hierarchy so that parent/child relationships are
			// properly saved: http://wordpress.stackexchange.com/a/8921
			delete_option( 'program_types_children' );
			WP_CLI::log( 'Generated default program types.' );
		} else {
			WP_CLI::log( 'Default program types already exist.' );
		}
	}

	/**
	 * Processes the degrees
	 * @author Jim Barnes
	 * @since 1.1.0
	 **/
	private function process_degrees() {
		$import_progress = \WP_CLI\Utils\make_progress_bar( 'Importing degree data...', count( $this->search_results ) );

		foreach( $this->search_results as $ss_program ) {
			// Import the degree as a new WP Post draft, or update existing
			$degree = new UCF_Degree_Import( $ss_program );
			$degree->import_post();

			// Update our new/existing post lists and increment counters
			$this->update_counters( $degree );

			$import_progress->tick();
		}

		$import_progress->finish();
	}

	/**
	 * Increments internal importer counters and post lists depending on
	 * whether or not the given degree is new or already existed.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @param object $degree | UCF_Degree_Import object
	 **/
	private function update_counters( $degree ) {
		$post_id = $degree->post_id;

		if ( $degree->is_new ) {
			// Add the post to the new post list
			$this->new_posts[$post_id] = $post_id;
			$this->new_count++;
		}
		else {
			// Remove the post from the existing post list
			unset( $this->existing_posts[$post_id] );

			// Add the post to the list of updated posts
			if ( ! isset( $this->updated_posts[$post_id] ) ) {
				$this->updated_posts[$post_id] = $post_id;

				// This is a duplicate if it's in the new posts array
				if ( isset( $this->new_posts[$post_id] ) ) {
					$this->duplicate_count++;
				} else {
					$this->existing_count++;
				}
			} else {
				$this->duplicate_count++;
			}
		}
	}

	/**
	 * Remove any degrees left from the existing_degree
	 * array once all other processing is finished.
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function remove_remaining_existing() {
		foreach( $this->existing_posts as $post_id ) {
			wp_delete_post( $post_id, true );
			$this->removed_count++;
		}
	}

	/**
	 * Publish any new degrees we're inserting.
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function publish_new_degrees() {
		foreach( $this->new_posts as $post_id ) {
			wp_publish_post( $post_id );
		}
	}
}


/**
 * Handles the conversion of a search service program object into a
 * WordPress degree post.
 */
class UCF_Degree_Import {
	private
		$plan_code,
		$subplan_code,
		$degree_id,
		$name,
		$slug,
		$description,
		$catalog_url,
		$career,
		$level,
		$program_types,
		$colleges,
		$departments,

		$existing_post, // an existing post object that matches the provided search service program
		$post_meta,
		$post_terms;

	public
		$is_new,
		$post_id; // ID of the new or existing post, set in $this->process_post()

	/**
	 * Constructor
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @param object $program | Imported program object from the search service
	 * @return UCF_Degree_Import
	 **/
	public function __construct( $program ) {
		$this->plan_code     = $program->plan_code;
		$this->subplan_code  = $program->subplan_code;
		$this->degree_id     = $program->plan_code . $program->subplan_code;
		$this->name          = $program->name;
		$this->slug          = sanitize_title( $this->name . $this->get_program_suffix() );
		$this->description   = $this->get_catalog_description( $program->descriptions );
		$this->catalog_url   = $program->catalog_url;
		$this->career        = $program->career;
		$this->level         = $program->level;
		$this->program_types = $this->get_program_types();
		$this->colleges      = $this->get_colleges( $program->colleges );
		$this->departments   = $this->get_departments( $program->departments );

		$this->existing_post = $this->get_existing_post();
		$this->is_new        = $this->existing_post === null ? true : false;

		$this->post_meta     = $this->get_post_metadata();
		$this->post_terms    = $this->get_post_terms();
	}

	/**
	 * Returns the suffix for the post_name
	 *
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param $name string | The program name
	 * @param $type string | The program type
	 * @param $graduate int | If the program is a graduate program
	 * @return string | The program suffix
	 **/
	private function get_program_suffix() {
		$lower_name = strtolower( $this->name );
		switch( $this->level ) {
			case 'Minor':
				return '-minor';
			case 'Certificate':
				if ( stripos( $lower_name, 'certificate' ) === false ) {
					return '-certificate';
				}
			default:
				return '';
		}
	}

	/**
	 * Returns the catalog description out of all available program descriptions.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @param array $descriptions | array of description search service objects
	 * @return mixed | catalog description string, or null on failure
	 */
	private function get_catalog_description( $descriptions ) {
		$description = null;

		if ( !empty( $descriptions ) ) {
			foreach ( $descriptions as $d ) {
				if ( $d->description_type->id === 1 ) {  // TODO make this configurable somehow
					$desription = $d->description;
				}
			}
		}

		return $description;
	}

	/**
	 * Converts a search service program's 'level' and 'career' to a set
	 * of program_type terms.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @return array | Array of program_type term names
	 **/
	private function get_program_types() {
		$program_types = array();

		switch ( $this->career ) {
			case 'Undergraduate':
				$program_types[] = 'Undergraduate Program';
				break;
			case 'Graduate':
				$program_types[] = 'Graduate Program';
				break;
			case 'Professional':
				$program_types[] = 'Professional Program';
				break;
		}

		switch ( $this->level ) {
			case 'Bachelors':
				$program_types[] = 'Bachelor';
				break;
			case 'Masters':
				$program_types[] = 'Master';
				break;
			case 'Certificate':
				$program_types[] = ( $this->career === 'Undergraduate' ) ? 'Undergraduate Certificate' : 'Graduate Certificate';
				break;
			case 'Doctoral':
				$program_types[] = 'Doctorate';
				break;
			case 'Specialist':
			case 'Minor':
				$program_types[] = $this->level;
				break;
		}

		return $program_types;
	}

	/**
	 * Converts a search service program's colleges to an array of
	 * college term names.
	 *
	 * Will return null if the 'colleges' taxonomy is not registered
	 * (e.g. if the UCF College Custom Taxonomy plugin is not activated).
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @param array $colleges | The program's 'colleges' array from the search service
	 * @return mixed | Array of college term names, or null if the 'colleges' taxonomy does not exist
	 **/
	private function get_colleges( $colleges ) {
		if ( taxonomy_exists( 'colleges' ) ) {
			$retval = array();

			foreach ( $colleges as $college ) {
				$retval[] = $this->get_college_name( $college->full_name );
			}

			return $retval;
		}

		return null;
	}

	/**
	 * Converts a search service program's departments to an array of
	 * department terms.
	 *
	 * Will return null if the 'departments' taxonomy is not registered
	 * (e.g. if the UCF Departments Taxonomy plugin is not activated).
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @param array $departments | The program's 'departments' array from the search service
	 * @return mixed | Array of department term names, or null if the 'departments' taxonomy does not exist
	 **/
	private function get_departments( $departments ) {
		if ( taxonomy_exists( 'departments' ) ) {
			$retval = array();

			foreach ( $departments as $department ) {
				$retval[] = $department->full_name;
			}

			return $retval;
		}

		return null;
	}

	/**
	 * Handles exceptions for college names
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param $college_name string | The college name from the search service
	 * @return string | The corrected college name
	 **/
	private function get_college_name( $college_name ) {
		$replacements = array(
			'College of Hospitality Management' => 'Rosen College of Hospitality Management',
			'Office of Undergraduate Studies' => 'College of Undergraduate Studies',
			'College of Nondegree' => ''
		);
		if ( isset( $replacements[$college_name] ) ) {
			return $replacements[$college_name];
		}
		return $college_name;
	}

	/**
	 * Generates the college slug
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param $college_name string | The college name
	 * @return string | The college slug
	 **/
	private function get_college_slug( $college_name ) {
		return sanitize_title( $this->get_college_alias( $college_name ) );
	}

	/**
	 * Returns the alias of the college
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param $college_name string | The name of the college
	 * @return string
	 **/
	private function get_college_alias( $college_name ) {
		// Remove "College of"
		$retval = str_replace( 'College of', '', $college_name );
		// Remove "Rosen"
		$retval = str_replace( 'Rosen', '', $retval );
		// Remove whitespace
		$retval = trim( $retval );
		return $retval;
	}

	/**
	 * Returns an existing degree post or null
	 *
	 * @since 3.0.0
	 * @author Jo Dickson
	 * @return mixed | WP_Post object if the post already exists, or null
	 */
	private function get_existing_post() {
		$args = array(
			'post_type'      => 'degree',
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'draft' ),
			'meta_query'     => array(
				array(
					'key'   => 'degree_id',
					'value' => $this->degree_id
				)
			),
			'tax_query'      => array(
				array(
					'taxonomy' => 'program_types',
					'field'    => 'name',
					'terms'    => $this->program_types
				)
			)
		);

		$existing_post = get_posts( $args );
		$existing_post = empty( $existing_post ) ? null : $existing_post[0];

		if ( has_filter( 'ucf_degree_existing_post' ) ) {
			$existing_post = apply_filters( 'ucf_degree_existing_post', $existing_post, $args, $this->program_types );
		}

		return $existing_post;
	}

	/**
	 * Returns an array of degree post data suitable for passing to
	 * wp_insert_post() or wp_update_post().
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @return array | Array of post data
	 **/
	private function get_formatted_post_data() {
		$post_data = array(
			'post_title'  => $this->name,
			'post_name'   => $this->slug,
			'post_status' => 'draft',
			'post_date'   => date( 'Y-m-d H:i:s' ),
			'post_author' => 1,
			'post_type'   => 'degree',
		);

		if ( ! $this->is_new ) {
			$post_data['ID'] = $this->existing_post->ID;
			$post_data['post_status'] = $this->existing_post->post_status;

			// Remove the post name so we're not updating permalinks
			unset( $post_data['post_name'] );

			// Remove the post date so publish date stays the same.
			unset( $post_data['post_date'] );
		}

		return $post_data;
	}

	/**
	 * Returns an associative array of degree post meta data to save with
	 * the degree post.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @return array | Assoc. array of post meta keys + values
	 **/
	private function get_post_metadata() {
		return array(
			'degree_id'          => $this->degree_id,
			'degree_description' => html_entity_decode( $this->description ),
			'degree_catalog_url' => $this->catalog_url,
		);
	}

	/**
	 * Returns an associative array of degree taxonomy term data to be
	 * saved with the degree post.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @return array | Assoc. array of taxonomy slugs + term names
	 **/
	private function get_post_terms() {
		$terms = array(
			'program_types' => $this->program_types,
		);

		if ( $this->colleges !== null ) {
			$terms['colleges'] = $this->colleges;
		}

		if ( $this->departments !== null ) {
			$terms['departments'] = $this->departments;
		}

		return $terms;
	}

	/**
	 * Creates a new draft, or updates an existing post.
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return int | The post ID
	 **/
	private function process_post() {
		$post_id = null;
		$post_data = $this->get_formatted_post_data();

		if ( ! $this->is_new ) {
			$post_id = $this->existing_post->ID;
			wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data );
		}

		return $post_id;
	}

	/**
	 * Sets the post terms
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function process_post_terms() {
		foreach ( $this->post_terms as $tax => $terms ) {
			foreach ( $terms as $term ) {
				$term_id = null;
				$existing_term = term_exists( $term, $tax );

				if ( ! empty( $existing_term ) && is_array( $existing_term ) ) {
					$term_id = $existing_term['term_id'];
				} else {
					$args = array();
					if ( $tax === 'colleges' && class_exists( 'UCF_College_Taxonomy' ) ) {
						$args['slug'] = $this->get_college_slug( $term );
					}

					$new_term = wp_insert_term( $term, $tax, $args );
					if ( is_array( $new_term ) ) {
						$term_id = $new_term['term_id'];
					}
				}

				if ( $term_id ) {
					// Set the alias
					wp_set_post_terms( $this->post_id, $term_id, $tax, true );
				} else {
					wp_delete_object_term_relationships( $this->post_id, $tax );
				}

				if ( $tax === 'colleges' && class_exists( 'UCF_College_Taxonomy' ) ) {
					$alias = $this->get_college_alias( $term );
					update_term_meta( $term_id, 'colleges_alias', $alias );
				}
			}
		}
	}

	/**
	 * Creates or updates the post_meta
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function process_post_meta() {
		foreach( $this->post_meta as $key => $val ) {
			update_field( $key, $val, $this->post_id );
		}
	}

	/**
	 * Handles all new/existing post processing.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 */
	public function import_post() {
		try {
			$this->post_id = $this->process_post();
			$this->process_post_terms();
			$this->process_post_meta();
		}
		catch ( Exception $e ) {
			throw $e;
		}
	}
}
