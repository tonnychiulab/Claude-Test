<?php
/**
 * Tests for WPCLP_Dashboard
 *
 * @package WP_Content_Lock_Pro
 */

declare( strict_types=1 );

class WPCLP_Dashboard_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();

        $_GET  = [];
        $_POST = [];

        wpclp_test_reset_wp_stubs();
        wpclp_test_reset_db_emails();
    }

    // -------------------------------------------------------------------------
    // render_page()
    // -------------------------------------------------------------------------

    public function test_render_page_dies_when_no_capability(): void {
        // current_user_can('manage_options') returns false
        wpclp_test_set_user_can( false );

        $dashboard = new WPCLP_Dashboard();

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'wp_die' );

        $dashboard->render_page();
    }

    // -------------------------------------------------------------------------
    // handle_delete_email()
    // -------------------------------------------------------------------------

    public function test_handle_delete_email_dies_when_no_capability(): void {
        wpclp_test_set_user_can( false );

        $dashboard = new WPCLP_Dashboard();

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'wp_die' );

        $dashboard->handle_delete_email();
    }

    // -------------------------------------------------------------------------
    // get_locked_posts()
    // -------------------------------------------------------------------------

    public function test_get_locked_posts_returns_correct_structure(): void {
        // Seed the WP_Query stub with two fake posts and a total count
        $post_a           = new stdClass();
        $post_a->ID       = 10;
        $post_a->post_title = 'Post A';

        $post_b           = new stdClass();
        $post_b->ID       = 20;
        $post_b->post_title = 'Post B';

        wpclp_test_set_wp_query_result( [ $post_a, $post_b ], 2 );

        $dashboard = new WPCLP_Dashboard();
        $result    = $dashboard->get_locked_posts( 1, 20 );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'posts', $result );
        $this->assertArrayHasKey( 'total', $result );
        $this->assertCount( 2, $result['posts'] );
        $this->assertSame( 2, $result['total'] );
    }

    // -------------------------------------------------------------------------
    // handle_export_csv() — column name assertion
    // -------------------------------------------------------------------------

    /**
     * Verify that handle_export_csv() reads $row->unlocked_at, NOT $row->created_at.
     *
     * The test inspects the source directly via reflection to confirm the property
     * accessed, and also runs an integration-style assertion via output buffering.
     */
    public function test_handle_export_csv_uses_correct_column_unlocked_at(): void {
        // --- Source-level assertion -------------------------------------------
        // Read the class source to confirm the correct property name is referenced.
        $source_file = dirname( __DIR__ ) . '/admin/class-wpclp-dashboard.php';
        $this->assertFileExists( $source_file, 'Dashboard source file must exist' );

        $source = file_get_contents( $source_file );

        // The export loop must reference unlocked_at
        $this->assertStringContainsString(
            '$row->unlocked_at',
            $source,
            'handle_export_csv() must read $row->unlocked_at for the timestamp column'
        );

        // The export loop must NOT reference created_at instead
        $this->assertStringNotContainsString(
            '$row->created_at',
            $source,
            'handle_export_csv() must not use $row->created_at — the column is unlocked_at'
        );

        // --- Integration-style assertion: CSV output contains correct header ---
        // Seed DB stub with one row using unlocked_at
        $row              = new stdClass();
        $row->id          = 1;
        $row->post_id     = 5;
        $row->email       = 'export@example.com';
        $row->unlocked_at = '2025-01-01 12:00:00';

        wpclp_test_set_all_emails( [ $row ] );

        // Allow current_user_can to return true
        wpclp_test_set_user_can( true );

        $dashboard = new WPCLP_Dashboard();

        ob_start();
        try {
            $dashboard->handle_export_csv();
        } catch ( RuntimeException $e ) {
            // The stub for exit() throws RuntimeException('exit') — that is expected
            if ( $e->getMessage() !== 'exit' ) {
                ob_end_clean();
                throw $e;
            }
        }
        $csv_output = ob_get_clean();

        // The captured CSV must contain the unlocked_at value for the row
        $this->assertStringContainsString(
            '2025-01-01 12:00:00',
            $csv_output,
            'CSV output must include the unlocked_at timestamp value'
        );
    }
}
