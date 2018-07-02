<?php
/**
 * Class for importing degrees
 */
class UCF_Degree_Importer {
	private
		$search_api,
		$additional_params,
		$api_key,
		$do_writebacks,
		$preserve_hierarchy,
		$force_delete_stale,

		$search_results = array(),
		$result_count,

		$existing_plan_posts = array(),
		$existing_subplan_posts = array(),
		$new_plan_posts = array(),
		$new_subplan_posts = array(),
		$updated_plan_posts = array(),
		$updated_subplan_posts = array(),

		$new_count = 0,
		$existing_count = 0,
		$removed_count = 0,
		$duplicate_count = 0,

		$program_types = array(),
		$default_program_types = array(
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
		);

	/**
	 * Constructor
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param string $search_url | The url of the UCF search service
	 * @param string $api_key | The API key to query against the search service with
	 * @param bool $do_writebacks | Whether or not writebacks to the search service should be enabled during the import process
	 * @param string $addition_params | Additional query params to pass to the search service when querying degrees to import
	 * @param bool $preserve_hierarchy | Whether or not plan/subplan hierarchies should be preserved in generated degree posts
	 * @param bool $force_delete_stale | Whether or not stale degrees should bypass trash when removed
	 * @return UCF_Degree_Importer
	 **/
	public function __construct( $search_url, $api_key, $do_writebacks, $additional_params='', $preserve_hierarchy=true, $force_delete_stale=true ) {
		$this->search_api = substr( $search_url, -1 ) === '/' ? $search_url : $search_url . '/';
		$this->additional_params = $additional_params;
		$this->api_key = $api_key;
		$this->do_writebacks = $do_writebacks;
		$this->preserve_hierarchy = $preserve_hierarchy;
		$this->force_delete_stale = $force_delete_stale;
		$this->program_types = apply_filters( 'ucf_degree_imported_program_types', $this->default_program_types );
	}

	/**
	 * Imports degrees into WordPress
	 * @author Jim Barnes
	 * @since 1.1.0
	 **/
	public function import() {
		try {
			$this->maybe_enable_search_writebacks();

			$this->search_results = $this->fetch_api_results();
			$this->existing_plan_posts = $this->get_existing_plan_posts();
			$this->existing_subplan_posts = $this->get_existing_subplan_posts();

			$this->create_program_types();

			// Process parent degree programs first
			$this->process_degree_plans();
			$this->remove_stale_degree_plans();
			// Publish new degree posts once stale posts have been removed to
			// ensure post slugs are generated without undesirable increments
			// (e.g. my-degree-2)
			$this->publish_new_degree_plans();

			// Process subplans after parent programs are processed
			$this->process_degree_subplans();
			$this->remove_stale_degree_subplans();
			$this->publish_new_degree_subplans();

			// Reset writeback hook updates
			$this->maybe_reset_search_writebacks();

			flush_rewrite_rules();
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
		if ( $this->do_writebacks ) {
			add_action( 'save_post_degree', array( 'UCF_Degree_Common', 'on_save_post' ), 99, 1 );
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
		if ( $this->do_writebacks ) {
			remove_action( 'save_post_degree', array( 'UCF_Degree_Common', 'on_save_post' ), 99 );
		}
	}

	/**
	 * Returns a message with the current counts.
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return string | The success statistics
	 **/
	public function get_stats() {
		$totaled_degrees = get_posts( array(
			'post_type'      => 'degree',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
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
			)
		) );
		$degree_total = count( $totaled_degrees );
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
	 * Generic function that appends a paginated response's result set
	 * from the search service to an existing list.
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @param string $url | the full search service URL to request
	 * @param array $all_results | an array of results to append paginated data to
	 * @param bool $return_count | whether or not the total number of results should be returned
	 * @return array | array containing the next page's URL, the updated result set, and, optionally, the total result count
	 */
	private function fetch_api_page( $url, $all_results, $return_count=false ) {
		$response = UCF_Degree_Common::fetch_json( $url );

		if ( ! $response || ! isset( $response->results ) ) {
			throw new Exception(
				'Failed to parse the Search Service JSON. ' .
				'Please make sure your Search Service Base URL and API Key are correct.',
				2
			);
		}

		if ( count( $response->results ) === 0 ) {
			throw new Exception(
				'No results found from the Search Service. ' .
				'Please make sure your Search Service Base URL and API Key are correct.',
				3
			);
		}

		$next_url = isset( $response->next ) ? $response->next : null;
		$all_results = array_merge( $all_results, $response->results );

		if ( $return_count ) {
			$count = isset( $response->count ) ? $response->count : 0;
			return array( $next_url, $all_results, $count );
		}
		return array( $next_url, $all_results );
	}

	/**
	 * Gets degrees from the search service
	 *
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @return Array | The array of degree data
	 **/
	private function fetch_api_results() {
		WP_CLI::log( 'Fetching API data...' );

		$results = $query = array();
		$count = 0;

		if ( $this->additional_params ) {
			parse_str( $this->additional_params, $query );
		}
		$query['key'] = $this->api_key;

		$url = add_query_arg( $query, $this->search_api . 'programs/search/' );

		// Perform an initial out-of-loop fetch and assign $count
		list( $url, $results, $count ) = $this->fetch_api_page( $url, $results, true );

		// Fetch remaining pages
		while ( !empty( $url ) ) {
			list( $url, $results ) = $this->fetch_api_page( $url, $results );
		}

		// Allow returned results and result count to be overridden by
		// other themes/plugins.
		// Functions passed to this filter MUST return both $results AND $count
		// as a two-value array.
		list( $results, $count ) = apply_filters( 'ucf_degree_import_results', $results, $count, $this->api_key );

		$this->result_count = $count;

		WP_CLI::log( sprintf( '%s API results fetched.', $count ) );

		return $results;
	}

	/**
	 * Gets all existing degree ids
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param array $custom_args | Array of custom get_posts() args
	 * @return array<int> | An array of existing degree ids
	 **/
	private function get_existing( $custom_args ) {
		$retval = array();

		$args = array_merge( array(
			'post_type'      => 'degree',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids'
		), $custom_args );

		if ( has_filter( 'ucf_degree_get_existing_args' ) ) {
			$args = apply_filters( 'ucf_degree_get_existing_args', $args );
		}

		$posts = get_posts( $args );

		foreach( $posts as $key => $val ) {
			$retval[intval( $val )] = intval( $val );
		}

		return $retval;
	}

	/**
	 * Returns all existing degree plan posts
	 *
	 * @since 3.0.0
	 * @author Jo Dickson
	 * @return array<int> | An array of existing degree plan post ids
	 */
	private function get_existing_plan_posts() {
		$existing = $this->get_existing( array(
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'relation' => 'AND',
					array(
						'key'      => 'degree_plan_code',
						'value'    => '',
						'compare'  => '!='
					),
					array(
						'key'      => 'degree_subplan_code',
						'value'    => '',
						'compare'  => '='
					)
				),
				array(
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
			)
		) );

		WP_CLI::log( sprintf( '%s existing degree plan posts were found.', count( $existing ) ) );

		return $existing;
	}

	/**
	 * Returns all existing degree subplan posts
	 *
	 * @since 3.0.0
	 * @author Jo Dickson
	 * @return array<int> | An array of existing degree subplan post ids
	 */
	private function get_existing_subplan_posts() {
		$existing = $this->get_existing( array(
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'relation' => 'AND',
					array(
						'key' => 'degree_plan_code',
						'value'    => '',
						'compare'  => '!='
					),
					array(
						'key' => 'degree_subplan_code',
						'value'    => '',
						'compare'  => '!='
					)
				),
				array(
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
			)
		) );

		WP_CLI::log( sprintf( '%s existing degree subplan posts were found.', count( $existing ) ) );

		return $existing;
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
	 * Processes the degree plans
	 * @author Jo Dickson
	 * @since 3.0.0
	 **/
	private function process_degree_plans() {
		$import_progress = \WP_CLI\Utils\make_progress_bar( 'Importing degree plans...', count( $this->search_results ) );

		foreach( $this->search_results as $ss_program ) {
			if ( $ss_program->parent_program === null ) {
				// Import the degree as a new WP Post draft, or update existing
				$degree = new UCF_Degree_Import( $ss_program , $this->api_key, $this->preserve_hierarchy );
				$degree->import_post();

				// Update our new/existing post lists and increment counters
				$this->update_counters( $degree );
			}

			$import_progress->tick();
		}

		$import_progress->finish();
	}

	/**
	 * Processes the degree subplans
	 * @author Jo Dickson
	 * @since 3.0.0
	 **/
	private function process_degree_subplans() {
		$import_progress = \WP_CLI\Utils\make_progress_bar( 'Importing degree subplans...', count( $this->search_results ) );

		foreach( $this->search_results as $ss_program ) {
			if ( $ss_program->parent_program !== null ) {
				// Import the degree as a new WP Post draft, or update existing
				$degree = new UCF_Degree_Import( $ss_program, $this->api_key, $this->preserve_hierarchy );
				$degree->import_post();

				// Update our new/existing post lists and increment counters
				$this->update_counters( $degree );
			}

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
		$new_posts = $existing_posts = $updated_posts = array();

		if ( $degree->is_subplan ) {
			$new_posts      = &$this->new_subplan_posts;
			$existing_posts = &$this->existing_subplan_posts;
			$updated_posts  = &$this->updated_subplan_posts;
		}
		else {
			$new_posts      = &$this->new_plan_posts;
			$existing_posts = &$this->existing_plan_posts;
			$updated_posts  = &$this->updated_plan_posts;
		}

		if ( $degree->is_new ) {
			// Add the post to the new post list
			$new_posts[$post_id] = $post_id;
			$this->new_count++;
		}
		else {
			// Remove the post from the existing post list
			unset( $existing_posts[$post_id] );

			if ( ! isset( $updated_posts[$post_id] ) ) {
				$updated_posts[$post_id] = $post_id;

				// This is a duplicate if it's in the new posts array
				if ( isset( $new_posts[$post_id] ) ) {
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
	 * @author Jo Dickson
	 * @since 3.0.0
	 **/
	private function remove_stale_degree_plans() {
		$delete_progress = \WP_CLI\Utils\make_progress_bar( 'Deleting stale degree plan posts...', count( $this->existing_plan_posts ) );

		foreach( $this->existing_plan_posts as $post_id ) {
			if ( $this->force_delete_stale ) {
				wp_delete_post( $post_id, true );
			}
			else {
				wp_trash_post( $post_id );
			}
			$this->removed_count++;
			$delete_progress->tick();
		}

		$delete_progress->finish();
	}

	/**
	 * Remove any degrees left from the existing_degree
	 * array once all other processing is finished.
	 * @author Jo Dickson
	 * @since 3.0.0
	 **/
	private function remove_stale_degree_subplans() {
		$delete_progress = \WP_CLI\Utils\make_progress_bar( 'Deleting stale degree subplan posts...', count( $this->existing_subplan_posts ) );

		foreach( $this->existing_subplan_posts as $post_id ) {
			if ( $this->force_delete_stale ) {
				wp_delete_post( $post_id, true );
			}
			else {
				wp_trash_post( $post_id );
			}
			$this->removed_count++;
			$delete_progress->tick();
		}

		$delete_progress->finish();
	}

	/**
	 * Publish any new degrees we're inserting.
	 * @author Jo Dickson
	 * @since 3.0.0
	 **/
	private function publish_new_degree_plans() {
		$publish_progress = \WP_CLI\Utils\make_progress_bar( 'Publishing new degree plan posts...', count( $this->new_plan_posts ) );

		foreach( $this->new_plan_posts as $post_id ) {
			wp_publish_post( $post_id );
			$publish_progress->tick();
		}

		$publish_progress->finish();
	}

	/**
	 * Publish any new subplans we're inserting.
	 * @author Jo Dickson
	 * @since 3.0.0
	 **/
	private function publish_new_degree_subplans() {
		$publish_progress = \WP_CLI\Utils\make_progress_bar( 'Publishing new degree subplan posts...', count( $this->new_subplan_posts ) );

		foreach( $this->new_subplan_posts as $post_id ) {
			wp_publish_post( $post_id );
			$publish_progress->tick();
		}

		$publish_progress->finish();
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
		$api_id,
		$name,
		$online,
		$catalog_url,
		$career,
		$level,
		$program_types,
		$colleges,
		$departments,
		$parent_post_id, // if this degree is a subplan, this references the parent plan's post ID
		$existing_post, // an existing post object that matches the provided search service program
		$name_short,
		$slug,
		$post_meta,
		$post_terms,
		$api_key,
		$preserve_hierarchy;

	public
		$program,
		$is_subplan,
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
	public function __construct( $program, $api_key=null, $preserve_hierarchy=true ) {
		$this->preserve_hierarchy = $preserve_hierarchy;

		$this->program       = $program;
		$this->plan_code     = $program->plan_code;
		$this->subplan_code  = $program->subplan_code;
		$this->degree_id     = $program->plan_code. ' ' . $program->subplan_code;
		$this->api_id        = $program->id;
		$this->name          = $program->name;
		$this->online        = $program->online;
		$this->catalog_url   = $program->catalog_url;
		$this->career        = $program->career;
		$this->level         = $program->level;
		$this->is_subplan    = $program->parent_program !== null ? true : false;
		$this->program_types = $this->get_program_types();
		$this->colleges      = $this->get_colleges();
		$this->departments   = $this->get_departments();
		$this->api_key       = $api_key;

		$this->parent_post_id = $this->get_parent_post_id();
		$this->existing_post  = $this->get_existing_post();
		$this->is_new         = $this->existing_post === null ? true : false;

		$this->name_short = $this->get_name_short();
		$this->slug       = $this->get_slug();

		$this->post_meta  = $this->get_post_metadata();
		$this->post_terms = $this->get_post_terms();
	}

	/**
	 * Returns a shortened degree name.  For subplans, this will be the name
	 * of the track specifically (with the parent program's name omitted).
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @return string
	 */
	private function get_name_short() {
		$name_short = $this->name;

		// If this degree is a subplan, determine the parent degree's name
		// and remove it from the beginning of the subplan's name, if present
		if ( $this->is_subplan ) {
			$parent_post = get_post( $this->parent_post_id );
			$parent_name = '';

			if ( $parent_post ) {
				$parent_name = $parent_post->post_title;
			}

			if ( $parent_name && substr( $this->name, 0, strlen( $parent_name ) ) === $parent_name ) {
				$name_short = substr_replace( $this->name, '', 0, strlen( $parent_name ) );
				$name_short = trim( $name_short );
				if ( substr( $name_short, 0, 2 ) === '- ' ) {
					$name_short = substr_replace( $name_short, '', 0, 2 );
				}
			}
		}

		return $name_short;
	}

	/**
	 * Returns the suffix for the post_name
	 *
	 * @author Jim Barnes
	 * @since 1.1.0
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
	 * Returns the degree's slug (post_name)
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @return string
	 */
	private function get_slug() {
		return sanitize_title( $this->name_short . $this->get_program_suffix() );
	}

	/**
	 * Converts a search service program's 'level' and 'career' to a set
	 * of program_type term names.
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

		// Allow overrides by themes/other plugins
		if ( has_filter( 'ucf_degree_get_program_types' ) ) {
			$program_types = apply_filters( 'ucf_degree_get_program_types', $program_types, $this->program );
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
	 * @return mixed | Array of college term names, or null if the 'colleges' taxonomy does not exist
	 **/
	private function get_colleges() {
		$colleges = $this->program->colleges;

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
	 * department term names.
	 *
	 * Will return null if the 'departments' taxonomy is not registered
	 * (e.g. if the UCF Departments Taxonomy plugin is not activated).
	 *
	 * @author Jo Dickson
	 * @since 3.0.0
	 * @return mixed | Array of department term names, or null if the 'departments' taxonomy does not exist
	 **/
	private function get_departments() {
		$departments = $this->program->departments;

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
	 * Returns the Search Service's program ID for this program's parent plan.
	 *
	 * @since 3.0.0
	 * @author Jo Dickson
	 * @return mixed | Parent program ID integer, or null on failure
	 */
	private function get_parent_program_id() {
		$id = $parent_program = null;

		// Check $this->preserve_hierarchy here to prevent
		// unneeded API calls
		if ( $this->is_subplan && $this->preserve_hierarchy ) {
			$params = array();

			if ( $this->api_key ) $params['key'] = $this->api_key;

			$parent_program = UCF_Degree_Common::fetch_api_value( $this->program->parent_program->url, $params );
			if ( $parent_program ) {
				$id = $parent_program->id;
			}
		}

		return $id;
	}

	/**
	 * Returns the WP degree post ID that corresponds to this program's
	 * parent plan.
	 *
	 * @since 3.0.0
	 * @author Jo Dickson
	 * @return int The parent degree post's ID
	 */
	private function get_parent_post_id() {
		if ( ! $this->preserve_hierarchy ) {
			return 0;
		}

		$parent = null;
		$parent_id = 0;
		$parent_program_id = $this->get_parent_program_id();

		if ( $parent_program_id !== null ) {
			$parent = get_posts( array(
				'post_type'      => 'degree',
				'posts_per_page' => 1,
				'post_parent'    => 0,
				'post_status'    => array( 'publish', 'draft' ),
				'meta_query'     => array(
					array(
						'key'   => 'degree_api_id',
						'value' => $parent_program_id
					)
				)
			) );
			$parent_id = $parent ? $parent[0]->ID : 0;
		}

		return $parent_id;
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
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'meta_query'     => array(
				array(
					'key'   => 'degree_id',
					'value' => $this->degree_id
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
			'post_parent' => $this->parent_post_id,
			'post_status' => 'draft',
			'post_date'   => date( 'Y-m-d H:i:s' ),
			'post_author' => 1,
			'post_type'   => 'degree',
		);

		$configurable_data = array(
			'post_title'  => $this->name,
			'post_name'   => $this->slug,
			'post_status' => 'draft',
			'post_author' => 1
		);

		if ( has_filter( 'ucf_degree_set_post_data' ) ) {
			$configurable_data = apply_filters( 'ucf_degree_set_post_data', $configurable_data, $this->is_new, $this->existing_post );
		}

		$post_data['post_title']  = $configurable_data['post_title'];
		$post_data['post_name']   = $configurable_data['post_name'];
		$post_data['post_status'] = $configurable_data['post_status'];
		$post_data['post_author'] = $configurable_data['post_author'];

		// Ensure post_status is any allowable value, other than publish
		$allowable_statuses = get_post_stati( null, 'names' );

		unset( $allowable_statuses['publish'] );

		if ( ! in_array( $post_data['post_status'], $allowable_statuses ) || $post_data['post_status'] === 'publish' ) {
			$post_data['post_status'] = 'draft';
		}

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
		$meta = array(
			'degree_id'           => $this->degree_id,
			'degree_api_id'       => $this->api_id,
			'degree_online'       => $this->online,
			'degree_pdf'          => $this->catalog_url,
			'degree_plan_code'    => $this->plan_code,
			'degree_subplan_code' => $this->subplan_code,
			'degree_name_short'   => $this->name_short
		);

		// Allow overrides by themes/other plugins
		if ( has_filter( 'ucf_degree_get_post_metadata' ) ) {
			$meta = apply_filters( 'ucf_degree_get_post_metadata', $meta, $this->program );
		}

		return $meta;
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

		// Allow overrides by themes/other plugins
		if ( has_filter( 'ucf_degree_get_post_terms' ) ) {
			$terms = apply_filters( 'ucf_degree_get_post_terms', $terms, $this->program );
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
