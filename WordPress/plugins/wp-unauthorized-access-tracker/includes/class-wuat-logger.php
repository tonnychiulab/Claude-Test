<?php
/**
 * WUAT Logger — creates the DB table and writes audit events.
 *
 * @package WUAT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAT_Logger
 *
 * Hooks into WordPress authentication events and persists them to the custom
 * audit-log table using safe, prepared queries.
 */
class WUAT_Logger {

	/**
	 * DB schema version (bumped when table structure changes).
	 */
	const DB_VERSION = '1.0';

	// ── Constructor / hook registration ─────────────────────────────────────

	public function __construct() {
		add_action( 'wp_login',        array( $this, 'on_login_success' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 10, 2 );
		add_action( 'wp_logout',       array( $this, 'on_logout' ), 10, 0 );
	}

	// ── WordPress hook callbacks ─────────────────────────────────────────────

	/**
	 * Record a successful login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       WordPress user object.
	 */
	public function on_login_success( $user_login, $user ) {
		$details = wp_json_encode(
			array(
				'user_id'    => absint( $user->ID ),
				'user_email' => sanitize_email( $user->user_email ),
				'roles'      => (array) $user->roles,
			)
		);

		$this->insert_log( 'login_success', $user_login, absint( $user->ID ), $details );
	}

	/**
	 * Record a failed login attempt.
	 *
	 * @param string   $username Username that was tried.
	 * @param WP_Error $error    Authentication error.
	 */
	public function on_login_failed( $username, $error ) {
		// NEVER log the attempted password — only the error code.
		$error_code = $error instanceof WP_Error ? $error->get_error_code() : 'unknown';
		$details    = wp_json_encode(
			array(
				'error_code' => sanitize_key( $error_code ),
			)
		);

		$this->insert_log( 'login_failed', $username, 0, $details );
	}

	/**
	 * Record a logout.
	 */
	public function on_logout() {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}

		$details = wp_json_encode( array( 'user_id' => absint( $user->ID ) ) );
		$this->insert_log( 'logout', $user->user_login, absint( $user->ID ), $details );
	}

	// ── Public write API ─────────────────────────────────────────────────────

	/**
	 * Insert an arbitrary audit event (used by Detector and Off_Hours).
	 *
	 * @param string $event_type Event key (login_success|login_failed|logout|lockout|offhours_login).
	 * @param string $user_login Username.
	 * @param int    $user_id    WP User ID (0 if unknown).
	 * @param string $details    JSON-encoded extra context.
	 */
	public function insert_log( $event_type, $user_login, $user_id = 0, $details = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . WUAT_TABLE_NAME;

		// Audit-trail inserts must go directly to DB — no caching makes sense here.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'event_type' => sanitize_key( $event_type ),
				'user_login' => sanitize_user( $user_login ),
				'user_id'    => absint( $user_id ),
				'ip_address' => $this->get_client_ip(),
				'user_agent' => sanitize_text_field( substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 500 ) ),
				'event_time' => current_time( 'mysql' ),
				'details'    => sanitize_text_field( $details ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	// ── DB helpers ────────────────────────────────────────────────────────────

	/**
	 * Create (or upgrade) the audit-log table using dbDelta.
	 * Called on plugin activation.
	 */
	public static function create_table() {
		global $wpdb;
		$table       = $wpdb->prefix . WUAT_TABLE_NAME;
		$charset     = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type  varchar(50)         NOT NULL DEFAULT '',
			user_login  varchar(60)         NOT NULL DEFAULT '',
			user_id     bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_address  varchar(45)         NOT NULL DEFAULT '',
			user_agent  text                NOT NULL,
			event_time  datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			details     text                NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_event_type (event_type),
			KEY idx_user_login (user_login),
			KEY idx_event_time (event_time),
			KEY idx_ip_address (ip_address)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wuat_db_version', self::DB_VERSION );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Return the client IP address, optionally anonymised.
	 *
	 * Uses only REMOTE_ADDR to avoid spoofing via proxy headers.
	 *
	 * @param bool|null $anonymize Pass true/false to override; null reads option.
	 * @return string
	 */
	public function get_client_ip( $anonymize = null ) {
		if ( null === $anonymize ) {
			$settings  = get_option( 'wuat_settings', array() );
			$anonymize = ! empty( $settings['ip_anonymize'] );
		}

		// Trust only REMOTE_ADDR — X-Forwarded-For can be spoofed.
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '0.0.0.0';
		}

		if ( $anonymize ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				$ip = preg_replace( '/\.\d+$/', '.0', $ip );
			} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				$packed = inet_pton( $ip );
				if ( false !== $packed ) {
					$ip = inet_ntop( substr( $packed, 0, 6 ) . str_repeat( "\0", 10 ) );
				}
			}
		}

		return (string) $ip;
	}
}
