<?php
/**
 * WUAT Off_Hours — detects admin logins that occur outside business hours.
 *
 * @package WUAT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAT_Off_Hours
 *
 * On every successful login this class checks whether:
 *   1. The user has a role that should be monitored (default: administrator).
 *   2. The current site-local time falls outside the configured business hours.
 *
 * If both conditions are true it logs an `offhours_login` event and sends an
 * alert email via WUAT_Alerter.
 */
class WUAT_Off_Hours {

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

		add_action( 'wp_login', array( $this, 'on_login_success' ), 20, 2 );
	}

	// ── Hook callback ─────────────────────────────────────────────────────────

	/**
	 * Evaluate a successful login against business-hour rules.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       WordPress user object.
	 */
	public function on_login_success( $user_login, WP_User $user ) {
		$settings = get_option( 'wuat_settings', array() );

		// Feature toggle.
		if ( empty( $settings['offhours_enabled'] ) ) {
			return;
		}

		// Role check.
		if ( ! $this->user_is_monitored( $user, $settings ) ) {
			return;
		}

		// Time check.
		if ( ! $this->is_off_hours( $settings ) ) {
			return;
		}

		$ip         = $this->logger->get_client_ip( false );
		$event_time = current_time( 'mysql' );

		$details = wp_json_encode(
			array(
				'user_id'     => absint( $user->ID ),
				'roles'       => (array) $user->roles,
				'ip'          => $ip,
				'login_time'  => $event_time,
				'biz_start'   => $settings['business_hours_start'] ?? '09:00',
				'biz_end'     => $settings['business_hours_end']   ?? '18:00',
				'biz_days'    => $settings['business_days']        ?? array( 1, 2, 3, 4, 5 ),
			)
		);

		// Log dedicated event type.
		$this->logger->insert_log( 'offhours_login', $user_login, absint( $user->ID ), $details );

		// Allow third-party plugins to react.
		do_action( 'wuat_offhours_login_detected', $user, $ip, $event_time );

		// Send alert.
		$this->alerter->send_offhours_alert(
			sanitize_user( $user_login ),
			absint( $user->ID ),
			$ip,
			$event_time
		);
	}

	// ── Business-hour logic ───────────────────────────────────────────────────

	/**
	 * Return true when the current time is OUTSIDE business hours.
	 *
	 * Uses the WordPress site timezone (configured under Settings > General).
	 *
	 * @param array $settings Plugin settings array.
	 * @return bool
	 */
	public function is_off_hours( array $settings ) {
		$tz = wp_timezone();

		// Current site-local time.
		$now     = new DateTimeImmutable( 'now', $tz );
		$dow     = (int) $now->format( 'N' ); // 1 = Mon … 7 = Sun
		$hm_now  = $now->format( 'H:i' );    // "14:35"

		// Configured business days (default Mon–Fri).
		$biz_days = array_map( 'absint', (array) ( $settings['business_days'] ?? array( 1, 2, 3, 4, 5 ) ) );

		// Today is not a business day → off-hours.
		if ( ! in_array( $dow, $biz_days, true ) ) {
			return true;
		}

		// Business-hour boundaries.
		$start = sanitize_text_field( $settings['business_hours_start'] ?? '09:00' );
		$end   = sanitize_text_field( $settings['business_hours_end']   ?? '18:00' );

		// Validate HH:MM format; fall back to defaults on garbage input.
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $start ) ) {
			$start = '09:00';
		}
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $end ) ) {
			$end = '18:00';
		}

		// Off-hours = before start OR at/after end.
		return ( $hm_now < $start || $hm_now >= $end );
	}

	/**
	 * Return true if the user's roles should be monitored for off-hours logins.
	 *
	 * Controlled by `offhours_monitor_roles` setting (array of role slugs).
	 * Default: only `administrator`.
	 *
	 * @param WP_User $user     WordPress user.
	 * @param array   $settings Plugin settings.
	 * @return bool
	 */
	private function user_is_monitored( WP_User $user, array $settings ) {
		$monitor_roles = (array) ( $settings['offhours_monitor_roles'] ?? array( 'administrator' ) );

		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $monitor_roles, true ) ) {
				return true;
			}
		}

		return false;
	}
}
