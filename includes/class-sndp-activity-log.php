<?php
/**
 * Activity Log class.
 *
 * Lightweight event log tracking snippet lifecycle events.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Activity_Log class.
 *
 * @since 1.0.0
 */
class SNDP_Activity_Log {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Activity_Log
	 */
	private static $instance = null;

	/**
	 * Option name for log storage.
	 */
	const OPTION_KEY = 'sndp_activity_log';

	/**
	 * Maximum number of entries to keep.
	 */
	const MAX_ENTRIES = 200;

	/**
	 * Valid event types.
	 *
	 * @var string[]
	 */
	private static $valid_types = array(
		'enabled',
		'disabled',
		'created',
		'updated',
		'deleted',
		'error',
		'imported',
		'settings_changed',
		'testing_publish',
		'testing_discard',
		'bulk_action',
	);

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 * @return SNDP_Activity_Log
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Log an activity event.
	 *
	 * @since 1.0.0
	 * @param string $type          Event type (enabled, disabled, created, etc.).
	 * @param array  $args {
	 *     Optional event data.
	 *     @type string $snippet_id    Snippet identifier.
	 *     @type string $snippet_title Snippet title.
	 *     @type string $snippet_type  Code type (php, js, css, html).
	 *     @type string $context       Where the event originated (library, custom, global, settings).
	 *     @type string $details       Additional details string.
	 * }
	 */
	public function log( $type, $args = array() ) {
		if ( ! in_array( $type, self::$valid_types, true ) ) {
			return;
		}

		$entry = array(
			'type'          => $type,
			'snippet_id'    => isset( $args['snippet_id'] ) ? sanitize_text_field( $args['snippet_id'] ) : '',
			'snippet_title' => isset( $args['snippet_title'] ) ? sanitize_text_field( $args['snippet_title'] ) : '',
			'snippet_type'  => isset( $args['snippet_type'] ) ? sanitize_text_field( $args['snippet_type'] ) : '',
			'context'       => isset( $args['context'] ) ? sanitize_text_field( $args['context'] ) : '',
			'details'       => isset( $args['details'] ) ? sanitize_text_field( $args['details'] ) : '',
			'user_id'       => get_current_user_id(),
			'timestamp'     => current_time( 'mysql', true ),
		);

		$log   = $this->get_log();
		$log[] = $entry;

		// Prune oldest entries if over limit.
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, -self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * Get the full activity log (newest first).
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_log() {
		return get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Get log entries in reverse chronological order with optional filtering.
	 *
	 * @since 1.0.0
	 * @param array $args {
	 *     Optional filter args.
	 *     @type string $type    Filter by event type.
	 *     @type int    $limit   Max entries to return.
	 *     @type int    $offset  Number of entries to skip.
	 * }
	 * @return array {
	 *     @type array $entries  Log entries.
	 *     @type int   $total    Total matching entries.
	 * }
	 */
	public function get_entries( $args = array() ) {
		$log = $this->get_log();

		// Reverse for newest-first.
		$log = array_reverse( $log );

		// Filter by type.
		if ( ! empty( $args['type'] ) ) {
			$filter_type = $args['type'];
			$log         = array_filter(
				$log,
				function ( $entry ) use ( $filter_type ) {
					return $entry['type'] === $filter_type;
				}
			);
			$log = array_values( $log );
		}

		$total  = count( $log );
		$limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$entries = array_slice( $log, $offset, $limit );

		return array(
			'entries' => $entries,
			'total'   => $total,
		);
	}

	/**
	 * Clear the entire log.
	 *
	 * @since 1.0.0
	 */
	public function clear() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Get the count of log entries.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_count() {
		$log = $this->get_log();
		return count( $log );
	}

	/**
	 * Get a human-readable label for an event type.
	 *
	 * @since 1.0.0
	 * @param string $type Event type.
	 * @return string
	 */
	public static function get_type_label( $type ) {
		$labels = array(
			'enabled'          => __( 'Enabled', 'snipdrop' ),
			'disabled'         => __( 'Disabled', 'snipdrop' ),
			'created'          => __( 'Created', 'snipdrop' ),
			'updated'          => __( 'Updated', 'snipdrop' ),
			'deleted'          => __( 'Deleted', 'snipdrop' ),
			'error'            => __( 'Error', 'snipdrop' ),
			'imported'         => __( 'Imported', 'snipdrop' ),
			'settings_changed' => __( 'Settings', 'snipdrop' ),
			'testing_publish'  => __( 'Published', 'snipdrop' ),
			'testing_discard'  => __( 'Discarded', 'snipdrop' ),
			'bulk_action'      => __( 'Bulk Action', 'snipdrop' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type );
	}

	/**
	 * Get CSS class for a badge based on event type.
	 *
	 * @since 1.0.0
	 * @param string $type Event type.
	 * @return string
	 */
	public static function get_type_badge_class( $type ) {
		$map = array(
			'enabled'          => 'success',
			'disabled'         => 'warning',
			'created'          => 'info',
			'updated'          => 'info',
			'deleted'          => 'danger',
			'error'            => 'danger',
			'imported'         => 'info',
			'settings_changed' => 'neutral',
			'testing_publish'  => 'success',
			'testing_discard'  => 'warning',
			'bulk_action'      => 'neutral',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : 'neutral';
	}

	/**
	 * Get all valid event types for filter dropdown.
	 *
	 * @since 1.0.0
	 * @return string[]
	 */
	public static function get_valid_types() {
		return self::$valid_types;
	}
}
