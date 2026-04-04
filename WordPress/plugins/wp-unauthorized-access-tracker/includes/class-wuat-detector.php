<?php
/**
 * WUAT Detector — brute-force detection logic.
 *
 * @package WUAT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAT_Detector
 *
 * Monitors failed-login counts per IP.  When the count exceeds the configured
 * threshold within the time window, it fires the `wuat_brute_force_detected`
 * action and dispatches an alert email.
 */
class WUAT_Detector {

	/** @var WUAT_Logger */
	private $logger;

	/** @var WUAT_Alerter */
	private $alerter;

	/**
	 * @param WUAT_Logger  $logger
	 * @param WUAT_Alerter $alerter
	 */
	public function __construct( WUAT_Logger $logger, WUAT_Alerter $alerter ) {
		$this->logger  = $logger;
		$this->alerter = $alerter;

		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 20, 2 );
	}

	// ── Hook callback ─────────────────────────────────────────────────────────

	/**
	 * Called after every failed login.  Checks whether the originating IP has
	 * exceeded the brute-force threshold.
	 *
	 * @param string   $username Username that was tried.
	 * @param WP_Error $error    WP_Error from the failed authentication.
	 */
	public function on_login_failed( $username, $error ) {
		$ip = $this->logger->get_client_ip( false ); // raw (non-anonymised) for detection

		if ( $this->check_brute_force( $ip ) ) {
			$details = wp_json_encode(
				array(
					'ip'       => $ip,
					'username' => sanitize_user( $username ),
					'trigger'  => 'brute_force_threshold',
				)
			);

			// Log a lockout event.
			$this->logger->insert_log( 'lockout', $username, 0, $details );

			// Allow third-party plugins to react.
			do_action( 'wuat_brute_force_detected', $ip, $username );

			// Send alert email.
			$this->alerter->send_brute_force_alert( $ip, sanitize_user( $username ) );
		}
	}

	// ── Detection logic ───────────────────────────────────────────────────────

	/**
	 * Return true if $ip has exceeded the failure threshold within the window.
	 *
	 * Uses a transient as a lightweight cache to avoid a DB hit on every request.
	 *
	 * @param string $ip Client IP address.
	 * @return bool
	 */
	public function check_brute_force( $ip ) {
		$settings  = get_option( 'wuat_settings', array() );
		$threshold = absint( $settings['brute_force_threshold'] ?? 5 );
		$window    = absint( $settings['brute_force_window'] ?? 15 );

		// Transient cache key — uses md5 so any IP string is safe as a key.
		$cache_key    = 'wuat_fail_count_' . md5( $ip );
		$cached_count = get_transient( $cache_key );

		if ( false !== $cached_count && intval( $cached_count ) >= $threshold ) {
			return true;
		}

		// Authoritative count from DB.
		global $wpdb;
		$table = $wpdb->prefix . WUAT_TABLE_NAME;
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $window * MINUTE_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}`
				 WHERE event_type = 'login_failed'
				   AND ip_address = %s
				   AND event_time > %s",
				$ip,
				$since
			)
		);

		// Refresh transient.
		set_transient( $cache_key, $count, $window * MINUTE_IN_SECONDS );

		return $count >= $threshold;
	}
}
