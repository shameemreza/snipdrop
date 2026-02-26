<?php
/**
 * Custom snippets manager class.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Custom_Snippets class.
 *
 * Manages user-created custom code snippets.
 *
 * @since 1.0.0
 */
class SNDP_Custom_Snippets {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Custom_Snippets
	 */
	private static $instance = null;

	/**
	 * Option name for storing custom snippets.
	 *
	 * @var string
	 */
	private $option_name = 'sndp_custom_snippets';

	/**
	 * Option name for storing snippet revisions.
	 *
	 * @var string
	 */
	private $revisions_option = 'sndp_snippet_revisions';

	/**
	 * Maximum revisions to keep per snippet.
	 *
	 * @var int
	 */
	private $max_revisions = 5;

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 * @return SNDP_Custom_Snippets
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
		// Private constructor.
	}

	/**
	 * Get all custom snippets.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_all() {
		return get_option( $this->option_name, array() );
	}

	/**
	 * Get a single custom snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return array|null
	 */
	public function get( $snippet_id ) {
		$snippets = $this->get_all();
		return isset( $snippets[ $snippet_id ] ) ? $snippets[ $snippet_id ] : null;
	}

	/**
	 * Save a custom snippet.
	 *
	 * @since 1.0.0
	 * @param array $snippet_data Snippet data.
	 * @return string Snippet ID.
	 */
	public function save( $snippet_data ) {
		$snippets = $this->get_all();

		// Generate ID if not provided.
		if ( empty( $snippet_data['id'] ) ) {
			$snippet_data['id'] = 'custom_' . wp_generate_password( 8, false, false );
		}

		$snippet_id = sanitize_key( $snippet_data['id'] );

		// Valid location values.
		$valid_locations = array(
			'everywhere',
			'frontend',
			'admin',
			'site_header',
			'site_footer',
			'before_content',
			'after_content',
			'shortcode',
		);

		// Valid user conditions.
		$valid_user_conds = array( 'all', 'logged_in', 'logged_out' );

		// Sanitize post types array.
		$post_types = array();
		if ( isset( $snippet_data['post_types'] ) && is_array( $snippet_data['post_types'] ) ) {
			$post_types = array_map( 'sanitize_key', $snippet_data['post_types'] );
		}

		// Get existing snippet to preserve created_by if editing.
		$existing = isset( $snippets[ $snippet_id ] ) ? $snippets[ $snippet_id ] : null;

		// Sanitize and validate data.
		$snippet = array(
			'id'          => $snippet_id,
			'title'       => sanitize_text_field( $snippet_data['title'] ?? __( 'Untitled Snippet', 'snipdrop' ) ),
			'description' => sanitize_textarea_field( $snippet_data['description'] ?? '' ),
			'code'        => $snippet_data['code'] ?? '',
			'code_type'   => in_array( $snippet_data['code_type'] ?? 'php', array( 'php', 'js', 'css', 'html' ), true )
				? $snippet_data['code_type']
				: 'php',
			'status'      => in_array( $snippet_data['status'] ?? 'inactive', array( 'active', 'inactive' ), true )
				? $snippet_data['status']
				: 'inactive',
			'hook'        => sanitize_text_field( $snippet_data['hook'] ?? 'init' ),
			'priority'    => absint( $snippet_data['priority'] ?? 10 ),
			'location'    => in_array( $snippet_data['location'] ?? 'everywhere', $valid_locations, true )
				? $snippet_data['location']
				: 'everywhere',
			'user_cond'   => in_array( $snippet_data['user_cond'] ?? 'all', $valid_user_conds, true )
				? $snippet_data['user_cond']
				: 'all',
			'post_types'  => $post_types,
			'page_ids'    => sanitize_text_field( $snippet_data['page_ids'] ?? '' ),
			'source'      => sanitize_text_field( $snippet_data['source'] ?? 'custom' ),
			'created_at'  => $existing ? $existing['created_at'] : current_time( 'mysql' ),
			'created_by'  => $existing && isset( $existing['created_by'] ) ? $existing['created_by'] : get_current_user_id(),
			'updated_at'  => current_time( 'mysql' ),
			'updated_by'  => get_current_user_id(),
		);

		// Store revision of previous code if editing an existing snippet.
		if ( $existing && isset( $existing['code'] ) && $existing['code'] !== $snippet['code'] ) {
			$this->store_revision( $snippet_id, $existing['code'], $existing['code_type'] ?? 'php' );
		}

		$snippets[ $snippet_id ] = $snippet;
		update_option( $this->option_name, $snippets, false );

		return $snippet_id;
	}

	/**
	 * Delete a custom snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool
	 */
	public function delete( $snippet_id ) {
		$snippets = $this->get_all();

		if ( ! isset( $snippets[ $snippet_id ] ) ) {
			return false;
		}

		unset( $snippets[ $snippet_id ] );
		update_option( $this->option_name, $snippets, false );

		return true;
	}

	/**
	 * Toggle snippet status.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool|string New status or false on failure.
	 */
	public function toggle( $snippet_id ) {
		$snippets = $this->get_all();

		if ( ! isset( $snippets[ $snippet_id ] ) ) {
			return false;
		}

		$new_status                            = 'active' === $snippets[ $snippet_id ]['status'] ? 'inactive' : 'active';
		$snippets[ $snippet_id ]['status']     = $new_status;
		$snippets[ $snippet_id ]['updated_at'] = current_time( 'mysql' );

		update_option( $this->option_name, $snippets, false );

		return $new_status;
	}

	/**
	 * Activate a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool
	 */
	public function activate( $snippet_id ) {
		$snippets = $this->get_all();

		if ( ! isset( $snippets[ $snippet_id ] ) ) {
			return false;
		}

		$snippets[ $snippet_id ]['status']     = 'active';
		$snippets[ $snippet_id ]['updated_at'] = current_time( 'mysql' );

		update_option( $this->option_name, $snippets, false );

		return true;
	}

	/**
	 * Deactivate a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool
	 */
	public function deactivate( $snippet_id ) {
		$snippets = $this->get_all();

		if ( ! isset( $snippets[ $snippet_id ] ) ) {
			return false;
		}

		$snippets[ $snippet_id ]['status']     = 'inactive';
		$snippets[ $snippet_id ]['updated_at'] = current_time( 'mysql' );

		update_option( $this->option_name, $snippets, false );

		return true;
	}

	/**
	 * Get all active custom snippets.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_active() {
		$snippets = $this->get_all();
		return array_filter(
			$snippets,
			function ( $snippet ) {
				return 'active' === $snippet['status'];
			}
		);
	}

	/**
	 * Duplicate a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return string|false New snippet ID or false on failure.
	 */
	public function duplicate( $snippet_id ) {
		$snippet = $this->get( $snippet_id );

		if ( ! $snippet ) {
			return false;
		}

		// Reset ID and status.
		$snippet['id']     = '';
		$snippet['status'] = 'inactive';
		$snippet['title']  = $snippet['title'] . ' ' . __( '(Copy)', 'snipdrop' );

		return $this->save( $snippet );
	}

	/**
	 * Create custom snippet from library snippet.
	 *
	 * @since 1.0.0
	 * @param array $library_snippet Library snippet data.
	 * @return string New custom snippet ID.
	 */
	public function create_from_library( $library_snippet ) {
		$snippet_data = array(
			'title'       => $library_snippet['title'] . ' ' . __( '(Custom)', 'snipdrop' ),
			'description' => $library_snippet['description'] ?? '',
			'code'        => $library_snippet['code'] ?? '',
			'code_type'   => $library_snippet['code_type'] ?? 'php',
			'status'      => 'inactive',
			'hook'        => $library_snippet['hook']['name'] ?? 'init',
			'priority'    => $library_snippet['hook']['priority'] ?? 10,
			'source'      => 'library:' . $library_snippet['id'],
		);

		return $this->save( $snippet_data );
	}

	/**
	 * Record error for a custom snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @param array  $error      Error details.
	 */
	public function record_error( $snippet_id, $error ) {
		$snippets = $this->get_all();

		if ( ! isset( $snippets[ $snippet_id ] ) ) {
			return;
		}

		// Deactivate the snippet.
		$snippets[ $snippet_id ]['status']     = 'inactive';
		$snippets[ $snippet_id ]['last_error'] = array(
			'message' => $error['message'] ?? __( 'Unknown error', 'snipdrop' ),
			'file'    => $error['file'] ?? '',
			'line'    => $error['line'] ?? 0,
			'time'    => current_time( 'mysql' ),
		);
		$snippets[ $snippet_id ]['updated_at'] = current_time( 'mysql' );

		update_option( $this->option_name, $snippets, false );
	}

	/**
	 * Clear error for a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 */
	public function clear_error( $snippet_id ) {
		$snippets = $this->get_all();

		if ( ! isset( $snippets[ $snippet_id ] ) || ! isset( $snippets[ $snippet_id ]['last_error'] ) ) {
			return;
		}

		unset( $snippets[ $snippet_id ]['last_error'] );
		update_option( $this->option_name, $snippets, false );
	}

	/**
	 * Validate PHP code syntax.
	 *
	 * @since 1.0.0
	 * @param string $code PHP code to validate.
	 * @return true|string True if valid, error message if not.
	 */
	public function validate_php_syntax( $code ) {
		// Remove opening PHP tag if present.
		$code = preg_replace( '/^\s*<\?php\s*/i', '', $code );

		// Try to check syntax using token_get_all.
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional error suppression for syntax checking.
			$tokens = @token_get_all( '<?php ' . $code );
			if ( empty( $tokens ) ) {
				return __( 'Unable to parse PHP code.', 'snipdrop' );
			}
		} catch ( \ParseError $e ) {
			return $e->getMessage();
		}

		return true;
	}

	/**
	 * Check code for suspicious/dangerous patterns.
	 *
	 * @since 1.0.0
	 * @param string $code    Code to check.
	 * @param string $type    Code type (php, js, css, html).
	 * @return array Array of warnings (empty if safe).
	 */
	public function check_suspicious_code( $code, $type = 'php' ) {
		$warnings = array();

		if ( 'php' === $type ) {
			$warnings = $this->check_php_suspicious( $code );
		} elseif ( 'js' === $type ) {
			$warnings = $this->check_js_suspicious( $code );
		}

		return $warnings;
	}

	/**
	 * Check PHP code for suspicious patterns.
	 *
	 * @since 1.0.0
	 * @param string $code PHP code.
	 * @return array Warnings.
	 */
	private function check_php_suspicious( $code ) {
		$warnings = array();

		// Dangerous functions that execute code or system commands.
		$dangerous_functions = array(
			'eval'       => __( 'eval() can execute arbitrary code and is a security risk.', 'snipdrop' ),
			'exec'       => __( 'exec() can execute system commands - use with extreme caution.', 'snipdrop' ),
			'shell_exec' => __( 'shell_exec() can execute system commands - use with extreme caution.', 'snipdrop' ),
			'system'     => __( 'system() can execute system commands - use with extreme caution.', 'snipdrop' ),
			'passthru'   => __( 'passthru() can execute system commands - use with extreme caution.', 'snipdrop' ),
			'popen'      => __( 'popen() can open process pipes - potential security risk.', 'snipdrop' ),
			'proc_open'  => __( 'proc_open() can execute system commands - use with extreme caution.', 'snipdrop' ),
			'pcntl_exec' => __( 'pcntl_exec() can execute programs - use with extreme caution.', 'snipdrop' ),
		);

		// Potentially dangerous functions.
		$risky_functions = array(
			'base64_decode'     => __( 'base64_decode() is often used to obfuscate malicious code.', 'snipdrop' ),
			'file_put_contents' => __( 'file_put_contents() can write arbitrary files - verify the destination.', 'snipdrop' ),
			'file_get_contents' => __( 'file_get_contents() with URLs can fetch external content - verify the source.', 'snipdrop' ),
			'curl_exec'         => __( 'curl_exec() makes external requests - verify the destination.', 'snipdrop' ),
			'fwrite'            => __( 'fwrite() writes to files - verify the destination.', 'snipdrop' ),
			'fputs'             => __( 'fputs() writes to files - verify the destination.', 'snipdrop' ),
			'unserialize'       => __( 'unserialize() can be dangerous with untrusted data.', 'snipdrop' ),
			'create_function'   => __( 'create_function() is deprecated and can execute arbitrary code.', 'snipdrop' ),
			'assert'            => __( 'assert() can execute code in older PHP versions.', 'snipdrop' ),
			'preg_replace'      => __( 'preg_replace() with /e modifier can execute code (deprecated).', 'snipdrop' ),
		);

		// Check for dangerous functions.
		foreach ( $dangerous_functions as $func => $message ) {
			if ( preg_match( '/\b' . preg_quote( $func, '/' ) . '\s*\(/i', $code ) ) {
				$warnings[] = array(
					'type'     => 'error',
					'function' => $func,
					'message'  => $message,
				);
			}
		}

		// Check for risky functions.
		foreach ( $risky_functions as $func => $message ) {
			if ( preg_match( '/\b' . preg_quote( $func, '/' ) . '\s*\(/i', $code ) ) {
				$warnings[] = array(
					'type'     => 'warning',
					'function' => $func,
					'message'  => $message,
				);
			}
		}

		// Check for obfuscation patterns.
		if ( preg_match( '/\$[a-zA-Z_]\w*\s*\(\s*\$/', $code ) ) {
			$warnings[] = array(
				'type'    => 'warning',
				'message' => __( 'Variable function calls detected - often used for code obfuscation.', 'snipdrop' ),
			);
		}

		// Check for encoded strings that might be malicious.
		if ( preg_match( '/\\\\x[0-9a-fA-F]{2}/', $code ) || preg_match( '/chr\s*\(\s*\d+\s*\)/', $code ) ) {
			$warnings[] = array(
				'type'    => 'warning',
				'message' => __( 'Encoded characters detected - verify the code is not obfuscated.', 'snipdrop' ),
			);
		}

		return $warnings;
	}

	/**
	 * Check JavaScript code for suspicious patterns.
	 *
	 * @since 1.0.0
	 * @param string $code JavaScript code.
	 * @return array Warnings.
	 */
	private function check_js_suspicious( $code ) {
		$warnings = array();

		// Risky patterns in JavaScript.
		$risky_patterns = array(
			'eval\s*\('                => __( 'eval() can execute arbitrary code - consider alternatives.', 'snipdrop' ),
			'document\.write'          => __( 'document.write() can overwrite page content - use DOM methods instead.', 'snipdrop' ),
			'innerHTML\s*='            => __( 'innerHTML can introduce XSS vulnerabilities - sanitize input first.', 'snipdrop' ),
			'\.cookie\s*='             => __( 'Direct cookie manipulation detected - verify this is intentional.', 'snipdrop' ),
			'new\s+Function\s*\('      => __( 'new Function() can execute arbitrary code like eval().', 'snipdrop' ),
			'setTimeout\s*\(\s*["\']'  => __( 'setTimeout with string argument can execute code - use function instead.', 'snipdrop' ),
			'setInterval\s*\(\s*["\']' => __( 'setInterval with string argument can execute code - use function instead.', 'snipdrop' ),
		);

		foreach ( $risky_patterns as $pattern => $message ) {
			if ( preg_match( '/' . $pattern . '/i', $code ) ) {
				$warnings[] = array(
					'type'    => 'warning',
					'message' => $message,
				);
			}
		}

		return $warnings;
	}

	/**
	 * Store a revision of snippet code.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @param string $code       Previous code.
	 * @param string $code_type  Code type.
	 */
	private function store_revision( $snippet_id, $code, $code_type ) {
		$all_revisions = get_option( $this->revisions_option, array() );
		if ( ! isset( $all_revisions[ $snippet_id ] ) ) {
			$all_revisions[ $snippet_id ] = array();
		}

		array_unshift(
			$all_revisions[ $snippet_id ],
			array(
				'code'      => $code,
				'code_type' => $code_type,
				'date'      => current_time( 'mysql' ),
				'user'      => get_current_user_id(),
			)
		);

		$all_revisions[ $snippet_id ] = array_slice( $all_revisions[ $snippet_id ], 0, $this->max_revisions );

		update_option( $this->revisions_option, $all_revisions, false );
	}

	/**
	 * Get revisions for a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return array Array of revisions.
	 */
	public function get_revisions( $snippet_id ) {
		$all_revisions = get_option( $this->revisions_option, array() );
		return isset( $all_revisions[ $snippet_id ] ) ? $all_revisions[ $snippet_id ] : array();
	}

	/**
	 * Restore a snippet to a specific revision.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id    Snippet ID.
	 * @param int    $revision_index Zero-based revision index.
	 * @return bool True on success, false on failure.
	 */
	public function restore_revision( $snippet_id, $revision_index ) {
		$revisions = $this->get_revisions( $snippet_id );

		if ( ! isset( $revisions[ $revision_index ] ) ) {
			return false;
		}

		$snippet = $this->get( $snippet_id );
		if ( ! $snippet ) {
			return false;
		}

		$snippet['code']      = $revisions[ $revision_index ]['code'];
		$snippet['code_type'] = $revisions[ $revision_index ]['code_type'];

		return (bool) $this->save( $snippet );
	}

	/**
	 * Export custom snippets as a structured array.
	 *
	 * @since 1.0.0
	 * @param array $snippet_ids Optional. Specific snippet IDs to export. Empty exports all.
	 * @return array Export data with metadata and snippets.
	 */
	public function export( $snippet_ids = array() ) {
		$snippets = $this->get_all();

		if ( ! empty( $snippet_ids ) ) {
			$snippets = array_intersect_key( $snippets, array_flip( $snippet_ids ) );
		}

		$export_snippets = array();
		foreach ( $snippets as $snippet_id => $snippet ) {
			$export_snippets[] = array(
				'title'       => $snippet['title'],
				'description' => $snippet['description'] ?? '',
				'code'        => $snippet['code'] ?? '',
				'code_type'   => $snippet['code_type'] ?? 'php',
				'status'      => $snippet['status'] ?? 'inactive',
				'location'    => $snippet['location'] ?? 'everywhere',
				'hook'        => $snippet['hook'] ?? 'init',
				'priority'    => $snippet['priority'] ?? 10,
				'user_cond'   => $snippet['user_cond'] ?? 'all',
				'post_types'  => $snippet['post_types'] ?? array(),
				'page_ids'    => $snippet['page_ids'] ?? '',
			);
		}

		return array(
			'plugin'   => 'snipdrop',
			'version'  => SNDP_VERSION,
			'exported' => gmdate( 'c' ),
			'count'    => count( $export_snippets ),
			'snippets' => $export_snippets,
		);
	}

	/**
	 * Import snippets from structured data.
	 *
	 * Supports SnipDrop, WPCode, and Code Snippets export formats.
	 *
	 * @since 1.0.0
	 * @param array $data Parsed JSON data.
	 * @return array|WP_Error Import result with count, or error.
	 */
	public function import( $data ) {
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid import data.', 'snipdrop' ) );
		}

		$snippets_to_import = $this->normalize_import_data( $data );

		if ( is_wp_error( $snippets_to_import ) ) {
			return $snippets_to_import;
		}

		if ( empty( $snippets_to_import ) ) {
			return new \WP_Error( 'no_snippets', __( 'No snippets found in the import file.', 'snipdrop' ) );
		}

		$imported = 0;
		$skipped  = 0;

		foreach ( $snippets_to_import as $snippet_data ) {
			$snippet_data['id']     = '';
			$snippet_data['status'] = 'inactive';

			$snippet_id = $this->save( $snippet_data );
			if ( $snippet_id ) {
				++$imported;
			} else {
				++$skipped;
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Normalize import data from various plugin formats.
	 *
	 * @since 1.0.0
	 * @param array $data Raw import data.
	 * @return array|WP_Error Normalized snippet array.
	 */
	private function normalize_import_data( $data ) {
		// SnipDrop native format.
		if ( isset( $data['plugin'] ) && 'snipdrop' === $data['plugin'] && isset( $data['snippets'] ) ) {
			return $this->normalize_snipdrop_format( $data['snippets'] );
		}

		// WPCode format: array of objects with 'code', 'code_type', 'title'.
		if ( isset( $data[0]['code'] ) && isset( $data[0]['code_type'] ) && isset( $data[0]['title'] ) ) {
			return $this->normalize_wpcode_format( $data );
		}

		// Code Snippets format: has 'snippets' key with objects containing 'name', 'code', 'scope'.
		if ( isset( $data['snippets'] ) && isset( $data['snippets'][0]['name'] ) && isset( $data['snippets'][0]['scope'] ) ) {
			return $this->normalize_code_snippets_format( $data['snippets'] );
		}

		// Single array of snippets without wrapper (generic).
		if ( isset( $data['snippets'] ) && is_array( $data['snippets'] ) ) {
			return $this->normalize_snipdrop_format( $data['snippets'] );
		}

		return new \WP_Error( 'unknown_format', __( 'Unrecognized import file format. Supported: SnipDrop, WPCode, Code Snippets.', 'snipdrop' ) );
	}

	/**
	 * Normalize SnipDrop native export format.
	 *
	 * @since 1.0.0
	 * @param array $snippets Raw snippet array.
	 * @return array Normalized snippets.
	 */
	private function normalize_snipdrop_format( $snippets ) {
		$normalized = array();

		foreach ( $snippets as $snippet ) {
			if ( empty( $snippet['code'] ) && empty( $snippet['title'] ) ) {
				continue;
			}

			$normalized[] = array(
				'title'       => $snippet['title'] ?? __( 'Imported Snippet', 'snipdrop' ),
				'description' => $snippet['description'] ?? '',
				'code'        => $snippet['code'] ?? '',
				'code_type'   => $snippet['code_type'] ?? 'php',
				'location'    => $snippet['location'] ?? 'everywhere',
				'hook'        => $snippet['hook'] ?? 'init',
				'priority'    => $snippet['priority'] ?? 10,
				'user_cond'   => $snippet['user_cond'] ?? 'all',
				'post_types'  => $snippet['post_types'] ?? array(),
				'page_ids'    => $snippet['page_ids'] ?? '',
				'source'      => 'import',
			);
		}

		return $normalized;
	}

	/**
	 * Normalize WPCode export format.
	 *
	 * @since 1.0.0
	 * @param array $snippets WPCode snippet array.
	 * @return array Normalized snippets.
	 */
	private function normalize_wpcode_format( $snippets ) {
		$normalized = array();

		$type_map = array(
			'php'  => 'php',
			'html' => 'html',
			'css'  => 'css',
			'js'   => 'js',
			'text' => 'html',
		);

		$location_map = array(
			'site_wide_header' => 'site_header',
			'site_wide_footer' => 'site_footer',
			'before_content'   => 'before_content',
			'after_content'    => 'after_content',
			'frontend_only'    => 'frontend',
			'admin_only'       => 'admin',
			'everywhere'       => 'everywhere',
		);

		foreach ( $snippets as $snippet ) {
			if ( empty( $snippet['code'] ) ) {
				continue;
			}

			$code_type = isset( $snippet['code_type'], $type_map[ $snippet['code_type'] ] )
				? $type_map[ $snippet['code_type'] ]
				: 'php';

			$location = 'everywhere';
			if ( isset( $snippet['location'] ) ) {
				$loc_key  = is_string( $snippet['location'] ) ? $snippet['location'] : '';
				$location = isset( $location_map[ $loc_key ] ) ? $location_map[ $loc_key ] : 'everywhere';
			}

			$normalized[] = array(
				'title'       => $snippet['title'] ?? __( 'Imported from WPCode', 'snipdrop' ),
				'description' => $snippet['note'] ?? '',
				'code'        => $snippet['code'],
				'code_type'   => $code_type,
				'location'    => $location,
				'hook'        => 'init',
				'priority'    => $snippet['priority'] ?? 10,
				'user_cond'   => 'all',
				'post_types'  => array(),
				'page_ids'    => '',
				'source'      => 'import:wpcode',
			);
		}

		return $normalized;
	}

	/**
	 * Normalize Code Snippets plugin export format.
	 *
	 * @since 1.0.0
	 * @param array $snippets Code Snippets array.
	 * @return array Normalized snippets.
	 */
	private function normalize_code_snippets_format( $snippets ) {
		$normalized = array();

		$scope_map = array(
			'global'         => 'everywhere',
			'front-end'      => 'frontend',
			'admin'          => 'admin',
			'single-use'     => 'everywhere',
			'head-content'   => 'site_header',
			'footer-content' => 'site_footer',
			'content'        => 'before_content',
		);

		foreach ( $snippets as $snippet ) {
			if ( empty( $snippet['code'] ) ) {
				continue;
			}

			$scope    = isset( $snippet['scope'] ) ? $snippet['scope'] : 'global';
			$location = isset( $scope_map[ $scope ] ) ? $scope_map[ $scope ] : 'everywhere';

			$code_type = 'php';
			if ( isset( $snippet['type'] ) ) {
				$code_type = in_array( $snippet['type'], array( 'php', 'html', 'css', 'js' ), true )
					? $snippet['type']
					: 'php';
			}

			$normalized[] = array(
				'title'       => $snippet['name'] ?? __( 'Imported from Code Snippets', 'snipdrop' ),
				'description' => $snippet['desc'] ?? '',
				'code'        => $snippet['code'],
				'code_type'   => $code_type,
				'location'    => $location,
				'hook'        => 'init',
				'priority'    => $snippet['priority'] ?? 10,
				'user_cond'   => 'all',
				'post_types'  => array(),
				'page_ids'    => '',
				'source'      => 'import:code-snippets',
			);
		}

		return $normalized;
	}

	/**
	 * Format warnings for display.
	 *
	 * @since 1.0.0
	 * @param array $warnings Array of warnings.
	 * @return string HTML formatted warnings.
	 */
	public function format_warnings( $warnings ) {
		if ( empty( $warnings ) ) {
			return '';
		}

		$html  = '<div class="sndp-code-warnings">';
		$html .= '<h4><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Security Warnings', 'snipdrop' ) . '</h4>';
		$html .= '<ul>';

		foreach ( $warnings as $warning ) {
			$class = 'error' === $warning['type'] ? 'sndp-warning-error' : 'sndp-warning-caution';
			$html .= '<li class="' . esc_attr( $class ) . '">';
			if ( isset( $warning['function'] ) ) {
				$html .= '<code>' . esc_html( $warning['function'] ) . '()</code>: ';
			}
			$html .= esc_html( $warning['message'] );
			$html .= '</li>';
		}

		$html .= '</ul>';
		$html .= '<p class="sndp-warning-note">' . esc_html__( 'These warnings do not prevent saving. Proceed only if you understand what this code does.', 'snipdrop' ) . '</p>';
		$html .= '</div>';

		return $html;
	}
}
