<?php
/**
 * Registers UCF Degrees options
 **/
if ( ! class_exists( 'UCF_Degree_Config' ) ) {
	class UCF_Degree_Config {
		public static
			$option_prefix = 'ucf_degree_',
			$options_defaults = array(
				'rest_api'           => false,
				'schedule_importer'  => false,
				'import_schedule'    => 'weekly',
				'search_filter'      => '',
				'api_base_url'       => 'https://search.cm.ucf.edu/api/v1/',
				'api_key'            => null,
				'update_desc'        => true,
				'update_prof'        => true,
				'desc_type'          => null,
				'prof_type'          => null
			);

		/**
		 * Fetches the ProgramDescriptionTypes from the
		 * search service.
		 * @return array The description types
		 */
		public static function get_description_types() {
			$transient_name    = 'ucf_degree_description_types';
			$transient_timeout = DAY_IN_SECONDS;
			$retval            = get_transient( $transient_name );

			if ( ! $retval ) {
				$base_url    = self::get_option_or_default( 'api_base_url' );
				$request_url = $base_url . 'descriptions/types/';

				$items = UCF_Degree_Common::fetch_api_values( $request_url );

				if ( $items ) {
					$retval = array();

					foreach( $items as $item ) {
						$retval[$item->id] = $item->name;
					}

					set_transient( $transient_name, $retval, $transient_timeout );
				}
			}

			return $retval;
		}

		/**
		 * Fetches the ProgramProfileTypes from the
		 * search service.
		 * @return array The profile types
		 */
		public static function get_profile_types() {
			$transient_name    = 'ucf_degree_profile_types';
			$transient_timeout = DAY_IN_SECONDS;
			$retval            = get_transient( $transient_name );

			if ( ! $retval ) {
				$base_url       = self::get_option_or_default( 'api_base_url' );
				$request_url = $base_url . 'profiles/types/';

				$items = UCF_Degree_Common::fetch_api_values( $request_url );

				if ( $items ) {
					$retval = array();

					foreach( $items as $item ) {
						$retval[$item->id] = $item->name;
					}

					set_transient( $transient_name, $retval, $transient_timeout );
				}
			}

			return $retval;
		}

		/**
		 * Determines if the WP Rest API is installed and activated
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @return bool
		 **/
		public static function rest_api_enabled() {
			return class_exists( 'WP_REST_Posts_Controller' );
		}

		/**
		 * Text to display if rest api is not enabled
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @return void | echos the content
		 **/
		public static function rest_api_message() {
			ob_start();
			if ( ! self::rest_api_enabled() ) :
		?>
			<p class="notice notice-info" style="padding: 24px 12px;">
				<span class="dashicons dashicons-info" style="color: #00a0d2;"></span> The UCF Degree Rest API routes require the <a href="https://wordpress.org/plugins/rest-api/" target="_blank"> WP Rest API v2 Plugin</a> to be installed.
			</p>
		<?php
			endif;
			echo ob_get_clean();
		}

		/**
		 * Creates options via the WP Options API that are utilized by the
		 * plugin.  Intended to be run on plugin activation.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @return void
		 **/
		public static function add_options() {
			$defaults = self::$options_defaults;

			add_option( self::$option_prefix . 'rest_api', $defaults['rest_api'] );
			add_option( self::$option_prefix . 'schedule_importer', $defaults['schedule_importer'] );
			add_option( self::$option_prefix . 'import_schedule', $defaults['import_schedule'] );
			add_option( self::$option_prefix . 'search_filter', $defaults['search_filter'] );
			add_option( self::$option_prefix . 'api_base_url', $defaults['api_base_url'] );
			add_option( self::$option_prefix . 'api_key', $defaults['api_key'] );
			add_option( self::$option_prefix . 'update_desc', $defaults['update_desc'] );
			add_option( self::$option_prefix . 'update_prof', $defaults['update_prof'] );
			add_option( self::$option_prefix . 'desc_type', $defaults['desc_type'] );
			add_option( self::$option_prefix . 'prof_type', $defaults['prof_type'] );
		}

		/**
		 * Deletes options via the WP Options API that are utilized by the
		 * plugin.  Intended to be run on plugin uninstallation.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @return void
		 **/
		public static function delete_options() {
			delete_option( self::$option_prefix . 'rest_api' );
			delete_option( self::$option_prefix . 'schedule_importer' );
			delete_option( self::$option_prefix . 'import_schedule' );
			delete_option( self::$option_prefix . 'search_filter' );
			delete_option( self::$option_prefix . 'api_base_url' );
			delete_option( self::$option_prefix . 'api_key' );
			delete_option( self::$option_prefix . 'update_desc' );
			delete_option( self::$option_prefix . 'update_prof' );
			delete_option( self::$option_prefix . 'desc_type' );
			delete_option( self::$option_prefix . 'prof_type' );
		}

		/**
		 * Returns a list of default plugin options. Applies any overridden
		 * default values set within the options page.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @return array
		 **/
		public static function get_option_defaults() {
			$defaults = self::$options_defaults;

			$configurable_defaults = array(
				'rest_api'           => get_option( self::$option_prefix . 'rest_api' ),
				'schedule_importer'  => get_option( self::$option_prefix . 'schedule_importer' ),
				'import_schedule'    => get_option( self::$option_prefix . 'import_schedule' ),
				'college_filter'     => get_option( self::$option_prefix . 'search_filter' ),
				'api_base_url'       => get_option( self::$option_prefix . 'api_base_url', $defaults['api_base_url'] ),
				'api_key'            => get_option( self::$option_prefix . 'api_key', $defaults['api_key'] ),
				'update_desc'        => get_option( self::$option_prefix . 'update_desc', $defaults['update_desc'] ),
				'update_prof'        => get_option( self::$option_prefix . 'update_prof', $defaults['update_prof'] ),
				'desc_type'          => get_option( self::$option_prefix . 'desc_type', $defaults['desc_type'] ),
				'prof_type'          => get_option( self::$option_prefix . 'prof_type', $defaults['prof_type'] )
			);

			$configurable_defaults = self::format_options( $configurable_defaults );

			$defaults = array_merge( $defaults, $configurable_defaults );

			return $defaults;
		}

		/**
		 * Returns an array with plugin defaults applied.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @param array $list
		 * @param boolean $list_keys_only Modifies results to only return array key
		 *                                values present in $list.
		 * @return array
		 **/
		public static function apply_option_defaults( $list, $list_keys_only=false ) {
			$defaults = self::get_option_defaults();
			$options = array();

			if ( $list_keys_only ) {
				foreach( $list as $key => $val ) {
					$options[$key] = ! empty( $val ) ? $val : $defaults[$key];
				}
			} else {
				$options = array_merge( $defaults, $list );
			}

			$options = self::format_options( $options );

			return $options;
		}

		/**
		 * Performs typecasting, sanitization, etc on an array of plugin options.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @param array $list
		 * @return array
		 **/
		public static function format_options( $list ) {
			foreach( $list as $key => $val ) {
				switch ( $key ) {
					case 'rest_api':
					case 'schedule_importer':
					case 'update_desc':
					case 'update_prof':
						$list[$key] = filter_var( $val, FILTER_VALIDATE_BOOLEAN );
						break;
					case 'desc_type':
					case 'prof_type':
						$list[$key] = filter_var( $val, FILTER_VALIDATE_INT );
						break;
					case 'api_base_url':
						$list[$key] = trailingslashit( $val );
						break;
					default:
						break;
				}
			}

			return $list;
		}

		/**
		 * Applies formatting to a single option. Intended to be passed to the
		 * option_{$option} hook.
		 * @param mixed $value | The value to be formatted
		 * @param string $option_name | The name of the option to be formatted
		 * @return mixed
		 */
		public static function format_option( $value, $option_name ) {
			$option_name_no_prefix = str_replace( self::$option_prefix, '', $option_name );

			$option_formatted = self::format_options( array( $option_name_no_prefix => $value ) );
			return $option_formatted[$option_name_no_prefix];
		}

		/**
		 * Adds filters for plugin options that apply
		 * our formatting rules to option values.
		 * @return void
		 */
		public static function add_option_formatting_filters() {
			$defaults = self::$options_defaults;

			foreach( $defaults as $option => $default ) {
				$option_name = self::$option_prefix . $option;
				add_filter( "option_{$option_name}", array( 'UCF_Degree_Config', 'format_option' ), 10, 2 );
			}
		}

		/**
		 * Convenience method for returning an option from the WP Options API
		 * or a plugin option default.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @param $option_name
		 * @return mixed
		 **/
		public static function get_option_or_default( $option_name ) {
			$option_name_no_prefix = str_replace( self::$option_prefix, '', $option_name );
			$option_name = self::$option_prefix . $option_name_no_prefix;

			$option = get_option( $option_name );
			$option_formatted = self::apply_option_defaults( array(
				$option_name_no_prefix => $option
			), true );

			return $option_formatted[$option_name_no_prefix];
		}

		/**
		 * Initializes setting registration with the Settings API.
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @return void
		 **/
		public static function settings_init() {
			$settings_slug = 'ucf_degree';
			$defaults      = self::$options_defaults;
			$display_fn    = array( 'UCF_Degree_Config', 'display_settings_field' );

			foreach( $defaults as $name => $value ) {
				register_setting(
					$settings_slug,
					self::$option_prefix . $name
				);
			}


			// Register sections
			add_settings_section(
				'ucf_degree_section_rest_api',
				'Rest API',
				array( 'UCF_Degree_Config', 'rest_api_message' ),
				$settings_slug
			);

			add_settings_section(
				'ucf_degree_section_importer',
				'Degree Importer',
				null,
				$settings_slug
			);

			$service_section = 'ucf_degree_search_service';

			add_settings_section(
				$service_section,
				'Search Service Settings',
				'',
				$settings_slug
			);

			$description_section = 'ucf_degree_description';

			add_settings_section(
				$description_section,
				'Description Updates',
				'',
				$settings_slug
			);

			$profile_section = 'ucf_degree_profile';

			add_settings_section(
				$profile_section,
				'Profile Updates',
				'',
				$settings_slug
			);


			// Register API settings
			if ( self::rest_api_enabled() ) {
				add_settings_field(
					self::$option_prefix . 'rest_api',
					'Use Rest API',
					array( 'UCF_Degree_Config', 'display_settings_field' ),
					$settings_slug,
					'ucf_degree_section_rest_api',
					array(
						'label_for'   => self::$option_prefix . 'rest_api',
						'description' => 'Enables the rest api route /degrees/ using the WP Rest API plugin',
						'type'        => 'checkbox'
					)
				);
			}

			// Register Importer Settings
			add_settings_field(
				self::$option_prefix . 'schedule_importer',
				'Schedule Degree Importers',
				array( 'UCF_Degree_Config', 'display_settings_field' ),
				$settings_slug,
				'ucf_degree_section_importer',
				array(
					'label_for'   => self::$option_prefix . 'schedule_importer',
					'description' => 'If checked, the degree importer will run on the specified schedule.',
					'type'        => 'checkbox'
				)
			);

			add_settings_field(
				self::$option_prefix . 'import_schedule',
				'Degree Import Frequency',
				array( 'UCF_Degree_Config', 'display_settings_field' ),
				$settings_slug,
				'ucf_degree_section_importer',
				array(
					'label_for'   => self::$option_prefix . 'import_schedule',
					'description' => 'Determines how often the degree importer runs.',
					'type'        => 'select',
					'options'       => array(
						''          => '-- Select Frequency --',
						'daily'     => 'Daily',
						'weekly'    => 'Weekly',
						'bi-weekly' => 'Bi-weekly',
						'monthly'   => 'Monthly'
					)
				)
			);

			add_settings_field(
				self::$option_prefix . 'search_filter',
				'Search Filter',
				array( 'UCF_Degree_Config', 'display_settings_field' ),
				$settings_slug,
				'ucf_degree_section_importer',
				array(
					'label_for'   => self::$option_prefix . 'search_filter',
					'description' => 'Additional query parameters to send to the Search Service when importing degrees.',
					'type'        => 'text'
				)
			);

			/**
			 * Register `General Settings`
			 */
			add_settings_field(
				self::$option_prefix . 'api_base_url', // Setting name
				'Search Service Base URL', // Setting display name
				$display_fn, // Display function
				$settings_slug, // The settings page slug
				$service_section,
				array( // Additional arguments to pass to the display function
					'label_for'   => self::$option_prefix . 'api_base_url',
					'description' => 'The base url of the UCF Search Service API. Should end with `/api/v1/` with trailing slash.',
					'type'        => 'text'
				)
			);

			add_settings_field(
				self::$option_prefix . 'api_key', // Setting name
				'Search Service API Key', // Setting display name
				$display_fn, // Display function
				$settings_slug, // The settings page slug
				$service_section,
				array( // Additional arguments to pass to the display function
					'label_for'   => self::$option_prefix . 'api_key',
					'description' => 'The API key used to access the Search Service API. This is required for all calls.',
					'type'        => 'text'
				)
			);

			/**
			 * Register `Update Descriptions` settings
			 */
			add_settings_field(
				self::$option_prefix . 'update_desc', // Setting name
				'Update Descriptions', // Setting display name
				$display_fn, // Display function
				$settings_slug, // The settings page slug
				$description_section,
				array( // Additional arguments to pass to the display function
					'label_for'   => self::$option_prefix . 'update_desc',
					'description' => 'When checked, descriptions will be written to the UCF Search Service on post save.',
					'type'        => 'checkbox'
				)
			);

			add_settings_field(
				self::$option_prefix . 'desc_type', // Setting name
				'Description Type', // Setting display name
				$display_fn, // Display function
				$settings_slug, // The settings page slug
				$description_section,
				array( // Additional arguments to pass to the display function
					'label_for'   => self::$option_prefix . 'desc_type',
					'description' => 'The description type to set when writing to the Search Service.',
					'type'        => 'select',
					'options'     => self::get_description_types()
				)
			);

			/**
			 * Register `Update Profiles` settings
			 */
			add_settings_field(
				self::$option_prefix . 'update_prof', // Setting name
				'Update Profile URLs', // Setting display name
				$display_fn, // Display function
				$settings_slug, // The settings page slug
				$profile_section,
				array( // Additional arguments to pass to the display function
					'label_for'   => self::$option_prefix . 'update_prof',
					'description' => 'When checked, profile URLs will be written to the UCF Search Service on post save.',
					'type'        => 'checkbox'
				)
			);

			add_settings_field(
				self::$option_prefix . 'prof_type', // Setting name
				'Profile Type', // Setting display name
				$display_fn, // Display function
				$settings_slug, // The settings page slug
				$profile_section,
				array( // Additional arguments to pass to the display function
					'label_for'   => self::$option_prefix . 'prof_type',
					'description' => 'The profile type to set when writing to the Search Service.',
					'type'        => 'select',
					'options'     => self::get_profile_types()
				)
			);
		}

		/**
		 * Displays an individual setting's field markup.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @return string | The formatted html of the field
		 **/
		public static function display_settings_field( $args ) {
			$option_name   = $args['label_for'];
			$description   = $args['description'];
			$field_type    = $args['type'];
			$options       = isset ( $args['options'] ) ? $args['options'] : null;
			$current_value = self::get_option_or_default( $option_name );
			$markup        = '';

			switch( $field_type ) {
				case 'checkbox':
					ob_start();
				?>
					<input type="checkbox" id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>" <?php echo ( $current_value == true ) ? 'checked' : ''; ?>>
					<p class="description">
						<?php echo $description; ?>
					</p>
				<?php
					break;
				case 'number':
					ob_start();
				?>
					<input type="number" id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>" value="<?php echo $current_value; ?>">
					<p class="description">
						<?php echo $description; ?>
					</p>
				<?php
					$markup = ob_get_clean();
					break;
				case 'select':
					ob_start();
				?>
					<?php if ( $options ) : ?>
					<select id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>">
						<?php foreach ( $options as $value => $text ) : ?>
							<option value="<?php echo $value; ?>" <?php echo ( (int)$current_value === $value ) ? 'selected' : ''; ?>><?php echo $text; ?></option>
						<?php endforeach; ?>
					</select>
					<?php else: ?>
					<p style="color: #d54e21;">There was an error retrieving the choices for this field.</p>
					<?php endif; ?>
					<p class="description">
						<?php echo $description; ?>
					</p>
				<?php
					$markup = ob_get_clean();
					break;
				case 'text':
				default:
					ob_start();
				?>
					<input type="text" id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>" value="<?php echo $current_value; ?>">
					<p class="description">
						<?php echo $description; ?>
					</p>
				<?php
					$markup = ob_get_clean();
					break;
			}

			echo $markup;
		}

		/**
		 * Registers the settings page to display in the WordPress admin.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @return string | The resulting page's hook_suffix
		 **/
		public static function add_options_page() {
			$page_title = 'UCF Degree Settings';
			$menu_title = 'UCF Degree';
			$capability = 'manage_options';
			$menu_slug  = 'ucf_degree';
			$callback   = array( 'UCF_Degree_Config', 'options_page_html' );

			return add_options_page(
				$page_title,
				$menu_title,
				$capability,
				$menu_slug,
				$callback
			);
		}

		/**
		 * Displays the plugin's settings page form.
		 * @author Jo Dickson
		 * @since 0.0.1
		 * @return void | echoes output
		 **/
		public static function options_page_html() {
			ob_start();
		?>
			<div class="wrap">
				<h1><?php echo get_admin_page_title(); ?></h1>
				<form method="post" action="options.php">
					<?php
						settings_fields( 'ucf_degree' );
						do_settings_sections( 'ucf_degree' );
						submit_button();
					?>
				</form>
			</div>
		<?php
			echo ob_get_clean();
		}
	}

	add_action( 'admin_init', array( 'UCF_Degree_Config', 'settings_init' ) );
	add_action( 'admin_menu', array( 'UCF_Degree_Config', 'add_options_page' ) );
	UCF_Degree_Config::add_option_formatting_filters();
}

?>
