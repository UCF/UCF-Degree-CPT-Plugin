<?php
/**
 * Various admin messages are defined here
 */
if ( ! class_exists( 'UCF_Degree_Messages' ) ) {
    class UCF_Degree_Messages {
        /**
         * Displays a success message after creating a tuition exception
         * @author Jim Barnes
         * @since 3.2.0
         * @return string
         */
        public static function created_tuition_success_message() {
            ob_start();
        ?>
            <div class="notice notice-success is-dismissible">
                <p>Successfully created a tuition exception for this program on the Search Service.</p>
            </div>
        <?php
            return ob_get_clean();
        }

        /**
         * Displays an error message after failing to create a tuition exception
         * @author Jim Barnes
         * @since 3.2.0
         * @return string
         */
        public static function created_tuition_error_message() {
            ob_start();
        ?>
            <div class="notice notice-error is-dismissible">
                <p>Failed to create a tuition exception for this program on the Search Service.</p>
                <p>Possible reasons might include:</p>
                <ul>
                    <li>Incorrect API key defined in the UCF Degree CPT plugin options.</li>
                    <li>Unable to contact the Search Service.</li>
                    <li>You may not have permission to update <code>TuitionOverride</code> objects.</li>
                </ul>
                <p>Contact <a href="mailto:<?php echo get_option( 'admin_email' );?>"><?php echo get_option( 'admin_email' ); ?></a> for more information.</p>
            </div>
        <?php
            return ob_get_clean();
        }

        /**
         * Displays a success message after update a tuition exception
         * @author Jim Barnes
         * @since 3.2.0
         * @return string
         */
        public static function updated_tuition_success_message() {
            ob_start();
        ?>
            <div class="notice notice-success is-dismissible">
                <p>Successfully updated this program's tuition exception on the Search Service.</p>
            </div>
        <?php
            return ob_get_clean();
        }

        /**
         * Displays an error message after failing to update a tuition exception
         * @author Jim Barnes
         * @since 3.2.0
         * @return string
         */
        public static function updated_tuition_error_message() {
            ob_start();
        ?>
            <div class="notice notice-error is-dismissible">
                <p>Failed to update this program's tuition exception on the Search Service.</p>
                <p>Possible reasons might include:</p>
                <ul>
                    <li>Incorrect API key defined in the UCF Degree CPT plugin options.</li>
                    <li>Unable to contact the Search Service.</li>
                    <li>You may not have permission to update <code>TuitionOverride</code> objects.</li>
                </ul>
                <p>Contact <a href="mailto:<?php echo get_option( 'admin_email' );?>"><?php echo get_option( 'admin_email' ); ?></a> for more information.</p>
            </div>
        <?php
            return ob_get_clean();
        }

        /**
         * Displays an error message after failing to contact the search service
         * @author Jim Barnes
         * @since 3.2.0
         * @return string
         */
        public static function retrieval_error_message() {
            ob_start();
        ?>
            <div class="notice notice-error is-dismissible">
                <p>Failed to retrieve results from the search service.</p>
                <p>Possible reasons might include:</p>
                <ul>
                    <li>Incorrect API key defined in the UCF Degree Custom Post Type plugin options.</li>
                    <li>Unable to contact the Search Service.</li>
                    <li>A program with the appropriate <code>plan_code</code> and <code>subplan_code</code> may not exist.</li>
                </ul>
                <p>Contact <a href="mailto:<?php echo get_option( 'admin_email' );?>"><?php echo get_option( 'admin_email' ); ?></a> for more information.</p>
            </div>
        <?php
            return ob_get_clean();
        }

        /**
         * Enqueues all messages
         */
        public static function enqueue_admin_notices() {
            $screen = get_current_screen();

            if ( ! $screen || ! $screen instanceof WP_Screen ) return;

            if ( $screen->id === 'degree' && isset( $_GET['degree_tuition_status'] ) ) {
                switch( $_GET['degree_tuition_status'] ) {
                    case 'created-success':
                        echo self::created_tuition_success_message();
                        break;
                    case 'created-error':
                        echo self::created_tuition_error_message();
                        break;
                    case 'updated-success':
                        echo self::updated_tuition_success_message();
                        break;
                    case 'updated-error':
                        echo self::updated_tuition_error_message();
                        break;
                    case 'retrieval-error':
                        echo self::retrieval_error_message();
                        break;
                }
            }
        }
    }
}