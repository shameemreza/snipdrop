<?php
/**
 * Plugin importer — import snippets from 7 competing plugins.
 *
 * Supported sources:
 *  - WPCode (free & premium)
 *  - Code Snippets (free)
 *  - Code Snippets Pro
 *  - Woody Code Snippets
 *  - Simple Custom CSS and JS
 *  - Header Footer Code Manager
 *  - Post Snippets
 *
 * @package SnipDrop
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SNDP_Importer
 */
class SNDP_Importer {

	/**
	 * Singleton instance.
	 *
	 * @var SNDP_Importer|null
	 */
	private static $instance = null;

	/**
	 * Registry of supported source plugins.
	 *
	 * @var array
	 */
	private $sources = array();

	/**
	 * Get singleton instance.
	 *
	 * @return SNDP_Importer
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — register sources and AJAX handlers.
	 */
	private function __construct() {
		$this->register_sources();

		add_action( 'wp_ajax_sndp_get_importers', array( $this, 'ajax_get_importers' ) );
		add_action( 'wp_ajax_sndp_get_source_snippets', array( $this, 'ajax_get_source_snippets' ) );
		add_action( 'wp_ajax_sndp_import_from_plugin', array( $this, 'ajax_import_from_plugin' ) );
	}

	/**
	 * Register all supported source plugins.
	 */
	private function register_sources() {
		$this->sources = array(
			'wpcode'                    => array(
				'name' => 'WPCode',
				'slug' => 'wpcode',
				'path' => 'insert-headers-and-footers/ihaf.php',
			),
			'wpcode-premium'            => array(
				'name' => 'WPCode Premium',
				'slug' => 'wpcode-premium',
				'path' => 'wpcode-premium/wpcode-premium.php',
			),
			'code-snippets'             => array(
				'name' => 'Code Snippets',
				'slug' => 'code-snippets',
				'path' => 'code-snippets/code-snippets.php',
			),
			'code-snippets-pro'         => array(
				'name' => 'Code Snippets Pro',
				'slug' => 'code-snippets-pro',
				'path' => 'code-snippets-pro/code-snippets.php',
			),
			'woody'                     => array(
				'name' => 'Woody Code Snippets',
				'slug' => 'woody',
				'path' => 'insert-php/insert_php.php',
			),
			'simple-custom-css-js'      => array(
				'name' => 'Simple Custom CSS and JS',
				'slug' => 'simple-custom-css-js',
				'path' => 'custom-css-js/custom-css-js.php',
			),
			'header-footer-code-manager' => array(
				'name' => 'Header Footer Code Manager',
				'slug' => 'header-footer-code-manager',
				'path' => 'header-footer-code-manager/99robots-header-footer-code-manager.php',
			),
			'post-snippets'             => array(
				'name' => 'Post Snippets',
				'slug' => 'post-snippets',
				'path' => 'post-snippets/post-snippets.php',
			),
		);
	}

	// ------------------------------------------------------------------
	// Detection helpers
	// ------------------------------------------------------------------

	/**
	 * Check if a source plugin is installed (file exists).
	 *
	 * @param string $slug Source slug.
	 * @return bool
	 */
	public function is_installed( $slug ) {
		if ( ! isset( $this->sources[ $slug ] ) ) {
			return false;
		}
		return file_exists( trailingslashit( WP_PLUGIN_DIR ) . $this->sources[ $slug ]['path'] );
	}

	/**
	 * Check if a source plugin is active.
	 *
	 * @param string $slug Source slug.
	 * @return bool
	 */
	public function is_active( $slug ) {
		if ( ! isset( $this->sources[ $slug ] ) ) {
			return false;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $this->sources[ $slug ]['path'] );
	}

	/**
	 * Get all importers with detection status.
	 *
	 * @return array
	 */
	public function get_available_importers() {
		$importers = array();

		foreach ( $this->sources as $slug => $source ) {
			// Skip WPCode Premium if the free version covers it (same CPT).
			if ( 'wpcode-premium' === $slug && $this->is_active( 'wpcode' ) ) {
				continue;
			}
			// Skip Code Snippets Pro if the free version covers it (same API).
			if ( 'code-snippets-pro' === $slug && $this->is_active( 'code-snippets' ) ) {
				continue;
			}

			$installed = $this->is_installed( $slug );
			$active    = $installed ? $this->is_active( $slug ) : false;

			// Only include if at least installed (data may still exist even if deactivated).
			if ( ! $installed ) {
				// For plugins that store data in custom tables/CPTs, check if data exists.
				$has_data = $this->has_data( $slug );
				if ( ! $has_data ) {
					continue;
				}
			}

			$importers[ $slug ] = array(
				'name'      => $source['name'],
				'slug'      => $slug,
				'installed' => $installed,
				'active'    => $active,
				'has_data'  => true,
			);
		}

		return $importers;
	}

	/**
	 * Check if a source plugin has importable data even if deactivated.
	 *
	 * @param string $slug Source slug.
	 * @return bool
	 */
	private function has_data( $slug ) {
		global $wpdb;

		switch ( $slug ) {
			case 'wpcode':
			case 'wpcode-premium':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wpcode'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $count > 0;

			case 'code-snippets':
			case 'code-snippets-pro':
				$table = $wpdb->prefix . 'snippets';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				if ( $exists !== $table ) {
					return false;
				}
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $count > 0;

			case 'woody':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wbcr-snippets'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $count > 0;

			case 'simple-custom-css-js':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'custom-css-js'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $count > 0;

			case 'header-footer-code-manager':
				$table = $wpdb->prefix . 'hfcm_scripts';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				if ( $exists !== $table ) {
					return false;
				}
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $count > 0;

			case 'post-snippets':
				$table = $wpdb->prefix . 'pspro_snippets';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				if ( $exists !== $table ) {
					return false;
				}
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $count > 0;

			default:
				return false;
		}
	}

	// ------------------------------------------------------------------
	// Snippet fetching — one method per source
	// ------------------------------------------------------------------

	/**
	 * Get snippets from a source plugin.
	 *
	 * @param string $slug Source slug.
	 * @return array Associative array: source_id => title.
	 */
	public function get_source_snippets( $slug ) {
		switch ( $slug ) {
			case 'wpcode':
			case 'wpcode-premium':
				return $this->get_wpcode_snippets();
			case 'code-snippets':
			case 'code-snippets-pro':
				return $this->get_code_snippets_snippets( $slug );
			case 'woody':
				return $this->get_woody_snippets();
			case 'simple-custom-css-js':
				return $this->get_simple_css_js_snippets();
			case 'header-footer-code-manager':
				return $this->get_hfcm_snippets();
			case 'post-snippets':
				return $this->get_post_snippets_snippets();
			default:
				return array();
		}
	}

	/**
	 * Get snippets from WPCode (free or premium). Reads CPT directly.
	 *
	 * @return array
	 */
	private function get_wpcode_snippets() {
		$posts = get_posts(
			array(
				'post_type'      => 'wpcode',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		$snippets = array();
		foreach ( $posts as $post ) {
			$label = $post->post_title ? $post->post_title : __( '(no title)', 'snipdrop' );
			if ( 'publish' !== $post->post_status ) {
				$label .= ' (' . $post->post_status . ')';
			}
			$snippets[ $post->ID ] = $label;
		}
		return $snippets;
	}

	/**
	 * Get snippets from Code Snippets (free/pro).
	 *
	 * @param string $slug 'code-snippets' or 'code-snippets-pro'.
	 * @return array
	 */
	private function get_code_snippets_snippets( $slug ) {
		// Try the API first (works when plugin is active).
		if ( function_exists( '\Code_Snippets\get_snippets' ) ) {
			$items    = \Code_Snippets\get_snippets();
			$snippets = array();
			foreach ( $items as $item ) {
				$name                    = $item->name ? $item->name : __( '(no title)', 'snipdrop' );
				$snippets[ $item->id ] = $name;
			}
			return $snippets;
		}

		// Fallback: read the table directly.
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT id, name FROM `{$table}`", ARRAY_A );

		$snippets = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$snippets[ $row['id'] ] = $row['name'] ? $row['name'] : __( '(no title)', 'snipdrop' );
			}
		}
		return $snippets;
	}

	/**
	 * Get snippets from Woody Code Snippets.
	 *
	 * @return array
	 */
	private function get_woody_snippets() {
		$posts = get_posts(
			array(
				'post_type'      => 'wbcr-snippets',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		$snippets = array();
		foreach ( $posts as $post ) {
			$snippets[ $post->ID ] = $post->post_title ? $post->post_title : __( '(no title)', 'snipdrop' );
		}
		return $snippets;
	}

	/**
	 * Get snippets from Simple Custom CSS and JS.
	 *
	 * @return array
	 */
	private function get_simple_css_js_snippets() {
		$posts = get_posts(
			array(
				'post_type'      => 'custom-css-js',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
			)
		);

		$snippets = array();
		foreach ( $posts as $post ) {
			$label = $post->post_title ? $post->post_title : __( '(no title)', 'snipdrop' );
			if ( 'publish' !== $post->post_status ) {
				$label .= ' (' . $post->post_status . ')';
			}
			$snippets[ $post->ID ] = $label;
		}
		return $snippets;
	}

	/**
	 * Get snippets from Header Footer Code Manager.
	 *
	 * @return array
	 */
	private function get_hfcm_snippets() {
		global $wpdb;
		$table = $wpdb->prefix . 'hfcm_scripts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT script_id, name FROM `{$table}`", ARRAY_A );

		$snippets = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$snippets[ $row['script_id'] ] = $row['name'] ? $row['name'] : __( '(no title)', 'snipdrop' );
			}
		}
		return $snippets;
	}

	/**
	 * Get snippets from Post Snippets.
	 *
	 * @return array
	 */
	private function get_post_snippets_snippets() {
		global $wpdb;
		$table = $wpdb->prefix . 'pspro_snippets';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT ID, snippet_title FROM `{$table}`", ARRAY_A );

		$snippets = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$snippets[ $row['ID'] ] = $row['snippet_title'] ? $row['snippet_title'] : __( '(no title)', 'snipdrop' );
			}
		}
		return $snippets;
	}

	// ------------------------------------------------------------------
	// Single snippet import — one mapper per source
	// ------------------------------------------------------------------

	/**
	 * Import a single snippet from a source plugin.
	 *
	 * @param string     $slug      Source slug.
	 * @param int|string $source_id Snippet ID in the source plugin.
	 * @return array|WP_Error Array with 'id' and 'title', or WP_Error.
	 */
	public function import_single( $slug, $source_id ) {
		switch ( $slug ) {
			case 'wpcode':
			case 'wpcode-premium':
				return $this->import_wpcode_snippet( $source_id );
			case 'code-snippets':
			case 'code-snippets-pro':
				return $this->import_code_snippets_snippet( $source_id );
			case 'woody':
				return $this->import_woody_snippet( $source_id );
			case 'simple-custom-css-js':
				return $this->import_simple_css_js_snippet( $source_id );
			case 'header-footer-code-manager':
				return $this->import_hfcm_snippet( $source_id );
			case 'post-snippets':
				return $this->import_post_snippets_snippet( $source_id );
			default:
				return new \WP_Error( 'unknown_source', __( 'Unknown import source.', 'snipdrop' ) );
		}
	}

	// ------------------------------------------------------------------
	// WPCode importer
	// ------------------------------------------------------------------

	/**
	 * Import a single WPCode snippet.
	 *
	 * @param int $source_id WPCode post ID.
	 * @return array|WP_Error
	 */
	private function import_wpcode_snippet( $source_id ) {
		$post = get_post( absint( $source_id ) );
		if ( ! $post || 'wpcode' !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Snippet not found.', 'snipdrop' ) );
		}

		$code_type = $this->get_wpcode_type( $post->ID );
		$location  = $this->map_wpcode_location( $post->ID );
		$tags      = $this->get_wpcode_tags( $post->ID );
		$tags[]    = 'imported-wpcode';

		$note     = get_post_meta( $post->ID, '_wpcode_note', true );
		$priority = absint( get_post_meta( $post->ID, '_wpcode_priority', true ) );
		if ( ! $priority ) {
			$priority = 10;
		}

		$snippet_data = array(
			'title'       => $post->post_title ? $post->post_title : __( 'Imported Snippet', 'snipdrop' ),
			'description' => $note ? sanitize_textarea_field( $note ) : '',
			'code'        => $post->post_content,
			'code_type'   => $code_type,
			'status'      => 'inactive',
			'location'    => $location,
			'hook'        => 'php' === $code_type ? 'init' : 'init',
			'priority'    => $priority,
			'tags'        => $tags,
			'source'      => 'import',
		);

		$conditional = $this->map_wpcode_conditional_rules( $post->ID );
		if ( ! empty( $conditional ) ) {
			$snippet_data['conditional_rules'] = $conditional;
		}

		return $this->save_imported( $snippet_data );
	}

	/**
	 * Resolve WPCode snippet code type from wpcode_type taxonomy.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_wpcode_type( $post_id ) {
		$terms = wp_get_post_terms( $post_id, 'wpcode_type', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 'php';
		}

		$map = array(
			'php'        => 'php',
			'html'       => 'html',
			'css'        => 'css',
			'javascript' => 'js',
			'js'         => 'js',
			'text'       => 'html',
			'universal'  => 'php',
		);

		return isset( $map[ $terms[0] ] ) ? $map[ $terms[0] ] : 'php';
	}

	/**
	 * Get tags from WPCode snippet.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_wpcode_tags( $post_id ) {
		$terms = wp_get_post_terms( $post_id, 'wpcode_tags', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		return $terms;
	}

	/**
	 * Map WPCode auto-insert location to SnipDrop location.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function map_wpcode_location( $post_id ) {
		$auto_insert = absint( get_post_meta( $post_id, '_wpcode_auto_insert', true ) );
		if ( ! $auto_insert ) {
			return 'everywhere';
		}

		$location_terms = wp_get_post_terms( $post_id, 'wpcode_location', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $location_terms ) || empty( $location_terms ) ) {
			return 'everywhere';
		}

		$loc = $location_terms[0];

		$map = array(
			'everywhere'       => 'everywhere',
			'frontend_only'    => 'frontend',
			'admin_only'       => 'admin',
			'site_wide_header' => 'site_header',
			'site_wide_footer' => 'site_footer',
			'site_wide_body'   => 'after_body',
			'before_content'   => 'before_content',
			'after_content'    => 'after_content',
			'before_paragraph' => 'before_paragraph',
			'after_paragraph'  => 'after_paragraph',
			'between_posts'    => 'everywhere',
			'before_excerpt'   => 'before_content',
			'after_excerpt'    => 'after_content',
			'admin_head'       => 'admin',
			'admin_footer'     => 'admin',
		);

		return isset( $map[ $loc ] ) ? $map[ $loc ] : 'everywhere';
	}

	/**
	 * Map WPCode conditional logic rules to SnipDrop format.
	 *
	 * @param int $post_id Post ID.
	 * @return array Empty if no rules.
	 */
	private function map_wpcode_conditional_rules( $post_id ) {
		$use_rules = get_post_meta( $post_id, '_wpcode_conditional_rules_enabled', true );
		if ( empty( $use_rules ) ) {
			return array();
		}

		$rules = get_post_meta( $post_id, '_wpcode_conditional_rules', true );
		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return array();
		}

		$show = isset( $rules['show'] ) ? $rules['show'] : 'show';

		$groups = array();
		if ( ! empty( $rules['groups'] ) && is_array( $rules['groups'] ) ) {
			foreach ( $rules['groups'] as $group ) {
				$mapped_group = array();
				if ( ! is_array( $group ) ) {
					continue;
				}
				foreach ( $group as $rule ) {
					if ( ! is_array( $rule ) || empty( $rule['type'] ) ) {
						continue;
					}
					$mapped_group[] = array(
						'type'     => sanitize_key( $rule['type'] ),
						'option'   => isset( $rule['option'] ) ? sanitize_key( $rule['option'] ) : '',
						'relation' => isset( $rule['relation'] ) ? sanitize_key( $rule['relation'] ) : '=',
						'value'    => isset( $rule['value'] ) ? $rule['value'] : '',
					);
				}
				if ( ! empty( $mapped_group ) ) {
					$groups[] = $mapped_group;
				}
			}
		}

		if ( empty( $groups ) ) {
			return array();
		}

		return array(
			'enabled' => true,
			'show'    => $show,
			'groups'  => $groups,
		);
	}

	// ------------------------------------------------------------------
	// Code Snippets importer
	// ------------------------------------------------------------------

	/**
	 * Import a single Code Snippets snippet.
	 *
	 * @param int $source_id Snippet ID in the snippets table.
	 * @return array|WP_Error
	 */
	private function import_code_snippets_snippet( $source_id ) {
		$snippet = null;

		if ( function_exists( '\Code_Snippets\get_snippets' ) ) {
			$results = \Code_Snippets\get_snippets( array( absint( $source_id ) ) );
			$snippet = ! empty( $results[0] ) ? $results[0] : null;
		}

		if ( ! $snippet ) {
			// Fallback: read from table directly.
			global $wpdb;
			$table   = $wpdb->prefix . 'snippets';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$snippet = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", absint( $source_id ) ) );
		}

		if ( ! $snippet ) {
			return new \WP_Error( 'not_found', __( 'Snippet not found.', 'snipdrop' ) );
		}

		$is_object = is_object( $snippet );
		$name      = $is_object ? ( $snippet->name ?? '' ) : ( $snippet['name'] ?? '' );
		$code      = $is_object ? ( $snippet->code ?? '' ) : ( $snippet['code'] ?? '' );
		$desc      = $is_object ? ( $snippet->desc ?? '' ) : ( $snippet['description'] ?? '' );
		$scope     = $is_object ? ( $snippet->scope ?? '' ) : ( $snippet['scope'] ?? '' );
		$priority  = $is_object ? ( $snippet->priority ?? 10 ) : ( $snippet['priority'] ?? 10 );
		$raw_tags  = $is_object ? ( $snippet->tags ?? array() ) : ( $snippet['tags'] ?? array() );

		$code_type = $this->map_code_snippets_type( $scope );
		$location  = $this->map_code_snippets_location( $scope, $code_type );

		$tags = is_array( $raw_tags ) ? $raw_tags : explode( ',', (string) $raw_tags );
		$tags = array_map( 'trim', array_filter( $tags ) );
		$tags[] = 'imported-code-snippets';

		$snippet_data = array(
			'title'       => $name ? $name : __( 'Imported Snippet', 'snipdrop' ),
			'description' => sanitize_textarea_field( $desc ),
			'code'        => $code,
			'code_type'   => $code_type,
			'status'      => 'inactive',
			'location'    => $location,
			'hook'        => 'php' === $code_type ? 'init' : 'init',
			'priority'    => absint( $priority ),
			'tags'        => $tags,
			'source'      => 'import',
		);

		return $this->save_imported( $snippet_data );
	}

	/**
	 * Map Code Snippets scope to SnipDrop code type.
	 *
	 * @param string $scope Code Snippets scope value.
	 * @return string
	 */
	private function map_code_snippets_type( $scope ) {
		if ( substr( $scope, -4 ) === '-css' ) {
			return 'css';
		}
		if ( substr( $scope, -3 ) === '-js' ) {
			return 'js';
		}
		if ( substr( $scope, -7 ) === 'content' ) {
			return 'html';
		}
		return 'php';
	}

	/**
	 * Map Code Snippets scope to SnipDrop location.
	 *
	 * @param string $scope     Scope value.
	 * @param string $code_type Resolved code type.
	 * @return string
	 */
	private function map_code_snippets_location( $scope, $code_type ) {
		if ( 'php' !== $code_type ) {
			return 'site_header';
		}

		switch ( $scope ) {
			case 'admin':
				return 'admin';
			case 'front-end':
				return 'frontend';
			default:
				return 'everywhere';
		}
	}

	// ------------------------------------------------------------------
	// Woody Code Snippets importer
	// ------------------------------------------------------------------

	/**
	 * Import a single Woody snippet.
	 *
	 * @param int $source_id Post ID.
	 * @return array|WP_Error
	 */
	private function import_woody_snippet( $source_id ) {
		$post = get_post( absint( $source_id ) );
		if ( ! $post || 'wbcr-snippets' !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Snippet not found.', 'snipdrop' ) );
		}

		$code      = '';
		$code_type = 'php';

		// Try WINP_Helper if available (plugin active).
		if ( class_exists( 'WINP_Helper' ) ) {
			$code      = \WINP_Helper::get_snippet_code( $post );
			$code_type = \WINP_Helper::get_snippet_type( $post->ID );
		} else {
			$code      = get_post_meta( $post->ID, '_winp_snippet_code', true );
			$type_meta = get_post_meta( $post->ID, '_winp_snippet_type', true );
			$type_map  = array(
				'php'  => 'php',
				'js'   => 'js',
				'css'  => 'css',
				'html' => 'html',
				'text' => 'html',
			);
			$code_type = isset( $type_map[ $type_meta ] ) ? $type_map[ $type_meta ] : 'php';
		}

		if ( empty( $code ) ) {
			$code = $post->post_content;
		}

		$desc = '';
		if ( class_exists( 'WINP_Helper' ) ) {
			$desc = \WINP_Helper::getMetaOption( $post->ID, 'snippet_description', '' );
		} else {
			$desc = get_post_meta( $post->ID, '_winp_snippet_description', true );
		}

		$location_raw = class_exists( 'WINP_Helper' )
			? \WINP_Helper::getMetaOption( $post->ID, 'snippet_location', '' )
			: get_post_meta( $post->ID, '_winp_snippet_location', true );

		$location_map = array(
			'header'  => 'site_header',
			'footer'  => 'site_footer',
			'content' => 'before_content',
		);
		$location     = isset( $location_map[ $location_raw ] ) ? $location_map[ $location_raw ] : 'everywhere';

		// Tags from taxonomy.
		$tags = array();
		if ( defined( 'WINP_SNIPPETS_TAXONOMY' ) ) {
			$tax_terms = wp_get_post_terms( $post->ID, WINP_SNIPPETS_TAXONOMY, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $tax_terms ) ) {
				$tags = $tax_terms;
			}
		}
		$tags[] = 'imported-woody';

		$priority = 10;
		if ( class_exists( 'WINP_Helper' ) ) {
			$priority = absint( \WINP_Helper::getMetaOption( $post->ID, 'snippet_priority', 10 ) );
		}

		$snippet_data = array(
			'title'       => $post->post_title ? $post->post_title : __( 'Imported Snippet', 'snipdrop' ),
			'description' => sanitize_textarea_field( $desc ),
			'code'        => $code,
			'code_type'   => $code_type,
			'status'      => 'inactive',
			'location'    => $location,
			'hook'        => 'php' === $code_type ? 'init' : 'init',
			'priority'    => $priority,
			'tags'        => $tags,
			'source'      => 'import',
		);

		return $this->save_imported( $snippet_data );
	}

	// ------------------------------------------------------------------
	// Simple Custom CSS and JS importer
	// ------------------------------------------------------------------

	/**
	 * Import a single Simple Custom CSS and JS snippet.
	 *
	 * @param int $source_id Post ID.
	 * @return array|WP_Error
	 */
	private function import_simple_css_js_snippet( $source_id ) {
		$post = get_post( absint( $source_id ) );
		if ( ! $post || 'custom-css-js' !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Snippet not found.', 'snipdrop' ) );
		}

		$options = get_post_meta( $post->ID, 'options', true );

		$code_type = 'css';
		if ( is_array( $options ) && isset( $options['language'] ) ) {
			$lang_map  = array(
				'js'   => 'js',
				'html' => 'html',
				'css'  => 'css',
			);
			$code_type = isset( $lang_map[ $options['language'] ] ) ? $lang_map[ $options['language'] ] : 'css';
		}

		$type_loc = is_array( $options ) && isset( $options['type'] ) ? $options['type'] : '';
		$location = 'site_header';
		if ( 'footer' === $type_loc ) {
			$location = 'site_footer';
		}

		// Handle admin-side placement.
		if ( is_array( $options ) && ! empty( $options['side'] ) ) {
			$sides = array_map( 'trim', explode( ',', $options['side'] ) );
			if ( in_array( 'admin', $sides, true ) && ! in_array( 'frontend', $sides, true ) ) {
				$location = 'admin';
			}
		}

		$priority = 10;
		if ( is_array( $options ) && isset( $options['priority'] ) ) {
			$priority = absint( $options['priority'] );
		}

		$snippet_data = array(
			'title'       => $post->post_title ? $post->post_title : __( 'Imported Snippet', 'snipdrop' ),
			'description' => '',
			'code'        => $post->post_content,
			'code_type'   => $code_type,
			'status'      => 'inactive',
			'location'    => $location,
			'hook'        => 'init',
			'priority'    => $priority,
			'tags'        => array( 'imported-simple-css-js' ),
			'source'      => 'import',
		);

		return $this->save_imported( $snippet_data );
	}

	// ------------------------------------------------------------------
	// Header Footer Code Manager importer
	// ------------------------------------------------------------------

	/**
	 * Import a single HFCM snippet.
	 *
	 * @param int $source_id Script ID in hfcm_scripts table.
	 * @return array|WP_Error
	 */
	private function import_hfcm_snippet( $source_id ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'hfcm_scripts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$snippet = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE script_id = %d", absint( $source_id ) ) );

		if ( ! $snippet ) {
			return new \WP_Error( 'not_found', __( 'Snippet not found.', 'snipdrop' ) );
		}

		$code = html_entity_decode( $snippet->snippet );

		$type_map  = array(
			'js'  => 'js',
			'css' => 'css',
		);
		$code_type = isset( $type_map[ $snippet->snippet_type ] ) ? $type_map[ $snippet->snippet_type ] : 'html';

		$loc_map  = array(
			'header' => 'site_header',
			'footer' => 'site_footer',
		);
		$location = isset( $loc_map[ $snippet->location ] ) ? $loc_map[ $snippet->location ] : 'site_header';

		$tags = array( 'imported-hfcm' );

		$snippet_data = array(
			'title'       => $snippet->name ? $snippet->name : __( 'Imported Snippet', 'snipdrop' ),
			'description' => '',
			'code'        => $code,
			'code_type'   => $code_type,
			'status'      => 'inactive',
			'location'    => $location,
			'hook'        => 'init',
			'priority'    => 10,
			'tags'        => $tags,
			'source'      => 'import',
		);

		// Map HFCM display targeting to conditional rules.
		$conditional = $this->map_hfcm_conditional_rules( $snippet );
		if ( ! empty( $conditional ) ) {
			$snippet_data['conditional_rules'] = $conditional;
		}

		return $this->save_imported( $snippet_data );
	}

	/**
	 * Map HFCM display_on targeting to SnipDrop conditional rules.
	 *
	 * @param object $snippet HFCM table row.
	 * @return array Empty if no rules.
	 */
	private function map_hfcm_conditional_rules( $snippet ) {
		$display_on = isset( $snippet->display_on ) ? $snippet->display_on : 'All';

		switch ( $display_on ) {
			case 's_pages':
				$pages = json_decode( $snippet->s_pages, true );
				if ( is_array( $pages ) && ! empty( $pages ) ) {
					return $this->build_conditional_rules( 'show', 'page', 'post_id', '=', array_map( 'absint', $pages ) );
				}
				break;

			case 's_posts':
				$posts = json_decode( $snippet->s_posts, true );
				if ( is_array( $posts ) && ! empty( $posts ) ) {
					return $this->build_conditional_rules( 'show', 'page', 'post_id', '=', array_map( 'absint', $posts ) );
				}
				break;

			case 's_categories':
				$cats = json_decode( $snippet->s_categories, true );
				if ( is_array( $cats ) && ! empty( $cats ) ) {
					return $this->build_conditional_rules( 'show', 'page', 'taxonomy_term', '=', $cats );
				}
				break;

			case 's_tags':
				$tags = json_decode( $snippet->s_tags, true );
				if ( is_array( $tags ) && ! empty( $tags ) ) {
					return $this->build_conditional_rules( 'show', 'page', 'taxonomy_term', '=', $tags );
				}
				break;

			case 's_custom_posts':
				$cpts = json_decode( $snippet->s_custom_posts, true );
				if ( is_array( $cpts ) && ! empty( $cpts ) ) {
					return $this->build_conditional_rules( 'show', 'page', 'post_type', '=', $cpts );
				}
				break;

			case 's_is_home':
				return $this->build_conditional_rules( 'show', 'page', 'type_of_page', '=', 'is_front_page' );

			case 's_is_search':
				return $this->build_conditional_rules( 'show', 'page', 'type_of_page', '=', 'is_search' );

			case 's_is_archive':
				return $this->build_conditional_rules( 'show', 'page', 'type_of_page', '=', 'is_archive' );

			case 'latest_posts':
				return $this->build_conditional_rules( 'show', 'page', 'type_of_page', '=', 'is_single' );

			case 'All':
			default:
				// Check for exclusions.
				$excludes = array();
				$ex_pages = json_decode( $snippet->s_pages ?? '[]', true );
				$ex_posts = json_decode( $snippet->s_posts ?? '[]', true );
				if ( is_array( $ex_pages ) ) {
					$excludes = array_merge( $excludes, $ex_pages );
				}
				if ( is_array( $ex_posts ) ) {
					$excludes = array_merge( $excludes, $ex_posts );
				}
				if ( ! empty( $excludes ) ) {
					return $this->build_conditional_rules( 'hide', 'page', 'post_id', '=', array_map( 'absint', $excludes ) );
				}
				break;
		}

		return array();
	}

	/**
	 * Build a conditional_rules array with a single rule group.
	 *
	 * @param string $show     'show' or 'hide'.
	 * @param string $type     Rule type.
	 * @param string $option   Rule option.
	 * @param string $relation Operator.
	 * @param mixed  $value    Rule value.
	 * @return array
	 */
	private function build_conditional_rules( $show, $type, $option, $relation, $value ) {
		return array(
			'enabled' => true,
			'show'    => $show,
			'groups'  => array(
				array(
					array(
						'type'     => $type,
						'option'   => $option,
						'relation' => $relation,
						'value'    => $value,
					),
				),
			),
		);
	}

	// ------------------------------------------------------------------
	// Post Snippets importer
	// ------------------------------------------------------------------

	/**
	 * Import a single Post Snippets snippet.
	 *
	 * @param int $source_id Snippet ID in pspro_snippets table.
	 * @return array|WP_Error
	 */
	private function import_post_snippets_snippet( $source_id ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'pspro_snippets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$snippet = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE ID = %d", absint( $source_id ) ), ARRAY_A );

		if ( empty( $snippet ) ) {
			return new \WP_Error( 'not_found', __( 'Snippet not found.', 'snipdrop' ) );
		}

		$code_type = 'html';
		if ( isset( $snippet['snippet_php'] ) ) {
			$php_map   = array(
				'1' => 'php',
				'2' => 'js',
				'3' => 'css',
			);
			$code_type = isset( $php_map[ $snippet['snippet_php'] ] ) ? $php_map[ $snippet['snippet_php'] ] : 'html';
		}

		$tags = array( 'imported-post-snippets' );

		// Resolve group names for tags.
		if ( ! empty( $snippet['snippet_group'] ) ) {
			$group_ids = maybe_unserialize( $snippet['snippet_group'] );
			if ( is_array( $group_ids ) ) {
				$group_table = $wpdb->prefix . 'pspro_groups';
				foreach ( $group_ids as $gid ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$gname = $wpdb->get_var( $wpdb->prepare( "SELECT group_name FROM `{$group_table}` WHERE ID = %d", absint( $gid ) ) );
					if ( $gname ) {
						$tags[] = sanitize_title( $gname );
					}
				}
			}
		}

		$is_shortcode = ! empty( $snippet['snippet_shortcode'] ) && '1' === $snippet['snippet_shortcode'];

		$location = 'everywhere';
		if ( 'php' !== $code_type && ! $is_shortcode ) {
			$location = 'site_header';
		}

		$shortcode_name = '';
		if ( $is_shortcode && ! empty( $snippet['snippet_title'] ) ) {
			$shortcode_name = sanitize_key( $snippet['snippet_title'] );
		}

		$snippet_data = array(
			'title'          => $snippet['snippet_title'] ? $snippet['snippet_title'] : __( 'Imported Snippet', 'snipdrop' ),
			'description'    => isset( $snippet['snippet_desc'] ) ? sanitize_textarea_field( $snippet['snippet_desc'] ) : '',
			'code'           => $snippet['snippet_content'] ?? '',
			'code_type'      => $code_type,
			'status'         => 'inactive',
			'location'       => $location,
			'hook'           => 'php' === $code_type ? 'init' : 'init',
			'priority'       => 10,
			'tags'           => $tags,
			'shortcode_name' => $shortcode_name,
			'source'         => 'import',
		);

		return $this->save_imported( $snippet_data );
	}

	// ------------------------------------------------------------------
	// Save helper
	// ------------------------------------------------------------------

	/**
	 * Save an imported snippet via SNDP_Custom_Snippets.
	 *
	 * @param array $data Snippet data array.
	 * @return array Array with 'id' and 'title'.
	 */
	private function save_imported( $data ) {
		$data['id']     = '';
		$data['status'] = 'inactive';

		$snippet_id = SNDP_Custom_Snippets::instance()->save( $data );

		if ( ! $snippet_id ) {
			return new \WP_Error( 'save_failed', __( 'Failed to save imported snippet.', 'snipdrop' ) );
		}

		return array(
			'id'    => $snippet_id,
			'title' => $data['title'],
			'edit'  => add_query_arg(
				array(
					'page' => 'snipdrop-add',
					'id'   => $snippet_id,
				),
				admin_url( 'admin.php' )
			),
		);
	}

	// ------------------------------------------------------------------
	// AJAX handlers
	// ------------------------------------------------------------------

	/**
	 * AJAX: Return list of available importers with detection status.
	 */
	public function ajax_get_importers() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$importers = $this->get_available_importers();

		wp_send_json_success( array( 'importers' => $importers ) );
	}

	/**
	 * AJAX: Return snippets from a specific source plugin.
	 */
	public function ajax_get_source_snippets() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$slug = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		if ( empty( $slug ) || ! isset( $this->sources[ $slug ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import source.', 'snipdrop' ) ) );
		}

		$snippets = $this->get_source_snippets( $slug );

		wp_send_json_success(
			array(
				'source'   => $slug,
				'name'     => $this->sources[ $slug ]['name'],
				'snippets' => $snippets,
				'count'    => count( $snippets ),
			)
		);
	}

	/**
	 * AJAX: Import a single snippet from a source plugin.
	 */
	public function ajax_import_from_plugin() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$slug      = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$source_id = isset( $_POST['source_id'] ) ? sanitize_text_field( wp_unslash( $_POST['source_id'] ) ) : '';

		if ( empty( $slug ) || ! isset( $this->sources[ $slug ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import source.', 'snipdrop' ) ) );
		}

		if ( empty( $source_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$result = $this->import_single( $slug, $source_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		SNDP_Activity_Log::instance()->log(
			'imported',
			array(
				'snippet_id'    => isset( $result['id'] ) ? $result['id'] : '',
				'snippet_title' => isset( $result['title'] ) ? $result['title'] : '',
				'context'       => 'custom',
				'details'       => sprintf(
					/* translators: %s: source plugin name */
					__( 'Imported from %s', 'snipdrop' ),
					isset( $this->sources[ $slug ]['name'] ) ? $this->sources[ $slug ]['name'] : $slug
				),
			)
		);

		wp_send_json_success( $result );
	}
}
