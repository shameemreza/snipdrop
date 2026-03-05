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
	 * Cached active custom snippets for the current request.
	 *
	 * @var array|null
	 */
	private $active_custom_snippets_cache = null;

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
	 * @since 1.0.0
	 */
	private function execute_library_snippets() {
		$enabled = $this->snippets->get_enabled_snippets();
		if ( empty( $enabled ) ) {
			return;
		}

		/**
		 * Filter the list of enabled library snippet IDs before execution.
		 *
		 * @since 1.0.0
		 * @param array $enabled Array of enabled snippet IDs.
		 */
		$enabled = apply_filters( 'snipdrop_enabled_snippets', $enabled );

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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function should_run_custom_snippet( $snippet ) {
		if ( $this->is_admin_bypass_active() ) {
			return false;
		}

		if ( ! $this->check_schedule_condition( $snippet ) ) {
			return false;
		}

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
			if ( ! $this->check_post_type_condition( $snippet ) ) {
				return false;
			}

			if ( ! $this->check_page_id_condition( $snippet ) ) {
				return false;
			}

			if ( ! $this->check_url_pattern_condition( $snippet ) ) {
				return false;
			}

			if ( ! $this->check_taxonomy_condition( $snippet ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if an auto-insert snippet should run.
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function should_run_auto_insert( $snippet ) {
		if ( $this->is_admin_bypass_active() ) {
			return false;
		}

		if ( ! $this->check_schedule_condition( $snippet ) ) {
			return false;
		}

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

		if ( ! $this->check_url_pattern_condition( $snippet ) ) {
			return false;
		}

		if ( ! $this->check_taxonomy_condition( $snippet ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Execute snippets in site header (wp_head).
	 *
	 * @since 1.0.0
	 */
	public function execute_header_snippets() {
		$this->execute_auto_insert_snippets( 'site_header' );
	}

	/**
	 * Execute snippets in site footer (wp_footer).
	 *
	 * @since 1.0.0
	 */
	public function execute_footer_snippets() {
		$this->execute_auto_insert_snippets( 'site_footer' );
	}

	/**
	 * Execute snippets before/after content.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @param string $location Location to execute.
	 */
	private function execute_auto_insert_snippets( $location ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Snippet output is intentionally raw.
		echo $this->get_auto_insert_output( $location );
	}

	/**
	 * Get output from auto-insert snippets.
	 *
	 * @since 1.0.0
	 * @param string $location Location to get output for.
	 * @return string Combined output.
	 */
	private function get_auto_insert_output( $location ) {
		if ( null === $this->active_custom_snippets_cache ) {
			$this->active_custom_snippets_cache = $this->custom_snippets->get_active();
		}
		$active_snippets = $this->active_custom_snippets_cache;
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
	 * @since 1.0.0
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
			$this->custom_snippets->record_error(
				$snippet_id,
				array(
					'message' => $e->getMessage(),
					'line'    => $e->getLine(),
				)
			);
		} catch ( \Error $e ) {
			$this->custom_snippets->record_error(
				$snippet_id,
				array(
					'message' => $e->getMessage(),
					'line'    => $e->getLine(),
				)
			);
		} catch ( \Exception $e ) {
			$this->custom_snippets->record_error(
				$snippet_id,
				array(
					'message' => $e->getMessage(),
					'line'    => $e->getLine(),
				)
			);
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
	 * @since 1.0.0
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
	 * Check if the snippet is within its scheduled date/time window.
	 *
	 * Uses the site's configured timezone via wp_timezone().
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function check_schedule_condition( $snippet ) {
		$start = isset( $snippet['schedule_start'] ) ? trim( $snippet['schedule_start'] ) : '';
		$end   = isset( $snippet['schedule_end'] ) ? trim( $snippet['schedule_end'] ) : '';

		if ( '' === $start && '' === $end ) {
			return true;
		}

		$tz  = wp_timezone();
		$now = new \DateTime( 'now', $tz );

		if ( '' !== $start ) {
			try {
				$start_dt = new \DateTime( $start, $tz );
				if ( $now < $start_dt ) {
					return false;
				}
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				unset( $e );
			}
		}

		if ( '' !== $end ) {
			try {
				$end_dt = new \DateTime( $end, $tz );
				if ( $now > $end_dt ) {
					return false;
				}
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				unset( $e );
			}
		}

		return true;
	}

	/**
	 * Check if frontend snippets should be bypassed for the current admin user.
	 *
	 * @since 1.0.0
	 * @return bool True if snippets should be skipped.
	 */
	private function is_admin_bypass_active() {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		if ( is_admin() ) {
			$cached = false;
			return false;
		}

		$settings = SNDP_Snippets::instance()->get_settings();
		if ( empty( $settings['disable_for_admins'] ) ) {
			$cached = false;
			return false;
		}

		$cached = current_user_can( 'manage_options' );
		return $cached;
	}

	/**
	 * Check user condition (logged in/out).
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * Check URL pattern condition.
	 *
	 * Supports wildcard (*) matching against the current request URI.
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function check_url_pattern_condition( $snippet ) {
		$patterns = isset( $snippet['url_patterns'] ) ? trim( $snippet['url_patterns'] ) : '';

		if ( empty( $patterns ) ) {
			return true;
		}

		$current_path = isset( $_SERVER['REQUEST_URI'] )
			? wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH )
			: '/';

		if ( ! $current_path ) {
			$current_path = '/';
		}

		$lines = array_filter( array_map( 'trim', explode( "\n", $patterns ) ) );

		foreach ( $lines as $pattern ) {
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
			if ( preg_match( $regex, $current_path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check taxonomy/term condition.
	 *
	 * Format: array of "taxonomy:slug" strings (e.g., "category:news", "product_cat:clothing").
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function check_taxonomy_condition( $snippet ) {
		$taxonomies = isset( $snippet['taxonomies'] ) ? $snippet['taxonomies'] : array();

		if ( empty( $taxonomies ) ) {
			return true;
		}

		$current_id = get_queried_object_id();
		if ( ! $current_id ) {
			return false;
		}

		foreach ( $taxonomies as $tax_term ) {
			$parts = explode( ':', $tax_term, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}

			$taxonomy = $parts[0];
			$slug     = $parts[1];

			if ( has_term( $slug, $taxonomy, $current_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace placeholders in code with user configuration values.
	 *
	 * @since 1.0.0
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

		// Replace each placeholder with escaped values to prevent code injection.
		foreach ( $config as $key => $value ) {
			$safe_value = addslashes( (string) $value );
			$code       = str_replace( '{{' . $key . '}}', $safe_value, $code );
			$code       = str_replace( '{' . $key . '}', $safe_value, $code );
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
		/**
		 * Filter whether a snippet should be executed.
		 *
		 * @since 1.0.0
		 * @param bool   $allow        Whether to allow execution. Default true.
		 * @param string $snippet_id   The snippet ID.
		 * @param string $snippet_type The snippet type ('library' or 'custom').
		 */
		if ( ! apply_filters( 'snipdrop_allow_execute', true, $snippet_id, $snippet_type ) ) {
			return false;
		}

		try {
			$code = preg_replace( '/^<\?php\s*/', '', $code );
			$code = preg_replace( '/\?>\s*$/', '', $code );

			/**
			 * Filter snippet code before execution.
			 *
			 * @since 1.0.0
			 * @param string $code         The PHP code to execute.
			 * @param string $snippet_id   The snippet ID.
			 * @param string $snippet_type The snippet type ('library' or 'custom').
			 */
			$code = apply_filters( 'snipdrop_before_execute', $code, $snippet_id, $snippet_type );

			// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found, Squiz.PHP.Eval.Discouraged -- eval() is required for dynamic snippet execution. All code is sanitized and user-controlled.
			eval( $code );

			/**
			 * Action after a snippet has been successfully executed.
			 *
			 * @since 1.0.0
			 * @param string $snippet_id   The snippet ID.
			 * @param string $snippet_type The snippet type ('library' or 'custom').
			 */
			do_action( 'snipdrop_after_execute', $snippet_id, $snippet_type );

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
	 * @since 1.0.0
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
