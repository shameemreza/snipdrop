<?php
/**
 * Compatibility checker class.
 *
 * Validates snippet requirements against the current environment
 * before enabling, preventing runtime failures.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Compatibility class.
 *
 * Checks PHP version, WordPress version, WooCommerce version,
 * and plugin dependencies for both library and custom snippets.
 *
 * @since 1.0.0
 */
class SNDP_Compatibility {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Compatibility
	 */
	private static $instance = null;

	/**
	 * Cached environment info for the current request.
	 *
	 * @var array|null
	 */
	private $environment_cache = null;

	/**
	 * PHP functions mapped to the minimum version that introduced them.
	 *
	 * @var array
	 */
	private static $php_version_functions = array(
		'str_contains'        => '8.0',
		'str_starts_with'     => '8.0',
		'str_ends_with'       => '8.0',
		'fdiv'                => '8.0',
		'get_debug_type'      => '8.0',
		'get_resource_id'     => '8.0',
		'preg_last_error_msg' => '8.0',
		'array_is_list'       => '8.1',
		'enum_exists'         => '8.1',
		'fiber_get_return'    => '8.1',
		'array_any'           => '8.2',
		'array_all'           => '8.2',
		'json_validate'       => '8.3',
		'mb_str_pad'          => '8.3',
		'array_find'          => '8.4',
		'array_find_key'      => '8.4',
	);

	/**
	 * PHP syntax features mapped to minimum version.
	 * Patterns are checked via token_get_all() or regex.
	 *
	 * @var array
	 */
	private static $php_version_syntax = array(
		'match'    => '8.0',
		'enum'     => '8.1',
		'readonly' => '8.1',
		'Fiber'    => '8.1',
		'never'    => '8.1',
		'#\[.*?\]' => '8.0', // Attributes.
	);

	/**
	 * Plugin-dependent function prefixes and class names.
	 *
	 * @var array
	 */
	private static $plugin_function_map = array(
		'woocommerce'            => array(
			'functions' => array( 'wc_', 'woocommerce_', 'WC(' ),
			'classes'   => array( 'WC_', 'WooCommerce' ),
		),
		'wordpress-seo'          => array(
			'functions' => array( 'wpseo_', 'yoast_' ),
			'classes'   => array( 'WPSEO_', 'Yoast_' ),
		),
		'elementor'              => array(
			'functions' => array( 'elementor_' ),
			'classes'   => array( 'Elementor\\', '\Elementor\\' ),
		),
		'wpforms'                => array(
			'functions' => array( 'wpforms_', 'wpforms(' ),
			'classes'   => array( 'WPForms' ),
		),
		'contact-form-7'         => array(
			'functions' => array( 'wpcf7_' ),
			'classes'   => array( 'WPCF7_' ),
		),
		'advanced-custom-fields' => array(
			'functions' => array( 'get_field(', 'the_field(', 'have_rows(', 'get_sub_field(' ),
			'classes'   => array( 'ACF' ),
		),
		'jetpack'                => array(
			'functions' => array( 'jetpack_' ),
			'classes'   => array( 'Jetpack' ),
		),
	);

	/**
	 * Known plugin slug to class/constant mapping for activation checks.
	 *
	 * @var array
	 */
	private static $plugin_detection = array(
		'woocommerce'                 => array( 'class' => 'WooCommerce' ),
		'woocommerce-subscriptions'   => array( 'class' => 'WC_Subscriptions' ),
		'woocommerce-bookings'        => array( 'class' => 'WC_Bookings' ),
		'woocommerce-product-addons'  => array( 'class' => 'WC_Product_Addons' ),
		'woocommerce-product-bundles' => array( 'class' => 'WC_Bundles' ),
		'elementor'                   => array( 'constant' => 'ELEMENTOR_VERSION' ),
		'wpforms'                     => array( 'constant' => 'WPFORMS_VERSION' ),
		'wordpress-seo'               => array( 'constant' => 'WPSEO_VERSION' ),
		'contact-form-7'              => array( 'constant' => 'WPCF7_VERSION' ),
		'jetpack'                     => array( 'constant' => 'JETPACK__VERSION' ),
		'advanced-custom-fields'      => array( 'class' => 'ACF' ),
	);

	/**
	 * Compatible status constant.
	 *
	 * @var string
	 */
	const STATUS_COMPATIBLE = 'compatible';

	/**
	 * Warning status constant.
	 *
	 * @var string
	 */
	const STATUS_WARNING = 'warning';

	/**
	 * Incompatible status constant.
	 *
	 * @var string
	 */
	const STATUS_INCOMPATIBLE = 'incompatible';

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 * @return SNDP_Compatibility
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
		// No initialization needed.
	}

	/**
	 * Check a library snippet's compatibility with the current environment.
	 *
	 * @since 1.0.0
	 * @param array $snippet Full snippet data (must include 'requires' key).
	 * @return array {
	 *     @type string $status       One of 'compatible', 'warning', 'incompatible'.
	 *     @type array  $issues       Array of human-readable issue strings.
	 *     @type array  $requirements Formatted requirement labels for display.
	 * }
	 */
	public function check_snippet( $snippet ) {
		$result = array(
			'status'       => self::STATUS_COMPATIBLE,
			'issues'       => array(),
			'requirements' => $this->format_requirements( $snippet ),
		);

		if ( empty( $snippet['requires'] ) || ! is_array( $snippet['requires'] ) ) {
			return $result;
		}

		$requires = $snippet['requires'];
		$env      = $this->get_environment();

		// Check PHP version.
		if ( ! empty( $requires['php_version'] ) ) {
			if ( version_compare( $env['php_version'], $requires['php_version'], '<' ) ) {
				$result['issues'][] = sprintf(
					/* translators: 1: Required PHP version, 2: Current PHP version */
					__( 'Requires PHP %1$s+ (you have %2$s)', 'snipdrop' ),
					$requires['php_version'],
					$env['php_version']
				);
				$result['status'] = self::STATUS_INCOMPATIBLE;
			}
		}

		// Check WordPress version.
		if ( ! empty( $requires['wp_version'] ) ) {
			if ( version_compare( $env['wp_version'], $requires['wp_version'], '<' ) ) {
				$result['issues'][] = sprintf(
					/* translators: 1: Required WP version, 2: Current WP version */
					__( 'Requires WordPress %1$s+ (you have %2$s)', 'snipdrop' ),
					$requires['wp_version'],
					$env['wp_version']
				);
				$result['status'] = self::STATUS_INCOMPATIBLE;
			}
		}

		// Check WooCommerce version.
		if ( ! empty( $requires['wc_version'] ) ) {
			if ( empty( $env['wc_version'] ) ) {
				$result['issues'][] = sprintf(
					/* translators: %s: Required WooCommerce version */
					__( 'Requires WooCommerce %s+ (not installed)', 'snipdrop' ),
					$requires['wc_version']
				);
				$result['status'] = self::STATUS_INCOMPATIBLE;
			} elseif ( version_compare( $env['wc_version'], $requires['wc_version'], '<' ) ) {
				$result['issues'][] = sprintf(
					/* translators: 1: Required WC version, 2: Current WC version */
					__( 'Requires WooCommerce %1$s+ (you have %2$s)', 'snipdrop' ),
					$requires['wc_version'],
					$env['wc_version']
				);
				$result['status'] = self::STATUS_INCOMPATIBLE;
			}
		}

		// Check required plugins.
		if ( ! empty( $requires['plugins'] ) && is_array( $requires['plugins'] ) ) {
			foreach ( $requires['plugins'] as $plugin_slug ) {
				if ( ! $this->is_plugin_active( $plugin_slug ) ) {
					$result['issues'][] = sprintf(
						/* translators: %s: Plugin name */
						__( 'Requires %s (not active)', 'snipdrop' ),
						$this->get_plugin_display_name( $plugin_slug )
					);
					if ( self::STATUS_COMPATIBLE === $result['status'] ) {
						$result['status'] = self::STATUS_INCOMPATIBLE;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Analyze custom snippet code for compatibility issues.
	 *
	 * Performs static analysis without executing the code.
	 *
	 * @since 1.0.0
	 * @param string $code      The snippet code.
	 * @param string $code_type Code type: 'php', 'js', 'css', 'html'.
	 * @return array {
	 *     @type array $php_issues    PHP version compatibility warnings.
	 *     @type array $plugin_issues Plugin dependency warnings.
	 * }
	 */
	public function analyze_custom_code( $code, $code_type = 'php' ) {
		$result = array(
			'php_issues'    => array(),
			'plugin_issues' => array(),
		);

		if ( empty( $code ) || 'php' !== $code_type ) {
			return $result;
		}

		$env = $this->get_environment();

		// Check for PHP version-specific functions.
		foreach ( self::$php_version_functions as $function => $min_version ) {
			if ( version_compare( $env['php_version'], $min_version, '>=' ) ) {
				continue;
			}

			// Match function call pattern: function_name( — avoids partial matches.
			$pattern = '/\b' . preg_quote( $function, '/' ) . '\s*\(/';
			if ( preg_match( $pattern, $code ) ) {
				$result['php_issues'][] = sprintf(
					/* translators: 1: Function name, 2: Required PHP version, 3: Current PHP version */
					__( '%1$s() requires PHP %2$s+ (you have %3$s)', 'snipdrop' ),
					$function,
					$min_version,
					$env['php_version']
				);
			}
		}

		// Check for PHP version-specific syntax via token_get_all().
		$result['php_issues'] = array_merge(
			$result['php_issues'],
			$this->check_php_syntax_features( $code, $env['php_version'] )
		);

		// Check for plugin-dependent function/class usage.
		foreach ( self::$plugin_function_map as $plugin_slug => $patterns ) {
			if ( $this->is_plugin_active( $plugin_slug ) ) {
				continue;
			}

			$found = false;

			if ( ! empty( $patterns['functions'] ) ) {
				foreach ( $patterns['functions'] as $prefix ) {
					if ( false !== strpos( $code, $prefix ) ) {
						$found = true;
						break;
					}
				}
			}

			if ( ! $found && ! empty( $patterns['classes'] ) ) {
				foreach ( $patterns['classes'] as $class_prefix ) {
					if ( false !== strpos( $code, $class_prefix ) ) {
						$found = true;
						break;
					}
				}
			}

			if ( $found ) {
				$result['plugin_issues'][] = sprintf(
					/* translators: %s: Plugin name */
					__( 'This code appears to use %s functions, but the plugin is not active', 'snipdrop' ),
					$this->get_plugin_display_name( $plugin_slug )
				);
			}
		}

		return $result;
	}

	/**
	 * Check for PHP syntax features that require specific versions.
	 *
	 * Uses token_get_all() for reliable detection of language constructs.
	 *
	 * @since 1.0.0
	 * @param string $code        PHP code to analyze.
	 * @param string $php_version Current PHP version.
	 * @return array Array of warning strings.
	 */
	private function check_php_syntax_features( $code, $php_version ) {
		$warnings = array();

		// Ensure code has opening PHP tag for tokenizer.
		$tokenize_code = $code;
		if ( 0 !== strpos( trim( $code ), '<?php' ) && 0 !== strpos( trim( $code ), '<?' ) ) {
			$tokenize_code = '<?php ' . $code;
		}

		// Suppress errors from token_get_all on intentionally partial/invalid code.
		$tokens = @token_get_all( $tokenize_code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! is_array( $tokens ) ) {
			return $warnings;
		}

		foreach ( $tokens as $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}

			$token_id   = $token[0];
			$token_text = $token[1];

			// PHP 8.0: match expression.
			if ( defined( 'T_MATCH' ) && T_MATCH === $token_id && version_compare( $php_version, '8.0', '<' ) ) {
				$warnings[] = sprintf(
					/* translators: %s: Current PHP version */
					__( '"match" expression requires PHP 8.0+ (you have %s)', 'snipdrop' ),
					$php_version
				);
			}

			// PHP 8.1: enum keyword.
			if ( defined( 'T_ENUM' ) && T_ENUM === $token_id && version_compare( $php_version, '8.1', '<' ) ) {
				$warnings[] = sprintf(
					/* translators: %s: Current PHP version */
					__( '"enum" keyword requires PHP 8.1+ (you have %s)', 'snipdrop' ),
					$php_version
				);
			}

			// PHP 8.1: readonly keyword.
			if ( defined( 'T_READONLY' ) && T_READONLY === $token_id && version_compare( $php_version, '8.1', '<' ) ) {
				$warnings[] = sprintf(
					/* translators: %s: Current PHP version */
					__( '"readonly" properties require PHP 8.1+ (you have %s)', 'snipdrop' ),
					$php_version
				);
			}

			// PHP 8.0: named arguments (identifier followed by colon in function call context).
			// Detected via T_STRING followed by ':' that isn't a label or ternary.
			// This is a heuristic — not 100% reliable, so we skip it to avoid false positives.

			// PHP 8.0: Attributes.
			if ( defined( 'T_ATTRIBUTE' ) && T_ATTRIBUTE === $token_id && version_compare( $php_version, '8.0', '<' ) ) {
				$warnings[] = sprintf(
					/* translators: %s: Current PHP version */
					__( 'PHP Attributes require PHP 8.0+ (you have %s)', 'snipdrop' ),
					$php_version
				);
			}

			// PHP 8.0: nullsafe operator.
			if ( T_STRING === $token_id && '?->' === $token_text && version_compare( $php_version, '8.0', '<' ) ) {
				$warnings[] = sprintf(
					/* translators: %s: Current PHP version */
					__( 'Nullsafe operator (?->) requires PHP 8.0+ (you have %s)', 'snipdrop' ),
					$php_version
				);
			}
		}

		// Fallback regex for nullsafe operator (tokenizer may not capture it on older PHP).
		if ( version_compare( $php_version, '8.0', '<' ) && preg_match( '/\?->/', $code ) ) {
			$already_warned = false;
			foreach ( $warnings as $w ) {
				if ( false !== strpos( $w, '?->' ) ) {
					$already_warned = true;
					break;
				}
			}
			if ( ! $already_warned ) {
				$warnings[] = sprintf(
					/* translators: %s: Current PHP version */
					__( 'Nullsafe operator (?->) requires PHP 8.0+ (you have %s)', 'snipdrop' ),
					$php_version
				);
			}
		}

		return array_unique( $warnings );
	}

	/**
	 * Get current environment information.
	 *
	 * Cached per request to avoid redundant lookups.
	 *
	 * @since 1.0.0
	 * @return array {
	 *     @type string      $php_version Current PHP version.
	 *     @type string      $wp_version  Current WordPress version.
	 *     @type string|null $wc_version  Current WooCommerce version, or null.
	 * }
	 */
	public function get_environment() {
		if ( null !== $this->environment_cache ) {
			return $this->environment_cache;
		}

		global $wp_version;

		$wc_version = null;
		if ( defined( 'WC_VERSION' ) ) {
			$wc_version = WC_VERSION;
		} elseif ( class_exists( 'WooCommerce' ) && isset( WooCommerce::instance()->version ) ) {
			$wc_version = WooCommerce::instance()->version;
		}

		$this->environment_cache = array(
			'php_version' => PHP_VERSION,
			'wp_version'  => $wp_version,
			'wc_version'  => $wc_version,
		);

		return $this->environment_cache;
	}

	/**
	 * Check if a plugin is active by slug.
	 *
	 * Uses class/constant detection for known plugins, falls back
	 * to WordPress is_plugin_active() for unknown slugs.
	 *
	 * @since 1.0.0
	 * @param string $plugin_slug Plugin slug (e.g., 'woocommerce').
	 * @return bool
	 */
	public function is_plugin_active( $plugin_slug ) {
		$plugin_slug = strtolower( trim( $plugin_slug ) );

		// Check known plugins via class or constant detection.
		if ( isset( self::$plugin_detection[ $plugin_slug ] ) ) {
			$check = self::$plugin_detection[ $plugin_slug ];

			if ( isset( $check['class'] ) ) {
				return class_exists( $check['class'] );
			}

			if ( isset( $check['constant'] ) ) {
				return defined( $check['constant'] );
			}
		}

		// Handle Yoast alias.
		if ( 'yoast' === $plugin_slug ) {
			return defined( 'WPSEO_VERSION' );
		}

		// Fallback: WordPress plugin API.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_slug . '/' . $plugin_slug . '.php' );
	}

	/**
	 * Format snippet requirements into displayable labels.
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data with 'requires' key.
	 * @return array Array of label strings (e.g., "WooCommerce 8.0+", "PHP 7.4+").
	 */
	public function format_requirements( $snippet ) {
		$labels = array();

		if ( empty( $snippet['requires'] ) || ! is_array( $snippet['requires'] ) ) {
			return $labels;
		}

		$requires = $snippet['requires'];

		// Plugin requirements.
		if ( ! empty( $requires['plugins'] ) && is_array( $requires['plugins'] ) ) {
			foreach ( $requires['plugins'] as $plugin_slug ) {
				$name = $this->get_plugin_display_name( $plugin_slug );

				// Append version if available (e.g., wc_version for woocommerce).
				if ( 'woocommerce' === strtolower( $plugin_slug ) && ! empty( $requires['wc_version'] ) ) {
					$name .= ' ' . $requires['wc_version'] . '+';
				}

				$labels[] = $name;
			}
		}

		// PHP version (only show if above the plugin minimum 7.4).
		if ( ! empty( $requires['php_version'] ) && version_compare( $requires['php_version'], '7.4', '>' ) ) {
			$labels[] = 'PHP ' . $requires['php_version'] . '+';
		}

		// WP version (only show if above the plugin minimum 6.0).
		if ( ! empty( $requires['wp_version'] ) && version_compare( $requires['wp_version'], '6.0', '>' ) ) {
			$labels[] = 'WordPress ' . $requires['wp_version'] . '+';
		}

		return $labels;
	}

	/**
	 * Get a human-readable display name for a plugin slug.
	 *
	 * @since 1.0.0
	 * @param string $slug Plugin slug.
	 * @return string Display name.
	 */
	private function get_plugin_display_name( $slug ) {
		$known_names = array(
			'woocommerce'                 => 'WooCommerce',
			'woocommerce-subscriptions'   => 'WooCommerce Subscriptions',
			'woocommerce-bookings'        => 'WooCommerce Bookings',
			'woocommerce-product-addons'  => 'WooCommerce Product Add-Ons',
			'woocommerce-product-bundles' => 'WooCommerce Product Bundles',
			'elementor'                   => 'Elementor',
			'wpforms'                     => 'WPForms',
			'wordpress-seo'               => 'Yoast SEO',
			'yoast'                       => 'Yoast SEO',
			'contact-form-7'              => 'Contact Form 7',
			'jetpack'                     => 'Jetpack',
			'advanced-custom-fields'      => 'Advanced Custom Fields',
		);

		$slug = strtolower( trim( $slug ) );

		if ( isset( $known_names[ $slug ] ) ) {
			return $known_names[ $slug ];
		}

		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * Detect performance weight of custom snippet code.
	 *
	 * Classifies code as lightweight, moderate, or heavy based
	 * on detected patterns. CSS/HTML/JS snippets are always lightweight.
	 *
	 * @since 1.0.0
	 * @param string $code      The snippet code.
	 * @param string $code_type Code type: 'php', 'js', 'css', 'html'.
	 * @return string One of 'lightweight', 'moderate', 'heavy'.
	 */
	public function detect_weight( $code, $code_type = 'php' ) {
		// Non-PHP code is always lightweight (client-side execution).
		if ( 'php' !== $code_type || empty( $code ) ) {
			return 'lightweight';
		}

		// Heavy patterns — DB queries, HTTP calls, file operations.
		$heavy_patterns = array(
			'$wpdb->',
			'wp_remote_get',
			'wp_remote_post',
			'wp_remote_request',
			'wp_remote_head',
			'wp_safe_remote_get',
			'wp_safe_remote_post',
			'file_get_contents',
			'file_put_contents',
			'curl_init',
			'curl_exec',
			'new WP_Query',
			'new WP_User_Query',
			'new WC_Order_Query',
			'get_posts(',
			'wp_delete_attachment',
			'TRUNCATE',
			'DELETE FROM',
			'INSERT INTO',
			'UPDATE ',
		);

		foreach ( $heavy_patterns as $pattern ) {
			if ( false !== stripos( $code, $pattern ) ) {
				return 'heavy';
			}
		}

		// Moderate patterns — frequent hooks, iteration, content modification.
		$moderate_hooks = array(
			'the_content',
			'woocommerce_get_price_html',
			'woocommerce_product_is_visible',
			'woocommerce_before_calculate_totals',
			'woocommerce_add_to_cart_validation',
			'template_redirect',
			'wp_loaded',
			'woocommerce_checkout_fields',
			'posts_where',
			'posts_join',
			'pre_get_posts',
		);

		foreach ( $moderate_hooks as $hook ) {
			if ( false !== strpos( $code, "'" . $hook . "'" ) || false !== strpos( $code, '"' . $hook . '"' ) ) {
				return 'moderate';
			}
		}

		// Moderate: loops combined with WC/WP API calls.
		$has_loop = preg_match( '/\b(foreach|for|while)\s*\(/', $code );
		$has_api  = preg_match( '/\b(get_post_meta|update_post_meta|wc_get_|get_option|update_option)\s*\(/', $code );
		if ( $has_loop && $has_api ) {
			return 'moderate';
		}

		return 'lightweight';
	}

	/**
	 * Batch-check compatibility for multiple snippets.
	 *
	 * Efficient for the library page where all cards load at once.
	 * Environment data is cached so it's only fetched once.
	 *
	 * @since 1.0.0
	 * @param array $snippets Array of snippet data arrays.
	 * @return array Keyed by snippet ID, each value is a check_snippet() result.
	 */
	public function check_snippets_batch( $snippets ) {
		$results = array();

		// Pre-warm the environment cache.
		$this->get_environment();

		foreach ( $snippets as $snippet ) {
			$id = isset( $snippet['id'] ) ? $snippet['id'] : '';
			if ( empty( $id ) ) {
				continue;
			}
			$results[ $id ] = $this->check_snippet( $snippet );
		}

		return $results;
	}
}
