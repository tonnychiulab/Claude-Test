<?php
/**
 * Tests for WPCLP_Meta_Box
 *
 * @package WP_Content_Lock_Pro
 */

declare( strict_types=1 );

class WPCLP_Meta_Box_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();

        $_POST = [];

        wpclp_test_reset_post_meta();
        wpclp_test_reset_wp_stubs();
    }

    // -------------------------------------------------------------------------
    // verify_save_request() — accessed via ReflectionClass (private)
    // -------------------------------------------------------------------------

    /**
     * Call the private verify_save_request() via reflection.
     */
    private function call_verify_save_request( WPCLP_Meta_Box $box, int $post_id ): bool {
        $ref    = new ReflectionClass( $box );
        $method = $ref->getMethod( 'verify_save_request' );
        $method->setAccessible( true );

        return $method->invoke( $box, $post_id );
    }

    public function test_verify_save_request_returns_false_when_no_nonce(): void {
        $_POST = []; // no wpclp_meta_nonce

        $box = new WPCLP_Meta_Box();
        $this->assertFalse( $this->call_verify_save_request( $box, 1 ) );
    }

    public function test_verify_save_request_returns_false_on_autosave(): void {
        // Define the constant if not already defined (guard against redefinition)
        if ( ! defined( 'DOING_AUTOSAVE' ) ) {
            define( 'DOING_AUTOSAVE', true );
        }

        // Provide a valid nonce so the autosave check is reached
        $_POST['wpclp_meta_nonce'] = wpclp_test_create_nonce( 'wpclp_meta_box' );

        $box = new WPCLP_Meta_Box();
        $this->assertFalse( $this->call_verify_save_request( $box, 1 ) );
    }

    public function test_verify_save_request_returns_false_when_no_capability(): void {
        // Provide a valid nonce
        $_POST['wpclp_meta_nonce'] = wpclp_test_create_nonce( 'wpclp_meta_box' );

        // Make current_user_can return false
        wpclp_test_set_user_can( false );

        $box = new WPCLP_Meta_Box();
        $this->assertFalse( $this->call_verify_save_request( $box, 1 ) );
    }

    // -------------------------------------------------------------------------
    // save() — sanitisation / whitelist
    // -------------------------------------------------------------------------

    public function test_save_sanitizes_gate_type_whitelist(): void {
        $post_id = 55;

        // Provide a valid nonce and capability so verify_save_request passes
        $_POST['wpclp_meta_nonce']  = wpclp_test_create_nonce( 'wpclp_meta_box' );
        $_POST['wpclp_gate_type']   = 'evil';        // not in whitelist
        $_POST['wpclp_enabled']     = '1';

        wpclp_test_set_user_can( true );

        $box = new WPCLP_Meta_Box();
        $box->save( $post_id );

        // Gate type must have been coerced to the default 'password'
        $saved = wpclp_test_get_saved_post_meta( $post_id, WPCLP_META_GATE_TYPE );
        $this->assertSame( 'password', $saved );
    }

    public function test_save_only_hashes_password_when_non_empty(): void {
        $post_id = 56;

        $_POST['wpclp_meta_nonce'] = wpclp_test_create_nonce( 'wpclp_meta_box' );
        $_POST['wpclp_gate_type']  = 'password';
        $_POST['wpclp_password']   = '';             // empty — must NOT update password meta

        wpclp_test_set_user_can( true );

        $box = new WPCLP_Meta_Box();
        $box->save( $post_id );

        // Password meta should NOT have been written
        $this->assertFalse( wpclp_test_was_post_meta_updated( $post_id, WPCLP_META_PASSWORD ) );
    }
}
