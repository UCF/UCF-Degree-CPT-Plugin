<?php
/**
 * Defines various helper functions for program types.
 **/
if ( ! class_exists( 'UCF_Degree_Program_Types_Common' ) ) {
	class UCF_Degree_Program_Types_Common {
		/**
		 * Returns a program type's alias, if available, or term name.
		 *
		 * @author Jo Dickson
		 * @since 1.0.3
		 * @param $dept WP_Term | The term object
		 * @return Mixed | alias or name string, or false
		 **/
		public static function get_name_or_alias( $program_type ) {
			if ( !$program_type->name || !$program_type->term_id ) { return false; }

			$retval = get_term_meta( $program_type->term_id, 'program_types_alias', true ) ?: $program_type->name;
			return $retval;
		}
	}
}
