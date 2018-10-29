<?php
/**
 * Class for importing interests and
 * assigning them to degrees
 */
class UCF_Degree_Interests_Importer {
    private
        $file,
        $interests,
        $interests_updated = 0,
        $interests_added = 0,
        $interests_errors = 0,
        $programs_added = 0,
        $rograms_errors = 0,
        $could_not_add = array(),
        $total_terms_processed = 0,
        $total_programs_processed = 0;

    /**
     * Constructs the importer class.
     * Will return an exception if the filepath
     * provided is not valid or is not valid JSON.
     * @author Jim Barnes
     * @since 3.1.0
     * @param string $file_path The path to the import file
     */
    public function __construct( $file_path ) {
        $this->file = file_get_contents( $file_path );

        if ( $this->file === false ) {
            throw new Exception(
                'Unable to locate or load the filepath provided.'
            );
        }

        $this->interests = json_decode( $this->file );

        if ( ! $this->interests ) {
            throw new Exception(
                'Unable to decode the JSON within the specified file.'
            );
        }
    }

    public function get_stats() {
        $retval =
"
Finished importing interests.

Interests Processed: {$this->total_terms_processed}
Interests Added    : {$this->interests_added}
Interests Updated  : {$this->interests_updated}
Interests Errors   : {$this->interests_errors}

Programs Processed : {$this->total_programs_processed}
Programs Updated   : {$this->programs_updated}
Programs Errors    : {$this->programs_errors}

The follow programs could not be found:
";
        foreach( $this->could_not_add as $program ) {
            $retval .= "\n{$program}";
        }

        $retval .= "\n";

        return $retval;
    }

    /**
     * Imports the interests into the site
     * and assigns all provided degrees.
     * @author Jim Barnes
     * @since 3.1.0
     */
    public function import() {
        foreach( $this->interests as $interest ) {
            $clean_name = $this->clean_name( $interest->name );

            $term = $this->add_or_get_interest( $clean_name, $interest->name, $interest->slug );

            foreach( $interest->programs as $program ) {
                $added = $this->add_degree_to_interest( $program, $term );

                if ( $added ) {
                    $this->programs_updated++;
                } else {
                    $this->programs_errors++;
                    $this->could_not_add[] = $program;
                }

                $this->total_programs_processed++;
            }

            $this->total_terms_processed++;
        }
    }

    /**
     * Adds or returns an existing interest.
     * @author Jim Barnes
     * @since 3.1.0
     * @param string $name The interest name
     * @param string $display_text The interest's display text
     * @param string $slug The interest's desired slug.
     * @return WP_Term The interest object
     */
    private function add_or_get_interest( $clean_name, $name, $slug ) {
        $term = get_term_by( 'name', $clean_name, 'interests' );

        if ( $term ) {
            $this->interests_updated++;
            return $term;
        }

        $term = wp_insert_term(
            $clean_name,
            'interests',
            array(
                'slug' => $slug
            )
        );

        add_term_meta( $term->term_id, 'interests_display_text', $name );

        $this->interests_added++;

        return $term;
    }

    /**
     * Adds the provided interest to the provided degree
     * @author Jim Barnes
     * @since 3.1.0
     * @param string $degree_name The name of the degree to add the interest to
     * @param WP_Term $interest The interests term to add to the degree
     * @return bool True if the degree is found and interest added, false if not.
     */
    private function add_degree_to_interest( $degree_name, $interest ) {
        $degree = get_page_by_title( $degree_name, 'OBJECT', 'degree' );

        if ( ! $degree ) return false;

        // Append terms to the degree.
        $retval = wp_set_post_terms( $degree->ID, array( $interest->term_id ), 'interests', true );

        // $retval will be an array if successful
        return is_array( $retval );
    }

    /**
     * Removes commas from the term
     * @author Jim Barnes
     * @since 3.1.0
     * @param string $name The interest name
     * @return string
     */
    private function clean_name( $name ) {
        return str_replace( ',', '', $name );
    }
}