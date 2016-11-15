<?php
/** 
 * Handles the registration of the Program type taxonomy
 * @author Jim Barnes
 * @since 0.0.1
 **/
if ( ! class_exists( 'UCF_Degree_ProgramType' ) ) {
	class UCF_Degree_ProgramType {
		public static function register_programtype() {
			register_taxonomy( 'program_types', array( 'degree' ), self::args() );
			self::register_meta_fields();
		}

		public static function register_meta_fields() {
			add_action( 'program_types_add_form_fields', array( 'UCF_Degree_ProgramType', 'add_program_types_fields' ), 10, 1 );
			add_action( 'program_types_edit_form_fields', array( 'UCF_Degree_ProgramType', 'edit_program_types_fields' ), 10, 2 );
			add_action( 'created_program_types', array( 'UCF_Degree_ProgramType', 'save_program_types_meta' ), 10, 2 );
			add_action( 'edited_program_types', array( 'UCF_Degree_ProgramType', 'edited_program_types_meta' ), 10, 2 );
		}

		public static function add_program_types_fields( $taxonomy ) {
?>
			<div class="form-field term-group">
				<label for="program_types_alias"><?php _e( 'Program Type Alias', 'ucf_degree' ); ?></label>
				<input type="text" id="program_types_alias" name="program_type_alias">
			</div>
			<div class="form-field term-group">
				<label for="program_types_color"><?php _e( 'Program Type Color', 'ucf_degree' ); ?></label>
				<input class="wp-color-field" type="text" id="program_types_color" name="program_types_color">
			</div>
<?php
		}

		public static function edit_program_types_fields( $term, $taxonomy ) {
			$alias = get_term_meta( $term->term_id, 'program_types_alias', true );
			$color = get_term_meta( $term->term_id, 'program_types_color', true );
?>
			<tr class="form-field term-group-wrap">
				<th scope="row"><label for="program_types_alias"><?php _e( 'Program Type Alias', 'ucf_degree' ); ?></label></th>
				<td><input type="text" id="program_types_alias" name="program_types_alias" value="<?php echo $alias; ?>"></td>
			</tr>
			<tr class="form-field term-group-wrap">
				<th scope="row"><label for="program_types_color"><?php _e( 'Program Type Color', 'ucf_degree' ); ?></label></th>
				<td><input class="wp-color-field" type="text" id="program_types_color" name="program_types_color" value="<?php echo $color; ?>"></td>
			</tr>
<?php
		}

		public static function save_program_types_meta( $term_id, $tt_id ) {
			if ( isset( $_POST['program_types_alias'] ) && '' !== $_POST['program_types_alias'] ) {
				$alias = $_POST['program_types_alias'];
				add_term_meta( $term_id, 'program_types_alias', $alias, true );
			}

			if ( isset( $_POST['program_types_color'] ) && '' !== $_POST['program_types_color'] ) {
				$color = $_POST['program_types_color'];
				add_term_meta( $term_id, 'program_types_color', $color, true );
			}
		}

		public static function edited_program_types_meta( $term_id, $tt_id ) {
			if ( isset( $_POST['program_types_alias'] ) && '' !== $_POST['program_types_alias'] ) {
				$alias = $_POST['program_types_alias'];
				update_term_meta( $term_id, 'program_types_alias', $alias, true );
			}

			if ( isset( $_POST['program_types_color'] ) && '' !== $_POST['program_types_color'] ) {
				$color = $_POST['program_types_color'];
				update_term_meta( $term_id, 'program_types_color', $color, true );
			}
		}

		public static function labels() {
			return array(
				'name'                       => _x( 'Program Types', 'Taxonomy General Name', 'ucf_degree' ),
				'singular_name'              => _x( 'Program Type', 'Taxonomy Singular Name', 'ucf_degree' ),
				'menu_name'                  => __( 'Program Types', 'ucf_degree' ),
				'all_items'                  => __( 'All Program Types', 'ucf_degree' ),
				'parent_item'                => __( 'Parent Program Type', 'ucf_degree' ),
				'parent_item_colon'          => __( 'Parent Program Type:', 'ucf_degree' ),
				'new_item_name'              => __( 'New Program Type Name', 'ucf_degree' ),
				'add_new_item'               => __( 'Add New Program Type', 'ucf_degree' ),
				'edit_item'                  => __( 'Edit Program Type', 'ucf_degree' ),
				'update_item'                => __( 'Update Program Type', 'ucf_degree' ),
				'view_item'                  => __( 'View Program Type', 'ucf_degree' ),
				'separate_items_with_commas' => __( 'Separate program types with commas', 'ucf_degree' ),
				'add_or_remove_items'        => __( 'Add or remove program types', 'ucf_degree' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'ucf_degree' ),
				'popular_items'              => __( 'Popular Program Types', 'ucf_degree' ),
				'search_items'               => __( 'Search Program Types', 'ucf_degree' ),
				'not_found'                  => __( 'Not Found', 'ucf_degree' ),
				'no_terms'                   => __( 'No program types', 'ucf_degree' ),
				'items_list'                 => __( 'Program Types list', 'ucf_degree' ),
				'items_list_navigation'      => __( 'Program types list navigation', 'ucf_degree' ),
			);
		}

		public static function args() {
			return array(
				'labels'                     => self::labels(),
				'hierarchical'               => true,
				'public'                     => true,
				'show_ui'                    => true,
				'show_admin_column'          => true,
				'show_in_nav_menus'          => true,
				'show_tagcloud'              => true,
			);
		}
	}
}

?>
