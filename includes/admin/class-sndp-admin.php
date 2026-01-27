<?php
/**
 * Admin class.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Admin class.
 *
 * @since 1.0.0
 */
class SNDP_Admin {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Admin
	 */
	private static $instance = null;

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
	 * @return SNDP_Admin
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

		// Admin menu.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// AJAX handlers - Library.
		add_action( 'wp_ajax_sndp_toggle_snippet', array( $this, 'ajax_toggle_snippet' ) );
		add_action( 'wp_ajax_sndp_sync_library', array( $this, 'ajax_sync_library' ) );
		add_action( 'wp_ajax_sndp_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		add_action( 'wp_ajax_sndp_get_snippet_code', array( $this, 'ajax_get_snippet_code' ) );
		add_action( 'wp_ajax_sndp_get_snippet_settings', array( $this, 'ajax_get_snippet_settings' ) );
		add_action( 'wp_ajax_sndp_save_snippet_config', array( $this, 'ajax_save_snippet_config' ) );
		add_action( 'wp_ajax_sndp_copy_to_custom', array( $this, 'ajax_copy_to_custom' ) );
		add_action( 'wp_ajax_sndp_load_snippets', array( $this, 'ajax_load_snippets' ) );

		// AJAX handlers - Custom snippets.
		add_action( 'wp_ajax_sndp_save_custom_snippet', array( $this, 'ajax_save_custom_snippet' ) );
		add_action( 'wp_ajax_sndp_delete_custom_snippet', array( $this, 'ajax_delete_custom_snippet' ) );
		add_action( 'wp_ajax_sndp_toggle_custom_snippet', array( $this, 'ajax_toggle_custom_snippet' ) );
		add_action( 'wp_ajax_sndp_duplicate_custom_snippet', array( $this, 'ajax_duplicate_custom_snippet' ) );
		add_action( 'wp_ajax_sndp_get_custom_snippet', array( $this, 'ajax_get_custom_snippet' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		add_menu_page(
			__( 'SnipDrop', 'snipdrop' ),
			__( 'SnipDrop', 'snipdrop' ),
			'manage_options',
			'snipdrop',
			array( $this, 'render_admin_page' ),
			'dashicons-editor-code',
			80
		);

		add_submenu_page(
			'snipdrop',
			__( 'Library', 'snipdrop' ),
			__( 'Library', 'snipdrop' ),
			'manage_options',
			'snipdrop',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			'snipdrop',
			__( 'My Snippets', 'snipdrop' ),
			__( 'My Snippets', 'snipdrop' ),
			'manage_options',
			'snipdrop-custom',
			array( $this, 'render_custom_snippets_page' )
		);

		add_submenu_page(
			'snipdrop',
			__( 'Add New', 'snipdrop' ),
			__( 'Add New', 'snipdrop' ),
			'manage_options',
			'snipdrop-add',
			array( $this, 'render_add_snippet_page' )
		);

		add_submenu_page(
			'snipdrop',
			__( 'Settings', 'snipdrop' ),
			__( 'Settings', 'snipdrop' ),
			'manage_options',
			'snipdrop-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		// Our admin pages.
		$our_pages = array(
			'toplevel_page_snipdrop',
			'snipdrop_page_snipdrop-settings',
			'snipdrop_page_snipdrop-custom',
			'snipdrop_page_snipdrop-add',
		);

		// Only load on our pages.
		if ( ! in_array( $hook_suffix, $our_pages, true ) ) {
			return;
		}

		// Styles.
		wp_enqueue_style(
			'sndp-admin',
			SNDP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SNDP_VERSION
		);

		// Code editor for custom snippets (editing only).
		$editor_settings = array();
		if ( in_array( $hook_suffix, array( 'snipdrop_page_snipdrop-add' ), true ) ) {
			// WordPress built-in code editor.
			$settings = wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );

			if ( false !== $settings ) {
				$editor_settings = $settings;
			}
		}

		// Scripts.
		wp_enqueue_script(
			'sndp-admin',
			SNDP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			SNDP_VERSION,
			true
		);

		wp_localize_script(
			'sndp-admin',
			'sndp_admin',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'sndp_admin_nonce' ),
				'editor_settings' => $editor_settings,
				'strings'         => array(
					'enabling'         => __( 'Enabling...', 'snipdrop' ),
					'disabling'        => __( 'Disabling...', 'snipdrop' ),
					'syncing'          => __( 'Syncing...', 'snipdrop' ),
					'saving'           => __( 'Saving...', 'snipdrop' ),
					'deleting'         => __( 'Deleting...', 'snipdrop' ),
					'error'            => __( 'An error occurred. Please try again.', 'snipdrop' ),
					'connection_error' => __( 'Unable to connect. Please check your internet connection and try again.', 'snipdrop' ),
					'confirm_delete'   => __( 'Are you sure you want to delete this snippet?', 'snipdrop' ),
					'copied'           => __( 'Snippet copied to My Snippets.', 'snipdrop' ),
					'plugin_required'  => __( 'This snippet requires the following plugin(s) to be active:', 'snipdrop' ),
					'loading'          => __( 'Loading snippets...', 'snipdrop' ),
					'no_snippets'      => __( 'No snippets found.', 'snipdrop' ),
					'no_results'       => __( 'No results found.', 'snipdrop' ),
					'showing'          => __( 'Showing', 'snipdrop' ),
					'of'               => __( 'of', 'snipdrop' ),
					'snippets'         => __( 'snippets', 'snipdrop' ),
					'page'             => __( 'Page', 'snipdrop' ),
					'prev'             => __( 'Previous', 'snipdrop' ),
					'next'             => __( 'Next', 'snipdrop' ),
				),
			)
		);
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// Activation notice.
		if ( get_transient( 'sndp_activated' ) ) {
			delete_transient( 'sndp_activated' );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: Link to SnipDrop admin page */
						esc_html__( 'SnipDrop activated! %s to start enabling snippets.', 'snipdrop' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=snipdrop' ) ) . '">' . esc_html__( 'Go to Snippets', 'snipdrop' ) . '</a>'
					);
					?>
				</p>
			</div>
			<?php
		}

		// Error notice.
		$error_handler = SNDP_Error_Handler::instance();
		$error_notice  = $error_handler->get_error_notice();
		if ( $error_notice ) {
			$error_handler->clear_error_notice();
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'SnipDrop:', 'snipdrop' ); ?></strong>
					<?php
					printf(
						/* translators: %s: Snippet ID */
						esc_html__( 'Snippet "%s" was automatically disabled due to an error.', 'snipdrop' ),
						esc_html( $error_notice['snippet_id'] )
					);
					?>
				</p>
				<p><code><?php echo esc_html( $error_notice['message'] ); ?></code></p>
			</div>
			<?php
		}

		// Safe mode notice.
		if ( $this->snippets->is_safe_mode() ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'SnipDrop Safe Mode Active', 'snipdrop' ); ?></strong> -
					<?php esc_html_e( 'All snippets are currently disabled. Disable safe mode in settings when ready.', 'snipdrop' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render main admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'snipdrop' ) );
		}

		// Get current filter (for initial state, actual loading is via AJAX).
		$current_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Get data for sidebar and header.
		$categories      = $this->library->get_categories();
		$enabled         = $this->snippets->get_enabled_snippets();
		$error_snippets  = $this->snippets->get_error_snippets();
		$library_version = $this->library->get_library_version();
		$last_sync       = $this->library->get_last_sync();
		$total_snippets  = $this->library->get_total_snippets();

		include SNDP_PLUGIN_DIR . 'includes/admin/views/admin-page.php';
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'snipdrop' ) );
		}

		// Handle form submission.
		if ( isset( $_POST['sndp_save_settings'] ) ) {
			check_admin_referer( 'sndp_settings_nonce', 'sndp_nonce' );

			$settings                        = get_option( 'sndp_settings', array() );
			$settings['safe_mode']           = isset( $_POST['sndp_safe_mode'] );
			$settings['delete_on_uninstall'] = isset( $_POST['sndp_delete_on_uninstall'] );
			update_option( 'sndp_settings', $settings );

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'snipdrop' ) . '</p></div>';
		}

		$settings      = get_option( 'sndp_settings', array() );
		$safe_mode     = isset( $settings['safe_mode'] ) && $settings['safe_mode'];
		$secret_key    = isset( $settings['secret_key'] ) ? $settings['secret_key'] : '';
		$safe_mode_url = $this->snippets->get_safe_mode_url();

		include SNDP_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
	}

	/**
	 * AJAX: Toggle snippet.
	 *
	 * @since 1.0.0
	 */
	public function ajax_toggle_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		// Check if enabling - if so, verify required plugins are active.
		$currently_enabled = $this->snippets->is_enabled( $snippet_id );
		if ( ! $currently_enabled ) {
			// Trying to enable - check requirements.
			$snippet = $this->library->get_snippet( $snippet_id );
			if ( ! is_wp_error( $snippet ) && ! empty( $snippet['requires']['plugins'] ) ) {
				$missing_plugins = $this->check_missing_plugins( $snippet['requires']['plugins'] );
				if ( ! empty( $missing_plugins ) ) {
					wp_send_json_error(
						array(
							'message'         => __( 'Required plugin(s) not active.', 'snipdrop' ),
							'missing_plugins' => $missing_plugins,
						)
					);
				}
			}
		}

		$new_status = $this->snippets->toggle_snippet( $snippet_id );

		wp_send_json_success(
			array(
				'enabled' => $new_status,
				'message' => $new_status
					? __( 'Snippet enabled.', 'snipdrop' )
					: __( 'Snippet disabled.', 'snipdrop' ),
			)
		);
	}

	/**
	 * Check for missing required plugins.
	 *
	 * @since 1.1.0
	 * @param array $required_plugins Array of required plugin slugs.
	 * @return array Array of missing plugin names.
	 */
	private function check_missing_plugins( $required_plugins ) {
		$missing = array();

		foreach ( $required_plugins as $plugin ) {
			$plugin = strtolower( $plugin );

			$is_active = false;
			switch ( $plugin ) {
				case 'woocommerce':
					$is_active = class_exists( 'WooCommerce' );
					break;

				case 'woocommerce-subscriptions':
					$is_active = class_exists( 'WC_Subscriptions' );
					break;

				case 'woocommerce-bookings':
					$is_active = class_exists( 'WC_Bookings' );
					break;

				case 'woocommerce-product-addons':
					$is_active = class_exists( 'WC_Product_Addons' );
					break;

				case 'woocommerce-product-bundles':
					$is_active = class_exists( 'WC_Bundles' );
					break;

				case 'elementor':
					$is_active = defined( 'ELEMENTOR_VERSION' );
					break;

				default:
					// Generic check.
					if ( ! function_exists( 'is_plugin_active' ) ) {
						include_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					$is_active = is_plugin_active( $plugin . '/' . $plugin . '.php' );
			}

			if ( ! $is_active ) {
				$missing[] = ucwords( str_replace( '-', ' ', $plugin ) );
			}
		}

		return $missing;
	}

	/**
	 * AJAX: Sync library.
	 *
	 * @since 1.0.0
	 */
	public function ajax_sync_library() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		// Clear cache and fetch fresh.
		$this->library->clear_cache();
		$manifest = $this->library->get_manifest( true );

		if ( is_wp_error( $manifest ) ) {
			wp_send_json_error( array( 'message' => $manifest->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'        => __( 'Library synced successfully.', 'snipdrop' ),
				'version'        => $this->library->get_library_version(),
				'total_snippets' => isset( $manifest['total_snippets'] ) ? $manifest['total_snippets'] : 0,
				'last_sync'      => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * AJAX: Load snippets with pagination.
	 *
	 * @since 1.2.0
	 */
	public function ajax_load_snippets() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 30;

		$result = $this->library->get_snippets_paginated(
			array(
				'category' => $category,
				'search'   => $search,
				'page'     => $page,
				'per_page' => min( $per_page, 100 ), // Max 100 per page.
			)
		);

		// Add enabled/error status to each snippet.
		$enabled_ids    = SNDP_Snippets::instance()->get_enabled_snippets();
		$error_snippets = SNDP_Snippets::instance()->get_error_snippets();

		foreach ( $result['snippets'] as &$snippet ) {
			$snippet['is_enabled'] = in_array( $snippet['id'], $enabled_ids, true );
			$snippet['has_error']  = isset( $error_snippets[ $snippet['id'] ] );
			$snippet['error_msg']  = $snippet['has_error'] ? $error_snippets[ $snippet['id'] ] : null;
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Dismiss notice.
	 *
	 * @since 1.0.0
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		$notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_id'] ) ) : '';

		if ( 'error' === $notice_id ) {
			$error_handler = SNDP_Error_Handler::instance();
			$error_handler->clear_error_notice();
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Get snippet code.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_snippet_code() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$snippet = $this->library->get_snippet( $snippet_id );
		if ( is_wp_error( $snippet ) ) {
			wp_send_json_error( array( 'message' => $snippet->get_error_message() ) );
		}

		$code             = isset( $snippet['code'] ) ? $snippet['code'] : '';
		$long_description = isset( $snippet['long_description'] ) ? $snippet['long_description'] : '';
		$author           = isset( $snippet['author'] ) ? $snippet['author'] : null;
		$source           = isset( $snippet['source'] ) ? $snippet['source'] : null;
		$requires         = isset( $snippet['requires'] ) ? $snippet['requires'] : null;

		wp_send_json_success(
			array(
				'code'             => $code,
				'long_description' => $long_description,
				'author'           => $author,
				'source'           => $source,
				'requires'         => $requires,
			)
		);
	}

	/**
	 * Render custom snippets page.
	 *
	 * @since 1.1.0
	 */
	public function render_custom_snippets_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'snipdrop' ) );
		}

		$custom_snippets = $this->custom_snippets->get_all();

		include SNDP_PLUGIN_DIR . 'includes/admin/views/custom-snippets-page.php';
	}

	/**
	 * Render add snippet page.
	 *
	 * @since 1.1.0
	 */
	public function render_add_snippet_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'snipdrop' ) );
		}

		// Check if editing existing snippet.
		$snippet_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$snippet    = null;

		if ( ! empty( $snippet_id ) ) {
			$snippet = $this->custom_snippets->get( $snippet_id );
		}

		include SNDP_PLUGIN_DIR . 'includes/admin/views/add-snippet-page.php';
	}

	/**
	 * AJAX: Get snippet settings for configuration.
	 *
	 * @since 1.1.0
	 */
	public function ajax_get_snippet_settings() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$snippet = $this->library->get_snippet( $snippet_id );
		if ( is_wp_error( $snippet ) ) {
			wp_send_json_error( array( 'message' => $snippet->get_error_message() ) );
		}

		$settings       = isset( $snippet['settings'] ) ? $snippet['settings'] : array();
		$saved_config   = $this->snippets->get_snippet_config( $snippet_id );
		$default_config = $this->snippets->get_default_config( $settings );
		$current_config = wp_parse_args( $saved_config, $default_config );

		wp_send_json_success(
			array(
				'settings' => $settings,
				'config'   => $current_config,
			)
		);
	}

	/**
	 * AJAX: Save snippet configuration.
	 *
	 * @since 1.1.0
	 */
	public function ajax_save_snippet_config() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		$config     = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		// Config will be sanitized in save_snippet_config.
		$this->snippets->save_snippet_config( $snippet_id, $config );

		wp_send_json_success( array( 'message' => __( 'Configuration saved.', 'snipdrop' ) ) );
	}

	/**
	 * AJAX: Copy library snippet to custom snippets.
	 *
	 * @since 1.1.0
	 */
	public function ajax_copy_to_custom() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$snippet = $this->library->get_snippet( $snippet_id );
		if ( is_wp_error( $snippet ) ) {
			wp_send_json_error( array( 'message' => $snippet->get_error_message() ) );
		}

		$new_id = $this->custom_snippets->create_from_library( $snippet );

		wp_send_json_success(
			array(
				'message'    => __( 'Snippet copied to My Snippets.', 'snipdrop' ),
				'snippet_id' => $new_id,
				'edit_url'   => admin_url( 'admin.php?page=snipdrop-add&id=' . $new_id ),
			)
		);
	}

	/**
	 * AJAX: Save custom snippet.
	 *
	 * @since 1.1.0
	 */
	public function ajax_save_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		// Sanitize post types array.
		$post_types = array();
		if ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ) {
			$post_types = array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) );
		}

		$snippet_data = array(
			'id'          => isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '',
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'code'        => isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'code_type'   => isset( $_POST['code_type'] ) ? sanitize_text_field( wp_unslash( $_POST['code_type'] ) ) : 'php',
			'status'      => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'inactive',
			'hook'        => isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : 'init',
			'priority'    => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
			'location'    => isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : 'everywhere',
			'user_cond'   => isset( $_POST['user_cond'] ) ? sanitize_text_field( wp_unslash( $_POST['user_cond'] ) ) : 'all',
			'post_types'  => $post_types,
			'page_ids'    => isset( $_POST['page_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['page_ids'] ) ) : '',
		);

		// Validate PHP syntax if PHP snippet.
		if ( 'php' === $snippet_data['code_type'] && ! empty( $snippet_data['code'] ) ) {
			$syntax_check = $this->custom_snippets->validate_php_syntax( $snippet_data['code'] );
			if ( true !== $syntax_check ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: Error message */
							__( 'PHP Syntax Error: %s', 'snipdrop' ),
							$syntax_check
						),
					)
				);
			}
		}

		// Check for suspicious code patterns.
		$warnings = array();
		if ( ! empty( $snippet_data['code'] ) ) {
			$warnings = $this->custom_snippets->check_suspicious_code( $snippet_data['code'], $snippet_data['code_type'] );
		}

		$snippet_id = $this->custom_snippets->save( $snippet_data );

		$response = array(
			'message'    => __( 'Snippet saved.', 'snipdrop' ),
			'snippet_id' => $snippet_id,
		);

		// Include warnings if any.
		if ( ! empty( $warnings ) ) {
			$response['warnings']      = $warnings;
			$response['warnings_html'] = $this->custom_snippets->format_warnings( $warnings );
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Delete custom snippet.
	 *
	 * @since 1.1.0
	 */
	public function ajax_delete_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$this->custom_snippets->delete( $snippet_id );

		wp_send_json_success( array( 'message' => __( 'Snippet deleted.', 'snipdrop' ) ) );
	}

	/**
	 * AJAX: Toggle custom snippet.
	 *
	 * @since 1.1.0
	 */
	public function ajax_toggle_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$new_status = $this->custom_snippets->toggle( $snippet_id );

		if ( false === $new_status ) {
			wp_send_json_error( array( 'message' => __( 'Snippet not found.', 'snipdrop' ) ) );
		}

		wp_send_json_success(
			array(
				'status'  => $new_status,
				'message' => 'active' === $new_status
					? __( 'Snippet activated.', 'snipdrop' )
					: __( 'Snippet deactivated.', 'snipdrop' ),
			)
		);
	}

	/**
	 * AJAX: Duplicate custom snippet.
	 *
	 * @since 1.1.0
	 */
	public function ajax_duplicate_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$new_id = $this->custom_snippets->duplicate( $snippet_id );

		if ( false === $new_id ) {
			wp_send_json_error( array( 'message' => __( 'Snippet not found.', 'snipdrop' ) ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Snippet duplicated.', 'snipdrop' ),
				'snippet_id' => $new_id,
			)
		);
	}

	/**
	 * AJAX: Get custom snippet data.
	 *
	 * @since 1.1.0
	 */
	public function ajax_get_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$snippet = $this->custom_snippets->get( $snippet_id );

		if ( ! $snippet ) {
			wp_send_json_error( array( 'message' => __( 'Snippet not found.', 'snipdrop' ) ) );
		}

		wp_send_json_success( array( 'snippet' => $snippet ) );
	}
}
