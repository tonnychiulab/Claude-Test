<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Template: Dashboard view
 * Available variables (set by WPCLP_Dashboard::render_page):
 *   $locked_posts  WP_Post[]  from get_locked_posts()['posts']
 *   $total         int
 *   $current_page  int
 *
 * WORKER: Render the dashboard table. Rules:
 * - All output: esc_html() / esc_attr() / esc_url().
 * - Export CSV button → admin-post.php?action=wpclp_export_emails + wp_nonce_field.
 * - Delete email → POST form with wp_nonce_field('wpclp_delete_email').
 * - Email addresses are PII — do NOT expose in page source beyond what's needed for display.
 */

$per_page     = 20;
$total_pages  = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
$admin_post   = admin_url( 'admin-post.php' );
$base_url     = admin_url( 'options-general.php?page=wp-content-lock-pro' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Content Lock — Dashboard', 'wp-content-lock-pro' ); ?></h1>

    <?php if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Email record deleted.', 'wp-content-lock-pro' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="wpclp-dashboard-header">
        <form method="post" action="<?php echo esc_url( $admin_post ); ?>">
            <input type="hidden" name="action" value="wpclp_export_emails" />
            <?php wp_nonce_field( 'wpclp_export_emails' ); ?>
            <button type="submit" class="button button-secondary wpclp-export-btn">
                <?php esc_html_e( 'Export All Emails (CSV)', 'wp-content-lock-pro' ); ?>
            </button>
        </form>
    </div>

    <?php if ( empty( $locked_posts ) ) : ?>
        <p class="wpclp-no-posts">
            <?php esc_html_e( 'No locked posts found.', 'wp-content-lock-pro' ); ?>
        </p>
    <?php else : ?>

        <table class="widefat striped wpclp-dashboard-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Post Title', 'wp-content-lock-pro' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Type', 'wp-content-lock-pro' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Gate Type', 'wp-content-lock-pro' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Emails Captured', 'wp-content-lock-pro' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Actions', 'wp-content-lock-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $locked_posts as $post ) : ?>
                    <?php
                    $gate_type   = get_post_meta( $post->ID, WPCLP_META_GATE_TYPE, true );
                    $email_count = WPCLP_DB::count_emails_for_post( $post->ID );
                    $post_emails = ( 'email' === $gate_type ) ? WPCLP_DB::get_emails_for_post( $post->ID ) : [];
                    $edit_url    = get_edit_post_link( $post->ID );
                    ?>
                    <tr>
                        <td class="wpclp-col-title">
                            <strong>
                                <a href="<?php echo esc_url( (string) $edit_url ); ?>">
                                    <?php echo esc_html( get_the_title( $post ) ); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html( $post->post_type ); ?></td>
                        <td><?php echo esc_html( $gate_type ?: 'password' ); ?></td>
                        <td>
                            <?php if ( 'email' === $gate_type ) : ?>
                                <span class="wpclp-email-count"><?php echo esc_html( (string) $email_count ); ?></span>
                                <?php if ( ! empty( $post_emails ) ) : ?>
                                    <button
                                        type="button"
                                        class="button-link wpclp-toggle-emails"
                                        data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
                                        aria-expanded="false"
                                    >
                                        <?php esc_html_e( 'Show emails', 'wp-content-lock-pro' ); ?>
                                    </button>
                                    <div
                                        class="wpclp-email-list"
                                        id="wpclp-emails-<?php echo esc_attr( (string) $post->ID ); ?>"
                                        hidden
                                    >
                                        <table class="wpclp-email-table">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e( 'Email', 'wp-content-lock-pro' ); ?></th>
                                                    <th><?php esc_html_e( 'Captured At', 'wp-content-lock-pro' ); ?></th>
                                                    <th><?php esc_html_e( 'Delete', 'wp-content-lock-pro' ); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ( $post_emails as $email_row ) : ?>
                                                    <tr>
                                                        <td><?php echo esc_html( $email_row->email ?? '' ); ?></td>
                                                        <td><?php echo esc_html( $email_row->created_at ?? '' ); ?></td>
                                                        <td>
                                                            <form method="post" action="<?php echo esc_url( $admin_post ); ?>" class="wpclp-delete-email-form">
                                                                <input type="hidden" name="action" value="wpclp_delete_email" />
                                                                <input type="hidden" name="email_id" value="<?php echo esc_attr( (string) absint( $email_row->id ?? 0 ) ); ?>" />
                                                                <?php wp_nonce_field( 'wpclp_delete_email' ); ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button-link wpclp-delete-btn"
                                                                    onclick="return confirm('<?php echo esc_js( __( 'Delete this email record?', 'wp-content-lock-pro' ) ); ?>')"
                                                                >
                                                                    <?php esc_html_e( 'Delete', 'wp-content-lock-pro' ); ?>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php esc_html_e( 'N/A', 'wp-content-lock-pro' ); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( (string) $edit_url ); ?>" class="button button-small">
                                <?php esc_html_e( 'Edit Post', 'wp-content-lock-pro' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="wpclp-pagination tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        'base'      => esc_url( add_query_arg( 'paged', '%#%', $base_url ) ),
                        'format'    => '',
                        'current'   => $current_page,
                        'total'     => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ] );
                    ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <p class="wpclp-dashboard-footer description">
        <?php
        printf(
            /* translators: %d: total number of locked posts */
            esc_html( _n( '%d locked post total.', '%d locked posts total.', $total, 'wp-content-lock-pro' ) ),
            esc_html( (string) $total )
        );
        ?>
    </p>
</div>
