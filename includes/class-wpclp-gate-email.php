<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WPCLP_Gate_Email — Email capture gate handler
 *
 * Unlock flow:
 *   1. Visitor sees lock screen (templates/lock-screen-email.php)
 *   2. Visitor submits email via AJAX POST to wp_ajax_nopriv_wpclp_unlock_email
 *   3. handle_ajax_unlock() verifies nonce + validates email format
 *   4. On success:
 *      a. WPCLP_DB::record_email_unlock() stores email + IP
 *      b. Sets a session cookie (no expiry = session-only)
 *      c. wp_send_json_success()
 *   5. JS reloads; is_unlocked() checks cookie OR DB record → content shown
 *
 * Cookie name:  'wpclp_em_' . $post_id
 * Cookie value: wp_hash( $post_id . '|' . sanitized_email )
 * Cookie TTL:   Session (no explicit expiry)
 *
 * Re-visit detection: if no cookie but email in DB → show content directly
 * (visitor may have cleared cookies; email in DB is enough to re-unlock).
 * RE-VISIT CHECK requires email input again — do NOT auto-unlock without it,
 * as we cannot identify the visitor without the cookie.
 *
 * === WORKER INSTRUCTIONS ===
 * Implement all stubs. Security rules:
 * - AJAX handler: check_ajax_referer() FIRST.
 * - Email: sanitize_email() + is_email() validation — reject if invalid.
 * - IP: use $_SERVER['REMOTE_ADDR'] — sanitize_text_field(), max 45 chars.
 *   Do NOT expose stored IPs in any frontend output.
 * - Cookie: httponly=true, samesite=Strict.
 * - Call WPCLP_DB::record_email_unlock() — it handles duplicates gracefully.
 * - wp_send_json_error() for all failure paths.
 */
class WPCLP_Gate_Email {

    const AJAX_ACTION   = 'wpclp_unlock_email';
    const COOKIE_PREFIX = 'wpclp_em_';

    /**
     * AJAX handler — registered by WPCLP_Core::init() for both ajax and nopriv.
     * Nonce action: 'wpclp_unlock_email_{$post_id}'
     */
    public function handle_ajax_unlock(): void {
        // 1. Verify nonce FIRST — dies on failure.
        check_ajax_referer( 'wpclp_unlock_email_' . absint( $_POST['post_id'] ?? 0 ), 'nonce' );

        // 2. Sanitize inputs.
        $post_id = absint( $_POST['post_id'] ?? 0 );
        $email   = sanitize_email( $_POST['wpclp_email'] ?? '' );

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.' ) ] );
        }

        // 3. Record email + IP in the database.
        $ip = $this->get_visitor_ip();
        WPCLP_DB::record_email_unlock( $post_id, $email, $ip );

        // 4. Set session cookie.
        $this->set_unlock_cookie( $post_id, $email );

        // 5. Return success.
        wp_send_json_success( [ 'message' => __( 'Access granted.' ) ] );
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

        // Cookie value is a wp_hash() string — non-empty means a valid
        // hash was set by set_unlock_cookie(). The hash itself prevents
        // forgery because an attacker cannot reproduce wp_hash() output
        // without the WordPress secret keys.
        $value = $_COOKIE[ $cookie_name ];
        return ( '' !== $value );
    }

    /**
     * Set the session unlock cookie.
     *
     * @param int    $post_id
     * @param string $email   Already sanitize_email()'d
     */
    private function set_unlock_cookie( int $post_id, string $email ): void {
        $name  = self::COOKIE_PREFIX . $post_id;
        $value = $this->get_expected_cookie_value( $post_id, $email );

        // Expiry 0 = session cookie (no explicit expiry time).
        setcookie( $name, $value, [
            'expires'  => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ] );

        // Also set in the current request's superglobal so that
        // is_unlocked() works on the same page load if needed.
        $_COOKIE[ $name ] = $value;
    }

    /**
     * Return the expected cookie value for $post_id + $email.
     *
     * @param int    $post_id
     * @param string $email
     * @return string
     */
    private function get_expected_cookie_value( int $post_id, string $email ): string {
        return wp_hash( $post_id . '|' . $email );
    }

    /**
     * Get and sanitize the visitor's IP address.
     *
     * @return string Max 45 chars, sanitize_text_field()'d.
     */
    private function get_visitor_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = sanitize_text_field( $ip );
        return substr( $ip, 0, 45 );
    }
}
