<?php
/**
 * Plugin Name: Unauthorized Access Tracker
 * Plugin URI:  https://example.com/unauthorized-access-tracker
 * Description: Detects and logs unauthorized WordPress admin access, including brute-force attempts and off-hours admin logins.
 * Version:     1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:      IT Team
 * Author URI:  https://example.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-unauthorized-access-tracker
 * Domain Path: /languages
 *
 * @package WUAT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WUAT_VERSION', '1.0.0' );
define( 'WUAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WUAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WUAT_PLUGIN_FILE', __FILE__ );
define( 'WUAT_TABLE_NAME', 'wuat_access_log' );

// ── Autoload ─────────────────────────────────────────────────────────────────
require_once WUAT_PLUGIN_DIR . 'includes/class-wuat-logger.php';
require_once WUAT_PLUGIN_DIR . 'includes/class-wuat-detector.php';
require_once WUAT_PLUGIN_DIR . 'includes/class-wuat-alerter.php';
require_once WUAT_PLUGIN_DIR . 'includes/class-wuat-offhours.php';
require_once WUAT_PLUGIN_DIR . 'includes/class-wuat-settings.php';
require_once WUAT_PLUGIN_DIR . 'includes/class-wuat-admin.php';

// ── Lifecycle hooks ───────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'wuat_activate' );
register_deactivation_hook( __FILE__, 'wuat_deactivate' );

/**
 * Plugin activation: create DB table + schedule cleanup cron.
 */
function wuat_activate() {
	WUAT_Logger::create_table();
	if ( ! wp_next_scheduled( 'wuat_cleanup_old_logs' ) ) {
		wp_schedule_event( time(), 'daily', 'wuat_cleanup_old_logs' );
	}
}

/**
 * Plugin deactivation: remove scheduled cron.
 */
function wuat_deactivate() {
	wp_clear_scheduled_hook( 'wuat_cleanup_old_logs' );
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wuat_init' );

/**
 * Main initialisation.
 */
function wuat_init() {
	// load_plugin_textdomain() is still required for plugins NOT hosted on WordPress.org
	// (e.g. private/internal plugins with custom language file paths). WordPress 4.6+
	// auto-loads only for wp.org-hosted plugins. phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	load_plugin_textdomain(
		'wp-unauthorized-access-tracker',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	$logger   = new WUAT_Logger();
	$alerter  = new WUAT_Alerter();
	$detector = new WUAT_Detector( $logger, $alerter );

	new WUAT_Off_Hours( $logger, $alerter );

	if ( is_admin() ) {
		new WUAT_Admin();
		new WUAT_Settings();
	}
}

// ── Cleanup cron ──────────────────────────────────────────────────────────────
add_action( 'wuat_cleanup_old_logs', 'wuat_run_cleanup' );

/**
 * Delete log entries older than the configured retention period.
 */
function wuat_run_cleanup() {
	global $wpdb;
	$settings = get_option( 'wuat_settings', array() );
	$days     = absint( $settings['log_retention_days'] ?? 90 );
	$table    = $wpdb->prefix . WUAT_TABLE_NAME;
	$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE event_time < %s", $cutoff ) );
}
