<?php
/**
 * SnipDrop Uninstall
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 *
 * This function removes all options, transients, and cached data
 * created by the SnipDrop plugin.
 *
 * @since 1.0.0
 */
function sndp_uninstall_cleanup() {
	global $wpdb;

	// Check if user opted to delete data on uninstall.
	$settings = get_option( 'sndp_settings', array() );
	// Always remove custom capability from all roles.
	$roles = wp_roles();
	foreach ( $roles->role_objects as $role ) {
		$role->remove_cap( 'sndp_manage_snippets' );
	}

	if ( empty( $settings['delete_on_uninstall'] ) ) {
		// User did not opt to delete data, only remove transients and capability.
		delete_transient( 'sndp_manifest' );
		delete_transient( 'sndp_activated' );
		delete_transient( 'sndp_error_notice' );
		delete_transient( 'sndp_new_snippet_count' );
		delete_transient( 'sndp_last_error_email' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup, bulk delete of transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sndp_snippet_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sndp_snippet_' ) . '%'
			)
		);

		return;
	}

	// Delete all plugin options.
	$options = array(
		'sndp_settings',
		'sndp_snippet_errors',
		'sndp_manifest_cache',
		'sndp_local_snippets',
		'sndp_custom_snippets',
		'sndp_snippet_configs',
		'sndp_error_history',
		'sndp_snippet_revisions',
		'sndp_global_scripts',
		'sndp_testing_mode',
		'sndp_activity_log',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients.
	delete_transient( 'sndp_manifest' );
	delete_transient( 'sndp_activated' );
	delete_transient( 'sndp_error_notice' );
	delete_transient( 'sndp_last_error_email' );
	delete_transient( 'sndp_new_snippet_count' );

	// Delete all snippet transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup, bulk delete of transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_sndp_snippet_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_sndp_snippet_' ) . '%'
		)
	);

	// Remove error log directory and files.
	$log_dir = WP_CONTENT_DIR . '/snipdrop-logs';
	if ( is_dir( $log_dir ) ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $log_dir, true );
		}
	}

	// Clear any scheduled events.
	wp_clear_scheduled_hook( 'sndp_scheduled_sync' );
	wp_clear_scheduled_hook( 'sndp_cleanup_logs' );
}

// Run cleanup.
sndp_uninstall_cleanup();
