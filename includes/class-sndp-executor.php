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
	 * Cached global scripts option.
	 *
	 * @var array|null
	 */
	private $global_scripts_cache = null;

	/**
	 * Queued inline JS from library snippets.
	 *
	 * @var array
	 */
	private $queued_js = array();

	/**
	 * Queued inline CSS from library snippets.
	 *
	 * @var array
	 */
	private $queued_css = array();

	/**
	 * Queued inline HTML from library snippets.
	 *
	 * @var array
	 */
	private $queued_html = array();

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

		// Output queued CSS/JS/HTML from library snippets.
		add_action( 'wp_head', array( $this, 'output_queued_css' ), 99 );
		add_action( 'wp_footer', array( $this, 'output_queued_js' ), 20 );
		add_action( 'wp_footer', array( $this, 'output_queued_html' ), 20 );

		// Global Header & Footer scripts.
		add_action( 'wp_head', array( $this, 'output_global_header_scripts' ), 100 );
		add_action( 'wp_body_open', array( $this, 'output_global_body_scripts' ), 1 );
		add_action( 'wp_footer', array( $this, 'output_global_footer_scripts' ), 99 );

		// Register auto-insert hooks for custom snippets.
		add_action( 'wp_head', array( $this, 'execute_header_snippets' ), 10 );
		add_action( 'wp_body_open', array( $this, 'execute_body_open_snippets' ), 10 );
		add_action( 'wp_footer', array( $this, 'execute_footer_snippets' ), 10 );
		add_filter( 'the_content', array( $this, 'execute_content_snippets' ), 10 );

		// WooCommerce auto-insert hooks.
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_before_shop_loop', array( $this, 'execute_wc_before_shop_loop' ), 5 );
			add_action( 'woocommerce_after_shop_loop', array( $this, 'execute_wc_after_shop_loop' ), 15 );
			add_action( 'woocommerce_before_single_product', array( $this, 'execute_wc_before_single_product' ), 5 );
			add_action( 'woocommerce_after_single_product', array( $this, 'execute_wc_after_single_product' ), 15 );
			add_action( 'woocommerce_before_cart', array( $this, 'execute_wc_before_cart' ), 5 );
			add_action( 'woocommerce_before_checkout_form', array( $this, 'execute_wc_before_checkout' ), 5 );
			add_action( 'woocommerce_after_checkout_form', array( $this, 'execute_wc_after_checkout' ), 15 );
			add_action( 'woocommerce_thankyou', array( $this, 'execute_wc_thankyou' ), 15 );
		}

		add_shortcode( 'snipdrop', array( $this, 'render_shortcode' ) );

		if ( ! is_admin() ) {
			$this->register_custom_shortcodes();
		}
	}

	/**
	 * Register custom named shortcodes from active snippets.
	 *
	 * Uses a lightweight cached index to avoid loading all snippet data.
	 */
	private function register_custom_shortcodes() {
		$index = $this->get_shortcode_index();

		foreach ( $index as $snippet_id => $name ) {
			if ( shortcode_exists( $name ) ) {
				continue;
			}

			add_shortcode(
				$name,
				function () use ( $snippet_id ) {
					return $this->render_shortcode( array( 'id' => $snippet_id ) );
				}
			);
		}
	}

	/**
	 * Get the shortcode index (snippet_id → shortcode_name map).
	 *
	 * Cached as a transient; rebuilt when snippets change.
	 *
	 * @since 1.0.0
	 * @return array<string, string>
	 */
	private function get_shortcode_index() {
		$index = get_transient( 'sndp_shortcode_index' );
		if ( is_array( $index ) ) {
			return $index;
		}

		return $this->rebuild_shortcode_index();
	}

	/**
	 * Rebuild the shortcode index from current snippets.
	 *
	 * @since 1.0.0
	 * @return array<string, string>
	 */
	public function rebuild_shortcode_index() {
		$index        = array();
		$all_snippets = $this->custom_snippets->get_all();

		foreach ( $all_snippets as $snippet ) {
			if ( empty( $snippet['shortcode_name'] ) ) {
				continue;
			}
			if ( 'active' !== ( $snippet['status'] ?? '' ) ) {
				continue;
			}
			if ( 'shortcode' !== ( $snippet['location'] ?? '' ) ) {
				continue;
			}

			$name = sanitize_key( $snippet['shortcode_name'] );
			if ( ! empty( $name ) ) {
				$index[ $snippet['id'] ] = $name;
			}
		}

		set_transient( 'sndp_shortcode_index', $index, DAY_IN_SECONDS );
		return $index;
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

		$code_type = isset( $snippet['code_type'] ) ? $snippet['code_type'] : 'php';

		// Non-PHP code types are queued for frontend output, not eval'd.
		switch ( $code_type ) {
			case 'js':
				$this->queued_js[ $snippet_id ] = $code;
				return true;

			case 'css':
				$this->queued_css[ $snippet_id ] = $code;
				return true;

			case 'html':
				$this->queued_html[ $snippet_id ] = $code;
				return true;
		}

		// Track current snippet for error handling.
		$this->current_snippet      = $snippet_id;
		$this->current_snippet_type = 'library';

		// Execute PHP code.
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
		if ( ! $this->check_common_conditions( $snippet ) ) {
			return false;
		}

		$location = isset( $snippet['location'] ) ? $snippet['location'] : 'everywhere';

		$auto_insert_locations = array(
			'site_header',
			'site_footer',
			'before_content',
			'after_content',
			'after_paragraph',
			'body_open',
			'shortcode',
			'wc_before_shop_loop',
			'wc_after_shop_loop',
			'wc_before_single_product',
			'wc_after_single_product',
			'wc_before_cart',
			'wc_before_checkout_form',
			'wc_after_checkout_form',
			'wc_thankyou',
		);
		if ( in_array( $location, $auto_insert_locations, true ) ) {
			return false;
		}

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
				return $this->check_user_condition( $snippet );
		}

		return $this->check_targeting_conditions( $snippet, ! is_admin() );
	}

	/**
	 * Check if an auto-insert snippet should run.
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	private function should_run_auto_insert( $snippet ) {
		if ( ! $this->check_common_conditions( $snippet ) ) {
			return false;
		}

		return $this->check_targeting_conditions( $snippet, true );
	}

	/**
	 * Run the shared pre-flight checks common to both custom and auto-insert snippets.
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return bool False if the snippet should not execute.
	 */
	private function check_common_conditions( $snippet ) {
		if ( $this->is_admin_bypass_active() ) {
			return false;
		}

		if ( ! $this->check_schedule_condition( $snippet ) ) {
			return false;
		}

		if ( $this->snippets->is_safe_mode() ) {
			return false;
		}

		return true;
	}

	/**
	 * Run user and page-level targeting conditions.
	 *
	 * @since 1.0.0
	 * @param array $snippet            Snippet data.
	 * @param bool  $check_page_targets Whether to evaluate page-level conditions
	 *                                  (post type, page ID, URL, taxonomy).
	 * @return bool
	 */
	private function check_targeting_conditions( $snippet, $check_page_targets = true ) {
		if ( ! $this->check_user_condition( $snippet ) ) {
			return false;
		}

		if ( $check_page_targets ) {
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

		if ( ! SNDP_Conditional_Logic::instance()->should_run( $snippet ) ) {
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
	 * Execute snippets after body open (wp_body_open).
	 *
	 * @since 1.0.0
	 */
	public function execute_body_open_snippets() {
		$this->execute_auto_insert_snippets( 'body_open' );
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
	 * Execute content filter snippets with paragraph insertion support.
	 *
	 * @since 1.0.0
	 * @param string $content The post content.
	 * @return string Modified content.
	 */
	public function execute_content_snippets( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$before  = $this->get_auto_insert_output( 'before_content' );
		$after   = $this->get_auto_insert_output( 'after_content' );
		$content = $this->insert_after_paragraph( $content );

		return $before . $content . $after;
	}

	/**
	 * Insert snippets after a specific paragraph number.
	 *
	 * @since 1.0.0
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	private function insert_after_paragraph( $content ) {
		if ( null === $this->active_custom_snippets_cache ) {
			$this->active_custom_snippets_cache = $this->custom_snippets->get_active();
		}

		$insertions = array();
		foreach ( $this->active_custom_snippets_cache as $snippet ) {
			if ( 'after_paragraph' !== ( $snippet['location'] ?? '' ) ) {
				continue;
			}
			if ( ! $this->should_run_auto_insert( $snippet ) ) {
				continue;
			}
			$para_num = absint( $snippet['insert_paragraph'] ?? 2 );
			if ( $para_num < 1 ) {
				$para_num = 2;
			}

			$code      = isset( $snippet['code'] ) ? $snippet['code'] : '';
			$code_type = isset( $snippet['code_type'] ) ? $snippet['code_type'] : 'php';
			$sid       = isset( $snippet['id'] ) ? $snippet['id'] : '';

			if ( empty( $code ) ) {
				continue;
			}

			switch ( $code_type ) {
				case 'php':
					$output = $this->execute_php_and_capture( $sid, $code );
					break;
				case 'js':
					$output = '<script>' . $code . '</script>';
					break;
				case 'css':
					$output = '<style>' . $code . '</style>';
					break;
				case 'html':
				default:
					$output = $code;
					break;
			}

			if ( '' !== $output ) {
				if ( ! isset( $insertions[ $para_num ] ) ) {
					$insertions[ $para_num ] = '';
				}
				$insertions[ $para_num ] .= $output;
			}
		}

		if ( empty( $insertions ) ) {
			return $content;
		}

		$paragraphs = explode( '</p>', $content );
		$total      = count( $paragraphs );

		foreach ( $insertions as $para_num => $output ) {
			$idx = min( $para_num, $total ) - 1;
			if ( $idx >= 0 && isset( $paragraphs[ $idx ] ) ) {
				$paragraphs[ $idx ] .= $output;
			}
		}

		return implode( '</p>', $paragraphs );
	}

	/** @since 1.0.0 */
	public function execute_wc_before_shop_loop() {
		$this->execute_auto_insert_snippets( 'wc_before_shop_loop' );
	}

	/** @since 1.0.0 */
	public function execute_wc_after_shop_loop() {
		$this->execute_auto_insert_snippets( 'wc_after_shop_loop' );
	}

	/** @since 1.0.0 */
	public function execute_wc_before_single_product() {
		$this->execute_auto_insert_snippets( 'wc_before_single_product' );
	}

	/** @since 1.0.0 */
	public function execute_wc_after_single_product() {
		$this->execute_auto_insert_snippets( 'wc_after_single_product' );
	}

	/** @since 1.0.0 */
	public function execute_wc_before_cart() {
		$this->execute_auto_insert_snippets( 'wc_before_cart' );
	}

	/** @since 1.0.0 */
	public function execute_wc_before_checkout() {
		$this->execute_auto_insert_snippets( 'wc_before_checkout_form' );
	}

	/** @since 1.0.0 */
	public function execute_wc_after_checkout() {
		$this->execute_auto_insert_snippets( 'wc_after_checkout_form' );
	}

	/** @since 1.0.0 */
	public function execute_wc_thankyou() {
		$this->execute_auto_insert_snippets( 'wc_thankyou' );
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
	 * Values are sanitized per their declared type from the snippet's settings
	 * definition. Unknown keys or values that fail validation are replaced with
	 * the setting's default to prevent code injection through eval().
	 *
	 * @since 1.0.0
	 * @param string $code              The snippet code.
	 * @param string $snippet_id        Snippet ID.
	 * @param array  $settings_definition Settings definition from snippet.
	 * @return string Code with placeholders replaced.
	 */
	private function replace_placeholders( $code, $snippet_id, $settings_definition ) {
		$user_config = $this->snippets->get_snippet_config( $snippet_id );
		$defaults    = $this->snippets->get_default_config( $settings_definition );
		$config      = wp_parse_args( $user_config, $defaults );

		$type_map    = $this->build_settings_type_map( $settings_definition );
		$options_map = $this->build_settings_options_map( $settings_definition );

		foreach ( $config as $key => $value ) {
			$type       = isset( $type_map[ $key ] ) ? $type_map[ $key ] : 'text';
			$default    = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
			$safe_value = $this->sanitize_placeholder_value( $value, $type, $default, isset( $options_map[ $key ] ) ? $options_map[ $key ] : array() );

			$code = str_replace( '{{' . $key . '}}', $safe_value, $code );
			$code = str_replace( '{' . $key . '}', $safe_value, $code );
		}

		return $code;
	}

	/**
	 * Build a map of setting ID → declared type from the settings definition.
	 *
	 * @since 1.0.0
	 * @param array $settings_definition Settings definition array.
	 * @return array Keyed by setting ID.
	 */
	private function build_settings_type_map( $settings_definition ) {
		$map = array();
		if ( ! is_array( $settings_definition ) ) {
			return $map;
		}
		foreach ( $settings_definition as $setting ) {
			if ( isset( $setting['id'], $setting['type'] ) ) {
				$map[ $setting['id'] ] = $setting['type'];
			}
		}
		return $map;
	}

	/**
	 * Build a map of setting ID → allowed option values for select/radio types.
	 *
	 * @since 1.0.0
	 * @param array $settings_definition Settings definition array.
	 * @return array Keyed by setting ID, each value is an array of allowed option values.
	 */
	private function build_settings_options_map( $settings_definition ) {
		$map = array();
		if ( ! is_array( $settings_definition ) ) {
			return $map;
		}
		foreach ( $settings_definition as $setting ) {
			if ( isset( $setting['id'], $setting['options'] ) && is_array( $setting['options'] ) ) {
				$map[ $setting['id'] ] = array_map( 'strval', array_keys( $setting['options'] ) );
			}
		}
		return $map;
	}

	/**
	 * Sanitize a single placeholder value based on its declared type.
	 *
	 * Falls back to the default if the value is invalid for the type.
	 *
	 * @since 1.0.0
	 * @param mixed  $value   Raw value.
	 * @param string $type    Setting type (text, number, select, checkbox, color, url).
	 * @param mixed  $default Default value to fall back to.
	 * @param array  $options Allowed values for select/radio types.
	 * @return string Safe string for placeholder replacement.
	 */
	private function sanitize_placeholder_value( $value, $type, $default, $options = array() ) {
		$value = (string) $value;

		switch ( $type ) {
			case 'number':
				if ( ! is_numeric( $value ) ) {
					return addslashes( (string) $default );
				}
				return $value;

			case 'select':
			case 'radio':
				if ( ! empty( $options ) && ! in_array( $value, $options, true ) ) {
					return addslashes( (string) $default );
				}
				return addslashes( $value );

			case 'checkbox':
				return in_array( $value, array( '1', 'yes', 'true', 'on' ), true ) ? '1' : '0';

			case 'color':
				if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
					return $value;
				}
				return addslashes( (string) $default );

			case 'url':
				$value = esc_url_raw( $value );
				return addslashes( $value );

			case 'text':
			default:
				return addslashes( $value );
		}
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
			 * SECURITY: Callbacks attached to this filter can modify the code
			 * that is passed to eval(). Only grant sndp_manage_snippets to
			 * trusted users, and audit any plugin that hooks into this filter.
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

	/**
	 * Output queued inline CSS from library snippets in wp_head.
	 *
	 * @since 1.0.0
	 */
	public function output_queued_css() {
		if ( empty( $this->queued_css ) ) {
			return;
		}

		foreach ( $this->queued_css as $snippet_id => $css ) {
			echo '<style id="sndp-' . esc_attr( $snippet_id ) . '">' . "\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS output from curated library snippets.
			echo $css . "\n";
			echo "</style>\n";
		}
	}

	/**
	 * Output queued inline JS from library snippets in wp_footer.
	 *
	 * @since 1.0.0
	 */
	public function output_queued_js() {
		if ( empty( $this->queued_js ) ) {
			return;
		}

		foreach ( $this->queued_js as $snippet_id => $js ) {
			echo '<script id="sndp-' . esc_attr( $snippet_id ) . '">' . "\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS output from curated library snippets.
			echo $js . "\n";
			echo "</script>\n";
		}
	}

	/**
	 * Output queued inline HTML from library snippets in wp_footer.
	 *
	 * @since 1.0.0
	 */
	public function output_queued_html() {
		if ( empty( $this->queued_html ) ) {
			return;
		}

		foreach ( $this->queued_html as $snippet_id => $html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML output from curated library snippets.
			echo $html . "\n";
		}
	}

	/**
	 * Output global header scripts (wp_head).
	 *
	 * @since 1.0.0
	 */
	/**
	 * Get global scripts option (cached per request).
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_global_scripts() {
		if ( null === $this->global_scripts_cache ) {
			$this->global_scripts_cache = get_option( 'sndp_global_scripts', array() );
		}
		return $this->global_scripts_cache;
	}

	public function output_global_header_scripts() {
		if ( $this->snippets->is_safe_mode() ) {
			return;
		}
		$scripts = $this->get_global_scripts();
		if ( ! empty( $scripts['header'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw script output configured by admin.
			echo $scripts['header'] . "\n";
		}
	}

	/**
	 * Output global body open scripts (wp_body_open).
	 *
	 * @since 1.0.0
	 */
	public function output_global_body_scripts() {
		if ( $this->snippets->is_safe_mode() ) {
			return;
		}
		$scripts = $this->get_global_scripts();
		if ( ! empty( $scripts['body_open'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw script output configured by admin.
			echo $scripts['body_open'] . "\n";
		}
	}

	/**
	 * Output global footer scripts (wp_footer).
	 *
	 * @since 1.0.0
	 */
	public function output_global_footer_scripts() {
		if ( $this->snippets->is_safe_mode() ) {
			return;
		}
		$scripts = $this->get_global_scripts();
		if ( ! empty( $scripts['footer'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw script output configured by admin.
			echo $scripts['footer'] . "\n";
		}
	}
}
