<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WPCLP_Dashboard — Admin dashboard page
 *
 * Adds "Content Lock" under the Settings menu.
 * Renders admin/views/dashboard.php.
 *
 * Features:
 *   1. List of all locked posts (query posts with _wpclp_enabled = '1')
 *   2. Per-post email count (via WPCLP_DB::count_emails_for_post)
 *   3. Expandable email list per post (via WPCLP_DB::get_emails_for_post)
 *   4. Delete individual email record (admin-post: wpclp_delete_email)
 *   5. Export all emails to CSV (admin-post: wpclp_export_emails)
 *
 * === WORKER INSTRUCTIONS ===
 * Implement all stubs. Security rules:
 * - register_menu(): add_options_page, capability 'manage_options'.
 * - render_page(): current_user_can('manage_options') check at top.
 *   All DB values output via esc_html(). Email addresses are PII — never output raw.
 * - enqueue_assets(): only on this page's $hook.
 * - handle_export_csv():
 *   a. check_admin_referer('wpclp_export_emails')
 *   b. current_user_can('manage_options')
 *   c. Output headers: Content-Type text/csv, Content-Disposition attachment
 *   d. Loop WPCLP_DB::get_all_emails() in batches of 200
 *   e. Sanitize each cell before fputcsv()
 *   f. exit after output
 * - handle_delete_email():
 *   a. check_admin_referer('wpclp_delete_email')
 *   b. current_user_can('manage_options')
 *   c. absint() the ID
 *   d. WPCLP_DB::delete_email()
 *   e. wp_safe_redirect() back to dashboard
 */
class WPCLP_Dashboard {

    /**
     * Page hook suffix returned by add_options_page().
     *
     * @var string
     */
    private string $page_hook = '';

    public function init(): void {
        add_action( 'admin_menu',                          [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts',               [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_wpclp_export_emails',      [ $this, 'handle_export_csv' ] );
        add_action( 'admin_post_wpclp_delete_email',       [ $this, 'handle_delete_email' ] );
    }

    /**
     * Register dashboard page under Settings.
     */
    public function register_menu(): void {
        $this->page_hook = add_options_page(
            __( 'Content Lock Pro', 'wp-content-lock-pro' ),
            __( 'Content Lock', 'wp-content-lock-pro' ),
            'manage_options',
            'wp-content-lock-pro',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Render the dashboard page.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-content-lock-pro' ) );
        }

        $current_page  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $results       = $this->get_locked_posts( $current_page );
        $locked_posts  = $results['posts'];
        $total         = $results['total'];

        require WPCLP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Enqueue dashboard assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {
        if ( $hook !== $this->page_hook ) {
            return;
        }

        wp_enqueue_style(
            'wpclp-admin',
            WPCLP_PLUGIN_URL . 'assets/admin.css',
            [],
            WPCLP_VERSION
        );

        wp_enqueue_script(
            'wpclp-admin',
            WPCLP_PLUGIN_URL . 'assets/admin.js',
            [],
            WPCLP_VERSION,
            true
        );
    }

    /**
     * Handle CSV export of all captured emails.
     * Streams CSV directly — calls exit after output.
     */
    public function handle_export_csv(): void {
        check_admin_referer( 'wpclp_export_emails' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-content-lock-pro' ) );
        }

        $filename = 'wpclp-emails-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            exit;
        }

        // UTF-8 BOM for Excel compatibility
        fwrite( $output, "\xEF\xBB\xBF" );

        // CSV header row
        fputcsv( $output, [ 'ID', 'Post ID', 'Email', 'Captured At' ] );

        $batch_size = 200;
        $offset     = 0;

        do {
            $rows = WPCLP_DB::get_all_emails( $batch_size, $offset );

            foreach ( $rows as $row ) {
                fputcsv( $output, [
                    absint( $row->id ?? 0 ),
                    absint( $row->post_id ?? 0 ),
                    sanitize_email( $row->email ?? '' ),
                    sanitize_text_field( $row->created_at ?? '' ),
                ] );
            }

            $offset += $batch_size;
        } while ( count( $rows ) === $batch_size );

        fclose( $output );
        exit;
    }

    /**
     * Handle deletion of a single email record.
     */
    public function handle_delete_email(): void {
        check_admin_referer( 'wpclp_delete_email' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-content-lock-pro' ) );
        }

        $email_id = isset( $_POST['email_id'] ) ? absint( $_POST['email_id'] ) : 0;

        if ( $email_id > 0 ) {
            WPCLP_DB::delete_email( $email_id );
        }

        $redirect_url = add_query_arg(
            [ 'page' => 'wp-content-lock-pro', 'deleted' => '1' ],
            admin_url( 'options-general.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Return paginated list of locked posts with their lock config.
     *
     * @param int $page 1-based page number
     * @param int $per_page
     * @return array { posts: WP_Post[], total: int }
     */
    public function get_locked_posts( int $page = 1, int $per_page = 20 ): array {
        $query = new WP_Query( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'   => WPCLP_META_ENABLED,
                    'value' => '1',
                ],
            ],
        ] );

        return [
            'posts' => $query->posts,
            'total' => (int) $query->found_posts,
        ];
    }
}
