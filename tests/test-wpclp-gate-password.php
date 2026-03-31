<?php
/**
 * Tests for WPCLP_Gate_Password
 *
 * @package WP_Content_Lock_Pro
 */

declare( strict_types=1 );

class WPCLP_Gate_Password_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();

        // Reset superglobals before each test
        $_COOKIE = [];
        $_POST   = [];

        // Reset get_post_meta stub state
        wpclp_test_reset_post_meta();
    }

    // -------------------------------------------------------------------------
    // is_unlocked()
    // -------------------------------------------------------------------------

    public function test_is_unlocked_returns_false_when_no_cookie(): void {
        $_COOKIE = [];

        $gate = new WPCLP_Gate_Password();
        $this->assertFalse( $gate->is_unlocked( 1 ) );
    }

    public function test_is_unlocked_returns_false_when_cookie_value_wrong(): void {
        $post_id     = 42;
        $stored_hash = wp_hash( 'correct_password' );

        // Seed the post meta stub so get_post_meta returns $stored_hash
        wpclp_test_set_post_meta( $post_id, WPCLP_META_PASSWORD, $stored_hash );

        // Set the cookie to a wrong value
        $_COOKIE[ WPCLP_Gate_Password::COOKIE_PREFIX . $post_id ] = 'wrong';

        $gate = new WPCLP_Gate_Password();
        $this->assertFalse( $gate->is_unlocked( $post_id ) );
    }

    public function test_is_unlocked_returns_true_when_cookie_matches(): void {
        $post_id     = 42;
        $stored_hash = wp_hash( 'correct_password' );

        // Seed post meta stub
        wpclp_test_set_post_meta( $post_id, WPCLP_META_PASSWORD, $stored_hash );

        // Compute the expected cookie value (mirrors get_expected_cookie_value())
        $expected_cookie = wp_hash( $post_id . '|' . $stored_hash );

        $_COOKIE[ WPCLP_Gate_Password::COOKIE_PREFIX . $post_id ] = $expected_cookie;

        $gate = new WPCLP_Gate_Password();
        $this->assertTrue( $gate->is_unlocked( $post_id ) );
    }

    // -------------------------------------------------------------------------
    // validate_password()
    // -------------------------------------------------------------------------

    public function test_validate_password_returns_false_when_no_stored_hash(): void {
        $post_id = 99;

        // get_post_meta returns '' (default in stub when no meta is set)
        $gate = new WPCLP_Gate_Password();
        $this->assertFalse( $gate->validate_password( $post_id, 'any_input' ) );
    }

    public function test_validate_password_returns_true_when_password_matches(): void {
        $post_id  = 10;
        $password = 'secret';

        wpclp_test_set_post_meta( $post_id, WPCLP_META_PASSWORD, wp_hash( $password ) );

        $gate = new WPCLP_Gate_Password();
        $this->assertTrue( $gate->validate_password( $post_id, $password ) );
    }

    public function test_validate_password_returns_false_when_password_wrong(): void {
        $post_id  = 10;
        $password = 'secret';

        wpclp_test_set_post_meta( $post_id, WPCLP_META_PASSWORD, wp_hash( $password ) );

        $gate = new WPCLP_Gate_Password();
        $this->assertFalse( $gate->validate_password( $post_id, 'wrong' ) );
    }

    // -------------------------------------------------------------------------
    // handle_ajax_unlock()
    // -------------------------------------------------------------------------

    public function test_handle_ajax_unlock_sends_error_on_empty_password(): void {
        $_POST['post_id']        = 1;
        $_POST['wpclp_password'] = '';

        $gate = new WPCLP_Gate_Password();

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'json_error' );

        $gate->handle_ajax_unlock();
    }

    public function test_handle_ajax_unlock_sends_success_on_correct_password(): void {
        $post_id  = 7;
        $password = 'hunter2';

        // Seed post meta so validate_password() returns true
        wpclp_test_set_post_meta( $post_id, WPCLP_META_PASSWORD, wp_hash( $password ) );

        $_POST['post_id']        = (string) $post_id;
        $_POST['wpclp_password'] = $password;

        $gate = new WPCLP_Gate_Password();

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'json_success' );

        $gate->handle_ajax_unlock();
    }
}
