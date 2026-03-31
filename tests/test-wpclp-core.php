<?php
/**
 * Tests for WPCLP_Core.
 *
 * Uses Brain\Monkey\Functions to override WordPress functions per test.
 */
class WPCLP_Core_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        Brain\Monkey\tearDown();
        parent::tearDown();
    }

    // ── get_lock_config ───────────────────────────────────────────────────────

    /**
     * When all get_post_meta calls return '' the config must reflect safe defaults.
     */
    public function test_get_lock_config_returns_defaults_when_no_meta(): void {
        Brain\Monkey\Functions\when( 'get_post_meta' )->justReturn( '' );

        $core   = new WPCLP_Core();
        $config = $core->get_lock_config( 1 );

        $this->assertFalse( $config['enabled'] );
        $this->assertSame( 'password', $config['gate_type'] );
        $this->assertSame( '', $config['message'] );
        $this->assertSame( 0, $config['partial_reveal'] );
        $this->assertSame( '', $config['password'] );
    }

    /**
     * An unrecognised gate_type value must be normalised to 'password'.
     */
    public function test_get_lock_config_normalizes_invalid_gate_type(): void {
        Brain\Monkey\Functions\when( 'get_post_meta' )
            ->alias( function ( int $post_id, string $key, bool $single ) {
                if ( $key === WPCLP_META_GATE_TYPE ) {
                    return 'invalid_type';
                }
                return '';
            } );

        $core   = new WPCLP_Core();
        $config = $core->get_lock_config( 1 );

        $this->assertSame( 'password', $config['gate_type'] );
    }

    /**
     * The gate_type 'email' must pass through validation unchanged.
     */
    public function test_get_lock_config_returns_valid_email_gate_type(): void {
        Brain\Monkey\Functions\when( 'get_post_meta' )
            ->alias( function ( int $post_id, string $key, bool $single ) {
                if ( $key === WPCLP_META_GATE_TYPE ) {
                    return 'email';
                }
                return '';
            } );

        $core   = new WPCLP_Core();
        $config = $core->get_lock_config( 1 );

        $this->assertSame( 'email', $config['gate_type'] );
    }

    // ── get_gate_types ────────────────────────────────────────────────────────

    /**
     * get_gate_types() must return exactly ['password', 'email'].
     */
    public function test_get_gate_types_returns_expected_array(): void {
        $this->assertSame( [ 'password', 'email' ], WPCLP_Core::get_gate_types() );
    }

    // ── filter_content ────────────────────────────────────────────────────────

    /**
     * When is_singular() returns false the original content must pass through unchanged.
     */
    public function test_filter_content_returns_original_when_not_singular(): void {
        Brain\Monkey\Functions\when( 'is_singular' )->justReturn( false );
        Brain\Monkey\Functions\when( 'is_admin' )->justReturn( false );
        Brain\Monkey\Functions\when( 'is_feed' )->justReturn( false );
        Brain\Monkey\Functions\when( 'is_preview' )->justReturn( false );

        $core    = new WPCLP_Core();
        $content = '<p>Original content</p>';

        $result = $core->filter_content( $content );

        $this->assertSame( $content, $result );
    }

    /**
     * When the post is singular but locking is not enabled the content must be unchanged.
     */
    public function test_filter_content_returns_original_when_not_enabled(): void {
        Brain\Monkey\Functions\when( 'is_singular' )->justReturn( true );
        Brain\Monkey\Functions\when( 'is_admin' )->justReturn( false );
        Brain\Monkey\Functions\when( 'is_feed' )->justReturn( false );
        Brain\Monkey\Functions\when( 'is_preview' )->justReturn( false );
        Brain\Monkey\Functions\when( 'get_the_ID' )->justReturn( 10 );

        // All post meta returns '' so enabled = false.
        Brain\Monkey\Functions\when( 'get_post_meta' )->justReturn( '' );

        $core    = new WPCLP_Core();
        $content = '<p>Unlocked content</p>';

        $result = $core->filter_content( $content );

        $this->assertSame( $content, $result );
    }
}
