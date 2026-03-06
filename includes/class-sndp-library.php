<?php
/**
 * Library class for fetching snippets from GitHub.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Library class.
 *
 * @since 1.0.0
 */
class SNDP_Library {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Library
	 */
	private static $instance = null;

	/**
	 * Cached manifest data.
	 *
	 * @var array|null
	 */
	private $manifest = null;

	/**
	 * Cache expiration time in seconds (24 hours).
	 *
	 * @var int
	 */
	private $cache_expiration = DAY_IN_SECONDS;

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 * @return SNDP_Library
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
		// Nothing to initialize here.
	}

	/**
	 * Get the manifest from GitHub or cache.
	 *
	 * @since 1.0.0
	 * @param bool $force_refresh Force refresh from remote.
	 * @return array|WP_Error
	 */
	public function get_manifest( $force_refresh = false ) {
		// Return cached if available and not forcing refresh.
		if ( ! $force_refresh && ! is_null( $this->manifest ) ) {
			return $this->manifest;
		}

		// Try to get from transient.
		$cached = get_transient( 'sndp_manifest' );
		if ( false !== $cached && ! $force_refresh ) {
			$this->manifest = $cached;
			return $this->manifest;
		}

		// Fetch from remote.
		$manifest = $this->fetch_remote_manifest();
		if ( is_wp_error( $manifest ) ) {
			// Return cached version if available, even if expired.
			$expired_cache = get_option( 'sndp_manifest_cache' );
			if ( $expired_cache ) {
				$this->manifest = $expired_cache;
				return $this->manifest;
			}
			return $manifest;
		}

		// Cache the manifest.
		set_transient( 'sndp_manifest', $manifest, $this->cache_expiration );
		update_option( 'sndp_manifest_cache', $manifest, false );

		// Update last sync time.
		$settings              = get_option( 'sndp_settings', array() );
		$settings['last_sync'] = time();
		update_option( 'sndp_settings', $settings );

		$this->manifest = $manifest;
		return $this->manifest;
	}

	/**
	 * Fetch manifest from remote GitHub repository.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	private function fetch_remote_manifest() {
		$url = SNDP_LIBRARY_URL . 'manifest.json';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error(
				'sndp_fetch_error',
				/* translators: %d: HTTP response code */
				sprintf( __( 'Failed to fetch library manifest. HTTP code: %d', 'snipdrop' ), $response_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'sndp_parse_error',
				__( 'Failed to parse library manifest.', 'snipdrop' )
			);
		}

		return $data;
	}

	/**
	 * Get a specific snippet from the library.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 * @return array|WP_Error
	 */
	public function get_snippet( $snippet_id ) {
		// Try local storage first (persistent cache).
		$local_snippets = get_option( 'sndp_local_snippets', array() );
		if ( isset( $local_snippets[ $snippet_id ] ) ) {
			return $local_snippets[ $snippet_id ];
		}

		// Try transient cache.
		$cached = get_transient( 'sndp_snippet_' . $snippet_id );
		if ( false !== $cached ) {
			// Store locally for future use.
			$this->store_snippet_locally( $snippet_id, $cached );
			return $cached;
		}

		// Get manifest to find snippet file path.
		$manifest = $this->get_manifest();
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		// Find snippet in manifest.
		$snippet_meta = null;
		if ( isset( $manifest['snippets'] ) ) {
			foreach ( $manifest['snippets'] as $snippet ) {
				if ( $snippet['id'] === $snippet_id ) {
					$snippet_meta = $snippet;
					break;
				}
			}
		}

		if ( ! $snippet_meta ) {
			return new WP_Error(
				'sndp_snippet_not_found',
				/* translators: %s: Snippet ID */
				sprintf( __( 'Snippet not found: %s', 'snipdrop' ), $snippet_id )
			);
		}

		// Fetch the full snippet data.
		$snippet_data = $this->fetch_remote_snippet( $snippet_meta['file'] );
		if ( is_wp_error( $snippet_data ) ) {
			return $snippet_data;
		}

		// Cache the snippet in transient.
		set_transient( 'sndp_snippet_' . $snippet_id, $snippet_data, $this->cache_expiration );

		// Store locally for persistent access (survives transient expiry).
		$this->store_snippet_locally( $snippet_id, $snippet_data );

		return $snippet_data;
	}

	/**
	 * Store snippet locally for persistent access.
	 *
	 * This ensures snippets work even if GitHub is unreachable.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id   Snippet ID.
	 * @param array  $snippet_data Snippet data.
	 */
	private function store_snippet_locally( $snippet_id, $snippet_data ) {
		$local_snippets                = get_option( 'sndp_local_snippets', array() );
		$local_snippets[ $snippet_id ] = $snippet_data;

		// Evict oldest entries if cache exceeds limit to prevent unbounded growth.
		$max_local = 200;
		if ( count( $local_snippets ) > $max_local ) {
			$local_snippets = array_slice( $local_snippets, -$max_local, $max_local, true );
		}

		update_option( 'sndp_local_snippets', $local_snippets, false );
	}

	/**
	 * Remove snippet from local storage.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id Snippet ID.
	 */
	public function remove_local_snippet( $snippet_id ) {
		$local_snippets = get_option( 'sndp_local_snippets', array() );
		if ( isset( $local_snippets[ $snippet_id ] ) ) {
			unset( $local_snippets[ $snippet_id ] );
			update_option( 'sndp_local_snippets', $local_snippets, false );
		}
	}

	/**
	 * Fetch a snippet file from remote.
	 *
	 * @since 1.0.0
	 * @param string $file_path Relative file path.
	 * @return array|WP_Error
	 */
	private function fetch_remote_snippet( $file_path ) {
		$url = SNDP_LIBRARY_URL . $file_path;

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			// Provide user-friendly error messages.
			if ( 404 === $response_code ) {
				$message = __( 'Snippet not found. Try syncing the library to get the latest updates.', 'snipdrop' );
			} elseif ( $response_code >= 500 ) {
				$message = __( 'The server is temporarily unavailable. Please try again in a moment.', 'snipdrop' );
			} else {
				/* translators: %d: HTTP response code */
				$message = sprintf( __( 'Could not load snippet (error %d). Please try again.', 'snipdrop' ), $response_code );
			}
			return new WP_Error( 'sndp_fetch_error', $message );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'sndp_parse_error',
				__( 'Failed to parse snippet data.', 'snipdrop' )
			);
		}

		return $data;
	}

	/**
	 * Get all categories.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_categories() {
		$manifest = $this->get_manifest();
		if ( is_wp_error( $manifest ) ) {
			return array();
		}

		return isset( $manifest['categories'] ) ? $manifest['categories'] : array();
	}

	/**
	 * Get all snippets from manifest.
	 *
	 * @since 1.0.0
	 * @param string $category Optional category filter.
	 * @return array
	 */
	public function get_snippets( $category = '' ) {
		$manifest = $this->get_manifest();
		if ( is_wp_error( $manifest ) ) {
			return array();
		}

		$snippets = isset( $manifest['snippets'] ) ? $manifest['snippets'] : array();

		if ( ! empty( $category ) ) {
			$snippets = array_filter(
				$snippets,
				function ( $snippet ) use ( $category ) {
					return isset( $snippet['category'] ) && $snippet['category'] === $category;
				}
			);
		}

		return array_values( $snippets );
	}

	/**
	 * Get snippets with pagination and search.
	 *
	 * @since 1.0.0
	 * @param array $args {
	 *     Optional. Arguments for fetching snippets.
	 *
	 *     @type string $category Category filter.
	 *     @type string $search   Search term.
	 *     @type int    $page     Page number (1-based).
	 *     @type int    $per_page Snippets per page.
	 * }
	 * @return array {
	 *     @type array $snippets    Array of snippet data.
	 *     @type int   $total       Total matching snippets.
	 *     @type int   $pages       Total pages.
	 *     @type int   $page        Current page.
	 * }
	 */
	public function get_snippets_paginated( $args = array() ) {
		$defaults = array(
			'category' => '',
			'search'   => '',
			'tag'      => '',
			'page'     => 1,
			'per_page' => 30,
		);
		$args     = wp_parse_args( $args, $defaults );

		$manifest = $this->get_manifest();
		if ( is_wp_error( $manifest ) ) {
			return array(
				'snippets' => array(),
				'total'    => 0,
				'pages'    => 0,
				'page'     => 1,
			);
		}

		$snippets = isset( $manifest['snippets'] ) ? $manifest['snippets'] : array();

		// Filter by category.
		if ( ! empty( $args['category'] ) ) {
			$snippets = array_filter(
				$snippets,
				function ( $snippet ) use ( $args ) {
					return isset( $snippet['category'] ) && $snippet['category'] === $args['category'];
				}
			);
		}

		// Filter by tag.
		if ( ! empty( $args['tag'] ) ) {
			$tag      = strtolower( $args['tag'] );
			$snippets = array_filter(
				$snippets,
				function ( $snippet ) use ( $tag ) {
					if ( ! isset( $snippet['tags'] ) || ! is_array( $snippet['tags'] ) ) {
						return false;
					}
					return in_array( $tag, array_map( 'strtolower', $snippet['tags'] ), true );
				}
			);
		}

		// Filter by search term.
		if ( ! empty( $args['search'] ) ) {
			$search   = strtolower( $args['search'] );
			$snippets = array_filter(
				$snippets,
				function ( $snippet ) use ( $search ) {
					// Search in title, description, and tags.
					$haystack = strtolower(
						$snippet['title'] . ' ' .
						( isset( $snippet['description'] ) ? $snippet['description'] : '' ) . ' ' .
						( isset( $snippet['tags'] ) && is_array( $snippet['tags'] ) ? implode( ' ', $snippet['tags'] ) : '' )
					);
					return strpos( $haystack, $search ) !== false;
				}
			);
		}

		$snippets         = array_values( $snippets );
		$total            = count( $snippets );
		$args['per_page'] = max( 1, $args['per_page'] );
		$pages            = ceil( $total / $args['per_page'] );
		$page             = max( 1, min( $args['page'], $pages ) );
		$offset           = ( $page - 1 ) * $args['per_page'];

		// Slice for pagination.
		$snippets = array_slice( $snippets, $offset, $args['per_page'] );

		return array(
			'snippets' => $snippets,
			'total'    => $total,
			'pages'    => $pages,
			'page'     => $page,
		);
	}

	/**
	 * Get total snippet count.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_total_snippets() {
		$manifest = $this->get_manifest();
		if ( is_wp_error( $manifest ) ) {
			return 0;
		}
		return isset( $manifest['total_snippets'] ) ? absint( $manifest['total_snippets'] ) : count( $this->get_snippets() );
	}

	/**
	 * Clear all caches.
	 *
	 * @since 1.0.0
	 * @param bool $clear_local Also clear locally stored snippets.
	 */
	public function clear_cache( $clear_local = false ) {
		delete_transient( 'sndp_manifest' );
		$this->manifest = null;

		// Clear individual snippet caches.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete of transients, caching not applicable.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sndp_snippet_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sndp_snippet_' ) . '%'
			)
		);

		// Optionally clear local snippet storage.
		if ( $clear_local ) {
			delete_option( 'sndp_local_snippets' );
		}
	}

	/**
	 * Get library version.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_library_version() {
		$manifest = $this->get_manifest();
		if ( is_wp_error( $manifest ) ) {
			return '0.0.0';
		}

		return isset( $manifest['library_version'] ) ? $manifest['library_version'] : '0.0.0';
	}

	/**
	 * Get last sync time.
	 *
	 * @since 1.0.0
	 * @return int Unix timestamp or 0 if never synced.
	 */
	public function get_last_sync() {
		$settings = get_option( 'sndp_settings', array() );
		return isset( $settings['last_sync'] ) ? absint( $settings['last_sync'] ) : 0;
	}
}
