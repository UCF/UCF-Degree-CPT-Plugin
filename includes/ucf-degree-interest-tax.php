<?php
/**
 * Handles the registration of the Interest taxonomy
 * @author Jim Barnes
 * @since 3.1.0
 */
if ( ! class_exists( 'UCF_Degree_Interest' ) ) {
    class UCF_Degree_Interest {
        public static
            $labels = array(
                'singular' => 'Area of Interest',
                'plural'   => 'Areas of Interest',
                'slug'     => 'interests'
            );

		/**
		 * Registers the `interests` custom taxonomy
		 * @author Jim Barnes
		 * @since 3.1.0
		 */
        public static function register_interest() {
            $labels = apply_filters( 'ucf_degree_interests_labels', self::$labels );

			register_taxonomy( $labels['slug'], array( 'degree' ), self::args( $labels ) );

			self::register_meta_fields();
        }

		/**
		 * Registers the meta fields and related forms
		 * @author Jim Barnes
		 * @since 3.1.0
		 */
		public static function register_meta_fields() {
			add_action( 'interests_add_form_fields', array( 'UCF_Degree_Interest', 'add_meta_fields' ), 10, 0 );
			add_action( 'interests_edit_form_fields', array( 'UCF_Degree_Interest', 'edit_meta_fields'), 10, 1 );
			add_action( 'created_interests', array( 'UCF_Degree_Interest', 'save_interests_meta' ), 10, 1 );
			add_action( 'edited_interests', array( 'UCF_Degree_Interest', 'edited_interests_meta' ), 10, 1 );
		}

		/**
		 * Forms fields to be added on the Add New Interest form
		 * @author Jim Barnes
		 * @since 3.1.0
		 */
		public static function add_meta_fields() {
?>
			<div class="form-field term-group">
				<label for="interests_display_text"><?php _e( 'Display Text', 'ucf_degree' ); ?></label>
				<input type="text" id="interests_display_text" name="interests_display_text">
				<p class="help-text">The text that will be used when displaying Interests on the front end. If the interst you are adding has a comma, enter that here, not in the <code>Name</code> field above. If no text is provided, the value in the <code>Name</code> field will be used.</p>
			</div>
<?php
		}

		/**
		 * Forms fields to be added on the Edit Interest form
		 * @author Jim Barnes
		 * @since 3.1.0
		 * @param WP_Term $term The term object
		 */
		public static function edit_meta_fields( $term ) {
			$display_name = get_term_meta( $term->term_id, 'interests_display_text', true );
?>
			<tr class="form-field term-group-wrap">
				<th scope="row"><label for="interests_display_text"><?php _e( 'Display Text', 'ucf_degree' ); ?></label>
				<td><input type="text" id="interests_display_text" name="interests_display_text" value="<?php echo $display_name; ?>"></td>
			</tr>
<?php
		}

		/**
		 * Called when a new term is created
		 * @author Jim Barnes
		 * @since 3.1.0
		 * @param int $term_id The id of the newly created term
		 */
		public static function save_interests_meta( $term_id ) {
			$display_text = '';

			if ( isset( $_POST['interests_display_text'] ) && '' !== $_POST['interests_display_text'] ) {
				$display_text = sanitize_text_field( $_POST['interests_display_text'] );
			} else {
				$term = get_term( $term_id );
				$display_text = ucf_degree_capitalize_title( $term->name );
			}

			add_term_meta( $term_id, 'interests_display_text', $display_text );
		}

		/**
		 * Called when a term is updated
		 * @author Jim Barnes
		 * @since 3.1.0
		 * @param int $term_id The id of the term being updated
		 */
		public static function edited_interests_meta( $term_id ) {
			$display_text = '';

			if ( isset( $_POST['interests_display_text'] ) && '' !== $_POST['interests_display_text'] ) {
				$display_text = sanitize_text_field( $_POST['interests_display_text'] );
			} else {
				$term = get_term( $term_id );
				$display_text = ucf_degree_capitalize_title( $term->name );
			}

			update_term_meta( $term_id, 'interests_display_text', $display_text );
		}

		/**
		 * Retrieves the array of labels use in
		 * registering the custom taxonomy
		 * @author Jim Barnes
		 * @since 3.1.0
		 * @param array $labels The array of singular, plural and slug labels
		 * @return array
		 */
        public static function labels( $labels ) {
            $singular = $labels['singular'];
            $plural   = $labels['plural'];

            return array(
				'name'                       => _x( $plural, 'Taxonomy General Name', 'ucf_degree' ),
				'singular_name'              => _x( $singular, 'Taxonomy Singular Name', 'ucf_degree' ),
				'menu_name'                  => __( $plural, 'ucf_degree' ),
				'all_items'                  => __( 'All ' . $plural, 'ucf_degree' ),
				'parent_item'                => __( 'Parent ' . $singular, 'ucf_degree' ),
				'parent_item_colon'          => __( 'Parent ' . $singular . ':', 'ucf_degree' ),
				'new_item_name'              => __( 'New ' . $singular . ' Name', 'ucf_degree' ),
				'add_new_item'               => __( 'Add New ' . $singular, 'ucf_degree' ),
				'edit_item'                  => __( 'Edit ' . $singular, 'ucf_degree' ),
				'update_item'                => __( 'Update ' . $singular, 'ucf_degree' ),
				'view_item'                  => __( 'View ' . $singular, 'ucf_degree' ),
				'separate_items_with_commas' => __( 'Separate ' . $plural . 'with commas', 'ucf_degree' ),
				'add_or_remove_items'        => __( 'Add or remove ' . $plural, 'ucf_degree' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'ucf_degree' ),
				'popular_items'              => __( 'Popular ' . $plural, 'ucf_degree' ),
				'search_items'               => __( 'Search ' . $plural, 'ucf_degree' ),
				'not_found'                  => __( 'Not Found', 'ucf_degree' ),
				'no_terms'                   => __( 'No items', 'ucf_degree' ),
				'items_list'                 => __( $plural . ' list', 'ucf_degree' ),
				'items_list_navigation'      => __( $plural . 'list navigation', 'ucf_degree' ),
			);
        }

		/**
		 * Returns the args array for registering
		 * the custom taxonomy
		 * @author Jim Barnes
		 * @since 3.1.0
		 * @param array $labels The array of singular, plural and slug labels
		 * @return array
		 */
        public static function args( $labels ) {
            $retval = array(
				'labels'                     => self::labels( $labels ),
				'hierarchical'               => false,
				'public'                     => true,
				'show_ui'                    => true,
				'show_admin_column'          => true,
				'show_in_nav_menus'          => true,
				'show_tagcloud'              => true,
			);

			$retval = apply_filters( 'ucf_degree_interests_taxonomy_args', $retval );

			return $retval;
        }
    }
}