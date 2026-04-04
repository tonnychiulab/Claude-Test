<?php
/**
 * Uninstall handler for WP Unauthorized Access Tracker.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin.
 *
 * @package WUAT
 */

// Only run when WordPress triggers an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Drop custom table.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wuat_table = $wpdb->prefix . 'wuat_access_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS `{$wuat_table}`" );

// 2. Delete plugin options.
delete_option( 'wuat_settings' );
delete_option( 'wuat_db_version' );

// 3. Remove all transients created by this plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM `{$wpdb->options}`
	 WHERE option_name LIKE '\_transient\_wuat\_%'
	    OR option_name LIKE '\_transient\_timeout\_wuat\_%'"
);

// 4. Clear scheduled hook (safety net — deactivation should have done this).
wp_clear_scheduled_hook( 'wuat_cleanup_old_logs' );
