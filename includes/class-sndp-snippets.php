<?php
/**
 * Snippets manager class.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Snippets class.
 *
 * Manages enabled/disabled snippets.
 *
 * @since 1.0.0
 */
class SNDP_Snippets {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Snippets
	 */
	private static $instance = null;

	/**
	 * Cached settings to avoid repeated get_option() calls.
	 *
	 * @var array|null
	 */
	private $settings_cache = null;

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 * @return SNDP_Snippets
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
	private function __construct() {
		// Nothing to initialize.
	}

	/**
	 * Get plugin settings with per-request caching.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings() {
		if ( null === $this->settings_cache ) {
			$this->settings_cache = get_option( 'sndp_settings', array() );
		}
		return $this->settings_cache;
	}

	/**
	 * Invalidate the settings cache after writes.
	 *
	 * @since 1.0.0
	 */
	public function invalidate_settings_cache() {
		$this->settings_cache = null;
	}

	/**
	 * Get all enabled snippets.
	 *
	 * @since 1.0.0
	 * @return array Array of enabled snippet IDs.
	 */
	public function get_enabled_snippets() {
		$settings = $this->get_settings();
		return isset( $settings['enabled_snippets'] ) ? (array) $settings['enabled_snippets'] : array();
	}

	/**
	 * Check if a snippet is enabled.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool
	 */
	public function is_enabled( $snippet_id ) {
		$enabled = $this->get_enabled_snippets();
		return in_array( $snippet_id, $enabled, true );
	}

	/**
	 * Enable a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool True on success.
	 */
	public function enable_snippet( $snippet_id ) {
		$settings = $this->get_settings();

		if ( ! isset( $settings['enabled_snippets'] ) ) {
			$settings['enabled_snippets'] = array();
		}

		if ( ! in_array( $snippet_id, $settings['enabled_snippets'], true ) ) {
			$settings['enabled_snippets'][] = $snippet_id;
			update_option( 'sndp_settings', $settings );
			$this->invalidate_settings_cache();

			$this->clear_snippet_error( $snippet_id );
		}

		return true;
	}

	/**
	 * Disable a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool True on success.
	 */
	public function disable_snippet( $snippet_id ) {
		$settings = $this->get_settings();

		if ( ! isset( $settings['enabled_snippets'] ) ) {
			return true;
		}

		$key = array_search( $snippet_id, $settings['enabled_snippets'], true );
		if ( false !== $key ) {
			unset( $settings['enabled_snippets'][ $key ] );
			$settings['enabled_snippets'] = array_values( $settings['enabled_snippets'] );
			update_option( 'sndp_settings', $settings );
			$this->invalidate_settings_cache();
		}

		return true;
	}

	/**
	 * Toggle snippet status.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool New status (true = enabled, false = disabled).
	 */
	public function toggle_snippet( $snippet_id ) {
		if ( $this->is_enabled( $snippet_id ) ) {
			$this->disable_snippet( $snippet_id );
			return false;
		} else {
			$this->enable_snippet( $snippet_id );
			return true;
		}
	}

	/**
	 * Get snippets with errors.
	 *
	 * @since 1.0.0
	 * @return array Array of snippet IDs with their error messages.
	 */
	public function get_error_snippets() {
		return get_option( 'sndp_snippet_errors', array() );
	}

	/**
	 * Check if a snippet has an error.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool
	 */
	public function has_error( $snippet_id ) {
		$errors = $this->get_error_snippets();
		return isset( $errors[ $snippet_id ] );
	}

	/**
	 * Get error message for a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return string|null Error message or null if no error.
	 */
	public function get_snippet_error( $snippet_id ) {
		$errors = $this->get_error_snippets();
		return isset( $errors[ $snippet_id ] ) ? $errors[ $snippet_id ] : null;
	}

	/**
	 * Record a snippet error.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @param array  $error      Error details.
	 */
	public function record_snippet_error( $snippet_id, $error ) {
		$errors = get_option( 'sndp_snippet_errors', array() );

		$errors[ $snippet_id ] = array(
			'message' => isset( $error['message'] ) ? sanitize_text_field( $error['message'] ) : __( 'Unknown error', 'snipdrop' ),
			'time'    => time(),
			'line'    => isset( $error['line'] ) ? absint( $error['line'] ) : 0,
		);

		update_option( 'sndp_snippet_errors', $errors, false );

		// Auto-disable the snippet.
		$this->disable_snippet( $snippet_id );
	}

	/**
	 * Clear snippet error.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 */
	public function clear_snippet_error( $snippet_id ) {
		$errors = get_option( 'sndp_snippet_errors', array() );

		if ( isset( $errors[ $snippet_id ] ) ) {
			unset( $errors[ $snippet_id ] );
			update_option( 'sndp_snippet_errors', $errors, false );
		}
	}

	/**
	 * Clear all snippet errors.
	 *
	 * @since 1.0.0
	 */
	public function clear_all_errors() {
		delete_option( 'sndp_snippet_errors' );
	}

	/**
	 * Get enabled snippets count.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_enabled_count() {
		return count( $this->get_enabled_snippets() );
	}

	/**
	 * Get error snippets count.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_error_count() {
		return count( $this->get_error_snippets() );
	}

	/**
	 * Check if safe mode is enabled.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_safe_mode() {
		if ( defined( 'SNDP_SAFE_MODE' ) && SNDP_SAFE_MODE ) {
			return true;
		}

		$settings = $this->get_settings();

		// Check URL parameter with secret key.
		if ( isset( $_GET['sndp_safe_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$secret = isset( $settings['secret_key'] ) ? $settings['secret_key'] : '';

			if ( ! empty( $secret ) && hash_equals( $secret, sanitize_text_field( wp_unslash( $_GET['sndp_safe_mode'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}
		}

		return isset( $settings['safe_mode'] ) && $settings['safe_mode'];
	}

	/**
	 * Get safe mode URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_safe_mode_url() {
		$settings = $this->get_settings();
		$secret   = isset( $settings['secret_key'] ) ? $settings['secret_key'] : '';

		return add_query_arg( 'sndp_safe_mode', $secret, home_url() );
	}

	/**
	 * Get snippet configuration values.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return array Configuration values.
	 */
	public function get_snippet_config( $snippet_id ) {
		$configs = get_option( 'sndp_snippet_configs', array() );
		return isset( $configs[ $snippet_id ] ) ? $configs[ $snippet_id ] : array();
	}

	/**
	 * Save snippet configuration values.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @param array  $config     Configuration values.
	 * @return bool
	 */
	public function save_snippet_config( $snippet_id, $config ) {
		$configs                = get_option( 'sndp_snippet_configs', array() );
		$configs[ $snippet_id ] = $this->sanitize_config( $config );
		return update_option( 'sndp_snippet_configs', $configs, false );
	}

	/**
	 * Delete snippet configuration.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool
	 */
	public function delete_snippet_config( $snippet_id ) {
		$configs = get_option( 'sndp_snippet_configs', array() );

		if ( isset( $configs[ $snippet_id ] ) ) {
			unset( $configs[ $snippet_id ] );
			return update_option( 'sndp_snippet_configs', $configs, false );
		}

		return true;
	}

	/**
	 * Check if snippet has saved configuration.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool
	 */
	public function has_config( $snippet_id ) {
		$configs = get_option( 'sndp_snippet_configs', array() );
		return isset( $configs[ $snippet_id ] ) && ! empty( $configs[ $snippet_id ] );
	}

	/**
	 * Get configuration value with fallback to default.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id    Snippet ID.
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	public function get_config_value( $snippet_id, $key, $default_value = '' ) {
		$config = $this->get_snippet_config( $snippet_id );
		return isset( $config[ $key ] ) ? $config[ $key ] : $default_value;
	}

	/**
	 * Sanitize configuration values.
	 *
	 * @since 1.0.0
	 * @param array $config Configuration values.
	 * @return array
	 */
	private function sanitize_config( $config ) {
		if ( ! is_array( $config ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $config as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Get default configuration from snippet settings definition.
	 *
	 * @since 1.0.0
	 * @param array $settings_definition Snippet settings array.
	 * @return array Default values keyed by setting ID.
	 */
	public function get_default_config( $settings_definition ) {
		$defaults = array();

		if ( ! is_array( $settings_definition ) ) {
			return $defaults;
		}

		foreach ( $settings_definition as $setting ) {
			if ( isset( $setting['id'] ) && isset( $setting['default'] ) ) {
				$defaults[ $setting['id'] ] = $setting['default'];
			}
		}

		return $defaults;
	}
}
