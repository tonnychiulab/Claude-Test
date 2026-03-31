<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WPCLP_Gate_Password — Password gate handler
 *
 * Unlock flow:
 *   1. Visitor sees lock screen (rendered via templates/lock-screen-password.php)
 *   2. Visitor submits password via AJAX POST to wp_ajax_nopriv_wpclp_unlock_password
 *   3. handle_ajax_unlock() verifies nonce + validates password
 *   4. On success: sets a short-lived cookie, responds with JSON success
 *   5. JS reloads the page; is_unlocked() reads the cookie → content is shown
 *
 * Cookie name:  'wpclp_pw_' . $post_id
 * Cookie value: wp_hash( $post_id . '|' . WPCLP_META_PASSWORD value )
 * Cookie TTL:   24 hours
 *
 * === WORKER INSTRUCTIONS ===
 * Implement all stubs. Security rules:
 * - AJAX handler: check_ajax_referer() FIRST, then logic.
 * - Password comparison: use wp_check_password() or hash_equals() — never == or ===.
 * - Stored password: must be wp_hash()'d, read from post meta WPCLP_META_PASSWORD.
 * - Cookie: httponly=true, samesite=Strict. Use setcookie() with proper flags.
 * - Never echo the correct password or hash in any response.
 * - wp_send_json_error() for all failure paths.
 */
class WPCLP_Gate_Password {

    const AJAX_ACTION = 'wpclp_unlock_password';
    const COOKIE_PREFIX = 'wpclp_pw_';
    const COOKIE_TTL    = DAY_IN_SECONDS;

    /**
     * AJAX handler — registered by WPCLP_Core::init() for both ajax and nopriv.
     * Nonce action: 'wpclp_unlock_password_{$post_id}'
     */
    public function handle_ajax_unlock(): void {
        check_ajax_referer( 'wpclp_unlock_password_' . absint( $_POST['post_id'] ?? 0 ), 'nonce' );

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $input   = sanitize_text_field( $_POST['wpclp_password'] ?? '' );

        if ( empty( $input ) ) {
            wp_send_json_error( [ 'message' => __( 'Password is required.' ) ] );
        }

        if ( ! $this->validate_password( $post_id, $input ) ) {
            wp_send_json_error( [ 'message' => __( 'Incorrect password.' ) ] );
        }

        $this->set_unlock_cookie( $post_id );
        wp_send_json_success( [ 'message' => __( 'Unlocked.' ) ] );
    }

    /**
     * Check whether the current visitor has a valid unlock cookie for $post_id.
     *
     * @param int $post_id
     * @return bool
     */
    public function is_unlocked( int $post_id ): bool {
        $cookie_name = self::COOKIE_PREFIX . $post_id;

        if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
            return false;
        }

        return hash_equals( $this->get_expected_cookie_value( $post_id ), $_COOKIE[ $cookie_name ] );
    }

    /**
     * Validate a plain-text password attempt against the stored hash.
     *
     * @param int    $post_id
     * @param string $input   Plain-text input from visitor (already sanitize_text_field'd)
     * @return bool
     */
    public function validate_password( int $post_id, string $input ): bool {
        $stored_hash = get_post_meta( $post_id, WPCLP_META_PASSWORD, true );

        if ( empty( $stored_hash ) ) {
            return false;
        }

        return hash_equals( $stored_hash, wp_hash( $input ) );
    }

    /**
     * Set the unlock cookie for $post_id.
     *
     * @param int $post_id
     */
    private function set_unlock_cookie( int $post_id ): void {
        $cookie_name = self::COOKIE_PREFIX . $post_id;

        setcookie( $cookie_name, $this->get_expected_cookie_value( $post_id ), [
            'expires'  => time() + DAY_IN_SECONDS,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ] );
    }

    /**
     * Return the expected cookie value (hash) for $post_id.
     *
     * @param int $post_id
     * @return string
     */
    private function get_expected_cookie_value( int $post_id ): string {
        $stored = get_post_meta( $post_id, WPCLP_META_PASSWORD, true );

        return wp_hash( $post_id . '|' . $stored );
    }
}
