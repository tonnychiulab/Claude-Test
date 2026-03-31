<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WPCLP_DB — Database abstraction layer
 *
 * Manages the wp_wpclp_emails table.
 * All public methods are static for simple cross-module access.
 *
 * Table schema:
 *   id           BIGINT UNSIGNED AUTO_INCREMENT PK
 *   post_id      BIGINT UNSIGNED NOT NULL  (FK to wp_posts.ID)
 *   email        VARCHAR(200) NOT NULL
 *   unlocked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
 *   ip_address   VARCHAR(45) NOT NULL DEFAULT ''  (IPv4 or IPv6)
 *   UNIQUE KEY (post_id, email)
 *
 * === WORKER INSTRUCTIONS ===
 * Implement every method stub below. Rules:
 * - NEVER use string interpolation in SQL. Use $wpdb->prepare() with %d/%s.
 * - Use $wpdb->insert() / $wpdb->delete() for writes — they escape automatically.
 * - On DB error: call error_log(), return false/empty array — never expose $wpdb->last_error.
 * - create_table() must use dbDelta() and charset/collate from $wpdb.
 */
class WPCLP_DB {

    /**
     * Create (or upgrade) the emails table using dbDelta.
     * Called from wpclp_activate().
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = WPCLP_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(200) NOT NULL,
            unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY post_email (post_id, email),
            KEY idx_post_id (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Record that $email unlocked $post_id.
     * Silently ignores duplicate (post_id + email) via INSERT IGNORE.
     *
     * @param int    $post_id Sanitized with absint() before calling.
     * @param string $email   Sanitized with sanitize_email() before calling.
     * @param string $ip      Sanitized with sanitize_text_field() before calling.
     * @return bool True on insert, false on failure.
     */
    public static function record_email_unlock( int $post_id, string $email, string $ip ): bool {
        global $wpdb;

        $table_name = WPCLP_TABLE;

        $sql = $wpdb->prepare(
            "INSERT IGNORE INTO `{$table_name}` (post_id, email, unlocked_at, ip_address)
             VALUES (%d, %s, %s, %s)",
            $post_id,
            $email,
            current_time( 'mysql' ),
            $ip
        );

        $result = $wpdb->query( $sql );

        if ( $result === false ) {
            error_log( '[wpclp] record_email_unlock() DB error for post_id=' . $post_id );
            return false;
        }

        // $result is 0 for duplicate (INSERT IGNORE skipped), 1 for actual insert.
        return $result === 1;
    }

    /**
     * Check whether $email has previously unlocked $post_id.
     *
     * @param int    $post_id
     * @param string $email
     * @return bool
     */
    public static function has_email_unlocked( int $post_id, string $email ): bool {
        global $wpdb;

        $table_name = WPCLP_TABLE;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_name}` WHERE post_id = %d AND email = %s",
                $post_id,
                $email
            )
        );

        if ( $result === null ) {
            error_log( '[wpclp] has_email_unlocked() DB error for post_id=' . $post_id );
            return false;
        }

        return (int) $result > 0;
    }

    /**
     * Return all email records for a given post (for admin display).
     *
     * @param int $post_id
     * @return array Array of row objects: {id, email, unlocked_at, ip_address}
     */
    public static function get_emails_for_post( int $post_id ): array {
        global $wpdb;

        $table_name = WPCLP_TABLE;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, email, unlocked_at, ip_address
                 FROM `{$table_name}`
                 WHERE post_id = %d
                 ORDER BY unlocked_at DESC",
                $post_id
            )
        );

        if ( $results === null ) {
            error_log( '[wpclp] get_emails_for_post() DB error for post_id=' . $post_id );
            return array();
        }

        return $results;
    }

    /**
     * Return paginated email records across all posts (for dashboard export).
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_all_emails( int $limit = 100, int $offset = 0 ): array {
        global $wpdb;

        $table_name  = WPCLP_TABLE;
        $posts_table = $wpdb->posts;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.id, e.post_id, e.email, e.unlocked_at, e.ip_address,
                        p.post_title
                 FROM `{$table_name}` AS e
                 LEFT JOIN `{$posts_table}` AS p ON p.ID = e.post_id
                 ORDER BY e.unlocked_at DESC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );

        if ( $results === null ) {
            error_log( '[wpclp] get_all_emails() DB error' );
            return array();
        }

        return $results;
    }

    /**
     * Delete a single email record by its primary key.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_email( int $id ): bool {
        global $wpdb;

        $result = $wpdb->delete(
            WPCLP_TABLE,
            array( 'id' => absint( $id ) ),
            array( '%d' )
        );

        if ( $result === false ) {
            error_log( '[wpclp] delete_email() DB error for id=' . $id );
            return false;
        }

        return $result > 0;
    }

    /**
     * Count how many unique emails have unlocked a post.
     *
     * @param int $post_id
     * @return int
     */
    public static function count_emails_for_post( int $post_id ): int {
        global $wpdb;

        $table_name = WPCLP_TABLE;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_name}` WHERE post_id = %d",
                $post_id
            )
        );

        if ( $count === null ) {
            error_log( '[wpclp] count_emails_for_post() DB error for post_id=' . $post_id );
            return 0;
        }

        return (int) $count;
    }
}
