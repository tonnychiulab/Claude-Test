<?php
/**
 * WUAT Settings — Settings API integration.
 *
 * @package WUAT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAT_Settings
 *
 * Registers and renders all plugin settings using the WordPress Settings API.
 * All options are stored in a single `wuat_settings` option array.
 */
class WUAT_Settings {

	/** Option name in wp_options. */
	const OPTION_KEY = 'wuat_settings';

	public function __construct() {
		add_action( 'admin_menu',    array( $this, 'add_settings_page' ) );
		add_action( 'admin_init',    array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// ── Menu ─────────────────────────────────────────────────────────────────

	public function add_settings_page() {
		add_submenu_page(
			'wuat-audit-trail',
			__( 'WUAT Settings', 'wp-unauthorized-access-tracker' ),
			__( 'Settings', 'wp-unauthorized-access-tracker' ),
			'manage_options',
			'wuat-settings',
			array( $this, 'render_page' )
		);
	}

	// ── Settings API registration ─────────────────────────────────────────────

	public function register_settings() {
		register_setting(
			'wuat_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		// ── Section: Brute-force detection ────────────────────────────────────
		add_settings_section(
			'wuat_section_bruteforce',
			__( 'Brute-Force Detection', 'wp-unauthorized-access-tracker' ),
			null,
			'wuat-settings'
		);

		$this->add_field( 'brute_force_threshold', __( 'Failure Threshold', 'wp-unauthorized-access-tracker' ),
			'render_number', 'wuat_section_bruteforce',
			__( 'Number of failed logins from the same IP that triggers an alert.', 'wp-unauthorized-access-tracker' ) );

		$this->add_field( 'brute_force_window', __( 'Time Window (minutes)', 'wp-unauthorized-access-tracker' ),
			'render_number', 'wuat_section_bruteforce',
			__( 'The rolling window (in minutes) in which failures are counted.', 'wp-unauthorized-access-tracker' ) );

		// ── Section: Off-Hours Detection ─────────────────────────────────────
		add_settings_section(
			'wuat_section_offhours',
			__( 'Off-Hours Login Detection', 'wp-unauthorized-access-tracker' ),
			array( $this, 'render_offhours_intro' ),
			'wuat-settings'
		);

		$this->add_field( 'offhours_enabled', __( 'Enable Off-Hours Detection', 'wp-unauthorized-access-tracker' ),
			'render_checkbox', 'wuat_section_offhours',
			__( 'Alert when a monitored user logs in outside business hours.', 'wp-unauthorized-access-tracker' ) );

		$this->add_field( 'business_hours_start', __( 'Business Hours Start', 'wp-unauthorized-access-tracker' ),
			'render_time', 'wuat_section_offhours',
			__( 'Format: HH:MM (24h). Uses the site timezone set under Settings › General.', 'wp-unauthorized-access-tracker' ) );

		$this->add_field( 'business_hours_end', __( 'Business Hours End', 'wp-unauthorized-access-tracker' ),
			'render_time', 'wuat_section_offhours',
			__( 'Format: HH:MM (24h).', 'wp-unauthorized-access-tracker' ) );

		$this->add_field( 'business_days', __( 'Business Days', 'wp-unauthorized-access-tracker' ),
			'render_business_days', 'wuat_section_offhours',
			__( 'Days considered business days. Logins on other days are always flagged.', 'wp-unauthorized-access-tracker' ) );

		$this->add_field( 'offhours_monitor_roles', __( 'Monitor Roles', 'wp-unauthorized-access-tracker' ),
			'render_monitor_roles', 'wuat_section_offhours',
			__( 'Only logins by users with these roles trigger off-hours alerts.', 'wp-unauthorized-access-tracker' ) );

		// ── Section: Notifications ─────────────────────────────────────────────
		add_settings_section(
			'wuat_section_notifications',
			__( 'Notifications', 'wp-unauthorized-access-tracker' ),
			null,
			'wuat-settings'
		);

		$this->add_field( 'enable_email_alerts', __( 'Enable Email Alerts', 'wp-unauthorized-access-tracker' ),
			'render_checkbox', 'wuat_section_notifications',
			__( 'Send email alerts for brute-force attacks and off-hours logins.', 'wp-unauthorized-access-tracker' ) );

		$this->add_field( 'alert_email', __( 'Alert Email Address', 'wp-unauthorized-access-tracker' ),
			'render_email', 'wuat_section_notifications',
			__( 'Recipient for security alerts. Defaults to the site admin email.', 'wp-unauthorized-access-tracker' ) );

		// ── Section: Log management ───────────────────────────────────────────
		add_settings_section(
			'wuat_section_log',
			__( 'Log Management', 'wp-unauthorized-access-tracker' ),
			null,
			'wuat-settings'
		);

		$this->add_field( 'log_retention_days', __( 'Log Retention (days)', 'wp-unauthorized-access-tracker' ),
			'render_number', 'wuat_section_log',
			__( 'Log entries older than this many days are automatically deleted.', 'wp-unauthorized-access-tracker' ) );

		$this->add_field( 'ip_anonymize', __( 'Anonymize IP Addresses', 'wp-unauthorized-access-tracker' ),
			'render_checkbox', 'wuat_section_log',
			__( 'Mask the last octet of IPv4 / last 80 bits of IPv6 before storage (GDPR).', 'wp-unauthorized-access-tracker' ) );

		$this->add_field( 'show_ip_lookup_link', __( 'Enable IP Lookup Links', 'wp-unauthorized-access-tracker' ),
			'render_checkbox', 'wuat_section_log',
			__( 'Show a clickable link to ipinfo.io for each IP address in the log table. Disabled by default to prevent sending visitor IPs to a third-party service.', 'wp-unauthorized-access-tracker' ) );
	}

	// ── Sanitization ──────────────────────────────────────────────────────────

	/**
	 * Sanitize all option values before saving.
	 *
	 * @param mixed $raw Raw POST data.
	 * @return array
	 */
	public function sanitize_options( $raw ) {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$defaults = self::defaults();
		$clean    = array();

		$clean['brute_force_threshold'] = max( 1, absint( $raw['brute_force_threshold'] ?? $defaults['brute_force_threshold'] ) );
		$clean['brute_force_window']    = max( 1, absint( $raw['brute_force_window']    ?? $defaults['brute_force_window'] ) );

		// Off-hours.
		$clean['offhours_enabled']      = ! empty( $raw['offhours_enabled'] );

		$start = sanitize_text_field( $raw['business_hours_start'] ?? $defaults['business_hours_start'] );
		$end   = sanitize_text_field( $raw['business_hours_end']   ?? $defaults['business_hours_end'] );
		$clean['business_hours_start']  = preg_match( '/^\d{2}:\d{2}$/', $start ) ? $start : $defaults['business_hours_start'];
		$clean['business_hours_end']    = preg_match( '/^\d{2}:\d{2}$/', $end )   ? $end   : $defaults['business_hours_end'];

		// Business days: array of ints 1–7.
		$raw_days = isset( $raw['business_days'] ) ? (array) $raw['business_days'] : $defaults['business_days'];
		$clean['business_days'] = array_values(
			array_filter(
				array_map( 'absint', $raw_days ),
				static function( $d ) { return $d >= 1 && $d <= 7; }
			)
		);

		// Monitor roles: sanitize each role slug.
		$raw_roles = isset( $raw['offhours_monitor_roles'] ) ? (array) $raw['offhours_monitor_roles'] : $defaults['offhours_monitor_roles'];
		$clean['offhours_monitor_roles'] = array_values( array_map( 'sanitize_key', $raw_roles ) );

		// Notifications.
		$clean['enable_email_alerts'] = ! empty( $raw['enable_email_alerts'] );
		$email = sanitize_email( $raw['alert_email'] ?? '' );
		$clean['alert_email'] = is_email( $email ) ? $email : get_option( 'admin_email' );

		// Log management.
		$clean['log_retention_days']   = max( 1, absint( $raw['log_retention_days'] ?? $defaults['log_retention_days'] ) );
		$clean['ip_anonymize']         = ! empty( $raw['ip_anonymize'] );
		$clean['show_ip_lookup_link']  = ! empty( $raw['show_ip_lookup_link'] );

		return $clean;
	}

	/**
	 * Return default option values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'brute_force_threshold'  => 5,
			'brute_force_window'     => 15,
			'offhours_enabled'       => false,
			'business_hours_start'   => '09:00',
			'business_hours_end'     => '18:00',
			'business_days'          => array( 1, 2, 3, 4, 5 ),
			'offhours_monitor_roles' => array( 'administrator' ),
			'enable_email_alerts'    => true,
			'alert_email'            => '',
			'log_retention_days'     => 90,
			'ip_anonymize'           => false,
			'show_ip_lookup_link'    => false,
		);
	}

	// ── Page render ───────────────────────────────────────────────────────────

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Unauthorized Access Tracker — Settings', 'wp-unauthorized-access-tracker' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wuat_settings_group' );
				do_settings_sections( 'wuat-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// ── Field renderers ───────────────────────────────────────────────────────

	private function get_option( $key ) {
		$options  = get_option( self::OPTION_KEY, array() );
		$defaults = self::defaults();
		return $options[ $key ] ?? $defaults[ $key ] ?? '';
	}

	public function render_offhours_intro() {
		echo '<p>' . esc_html__( 'Alert when a monitored user logs in outside the configured business hours. Uses the site timezone configured under Settings › General.', 'wp-unauthorized-access-tracker' ) . '</p>';
	}

	public function render_number( $args ) {
		$key   = $args['label_for'];
		$value = $this->get_option( $key );
		printf(
			'<input type="number" min="1" id="%s" name="%s[%s]" value="%s" class="small-text"> <p class="description">%s</p>',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_html( $args['description'] ?? '' )
		);
	}

	public function render_checkbox( $args ) {
		$key     = $args['label_for'];
		$checked = $this->get_option( $key );
		printf(
			'<label><input type="checkbox" id="%s" name="%s[%s]" value="1" %s> %s</label>',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $args['description'] ?? '' )
		);
	}

	public function render_email( $args ) {
		$key   = $args['label_for'];
		$value = $this->get_option( $key );
		if ( ! $value ) {
			$value = get_option( 'admin_email' );
		}
		printf(
			'<input type="email" id="%s" name="%s[%s]" value="%s" class="regular-text"> <p class="description">%s</p>',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_html( $args['description'] ?? '' )
		);
	}

	public function render_time( $args ) {
		$key   = $args['label_for'];
		$value = $this->get_option( $key );
		printf(
			'<input type="time" id="%s" name="%s[%s]" value="%s"> <p class="description">%s</p>',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_html( $args['description'] ?? '' )
		);
	}

	public function render_business_days( $args ) {
		$selected = (array) $this->get_option( 'business_days' );
		$days     = array(
			1 => __( 'Monday', 'wp-unauthorized-access-tracker' ),
			2 => __( 'Tuesday', 'wp-unauthorized-access-tracker' ),
			3 => __( 'Wednesday', 'wp-unauthorized-access-tracker' ),
			4 => __( 'Thursday', 'wp-unauthorized-access-tracker' ),
			5 => __( 'Friday', 'wp-unauthorized-access-tracker' ),
			6 => __( 'Saturday', 'wp-unauthorized-access-tracker' ),
			7 => __( 'Sunday', 'wp-unauthorized-access-tracker' ),
		);

		echo '<fieldset>';
		foreach ( $days as $num => $label ) {
			printf(
				'<label style="margin-right:12px"><input type="checkbox" name="%s[business_days][]" value="%d" %s> %s</label>',
				esc_attr( self::OPTION_KEY ),
				(int) $num,
				checked( in_array( $num, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html( $args['description'] ?? '' ) . '</p>';
	}

	public function render_monitor_roles( $args ) {
		$selected     = (array) $this->get_option( 'offhours_monitor_roles' );
		$all_roles    = wp_roles()->get_names();

		echo '<fieldset>';
		foreach ( $all_roles as $slug => $name ) {
			printf(
				'<label style="margin-right:12px"><input type="checkbox" name="%s[offhours_monitor_roles][]" value="%s" %s> %s</label>',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $slug ),
				checked( in_array( $slug, $selected, true ), true, false ),
				esc_html( translate_user_role( $name ) )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html( $args['description'] ?? '' ) . '</p>';
	}

	// ── Asset enqueue ────────────────────────────────────────────────────────

	public function enqueue_assets( $hook ) {
		if ( 'audit-trail_page_wuat-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wuat-admin-css',
			WUAT_PLUGIN_URL . 'assets/css/wuat-admin.css',
			array(),
			WUAT_VERSION
		);
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Shorthand to register a field under a section.
	 */
	private function add_field( $key, $title, $render_cb, $section, $description = '' ) {
		add_settings_field(
			$key,
			$title,
			array( $this, $render_cb ),
			'wuat-settings',
			$section,
			array(
				'label_for'   => $key,
				'description' => $description,
			)
		);
	}
}
