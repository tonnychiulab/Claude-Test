<?php
/**
 * WUAT Alerter — email notification dispatcher.
 *
 * @package WUAT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAT_Alerter
 *
 * Sends admin email alerts for brute-force attacks and off-hours logins.
 * Exposes the `wuat_send_alert` filter so integrations (Slack, webhook, etc.)
 * can intercept or augment the delivery.
 */
class WUAT_Alerter {

	// ── Public alert methods ──────────────────────────────────────────────────

	/**
	 * Send a brute-force alert email.
	 *
	 * @param string $ip       Client IP address.
	 * @param string $username Username that triggered the lockout.
	 */
	public function send_brute_force_alert( $ip, $username ) {
		$settings = get_option( 'wuat_settings', array() );

		if ( empty( $settings['enable_email_alerts'] ) ) {
			return;
		}

		$recipient = sanitize_email( $settings['alert_email'] ?? get_option( 'admin_email' ) );
		if ( ! is_email( $recipient ) ) {
			return;
		}

		/* translators: Email subject for brute-force alert. %s = site name. */
		$subject = sprintf(
			__( '[%s] Security Alert: Brute-Force Attack Detected', 'wp-unauthorized-access-tracker' ),
			get_bloginfo( 'name' )
		);

		$recent = $this->get_recent_failures( $ip, 10 );

		$body = $this->build_email_body(
			/* translators: Email body heading. */
			__( 'Brute-Force Attack Detected', 'wp-unauthorized-access-tracker' ),
			array(
				/* translators: 1: site URL */
				sprintf( __( 'Site: %s', 'wp-unauthorized-access-tracker' ), esc_url( get_home_url() ) ),
				/* translators: 1: IP address */
				sprintf( __( 'Source IP: %s', 'wp-unauthorized-access-tracker' ), esc_html( $ip ) ),
				/* translators: 1: username */
				sprintf( __( 'Username: %s', 'wp-unauthorized-access-tracker' ), esc_html( $username ) ),
				/* translators: 1: timestamp */
				sprintf( __( 'Time (UTC): %s', 'wp-unauthorized-access-tracker' ), gmdate( 'Y-m-d H:i:s' ) ),
			),
			$recent
		);

		/**
		 * Filter the alert before sending.
		 * Return false to cancel delivery entirely.
		 *
		 * @param array  $alert     { 'to', 'subject', 'body' }
		 * @param string $type      Alert type: 'brute_force' | 'offhours_login'
		 * @param array  $context   Event context data
		 */
		$alert = apply_filters(
			'wuat_send_alert',
			array(
				'to'      => $recipient,
				'subject' => $subject,
				'body'    => $body,
			),
			'brute_force',
			compact( 'ip', 'username' )
		);

		if ( false !== $alert ) {
			wp_mail( $alert['to'], $alert['subject'], $alert['body'] );
		}
	}

	/**
	 * Send an off-hours login alert email.
	 *
	 * @param string $username  Username that logged in.
	 * @param int    $user_id   WP user ID.
	 * @param string $ip        Client IP.
	 * @param string $event_time Login timestamp (local WP time, mysql format).
	 */
	public function send_offhours_alert( $username, $user_id, $ip, $event_time ) {
		$settings = get_option( 'wuat_settings', array() );

		if ( empty( $settings['enable_email_alerts'] ) ) {
			return;
		}

		$recipient = sanitize_email( $settings['alert_email'] ?? get_option( 'admin_email' ) );
		if ( ! is_email( $recipient ) ) {
			return;
		}

		/* translators: Email subject for off-hours login alert. %s = site name. */
		$subject = sprintf(
			__( '[%s] Security Alert: Off-Hours Admin Login Detected', 'wp-unauthorized-access-tracker' ),
			get_bloginfo( 'name' )
		);

		$body = $this->build_email_body(
			__( 'Off-Hours Admin Login Detected', 'wp-unauthorized-access-tracker' ),
			array(
				sprintf( __( 'Site: %s', 'wp-unauthorized-access-tracker' ), esc_url( get_home_url() ) ),
				sprintf( __( 'Username: %s', 'wp-unauthorized-access-tracker' ), esc_html( $username ) ),
				sprintf( __( 'User ID: %d', 'wp-unauthorized-access-tracker' ), absint( $user_id ) ),
				sprintf( __( 'Source IP: %s', 'wp-unauthorized-access-tracker' ), esc_html( $ip ) ),
				sprintf( __( 'Login Time (site): %s', 'wp-unauthorized-access-tracker' ), esc_html( $event_time ) ),
			),
			array()
		);

		$alert = apply_filters(
			'wuat_send_alert',
			array(
				'to'      => $recipient,
				'subject' => $subject,
				'body'    => $body,
			),
			'offhours_login',
			compact( 'username', 'user_id', 'ip', 'event_time' )
		);

		if ( false !== $alert ) {
			wp_mail( $alert['to'], $alert['subject'], $alert['body'] );
		}
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Build a plain-text email body.
	 *
	 * @param string $heading  Alert heading line.
	 * @param array  $details  Lines of detail text.
	 * @param array  $recent   Recent log rows (stdClass[]).
	 * @return string
	 */
	private function build_email_body( $heading, array $details, array $recent ) {
		$nl   = "\r\n";
		$sep  = str_repeat( '-', 60 );
		$body = $heading . $nl . $sep . $nl;

		foreach ( $details as $line ) {
			$body .= $line . $nl;
		}

		if ( ! empty( $recent ) ) {
			$body .= $nl . __( 'Recent failed attempts from this IP:', 'wp-unauthorized-access-tracker' ) . $nl;
			$body .= $sep . $nl;
			foreach ( $recent as $row ) {
				$body .= sprintf(
					'%s  %s  %s' . $nl,
					esc_html( $row->event_time ),
					esc_html( $row->user_login ),
					esc_html( $row->event_type )
				);
			}
		}

		$body .= $sep . $nl;
		$body .= sprintf(
			/* translators: Link to plugin settings page. */
			__( 'Manage alerts: %s', 'wp-unauthorized-access-tracker' ),
			admin_url( 'admin.php?page=wuat-settings' )
		);

		return $body;
	}

	/**
	 * Fetch the N most recent login_failed rows for a given IP.
	 *
	 * @param string $ip    Client IP.
	 * @param int    $limit Max rows to return.
	 * @return array
	 */
	private function get_recent_failures( $ip, $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . WUAT_TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_time, user_login, event_type FROM `{$table}`
				 WHERE event_type = 'login_failed' AND ip_address = %s
				 ORDER BY event_time DESC
				 LIMIT %d",
				$ip,
				absint( $limit )
			)
		);
	}
}
