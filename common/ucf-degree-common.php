<?php
/**
 * Common Functions
 */
if ( ! class_exists( 'UCF_Degree_Common' ) ) {
	class UCF_Degree_Common {
		/**
		 * Returns a JSON object from the provided URL.  Detects undesirable status
		 * codes and returns false if the response doesn't look valid.
		 *
		 * @since 3.0.0
		 * @author Jo Dickson
		 * @param string $url URL that points to a JSON object/feed
		 * @return mixed JSON-decoded object or false on failure
		 */
		public static function fetch_json( $url ) {
			$response      = wp_remote_get( $url, array( 'timeout' => 5 ) );
			$response_code = wp_remote_retrieve_response_code( $response );
			$result        = false;

			/**
			 * All good responses should have a response code
			 * that is less than 400.
			 */
			if ( is_array( $response ) && is_int( $response_code ) && $response_code < 400 ) {
				$result = json_decode( wp_remote_retrieve_body( $response ) );
			}

			return $result;
		}

		/**
		 * Retrieves a return value via an HTTP Request
		 * @param string $url | The url of the API endpoint
		 * @param array $args | The argument array
		 * @return mixed The returned value
		 */
		private static function fetch_api_response( $url, $params ) {
			if ( ! array_key_exists( 'key', $params ) ) {
				$params['key'] = UCF_Degree_Config::get_option_or_default( 'api_key' );
			}

			$url = add_query_arg( $params, $url );

			return self::fetch_json( $url );
		}

		/**
		 * Retrieves a scalar value via an HTTP Request
		 * @param string $url | The url of the API endpoint
		 * @param array $args | The argument array
		 * @return mixed The returned value
		 */
		public static function fetch_api_value( $url, $params=array() ) {
			$retval = self::fetch_api_response( $url, $params );

			/**
			 * All responses are paged by default, so results
			 * include a `results` object. If the request returned
			 * an error, $retval will be false.
			 */
			return $retval;
		}

		/**
		 * Retrieves values via an HTTP Request
		 * @param string $url | The url of the API endpoint
		 * @param array $args | The argument array
		 * @return mixed The returned value
		 */
		public static function fetch_api_values( $url, $params=array() ) {
			$retval = self::fetch_api_response( $url, $params );

			/**
			 * All responses are paged by default, so results
			 * include a `results` object. If the request returned
			 * an error, $retval will be false.
			 */
			if ( ! $retval ) {
				return $retval;
			}

			return $retval->results;
		}

		/**
		 * Updates the search service with description
		 * and profile based on config options.
		 * @param int $post_id | The id of the post to update
		 * @param int $program_id | The id of the program in the Search Service (optional)
		 */
		public static function update_service_values( $post_id, $program_id=null ) {
			// Get plancode and subplan code
			$plan_code = get_post_meta( $post_id, 'degree_plan_code', true );
			$subplan_code = get_post_meta( $post_id, 'degree_subplan_code', true );

			$subplan_code = empty( $subplan_code ) ? null : $subplan_code;
			$result = null;

			$base_url = UCF_Degree_Config::get_option_or_default( 'api_base_url' );
			$endpoint = 'programs/';

			if ( $program_id ) {
				$endpoint .= $program_id . '/';
				$result = self::fetch_api_value( $endpoint );
			}

			if ( ! $result ) {
				$params = array(
					'plan_code' => $plan_code,
				);

				if ( $subplan_code ) {
					$params['subplan_code'] = $subplan_code;
				} else {
					$params['subplan_code__isnull'] = True;
				}

				$base_url = UCF_Degree_Config::get_option_or_default( 'api_base_url' );
				$endpoint = $base_url . 'programs/search/';

				$results = self::fetch_api_values( $endpoint, $params );
				$result = self::return_verified_result( $results, $params );
			}

			if ( $result ) {

				if ( UCF_Degree_Config::get_option_or_default( 'update_desc' ) ) {
					self::update_description( $post_id, $result );
				}

				if ( UCF_Degree_Config::get_option_or_default( 'update_prof' ) ) {
					self::update_profile( $post_id, $result );
				}
			}
		}

		/**
		 * Verifies the result returned matches plancode and subplan.
		 * @param array $results | The result array
		 * @param array $params | The parameter array
		 * @return object | The result
		 */
		private static function return_verified_result( $results, $params ) {
			$plan_code = $params['plan_code'];
			$subplan_code = isset( $params['plan_code'] ) ? $params['plan_code'] : null;

			foreach( $results as $result ) {
				if (
					$result->plan_code === $plan_code &&
					$result->subplan_code === $subplan_code
				) {
					return $result;
				}
			}

			return false;
		}

		/**
		 * Updates the description in the search service
		 * @param int $post_id | The id of the post
		 * @param object $result | The matched API object
		 * @return bool True if the entry was successfully created or updated
		 */
		private static function update_description( $post_id, $result ) {
			$desc_type = (int)UCF_Degree_Config::get_option_or_default( 'desc_type' );
			$base_url  = UCF_Degree_Config::get_option_or_default( 'api_base_url' );
			$key       = UCF_Degree_Config::get_option_or_default( 'api_key' );
			$match     = false;

			$update_url = false;

			foreach( $result->descriptions as $description ) {
				if ( $description->description_type->id === $desc_type ) {
					$match = $description;
				}
			}

			$post = get_post( $post_id );

			$post_content = $post->post_content;

			if ( $match ) {
				$request_body = array(
					'description_type' => $match->description_type,
					'description' => $post_content,
					'primary' => $match->primary,
					'program' => $result->id
				);

				$args = array(
					'method'      => 'PUT',
					'timeout'     => 5,
					'redirection' => 2,
					'body'        => $request_body
				);

				$url = $match->update_url . '?' . http_build_query( array(
						'key' => $key
					)
				);

				$response = wp_remote_request( $url, $args );

				$response_body = json_decode( wp_remote_retrieve_body( $response ) );

				if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ) {
					return true;
				}
			} else {
				$request_body = array(
					'description_type' => $desc_type,
					'description' => $post_content,
					'primary' => false,
					'program' => $result->id
				);

				$args = array(
					'timeout'     => 5,
					'redirection' => 2,
					'body' => $request_body
				);

				$url = $base_url . 'descriptions/create/?' . http_build_query( array(
						'key' => $key
					)
				);

				$response = wp_remote_post( $url, $args );

				if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Updates the profile in the search service
		 * @param int $post_id | The id of the post
		 * @param object $result | The matched API object
		 * @return void
		 */
		private static function update_profile( $post_id, $result ) {
			$prof_type = (int)UCF_Degree_Config::get_option_or_default( 'prof_type' );
			$base_url  = UCF_Degree_Config::get_option_or_default( 'api_base_url' );
			$key       = UCF_Degree_Config::get_option_or_default( 'api_key' );
			$match     = false;

			$update_url = false;

			foreach( $result->profiles as $profile ) {
				if ( $profile->profile_type->id === $prof_type ) {
					$match = $profile;
				}
			}

			$permalink = get_permalink( $post_id );

			if ( $match ) {
				$request_body = array(
					'profile_type' => $match->profile_type,
					'url' => $permalink,
					'primary' => $match->primary,
					'program' => $result->id
				);

				$args = array(
					'method'      => 'PUT',
					'timeout'     => 5,
					'redirection' => 2,
					'body'        => $request_body
				);

				$url = $match->update_url . '?' . http_build_query( array(
						'key' => $key
					)
				);

				$response = wp_remote_request( $url, $args );

				if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ) {
					return true;
				}
			} else {
				$request_body = array(
					'profile_type' => $prof_type,
					'url' => $permalink,
					'primary' => false,
					'program' => $result->id
				);

				$args = array(
					'timeout'     => 5,
					'redirection' => 2,
					'body' => $request_body
				);

				$url = $base_url . 'profiles/create/?' . http_build_query( array(
						'key' => $key
					)
				);

				$response = wp_remote_post( $url, $args );

				if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * The entry point for the `post_save` hook.
		 * @param int $post_id | The id of the post being saved.
		 */
		public static function on_save_post( $post_id ) {
			// Don't run anything on revision saves
			if ( wp_is_post_revision( $post_id ) )
				return;

			self::update_service_values( $post_id );
		}
	}
}
