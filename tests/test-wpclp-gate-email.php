<?php
/**
 * Tests for WPCLP_Gate_Email
 *
 * @package WP_Content_Lock_Pro
 */

declare( strict_types=1 );

class WPCLP_Gate_Email_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();

        $_COOKIE = [];
        $_POST   = [];

        // Reset the WPCLP_DB stub state
        wpclp_test_reset_db_emails();
    }

    // -------------------------------------------------------------------------
    // is_unlocked()
    // -------------------------------------------------------------------------

    public function test_is_unlocked_returns_false_when_no_cookie(): void {
        $_COOKIE = [];

        $gate = new WPCLP_Gate_Email();
        $this->assertFalse( $gate->is_unlocked( 1 ) );
    }

    public function test_is_unlocked_returns_false_when_cookie_is_non_hex(): void {
        $post_id = 5;

        // 'abc' is only 3 chars and not a valid 64-char hex string
        $_COOKIE[ WPCLP_Gate_Email::COOKIE_PREFIX . $post_id ] = 'abc';

        $gate = new WPCLP_Gate_Email();
        $this->assertFalse( $gate->is_unlocked( $post_id ) );
    }

    public function test_is_unlocked_returns_false_when_no_db_records_match(): void {
        $post_id = 5;

        // A valid 64-character lowercase hex string that does NOT match any record
        $fake_hex_cookie = str_repeat( 'a', 64 );
        $_COOKIE[ WPCLP_Gate_Email::COOKIE_PREFIX . $post_id ] = $fake_hex_cookie;

        // Stub returns empty list for this post
        wpclp_test_set_db_emails( $post_id, [] );

        $gate = new WPCLP_Gate_Email();
        $this->assertFalse( $gate->is_unlocked( $post_id ) );
    }

    public function test_is_unlocked_returns_true_when_cookie_matches_db_email(): void {
        $post_id = 1;
        $email   = 'test@example.com';

        // Compute the cookie value the gate would have set
        $expected_cookie = wp_hash( $post_id . '|' . $email );

        $_COOKIE[ WPCLP_Gate_Email::COOKIE_PREFIX . $post_id ] = $expected_cookie;

        // DB stub returns a record with the matching email
        $record        = new stdClass();
        $record->email = $email;
        wpclp_test_set_db_emails( $post_id, [ $record ] );

        $gate = new WPCLP_Gate_Email();
        $this->assertTrue( $gate->is_unlocked( $post_id ) );
    }

    // -------------------------------------------------------------------------
    // handle_ajax_unlock()
    // -------------------------------------------------------------------------

    public function test_handle_ajax_unlock_sends_error_on_invalid_email(): void {
        $_POST['post_id']    = 1;
        $_POST['wpclp_email'] = 'not-an-email';

        $gate = new WPCLP_Gate_Email();

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'json_error' );

        $gate->handle_ajax_unlock();
    }

    public function test_handle_ajax_unlock_sends_success_on_valid_email(): void {
        $_POST['post_id']     = 2;
        $_POST['wpclp_email'] = 'visitor@example.com';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $gate = new WPCLP_Gate_Email();

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'json_success' );

        $gate->handle_ajax_unlock();
    }
}
