<?php
/**
 * Plugin Name:  WP Content Lock Pro
 * Plugin URI:   https://example.com/wp-content-lock-pro
 * Description:  Lock post/page content behind multiple gate types: password or email capture.
 * Version:      1.0.0
 * Author:       Your Name
 * License:      GPL-2.0-or-later
 * Text Domain:  wp-content-lock-pro
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'WPCLP_VERSION',    '1.0.0' );
define( 'WPCLP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCLP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCLP_TABLE',      $GLOBALS['wpdb']->prefix . 'wpclp_emails' );

// ── Meta key constants (shared across modules) ────────────────────────────────
define( 'WPCLP_META_ENABLED',        '_wpclp_enabled' );
define( 'WPCLP_META_GATE_TYPE',      '_wpclp_gate_type' );    // 'password'|'email'
define( 'WPCLP_META_PASSWORD',       '_wpclp_password' );     // wp_hash()-ed
define( 'WPCLP_META_MESSAGE',        '_wpclp_message' );      // custom headline
define( 'WPCLP_META_PARTIAL_REVEAL', '_wpclp_partial_reveal' ); // words to show

// ── Autoload modules ───────────────────────────────────────────────────────────
require_once WPCLP_PLUGIN_DIR . 'includes/class-wpclp-db.php';
require_once WPCLP_PLUGIN_DIR . 'includes/interface-wpclp-gate.php';
require_once WPCLP_PLUGIN_DIR . 'includes/class-wpclp-gate-password.php';
require_once WPCLP_PLUGIN_DIR . 'includes/class-wpclp-gate-email.php';
require_once WPCLP_PLUGIN_DIR . 'includes/class-wpclp-core.php';

if ( is_admin() ) {
    require_once WPCLP_PLUGIN_DIR . 'admin/class-wpclp-meta-box.php';
    require_once WPCLP_PLUGIN_DIR . 'admin/class-wpclp-dashboard.php';
}

// ── Activation / Deactivation ─────────────────────────────────────────────────
register_activation_hook( __FILE__,   'wpclp_activate' );
register_deactivation_hook( __FILE__, 'wpclp_deactivate' );

function wpclp_activate() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'wp-content-lock-pro' ) );
    }
    WPCLP_DB::create_table();
}

function wpclp_deactivate() {
    wp_clear_scheduled_hook( 'wpclp_cleanup_emails' );
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wpclp_boot' );

function wpclp_boot() {
    ( new WPCLP_Core() )->init();

    if ( is_admin() ) {
        ( new WPCLP_Meta_Box() )->init();
        ( new WPCLP_Dashboard() )->init();
    }
}
