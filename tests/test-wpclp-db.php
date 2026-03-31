<?php
/**
 * Tests for WPCLP_DB.
 *
 * Each test replaces methods on the $GLOBALS['wpdb'] mock to control
 * return values, then restores the original mock in tearDown().
 */
class WPCLP_DB_Test extends \PHPUnit\Framework\TestCase {

    /** @var object Original wpdb mock snapshot */
    private object $originalWpdb;

    protected function setUp(): void {
        parent::setUp();
        // Preserve the base mock so tearDown can restore it.
        $this->originalWpdb = $GLOBALS['wpdb'];
    }

    protected function tearDown(): void {
        // Restore the original mock between tests.
        $GLOBALS['wpdb'] = $this->originalWpdb;
        parent::tearDown();
    }

    // ── record_email_unlock ───────────────────────────────────────────────────

    /**
     * When wpdb->query() returns false the method must return false.
     */
    public function test_record_email_unlock_returns_false_on_db_error(): void {
        $GLOBALS['wpdb'] = new class extends stdClass {
            public string $prefix     = 'wp_';
            public string $last_error = 'Simulated DB error';

            public function prepare( string $query, ...$args ): string {
                return $query; // Return raw for simplicity in this test.
            }

            public function query( string $sql ) {
                return false; // Simulate DB failure.
            }
        };

        $result = WPCLP_DB::record_email_unlock( 1, 'test@example.com', '127.0.0.1' );

        $this->assertFalse( $result );
    }

    // ── has_email_unlocked ────────────────────────────────────────────────────

    /**
     * When wpdb->get_var() returns '0' the method must return false.
     */
    public function test_has_email_unlocked_returns_false_when_no_record(): void {
        $GLOBALS['wpdb'] = new class extends stdClass {
            public string $prefix     = 'wp_';
            public string $last_error = '';

            public function prepare( string $query, ...$args ): string {
                return $query;
            }

            public function get_var( ?string $query = null ) {
                return '0'; // No matching rows.
            }
        };

        $result = WPCLP_DB::has_email_unlocked( 42, 'nobody@example.com' );

        $this->assertFalse( $result );
    }

    /**
     * When wpdb->get_var() returns '1' the method must return true.
     */
    public function test_has_email_unlocked_returns_true_when_record_exists(): void {
        $GLOBALS['wpdb'] = new class extends stdClass {
            public string $prefix     = 'wp_';
            public string $last_error = '';

            public function prepare( string $query, ...$args ): string {
                return $query;
            }

            public function get_var( ?string $query = null ) {
                return '1'; // One matching row.
            }
        };

        $result = WPCLP_DB::has_email_unlocked( 42, 'user@example.com' );

        $this->assertTrue( $result );
    }

    // ── get_emails_for_post ───────────────────────────────────────────────────

    /**
     * When wpdb->get_results() returns null (DB error) the method must return [].
     */
    public function test_get_emails_for_post_returns_empty_on_db_error(): void {
        $GLOBALS['wpdb'] = new class extends stdClass {
            public string $prefix     = 'wp_';
            public string $last_error = 'Simulated DB error';

            public function prepare( string $query, ...$args ): string {
                return $query;
            }

            public function get_results( ?string $query = null, string $output = 'OBJECT' ): ?array {
                return null; // Simulate DB failure.
            }
        };

        $result = WPCLP_DB::get_emails_for_post( 7 );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ── delete_email ──────────────────────────────────────────────────────────

    /**
     * When wpdb->delete() returns false the method must return false.
     */
    public function test_delete_email_returns_false_on_db_error(): void {
        $GLOBALS['wpdb'] = new class extends stdClass {
            public string $prefix     = 'wp_';
            public string $last_error = 'Simulated DB error';

            public function delete( string $table, array $where, $where_format = null ) {
                return false; // Simulate DB failure.
            }
        };

        $result = WPCLP_DB::delete_email( 99 );

        $this->assertFalse( $result );
    }

    // ── count_emails_for_post ─────────────────────────────────────────────────

    /**
     * When wpdb->get_var() returns null (DB error) the method must return 0.
     */
    public function test_count_emails_for_post_returns_zero_on_db_error(): void {
        $GLOBALS['wpdb'] = new class extends stdClass {
            public string $prefix     = 'wp_';
            public string $last_error = 'Simulated DB error';

            public function prepare( string $query, ...$args ): string {
                return $query;
            }

            public function get_var( ?string $query = null ) {
                return null; // Simulate DB failure / null result.
            }
        };

        $result = WPCLP_DB::count_emails_for_post( 5 );

        $this->assertSame( 0, $result );
    }
}
