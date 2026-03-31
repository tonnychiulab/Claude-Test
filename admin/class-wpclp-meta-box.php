<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WPCLP_Meta_Box — Post editor meta box
 *
 * Adds a "Content Lock" meta box to all public post types.
 * Renders admin/views/meta-box.php.
 *
 * Meta keys written (all use WPCLP_META_* constants from main file):
 *   _wpclp_enabled        '1' or ''
 *   _wpclp_gate_type      'password' | 'email'
 *   _wpclp_password       wp_hash()'d — only update if new value submitted
 *   _wpclp_message        sanitize_textarea_field()
 *   _wpclp_partial_reveal absint()
 *
 * === WORKER INSTRUCTIONS ===
 * Implement all stubs. Security rules:
 * - register(): use add_meta_boxes hook. Screen: all public post types from get_post_types(['public'=>true]).
 * - render(): output nonce with wp_nonce_field('wpclp_meta_box', 'wpclp_meta_nonce').
 *   All existing values output via esc_attr() / esc_html() / checked() / selected().
 *   Never output the stored password hash — show a placeholder instead.
 * - save():
 *   a. verify_save_request() must pass before any write.
 *   b. Sanitize every field with the function listed above.
 *   c. Only hash + save password if non-empty string submitted.
 *   d. Use update_post_meta() / delete_post_meta().
 * - verify_save_request(): wp_verify_nonce + current_user_can('edit_post', $post_id)
 *   + check it's not an autosave/revision.
 */
class WPCLP_Meta_Box {

    public function init(): void {
        add_action( 'add_meta_boxes', [ $this, 'register' ] );
        add_action( 'save_post',      [ $this, 'save' ] );
    }

    /**
     * Register meta box on all public post types.
     */
    public function register(): void {
        $post_types = get_post_types( [ 'public' => true ] );
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'wpclp_meta_box',
                __( 'Content Lock', 'wp-content-lock-pro' ),
                [ $this, 'render' ],
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render the meta box HTML.
     *
     * @param WP_Post $post
     */
    public function render( WP_Post $post ): void {
        wp_nonce_field( 'wpclp_meta_box', 'wpclp_meta_nonce' );

        $enabled        = (bool) get_post_meta( $post->ID, WPCLP_META_ENABLED, true );
        $gate_type      = (string) get_post_meta( $post->ID, WPCLP_META_GATE_TYPE, true );
        $message        = (string) get_post_meta( $post->ID, WPCLP_META_MESSAGE, true );
        $partial_reveal = absint( get_post_meta( $post->ID, WPCLP_META_PARTIAL_REVEAL, true ) );

        // Validate gate_type against whitelist; default to 'password'
        $allowed_gate_types = array_keys( WPCLP_Core::get_gate_types() );
        if ( ! in_array( $gate_type, $allowed_gate_types, true ) ) {
            $gate_type = 'password';
        }

        // NOTE: never pass the raw password hash to the template
        require WPCLP_PLUGIN_DIR . 'admin/views/meta-box.php';
    }

    /**
     * Save meta box values on post save.
     *
     * @param int $post_id
     */
    public function save( int $post_id ): void {
        if ( ! $this->verify_save_request( $post_id ) ) {
            return;
        }

        // Sanitize enabled
        $enabled = isset( $_POST['wpclp_enabled'] ) ? '1' : '';
        update_post_meta( $post_id, WPCLP_META_ENABLED, $enabled );

        // Sanitize gate_type with whitelist
        $allowed_gate_types = array_keys( WPCLP_Core::get_gate_types() );
        $gate_type = isset( $_POST['wpclp_gate_type'] )
            ? sanitize_key( wp_unslash( $_POST['wpclp_gate_type'] ) )
            : 'password';
        if ( ! in_array( $gate_type, $allowed_gate_types, true ) ) {
            $gate_type = 'password';
        }
        update_post_meta( $post_id, WPCLP_META_GATE_TYPE, $gate_type );

        // Sanitize message
        $message = isset( $_POST['wpclp_message'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['wpclp_message'] ) )
            : '';
        update_post_meta( $post_id, WPCLP_META_MESSAGE, $message );

        // Sanitize partial_reveal
        $partial_reveal = isset( $_POST['wpclp_partial_reveal'] )
            ? absint( $_POST['wpclp_partial_reveal'] )
            : 0;
        update_post_meta( $post_id, WPCLP_META_PARTIAL_REVEAL, $partial_reveal );

        // Password: only update if non-empty string submitted
        if ( isset( $_POST['wpclp_password'] ) ) {
            $new_password = trim( wp_unslash( $_POST['wpclp_password'] ) );
            if ( '' !== $new_password ) {
                $hashed = wp_hash( $new_password );
                update_post_meta( $post_id, WPCLP_META_PASSWORD, $hashed );
            }
        }
    }

    /**
     * Return true if this save request is valid and should be processed.
     *
     * @param int $post_id
     * @return bool
     */
    private function verify_save_request( int $post_id ): bool {
        // Check nonce is present and valid
        if ( ! isset( $_POST['wpclp_meta_nonce'] ) ) {
            return false;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpclp_meta_nonce'] ) ), 'wpclp_meta_box' ) ) {
            return false;
        }

        // Skip autosaves
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return false;
        }

        // Skip post revisions
        if ( wp_is_post_revision( $post_id ) ) {
            return false;
        }

        // Check user capability
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }

        return true;
    }
}
