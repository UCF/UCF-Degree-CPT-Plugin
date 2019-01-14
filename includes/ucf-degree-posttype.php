<?php
/**
 * Handles the registration of the degree custom post type.
 * @author Jim Barnes
 * @since 0.0.1
 **/
if ( ! class_exists( 'UCF_Degree_PostType' ) ) {
	class UCF_Degree_PostType {
		public static function register_degree_posttype() {
			register_post_type( 'degree', self::args() );
			add_action( 'add_meta_boxes', array( 'UCF_Degree_PostType', 'register_metabox' ) );
			add_action( 'save_post', array( 'UCF_Degree_PostType', 'save_metabox' ) );
		}

		/**
		 * Registers the metabox for degrees
		 * @author Jim Barnes
		 * @since 1.1.0
		 **/
		public static function register_metabox() {
			add_meta_box(
				'ucf_degree_import_metabox',
				'Degree Import Options',
				array( 'UCF_Degree_PostType', 'register_import_metafields' ),
				'degree',
				'normal',
				'low'
			);
		}

		/**
		 * Adds the metafields for the import fields
		 * @author Jim Barnes
		 * @since 1.1.0
		 * @param $post WP_POST | The post object
		 * @return string | The html markup for the metafields
		 **/
		public static function register_import_metafields( $post ) {
			$update_tuition = UCF_Degree_Config::get_option_or_default( 'update_tuition' );

			wp_nonce_field( 'ucf_degree_import_nonce_save', 'ucf_degree_import_nonce' );
			$ignore = get_post_meta( $post->ID, 'degree_import_ignore', true );
			if ( $update_tuition ) {
				$skip   = get_post_meta( $post->ID, 'degree_tuition_skip', true );
			}
?>
			<table class="form-table">
				<tbody>
					<tr>
						<th><label class="block" for="degree_import_ignore"><strong>Ignore On Import</label></th>
						<td>
							<p class="description">When checked, the degree will not be updated or removed on import.</p>
							<input type="checkbox" name="degree_import_ignore" id="degree_import_ignore"<?php echo $ignore === 'on' ? ' checked' : ''; ?>>
						</td>
					</tr>
					<?php if ( $update_tuition ) : ?>
					<tr>
						<th><label class="block" for="degree_tuition_skip"><strong>Skip Tuition</strong></label></th>
						<td>
							<p class="description">When checked, tuition data will not be imported and will be set to null.</p>
							<input type="checkbox" name="degree_tuition_skip" id="degree_tuition_skip"<?php echo $skip === 'on' ? ' checked' : ''; ?>>
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
<?php
		}

		/**
		 * Handles saving metafield data
		 * @author Jim Barnes
		 * @since 1.1.0
		 * @param $post_id int | The id of the post being saved
		 **/
		public static function save_metabox( $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( 'degree' !== $post_type ) return;

			if ( isset( $_POST['degree_import_ignore'] ) ) {
				$ignore = $_POST['degree_import_ignore'];
			} else {
				$ignore = 'off';
			}

			update_post_meta( $post_id, 'degree_import_ignore', $ignore );

			if ( isset( $_POST['degree_tuition_skip'] ) ) {
				$skip = $_POST['degree_tuition_skip'];
			} else {
				$skip = 'off';
			}

			$current_skip_value = get_post_meta( $post_id, 'degree_tuition_skip', true );

			update_post_meta( $post_id, 'degree_tuition_skip', $skip );

			// Only make these API calls if there is a new value.
			if ( $current_skip_value !== $skip ) {
				$skip_bool = $skip === 'on' ? true : false;
				// Call common function to update value
				UCF_Degree_Common::add_tuition_exception( $post_id, $skip_bool );
			}
		}

		/**
		 * Returns the labels array
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @return array
		 */
		public static function labels() {
			return array(
				'name'                  => _x( 'Degrees', 'Post Type General Name', 'ucf_degree' ),
				'singular_name'         => _x( 'Degree', 'Post Type Singular Name', 'ucf_degree' ),
				'menu_name'             => __( 'Degrees', 'ucf_degree' ),
				'name_admin_bar'        => __( 'Degree', 'ucf_degree' ),
				'archives'              => __( 'Degree Archives', 'ucf_degree' ),
				'parent_item_colon'     => __( 'Parent Degree:', 'ucf_degree' ),
				'all_items'             => __( 'All Degrees', 'ucf_degree' ),
				'add_new_item'          => __( 'Add New Degree', 'ucf_degree' ),
				'add_new'               => __( 'Add New', 'ucf_degree' ),
				'new_item'              => __( 'New Degree', 'ucf_degree' ),
				'edit_item'             => __( 'Edit Degree', 'ucf_degree' ),
				'update_item'           => __( 'Update Degree', 'ucf_degree' ),
				'view_item'             => __( 'View Degree', 'ucf_degree' ),
				'search_items'          => __( 'Search Degrees', 'ucf_degree' ),
				'not_found'             => __( 'Not found', 'ucf_degree' ),
				'not_found_in_trash'    => __( 'Not found in Trash', 'ucf_degree' ),
				'featured_image'        => __( 'Featured Image', 'ucf_degree' ),
				'set_featured_image'    => __( 'Set featured image', 'ucf_degree' ),
				'remove_featured_image' => __( 'Remove featured image', 'ucf_degree' ),
				'use_featured_image'    => __( 'Use as featured image', 'ucf_degree' ),
				'insert_into_item'      => __( 'Insert into degree', 'ucf_degree' ),
				'uploaded_to_this_item' => __( 'Uploaded to this degree', 'ucf_degree' ),
				'items_list'            => __( 'Degrees list', 'ucf_degree' ),
				'items_list_navigation' => __( 'Degrees list navigation', 'ucf_degree' ),
				'filter_items_list'     => __( 'Filter degrees list', 'ucf_degree' ),
			);
		}

		/**
		 * Returns the argument array
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @return array
		 */
		public static function args() {
			$args = array(
				'label'                 => __( 'Degree', 'ucf_degree' ),
				'description'           => __( 'Degree Programs', 'ucf_degree' ),
				'labels'                => self::labels(),
				'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
				'taxonomies'            => self::taxonomies(),
				'hierarchical'          => true,
				'public'                => true,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'menu_position'         => 5,
				'menu_icon'             => 'dashicons-welcome-learn-more',
				'show_in_admin_bar'     => true,
				'show_in_nav_menus'     => true,
				'can_export'            => true,
				'has_archive'           => true,
				'exclude_from_search'   => false,
				'publicly_queryable'    => true,
				'capability_type'       => 'post',
			);

			$args = apply_filters( 'ucf_degree_post_type_args', $args );

			return $args;
		}

		/**
		 * Returns the array of taxonomies that will be associated
		 * with the custom post type.
		 * @author Jim Barnes
		 * @since 0.0.1
		 * @return array
		 */
		public static function taxonomies() {
			$retval = array();
			$valid_taxonomies = array(
				'post_tag',
				'program_types',
				'colleges',
				'career_paths',
				'interests'
			);
			$valid_taxonomies = apply_filters( 'ucf_degree_taxonomies', $valid_taxonomies );

			foreach( $valid_taxonomies as $taxonomy ) {
				if ( taxonomy_exists( $taxonomy ) ) {
					$retval[] = $taxonomy;
				}
			}

			return $retval;
		}
	}
}

?>
