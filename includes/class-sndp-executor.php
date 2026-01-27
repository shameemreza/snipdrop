<?php
/**
 * Snippet executor class.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Executor class.
 *
 * Executes enabled snippets.
 *
 * @since 1.0.0
 */
class SNDP_Executor {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Executor
	 */
	private static $instance = null;

	/**
	 * Currently executing snippet ID.
	 *
	 * @var string|null
	 */
	public $current_snippet = null;

	/**
	 * Currently executing snippet type (library or custom).
	 *
	 * @var string|null
	 */
	public $current_snippet_type = null;

	/**
	 * Snippets manager.
	 *
	 * @var SNDP_Snippets
	 */
	private $snippets;

	/**
	 * Library manager.
	 *
	 * @var SNDP_Library
	 */
	private $library;

	/**
	 * Custom snippets manager.
	 *
	 * @var SNDP_Custom_Snippets
	 */
	private $custom_snippets;

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 * @return SNDP_Executor
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
		$this->snippets        = SNDP_Snippets::instance();
		$this->library         = SNDP_Library::instance();
		$this->custom_snippets = SNDP_Custom_Snippets::instance();

		// Execute snippets on init (after plugins_loaded but early enough).
		add_action( 'init', array( $this, 'execute_snippets' ), 1 );

		// Register auto-insert hooks for custom snippets.
		add_action( 'wp_head', array( $this, 'execute_header_snippets' ), 10 );
		add_action( 'wp_footer', array( $this, 'execute_footer_snippets' ), 10 );
		add_filter( 'the_content', array( $this, 'execute_content_snippets' ), 10 );

		// Register shortcode.
		add_shortcode( 'snipdrop', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Execute all enabled snippets.
	 *
	 * @since 1.0.0
	 */
	public function execute_snippets() {
		// Check safe mode.
		if ( $this->snippets->is_safe_mode() ) {
			return;
		}

		// Execute library snippets.
		$this->execute_library_snippets();

		// Execute custom snippets.
		$this->execute_custom_snippets();
	}

	/**
	 * Execute all enabled library snippets.
	 *
	 * @since 1.1.0
	 */
	private function execute_library_snippets() {
		// Get enabled snippets.
		$enabled = $this->snippets->get_enabled_snippets();
		if ( empty( $enabled ) ) {
			return;
		}

		// Get error snippets to skip.
		$error_snippets = $this->snippets->get_error_snippets();

		foreach ( $enabled as $snippet_id ) {
			// Skip snippets with errors.
			if ( isset( $error_snippets[ $snippet_id ] ) ) {
				continue;
			}

			$this->execute_library_snippet( $snippet_id );
		}
	}

	/**
	 * Execute all active custom snippets.
	 *
	 * @since 1.1.0
	 */
	private function execute_custom_snippets() {
		$active_snippets = $this->custom_snippets->get_active();

		foreach ( $active_snippets as $snippet_id => $snippet ) {
			// Skip non-PHP snippets for now.
			if ( 'php' !== $snippet['code_type'] ) {
				continue;
			}

			// Check location.
			if ( ! $this->should_run_custom_snippet( $snippet ) ) {
				continue;
			}

			$this->execute_custom_snippet( $snippet_id, $snippet );
		}
	}

	/**
	 * Execute a single library snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_library_snippet( $snippet_id ) {
		// Get snippet data.
		$snippet = $this->library->get_snippet( $snippet_id );
		if ( is_wp_error( $snippet ) ) {
			return false;
		}

		// Check requirements.
		if ( ! $this->check_requirements( $snippet ) ) {
			return false;
		}

		// Get the code.
		$code = isset( $snippet['code'] ) ? $snippet['code'] : '';
		if ( empty( $code ) ) {
			return false;
		}

		// Replace placeholders with user configuration.
		if ( ! empty( $snippet['configurable'] ) && ! empty( $snippet['settings'] ) ) {
			$code = $this->replace_placeholders( $code, $snippet_id, $snippet['settings'] );
		}

		// Track current snippet for error handling.
		$this->current_snippet      = $snippet_id;
		$this->current_snippet_type = 'library';

		// Execute the code.
		$result = $this->run_code( $code, $snippet_id, 'library' );

		// Clear current snippet.
		$this->current_snippet      = null;
		$this->current_snippet_type = null;

		return $result;
	}

	/**
	 * Execute a single custom snippet.
	 *
	 * @since 1.1.0
	 * @param string $snippet_id Snippet ID.
	 * @param array  $snippet    Snippet data.
	 * @return bool True on success, false on failure.
	 */
	public function execute_custom_snippet( $snippet_id, $snippet ) {
		$code = isset( $snippet['code'] ) ? $snippet['code'] : '';
		if ( empty( $code ) ) {
			return false;
		}

		// Track current snippet for error handling.
		$this->current_snippet      = $snippet_id;
		$this->current_snippet_type = 'custom';

		// Execute the code.
		$result = $this->run_code( $code, $snippet_id, 'custom' );

		// Clear current snippet.
		$this->current_snippet      = null;
		$this->current_snippet_type = null;

		return $result;
	}

	/**
	 * Check if custom snippet should run based on conditions (for hook-based execution).
	 *
	 * @since 1.1.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function should_run_custom_snippet( $snippet ) {
		$location = isset( $snippet['location'] ) ? $snippet['location'] : 'everywhere';

		// Auto-insert locations are handled by their own hooks.
		$auto_insert_locations = array( 'site_header', 'site_footer', 'before_content', 'after_content', 'shortcode' );
		if ( in_array( $location, $auto_insert_locations, true ) ) {
			return false;
		}

		// Check location (admin/frontend/everywhere).
		switch ( $location ) {
			case 'frontend':
				if ( is_admin() ) {
					return false;
				}
				break;

			case 'admin':
				if ( ! is_admin() ) {
					return false;
				}
				// Admin snippets don't need further frontend checks.
				return $this->check_user_condition( $snippet );
		}

		// Check user condition.
		if ( ! $this->check_user_condition( $snippet ) ) {
			return false;
		}

		// Frontend-only checks.
		if ( ! is_admin() ) {
			// Check post types (only on frontend and after query is set).
			if ( ! $this->check_post_type_condition( $snippet ) ) {
				return false;
			}

			// Check specific page IDs.
			if ( ! $this->check_page_id_condition( $snippet ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if an auto-insert snippet should run.
	 *
	 * @since 1.2.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function should_run_auto_insert( $snippet ) {
		// Check safe mode.
		if ( $this->snippets->is_safe_mode() ) {
			return false;
		}

		// Check user condition.
		if ( ! $this->check_user_condition( $snippet ) ) {
			return false;
		}

		// Check post type condition.
		if ( ! $this->check_post_type_condition( $snippet ) ) {
			return false;
		}

		// Check page ID condition.
		if ( ! $this->check_page_id_condition( $snippet ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Execute snippets in site header (wp_head).
	 *
	 * @since 1.2.0
	 */
	public function execute_header_snippets() {
		$this->execute_auto_insert_snippets( 'site_header' );
	}

	/**
	 * Execute snippets in site footer (wp_footer).
	 *
	 * @since 1.2.0
	 */
	public function execute_footer_snippets() {
		$this->execute_auto_insert_snippets( 'site_footer' );
	}

	/**
	 * Execute snippets before/after content.
	 *
	 * @since 1.2.0
	 * @param string $content The post content.
	 * @return string Modified content.
	 */
	public function execute_content_snippets( $content ) {
		// Only run on single posts/pages in the main query.
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$before = $this->get_auto_insert_output( 'before_content' );
		$after  = $this->get_auto_insert_output( 'after_content' );

		return $before . $content . $after;
	}

	/**
	 * Execute auto-insert snippets for a location.
	 *
	 * @since 1.2.0
	 * @param string $location Location to execute.
	 */
	private function execute_auto_insert_snippets( $location ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Snippet output is intentionally raw.
		echo $this->get_auto_insert_output( $location );
	}

	/**
	 * Get output from auto-insert snippets.
	 *
	 * @since 1.2.0
	 * @param string $location Location to get output for.
	 * @return string Combined output.
	 */
	private function get_auto_insert_output( $location ) {
		$active_snippets = $this->custom_snippets->get_active();
		$output          = '';

		foreach ( $active_snippets as $snippet_id => $snippet ) {
			// Check location matches.
			$snippet_location = isset( $snippet['location'] ) ? $snippet['location'] : 'everywhere';
			if ( $snippet_location !== $location ) {
				continue;
			}

			// Check conditions.
			if ( ! $this->should_run_auto_insert( $snippet ) ) {
				continue;
			}

			// Execute based on code type.
			$code_type = isset( $snippet['code_type'] ) ? $snippet['code_type'] : 'php';
			$code      = isset( $snippet['code'] ) ? $snippet['code'] : '';

			if ( empty( $code ) ) {
				continue;
			}

			switch ( $code_type ) {
				case 'php':
					$output .= $this->execute_php_and_capture( $snippet_id, $code );
					break;

				case 'js':
					$output .= '<script>' . $code . '</script>';
					break;

				case 'css':
					$output .= '<style>' . $code . '</style>';
					break;

				case 'html':
					$output .= $code;
					break;
			}
		}

		return $output;
	}

	/**
	 * Execute PHP code and capture output.
	 *
	 * Supports both echo-style output and return statements.
	 *
	 * @since 1.2.0
	 * @param string $snippet_id Snippet ID.
	 * @param string $code       PHP code.
	 * @return string Captured output.
	 */
	private function execute_php_and_capture( $snippet_id, $code ) {
		$this->current_snippet      = $snippet_id;
		$this->current_snippet_type = 'custom';

		ob_start();
		$return_value = null;

		try {
			// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found, Squiz.PHP.Eval.Discouraged -- eval() is required for dynamic snippet execution.
			$return_value = eval( $code );
		} catch ( \ParseError $e ) {
			$this->custom_snippets->record_error( $snippet_id, array( 'message' => $e->getMessage(), 'line' => $e->getLine() ) );
		} catch ( \Error $e ) {
			$this->custom_snippets->record_error( $snippet_id, array( 'message' => $e->getMessage(), 'line' => $e->getLine() ) );
		} catch ( \Exception $e ) {
			$this->custom_snippets->record_error( $snippet_id, array( 'message' => $e->getMessage(), 'line' => $e->getLine() ) );
		}

		$output = ob_get_clean();

		$this->current_snippet      = null;
		$this->current_snippet_type = null;

		// If code returned a string value, use that. Otherwise use captured output.
		if ( is_string( $return_value ) ) {
			return $return_value;
		}

		return $output;
	}

	/**
	 * Render shortcode.
	 *
	 * @since 1.2.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => '',
			),
			$atts,
			'snipdrop'
		);

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		// Get snippet.
		$snippet = $this->custom_snippets->get( $atts['id'] );
		if ( ! $snippet || 'active' !== $snippet['status'] ) {
			return '';
		}

		// Check if it's a shortcode snippet.
		$location = isset( $snippet['location'] ) ? $snippet['location'] : 'everywhere';
		if ( 'shortcode' !== $location ) {
			return '';
		}

		// Check conditions.
		if ( ! $this->should_run_auto_insert( $snippet ) ) {
			return '';
		}

		// Execute based on code type.
		$code_type = isset( $snippet['code_type'] ) ? $snippet['code_type'] : 'php';
		$code      = isset( $snippet['code'] ) ? $snippet['code'] : '';

		if ( empty( $code ) ) {
			return '';
		}

		switch ( $code_type ) {
			case 'php':
				return $this->execute_php_and_capture( $atts['id'], $code );

			case 'js':
				return '<script>' . $code . '</script>';

			case 'css':
				return '<style>' . $code . '</style>';

			case 'html':
				return $code;

			default:
				return '';
		}
	}

	/**
	 * Check user condition (logged in/out).
	 *
	 * @since 1.1.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function check_user_condition( $snippet ) {
		$user_cond = isset( $snippet['user_cond'] ) ? $snippet['user_cond'] : 'all';

		switch ( $user_cond ) {
			case 'logged_in':
				return is_user_logged_in();

			case 'logged_out':
				return ! is_user_logged_in();

			case 'all':
			default:
				return true;
		}
	}

	/**
	 * Check post type condition.
	 *
	 * @since 1.1.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function check_post_type_condition( $snippet ) {
		$post_types = isset( $snippet['post_types'] ) ? $snippet['post_types'] : array();

		// Empty means all post types.
		if ( empty( $post_types ) ) {
			return true;
		}

		// Get current post type.
		$current_post_type = get_post_type();
		if ( ! $current_post_type ) {
			// Not on a singular page, allow the snippet.
			return true;
		}

		return in_array( $current_post_type, $post_types, true );
	}

	/**
	 * Check specific page ID condition.
	 *
	 * @since 1.1.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function check_page_id_condition( $snippet ) {
		$page_ids = isset( $snippet['page_ids'] ) ? $snippet['page_ids'] : '';

		// Empty means all pages.
		if ( empty( $page_ids ) ) {
			return true;
		}

		// Parse comma-separated IDs.
		$ids = array_map( 'absint', array_map( 'trim', explode( ',', $page_ids ) ) );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return true;
		}

		// Get current page/post ID.
		$current_id = get_queried_object_id();
		if ( ! $current_id ) {
			return false;
		}

		return in_array( $current_id, $ids, true );
	}

	/**
	 * Replace placeholders in code with user configuration values.
	 *
	 * @since 1.1.0
	 * @param string $code              The snippet code.
	 * @param string $snippet_id        Snippet ID.
	 * @param array  $settings_definition Settings definition from snippet.
	 * @return string Code with placeholders replaced.
	 */
	private function replace_placeholders( $code, $snippet_id, $settings_definition ) {
		// Get user configuration.
		$user_config = $this->snippets->get_snippet_config( $snippet_id );

		// Get defaults.
		$defaults = $this->snippets->get_default_config( $settings_definition );

		// Merge user config with defaults.
		$config = wp_parse_args( $user_config, $defaults );

		// Replace each placeholder.
		foreach ( $config as $key => $value ) {
			// Support both {{key}} and {key} placeholders.
			$code = str_replace( '{{' . $key . '}}', $value, $code );
			$code = str_replace( '{' . $key . '}', $value, $code );
		}

		return $code;
	}

	/**
	 * Run snippet code safely.
	 *
	 * @since 1.0.0
	 * @param string $code         PHP code to execute.
	 * @param string $snippet_id   Snippet ID for error tracking.
	 * @param string $snippet_type Snippet type (library or custom).
	 * @return bool
	 */
	private function run_code( $code, $snippet_id, $snippet_type = 'library' ) {
		// The code should already include the hook registration.
		// We just need to eval it.
		try {
			// Remove opening PHP tag if present.
			$code = preg_replace( '/^<\?php\s*/', '', $code );
			$code = preg_replace( '/\?>\s*$/', '', $code );

			// Execute the code.
			// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found, Squiz.PHP.Eval.Discouraged -- eval() is required for dynamic snippet execution. All code is sanitized and user-controlled.
			eval( $code );

			return true;
		} catch ( \ParseError $e ) {
			$this->record_error( $snippet_id, $snippet_type, $e );
			return false;
		} catch ( \Error $e ) {
			$this->record_error( $snippet_id, $snippet_type, $e );
			return false;
		} catch ( \Exception $e ) {
			$this->record_error( $snippet_id, $snippet_type, $e );
			return false;
		}
	}

	/**
	 * Record error for snippet.
	 *
	 * @since 1.1.0
	 * @param string     $snippet_id   Snippet ID.
	 * @param string     $snippet_type Snippet type (library or custom).
	 * @param \Throwable $exception    Exception or Error object.
	 */
	private function record_error( $snippet_id, $snippet_type, $exception ) {
		$error = array(
			'message' => $exception->getMessage(),
			'line'    => $exception->getLine(),
		);

		if ( 'custom' === $snippet_type ) {
			$this->custom_snippets->record_error( $snippet_id, $error );
		} else {
			$this->snippets->record_snippet_error( $snippet_id, $error );
		}
	}

	/**
	 * Check if snippet requirements are met.
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function check_requirements( $snippet ) {
		if ( ! isset( $snippet['requires'] ) ) {
			return true;
		}

		$requires = $snippet['requires'];

		// Check required plugins.
		if ( isset( $requires['plugins'] ) && is_array( $requires['plugins'] ) ) {
			foreach ( $requires['plugins'] as $plugin ) {
				if ( ! $this->is_plugin_active( $plugin ) ) {
					return false;
				}
			}
		}

		// Check PHP version.
		if ( isset( $requires['php_version'] ) ) {
			if ( version_compare( PHP_VERSION, $requires['php_version'], '<' ) ) {
				return false;
			}
		}

		// Check WordPress version.
		if ( isset( $requires['wp_version'] ) ) {
			global $wp_version;
			if ( version_compare( $wp_version, $requires['wp_version'], '<' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @since 1.0.0
	 * @param string $plugin Plugin slug (e.g., 'woocommerce').
	 * @return bool
	 */
	private function is_plugin_active( $plugin ) {
		$plugin = strtolower( $plugin );

		switch ( $plugin ) {
			case 'woocommerce':
				return class_exists( 'WooCommerce' );

			case 'elementor':
				return defined( 'ELEMENTOR_VERSION' );

			case 'wpforms':
				return defined( 'WPFORMS_VERSION' );

			case 'yoast':
			case 'wordpress-seo':
				return defined( 'WPSEO_VERSION' );

			default:
				// Generic check using is_plugin_active.
				if ( ! function_exists( 'is_plugin_active' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				return is_plugin_active( $plugin . '/' . $plugin . '.php' );
		}
	}

	/**
	 * Get currently executing snippet ID.
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	public function get_current_snippet() {
		return $this->current_snippet;
	}
}
