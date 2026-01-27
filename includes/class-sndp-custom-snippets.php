<?php
/**
 * Custom snippets manager class.
 *
 * @package SnipDrop
 * @since   1.1.0
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
 * @since 1.1.0
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
	 * Get instance.
	 *
	 * @since 1.1.0
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
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor.
	}

	/**
	 * Get all custom snippets.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_all() {
		return get_option( $this->option_name, array() );
	}

	/**
	 * Get a single custom snippet.
	 *
	 * @since 1.1.0
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
	 * @since 1.1.0
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

		$snippets[ $snippet_id ] = $snippet;
		update_option( $this->option_name, $snippets, false );

		return $snippet_id;
	}

	/**
	 * Delete a custom snippet.
	 *
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.1.0
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
	 * @since 1.2.0
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
	 * @since 1.2.0
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
	 * @since 1.2.0
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
	 * Format warnings for display.
	 *
	 * @since 1.2.0
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
