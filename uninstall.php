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
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		// User did not opt to delete data, only remove transients.
		delete_transient( 'sndp_manifest' );
		delete_transient( 'sndp_activated' );
		delete_transient( 'sndp_snippet_error_notice' );

		// Delete all snippet transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup, bulk delete of transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_sndp_snippet_%' 
			OR option_name LIKE '_transient_timeout_sndp_snippet_%'"
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
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients.
	delete_transient( 'sndp_manifest' );
	delete_transient( 'sndp_activated' );
	delete_transient( 'sndp_snippet_error_notice' );

	// Delete all snippet transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup, bulk delete of transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE '_transient_sndp_snippet_%' 
		OR option_name LIKE '_transient_timeout_sndp_snippet_%'"
	);

	// Clear any scheduled events.
	wp_clear_scheduled_hook( 'sndp_scheduled_sync' );
}

// Run cleanup.
sndp_uninstall_cleanup();
