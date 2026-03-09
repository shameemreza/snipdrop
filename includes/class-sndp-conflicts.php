<?php
/**
 * Snippet conflict detection class.
 *
 * Detects when two or more active snippets hook into the same
 * WordPress/WooCommerce hook, and warns the user before enabling.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Conflicts class.
 *
 * Builds a hook map from all active snippets and detects
 * potential conflicts between them.
 *
 * @since 1.0.0
 */
class SNDP_Conflicts {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Conflicts
	 */
	private static $instance = null;

	/**
	 * Cached hook map for the current request.
	 *
	 * @var array|null
	 */
	private $hook_map_cache = null;

	/**
	 * Hooks where conflicts almost always cause problems.
	 *
	 * @var array
	 */
	private static $high_risk_hooks = array(
		'woocommerce_checkout_fields',
		'woocommerce_order_button_text',
		'woocommerce_get_price_html',
		'woocommerce_is_purchasable',
		'woocommerce_add_to_cart_validation',
		'woocommerce_payment_complete_order_status',
		'login_redirect',
		'wp_mail',
		'the_content',
		'template_redirect',
		'woocommerce_before_calculate_totals',
		'woocommerce_product_is_visible',
		'admin_footer_text',
	);

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 * @return SNDP_Conflicts
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
	 * Build a map of hook_name → snippet info from all active snippets.
	 *
	 * Reads hooks from library snippets (via `hooks` array) and custom
	 * snippets (via regex parsing of code).
	 *
	 * @since 1.0.0
	 * @return array Keyed by hook name, each value is an array of
	 *               [ 'id' => string, 'title' => string, 'source' => 'library'|'custom' ].
	 */
	public function get_hook_map() {
		if ( null !== $this->hook_map_cache ) {
			return $this->hook_map_cache;
		}

		$cached = get_transient( 'sndp_conflict_hook_map' );
		if ( is_array( $cached ) ) {
			$this->hook_map_cache = $cached;
			return $cached;
		}

		$map = array();

		$this->build_library_hook_map( $map );
		$this->build_custom_hook_map( $map );

		$this->hook_map_cache = $map;
		set_transient( 'sndp_conflict_hook_map', $map, HOUR_IN_SECONDS );
		return $map;
	}

	/**
	 * Invalidate the cached conflict hook map.
	 *
	 * Call when snippets are enabled, disabled, saved, or deleted.
	 *
	 * @since 1.0.0
	 */
	public function invalidate_cache() {
		$this->hook_map_cache = null;
		delete_transient( 'sndp_conflict_hook_map' );
	}

	/**
	 * Add library snippet hooks to the map.
	 *
	 * @since 1.0.0
	 * @param array $map Reference to the hook map being built.
	 */
	private function build_library_hook_map( &$map ) {
		$library  = SNDP_Library::instance();
		$snippets = SNDP_Snippets::instance();
		$enabled  = $snippets->get_enabled_snippets();

		if ( empty( $enabled ) ) {
			return;
		}

		foreach ( $enabled as $snippet_id ) {
			$snippet = $library->get_snippet( $snippet_id );

			if ( is_wp_error( $snippet ) ) {
				continue;
			}

			$title = isset( $snippet['title'] ) ? $snippet['title'] : $snippet_id;
			$hooks = $this->extract_library_hooks( $snippet );

			foreach ( $hooks as $hook ) {
				$hook_name = $hook['name'];
				if ( ! isset( $map[ $hook_name ] ) ) {
					$map[ $hook_name ] = array();
				}
				$map[ $hook_name ][] = array(
					'id'     => $snippet_id,
					'title'  => $title,
					'source' => 'library',
				);
			}
		}
	}

	/**
	 * Extract hooks from a library snippet's data.
	 *
	 * @since 1.0.0
	 * @param array $snippet Snippet data.
	 * @return array Array of hook objects [ 'name', 'type', 'priority' ].
	 */
	private function extract_library_hooks( $snippet ) {
		if ( ! empty( $snippet['hooks'] ) && is_array( $snippet['hooks'] ) ) {
			return $snippet['hooks'];
		}

		return array();
	}

	/**
	 * Add custom snippet hooks to the map by parsing their code.
	 *
	 * @since 1.0.0
	 * @param array $map Reference to the hook map being built.
	 */
	private function build_custom_hook_map( &$map ) {
		$custom_snippets = SNDP_Custom_Snippets::instance();
		$all_custom      = $custom_snippets->get_all();

		foreach ( $all_custom as $snippet ) {
			if ( empty( $snippet['status'] ) || 'active' !== $snippet['status'] ) {
				continue;
			}

			if ( empty( $snippet['code'] ) || 'php' !== ( $snippet['code_type'] ?? 'php' ) ) {
				continue;
			}

			$snippet_id = isset( $snippet['id'] ) ? $snippet['id'] : '';
			$title      = isset( $snippet['title'] ) ? $snippet['title'] : $snippet_id;
			$hooks      = $this->parse_hooks_from_code( $snippet['code'] );

			foreach ( $hooks as $hook_name ) {
				if ( ! isset( $map[ $hook_name ] ) ) {
					$map[ $hook_name ] = array();
				}
				$map[ $hook_name ][] = array(
					'id'     => $snippet_id,
					'title'  => $title,
					'source' => 'custom',
				);
			}
		}
	}

	/**
	 * Parse PHP code to extract hook names from add_action/add_filter calls.
	 *
	 * @since 1.0.0
	 * @param string $code PHP code to parse.
	 * @return array Array of unique hook name strings.
	 */
	public function parse_hooks_from_code( $code ) {
		$hooks = array();

		// Match add_action( 'hook_name' and add_filter( 'hook_name' patterns.
		// Captures both single and double quoted hook names.
		$pattern = '/\b(?:add_action|add_filter)\s*\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/';

		if ( preg_match_all( $pattern, $code, $matches ) ) {
			$hooks = array_unique( $matches[1] );
		}

		return array_values( $hooks );
	}

	/**
	 * Check if enabling a specific snippet would create conflicts
	 * with currently active snippets.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id   The snippet to check.
	 * @param array  $snippet_data Full snippet data (for library snippets)
	 *                             or [ 'code' => '...', 'code_type' => 'php' ] for custom.
	 * @param string $source       'library' or 'custom'.
	 * @return array {
	 *     @type bool   $has_conflicts Whether any conflicts were found.
	 *     @type array  $conflicts     Array of conflict details.
	 * }
	 */
	public function check_conflicts( $snippet_id, $snippet_data, $source = 'library' ) {
		$result = array(
			'has_conflicts' => false,
			'conflicts'     => array(),
		);

		// Get hooks from the snippet being checked.
		if ( 'library' === $source ) {
			$snippet_hooks = $this->extract_library_hooks( $snippet_data );
			$hook_names    = wp_list_pluck( $snippet_hooks, 'name' );
		} else {
			$code      = isset( $snippet_data['code'] ) ? $snippet_data['code'] : '';
			$code_type = isset( $snippet_data['code_type'] ) ? $snippet_data['code_type'] : 'php';

			if ( 'php' !== $code_type || empty( $code ) ) {
				return $result;
			}

			$hook_names = $this->parse_hooks_from_code( $code );
		}

		if ( empty( $hook_names ) ) {
			return $result;
		}

		// Build hook map of currently active snippets.
		$hook_map = $this->get_hook_map();

		foreach ( $hook_names as $hook_name ) {
			if ( ! isset( $hook_map[ $hook_name ] ) ) {
				continue;
			}

			// Filter out the snippet itself (in case it's already active and being re-checked).
			$conflicting = array();
			foreach ( $hook_map[ $hook_name ] as $entry ) {
				if ( $entry['id'] !== $snippet_id ) {
					$conflicting[] = $entry;
				}
			}

			if ( empty( $conflicting ) ) {
				continue;
			}

			$is_high_risk = in_array( $hook_name, self::$high_risk_hooks, true );

			foreach ( $conflicting as $conflict ) {
				$result['has_conflicts'] = true;
				$result['conflicts'][]   = array(
					'hook'          => $hook_name,
					'snippet_id'    => $conflict['id'],
					'snippet_title' => $conflict['title'],
					'source'        => $conflict['source'],
					'high_risk'     => $is_high_risk,
				);
			}
		}

		return $result;
	}

	/**
	 * Get all detected conflicts for currently active snippets.
	 *
	 * Returns hooks that have more than one active snippet attached.
	 *
	 * @since 1.0.0
	 * @return array Array of conflict groups, keyed by hook name.
	 */
	public function get_all_conflicts() {
		$hook_map  = $this->get_hook_map();
		$conflicts = array();

		foreach ( $hook_map as $hook_name => $entries ) {
			if ( count( $entries ) < 2 ) {
				continue;
			}

			$conflicts[ $hook_name ] = array(
				'hook'      => $hook_name,
				'high_risk' => in_array( $hook_name, self::$high_risk_hooks, true ),
				'snippets'  => $entries,
			);
		}

		return $conflicts;
	}

	/**
	 * Build a conflict map keyed by snippet ID.
	 *
	 * For each snippet that has at least one conflict, returns the
	 * conflict details. Used for batch display on library/custom pages.
	 *
	 * @since 1.0.0
	 * @return array Keyed by snippet ID. Each value:
	 *               [ 'conflicts' => array, 'has_high_risk' => bool ]
	 */
	public function get_conflicts_by_snippet() {
		$all_conflicts = $this->get_all_conflicts();
		$by_snippet    = array();

		foreach ( $all_conflicts as $hook_name => $group ) {
			foreach ( $group['snippets'] as $entry ) {
				$sid = $entry['id'];

				if ( ! isset( $by_snippet[ $sid ] ) ) {
					$by_snippet[ $sid ] = array(
						'conflicts'     => array(),
						'has_high_risk' => false,
					);
				}

				// Add the other snippets as conflicts for this one.
				foreach ( $group['snippets'] as $other ) {
					if ( $other['id'] === $sid ) {
						continue;
					}

					$by_snippet[ $sid ]['conflicts'][] = array(
						'hook'          => $hook_name,
						'snippet_id'    => $other['id'],
						'snippet_title' => $other['title'],
						'source'        => $other['source'],
						'high_risk'     => $group['high_risk'],
					);

					if ( $group['high_risk'] ) {
						$by_snippet[ $sid ]['has_high_risk'] = true;
					}
				}
			}
		}

		return $by_snippet;
	}

	/**
	 * Clear the cached hook map (e.g., after a snippet is toggled).
	 *
	 * @since 1.0.0
	 */
	public function clear_cache() {
		$this->hook_map_cache = null;
	}
}
