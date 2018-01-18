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
		$duplicate_count = 0,
		$program_types = array(
			'Undergraduate Program' => array(
				'Undergraduate Degree',
				'Minor',
				'Articulated Program',
				'Accelerated Program'
			),
			'Graduate Program' => array(
				'Master',
				'Doctorate',
				'Certificate'
			)
		), // Array of default program_types
		$doctoral_mapping = array(
			'DPT',
			'DNP',
			'EdD',
			'PhD',
			'MD'
		);

	/**
	 * Constructor
	 * @author Jim Barnes
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @author Jim Barnes
	 * @since 1.1.0
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
	 * Fetches data from the undergraduate catalog
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return Array | An array of catalog data
	 **/
	private function fetch_catalog_data() {
		$retval = null;
		$args = array(
			'timeout' => 15
		);
		$response = wp_remote_get( $this->catalog_api, $args );
		if ( is_array( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			$retval = json_decode( $response_body );
			if ( ! $retval ) {
				throw new Degree_Importer_Exception(
					'Failed to parse the undergraduate catalog json. ' .
					'Please make sure your catalog url is correct.',
					5
				);
			}
		} else {
			throw new Degree_Importer_Exception(
				'Failed to connect to the undergraduate catalog. ' .
				'Please make sure your catalog url is correct.',
				4
			);
		}
		if( isset( $retval->programs ) ) {
			return $retval->programs;
		} else {
			throw new Degree_Importer_Exception(
				'No programs found in the undergraduate catalog api. ',
				6
			);
		}
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
			if ( ! term_exists( $key, 'program_types' ) ) {
				$parent = wp_insert_term( $key, 'program_types' );
				foreach( $val as $program ) {
					wp_insert_term(
						$program,
						'program_types',
						array(
							'parent' => $parent['term_id']
						)
					);
				}
				$created = true;
			}
		}
		if ( $created ) {
			// Force a purge of any cached hierarchy so that parent/child relationships are
			// properly saved: http://wordpress.stackexchange.com/a/8921
			delete_option('program_types_children');
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
		$post_array = array();
		$gather_progress = \WP_CLI\Utils\make_progress_bar( 'Processing search results...', count( $this->search_results ) );

		foreach( $this->search_results as $program ) {
			$program->suffix = $this->get_program_suffix( $program->name, $program->type, $program->graduate );
			$program->type = $this->get_program_type( $program->type, $program->graduate, $program->name );
			$program->type_ucmatch = $this->get_uc_program_type( $program->type );

			if ( class_exists( 'UCF_College_Taxonomy' ) ) {
				$program->college_name = $this->get_college_name( $program->college_name );
			}

			if ( $program->graduate === 0 ) {
				$program->catalog_url = $this->get_uc_catalog_url( $program );
			}

			if ( has_filter( 'ucf_degree_get_program_data' ) ) {
				$program = apply_filters( 'ucf_degree_get_program_data', $program );
			}

			$post_array_item = $this->format_post_data( $program );

			if ( has_filter( 'ucf_degree_format_post_data' ) ) {
				$post_array_item = apply_filters( 'ucf_degree_format_post_data', $post_array_item, $program );
			}

			$post_array[] = $post_array_item;

			$gather_progress->tick();
		}

		$gather_progress->finish();

		$import_progress = \WP_CLI\Utils\make_progress_bar( 'Importing degrees...', count( $post_array ) );

		foreach( $post_array as $post ) {
			$post_data = $post['post_data'];
			$post_meta = $post['post_meta'];
			$post_terms = $post['post_terms'];

			$degree_id = isset( $post_meta['degree_id'] ) ? $post_meta['degree_id'] : null;
			$degree_type_id = isset( $post_meta['degree_type_id'] ) ? $post_meta['degree_type_id'] : null;
			$program_types = isset( $post_terms['program_types'] ) ? $post_terms['program_types'] : null;

			$post_id = $this->process_post( $post_data, $degree_id, $degree_type_id, $program_types );
			$this->process_post_meta( $post_id, $post_meta );
			$this->process_post_terms( $post_id, $post_terms );

			$import_progress->tick();
		}

		$import_progress->finish();
	}

	/**
	 * Cleans a name to make it easier to string compare
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @return string | The cleaned string
	 **/
	private function clean_name( $name ) {
		$blacklist = array( 'degree', 'program' );
		$name = strtolower( html_entity_decode( $name, ENT_NOQUOTES, 'UTF-8' ) );
		$name = str_replace( $blacklist, '', $name );
		$name = preg_replace( '/[^a-z0-9]/', '', $name );
		return $name;
	}

	/**
	 * Returns the program type
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param $type string | The type from the search service
	 * @param $graduate int | The graduate value from the search service
	 * @param $name string | The program name
	 * @return string | The newly formatted type
	 **/
	private function get_program_type( $type, $graduate, $name ) {
		switch( $type ) {
			case 'major':
				if ( $graduate === 0 ) {
					$type = 'Undergraduate Degree';
				} else {
					foreach( $this->doctoral_mapping as $dm ) {
						if ( stripos( $name, $dm ) !== false ) {
							$type = 'Doctorate';
							break;
						}
					}
					if ( $type !== 'Doctorate' ) {
						$type = 'Master';
					}
					break;
				}
				break;
			case 'articulated':
			case 'accelerated':
				$type = ucwords( $type ) . ' Program';
				break;
			default:
				$type = ucwords( $type );
				break;
		}
		return $type;
	}

	/**
	 * Returns the undergraduate catalog program type
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param $type string | The search service program type
	 * @return string | The undergraduate catalog program type.
	 **/
	private function get_uc_program_type( $type ) {
		$uc_type = false;

		switch ( $type ) {
			case 'Minor':
			case 'Certificate':
				$uc_type = $type;
				break;
			case 'Articulated Program':
				$uc_type = 'Statewide Articulated A.S. To B.S. Programs';
				break;
			case 'Accelerated Program':
				$uc_type = 'Accelerated Undergraduate-Graduate Programs';
				break;
			case 'Undergraduate Degree':
			default:
				$uc_type = 'Degree Program';
				break;
		}

		return $uc_type;
	}

	/**
	 * Returns the suffix for the post_name
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param $name string | The program name
	 * @param $type string | The program type
	 * @param $graduate int | If the program is a graduate program
	 * @return string | The program suffix
	 **/
	private function get_program_suffix( $name, $type, $graduate ) {
		$lower_name = strtolower( $name );
		switch( $type ) {
			case 'minor':
				return '-minor';
			case 'certificate':
				if ( stripos( $lower_name, 'certificate' ) === false ) {
					return '-certificate';
				}
			default:
				return '';
		}
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
	 * @param $name string | The college name
	 * @return string | The college slug
	 **/
	private function get_college_slug( $name ) {
		return sanitize_title( $this->get_college_alias( $name ) );
	}

	/**
	 * Returns the alias of the college
	 * @author Jim Barnes
	 * @since 1.1.0
	 * @param name string | The name of the college
	 * @return string
	 **/
	private function get_college_alias( $name ) {
		// Remove "College of"
		$retval = str_replace( 'College of', '', $name );
		// Remove "Rosen"
		$retval = str_replace( 'Rosen', '', $retval );
		// Remove whitespace
		$retval = trim( $retval );
		return $retval;
	}

	/**
	 * Handles matching undergraduate degrees
	 * to the undergraduate catalog data
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $program Object | The program object
	 * @return (string|NULL) | The catalog url if available.
	 **/
	private function get_uc_catalog_url( $program ) {
		$clean_program_name = $this->clean_name( $program->name );
		$clean_college_name = $this->clean_name( $program->college_name );

		if ( $this->catalog_programs ) {

			foreach ( $this->catalog_programs as $key => $uc_program ) {
				$uc_clean_program_name = $this->clean_name( $uc_program->name );
				$uc_clean_type_name    = $this->clean_name( $uc_program->type );

				/**
				 * Check if:
				 * 1) Our program type and the catalog's program type match,
				 * 2) AND, The program names either:
				 *    a) match, or
				 *    b) in cases where the program is Accelerated, either:
				 *        i) our program name is a substring of the catalog
				 *           program name, or
				 *       ii) the catalog program name + type are a substring of
				 *           our program name
				 **/
				if (
					$uc_program->type === $program->type_ucmatch
					&& (
						$clean_program_name === $uc_clean_program_name
						|| (
							$program->type === 'Accelerated Program'
							&& (
								stripos( $clean_program_name, $uc_clean_program_name ) !== false
								|| stripos( $uc_clean_program_name.$uc_clean_type_name, $clean_program_name ) !== false
							)
						)
					)
				) {
					return ( ! empty( $uc_program->pdf ) ) ? $uc_program->pdf : '';
				}
			}

		}

		return '';
	}

	/**
	 * Takes all the information we've generated
	 * up to this point and puts into a format
	 * WordPress can use to create a post.
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param Object | The program object
	 * @return Array | The post array
	 **/
	private function format_post_data( $program ) {
		return array(
			'post_data' => array(
				'post_title'  => $program->name,
				'post_name'   => sanitize_title( $program->name . $program->suffix ),
				'post_status' => 'draft',
				'post_date'   => date( 'Y-m-d H:i:s' ),
				'post_author' => 1,
				'post_type'   => 'degree',
			),
			'post_meta'  => array(
				'degree_id'              => $program->degree_id,
				'degree_type_id'         => $program->type_id,
				'degree_description'     => html_entity_decode( $program->description ),
				'degree_pdf'             => $program->catalog_url,
			),
			'post_terms' => array(
				'program_types' => $program->type,
				'colleges'      => $program->college_name,
				'departments'   => $program->department_name
			)
		);
	}

	/**
	 * Creates or returns the post
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $post_data Array | The array of post data
	 * @param $degree_id int | The degree id
	 * @return int | The post id
	 **/
	private function process_post( $post_data, $degree_id, $degree_type_id=null, $program_types=null ) {
		$retval = null;
		// Attempt to fetch an existing post
		$args = array(
			'post_type'      => $post_data['post_type'],
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'draft' ),
			'meta_query'     => array(
				array(
					'key'   => 'degree_id',
					'value' => $degree_id
				)
			),
			'tax_query'      => array(
				array(
					'taxonomy' => 'program_types',
					'field'    => 'slug',
					'terms'    => sanitize_title( $program_types )
				)
			)
		);
		if ( $degree_type_id ) {
			$args['meta_query'][] = array(
				'key'   => 'degree_type_id',
				'value' => $degree_type_id
			);
		}
		$existing_post = get_posts( $args );
		$existing_post = empty( $existing_post ) ? false : $existing_post[0];

		if ( has_filter( 'ucf_degree_existing_post' ) ) {
			$existing_post = apply_filters( 'ucf_degree_existing_post', $existing_post, $post_data, $program_types );
		}

		if ( $existing_post !== false ) {
			$retval = $existing_post->ID;
			$post_data['ID'] = $retval;
			$post_data['post_status'] = $existing_post->post_status;
			// Remove the post name so we're not updating permalinks
			unset( $post_data['post_name'] );
			// Remove the post date so publish date stays the same.
			unset( $post_data['post_date'] );
			wp_update_post( $post_data );
			// Remove the post from the existing array
			unset( $this->existing_posts[$post_data['ID']] );
			// Added to ensure we have an accurated updated count
			if ( ! isset( $this->updated_posts[$post_data['ID']] ) ) {
				$this->updated_posts[$post_data['ID']] = $post_data['ID'];
				// This is a duplicate if it's in the new posts array
				if ( isset( $this->new_posts[$retval] ) ) {
					$this->duplicate_count++;
				} else {
					$this->existing_count++;
				}
			} else {
				$this->duplicate_count++;
			}
		} else {
			$retval = wp_insert_post( $post_data );
			$this->new_posts[$retval] = $retval;
			$this->new_count++;
		}
		return $retval;
	}
	/**
	 * Creates or updates the post_meta
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $post_id int | The id of the post
	 * @param $post_meta Array | An array of post meta
	 **/
	private function process_post_meta( $post_id, $post_meta ) {
		if ( is_array( $post_meta ) ) {
			foreach( $post_meta as $key => $val ) {
				update_field( $key, $val, $post_id );
			}
		}
	}
	/**
	 * Sets the post terms
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $post_id int | The id of the post
	 * @param $post_terms Array | The array of post terms
	 **/
	private function process_post_terms( $post_id, $post_terms ) {
		if ( is_array( $post_terms ) ) {
			foreach( $post_terms as $tax => $term ) {
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
					if ( gettype( $new_term ) === 'array' ) {
						$term_id = $new_term['term_id'];
					}
				}

				if ( $term_id ) {
					// Set the alias
					wp_set_post_terms( $post_id, $term_id, $tax, true );
				} else {
					wp_delete_object_term_relationships( $post_id, $tax );
				}

				if ( $tax === 'colleges' && class_exists( 'UCF_College_Taxonomy' ) ) {
					$alias = $this->get_college_alias( $term );
					update_term_meta( $term_id, 'colleges_alias', $alias );
				}
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
