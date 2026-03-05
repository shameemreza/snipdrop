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
	 * Compatibility checker.
	 *
	 * @var SNDP_Compatibility
	 */
	private $compatibility;

	/**
	 * Conflict detector.
	 *
	 * @var SNDP_Conflicts
	 */
	private $conflicts;

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
		$this->compatibility   = SNDP_Compatibility::instance();
		$this->conflicts       = SNDP_Conflicts::instance();

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

		// Import/Export handlers.
		add_action( 'admin_post_sndp_export_snippets', array( $this, 'handle_export_snippets' ) );
		add_action( 'wp_ajax_sndp_import_snippets', array( $this, 'ajax_import_snippets' ) );

		// Bulk actions.
		add_action( 'wp_ajax_sndp_bulk_action', array( $this, 'ajax_bulk_action' ) );

		// Admin bar.
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );

		// Revisions.
		add_action( 'wp_ajax_sndp_restore_revision', array( $this, 'ajax_restore_revision' ) );

		// Post/page search for conditional targeting.
		add_action( 'wp_ajax_sndp_search_posts', array( $this, 'ajax_search_posts' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		$new_count  = $this->get_new_snippet_count();
		$menu_label = __( 'SnipDrop', 'snipdrop' );
		if ( $new_count > 0 ) {
			$menu_label .= ' <span class="awaiting-mod">' . absint( $new_count ) . '</span>';
		}

		add_menu_page(
			__( 'SnipDrop', 'snipdrop' ),
			$menu_label,
			SNDP_CAPABILITY,
			'snipdrop',
			array( $this, 'render_admin_page' ),
			'dashicons-superhero',
			80
		);

		add_submenu_page(
			'snipdrop',
			__( 'Library', 'snipdrop' ),
			__( 'Library', 'snipdrop' ),
			SNDP_CAPABILITY,
			'snipdrop',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			'snipdrop',
			__( 'My Snippets', 'snipdrop' ),
			__( 'My Snippets', 'snipdrop' ),
			SNDP_CAPABILITY,
			'snipdrop-custom',
			array( $this, 'render_custom_snippets_page' )
		);

		add_submenu_page(
			'snipdrop',
			__( 'Add New', 'snipdrop' ),
			__( 'Add New', 'snipdrop' ),
			SNDP_CAPABILITY,
			'snipdrop-add',
			array( $this, 'render_add_snippet_page' )
		);

		add_submenu_page(
			'snipdrop',
			__( 'Settings', 'snipdrop' ),
			__( 'Settings', 'snipdrop' ),
			SNDP_CAPABILITY,
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

		// Code editor and datepicker for custom snippets (editing only).
		$editor_settings = array();
		if ( in_array( $hook_suffix, array( 'snipdrop_page_snipdrop-add' ), true ) ) {
			// WordPress built-in code editor.
			$settings = wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );

			if ( false !== $settings ) {
				$editor_settings = $settings;
			}

			// jQuery UI datepicker for schedule fields.
			wp_enqueue_script( 'jquery-ui-datepicker' );
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
					'enabling'             => __( 'Enabling...', 'snipdrop' ),
					'disabling'            => __( 'Disabling...', 'snipdrop' ),
					'syncing'              => __( 'Syncing...', 'snipdrop' ),
					'saving'               => __( 'Saving...', 'snipdrop' ),
					'deleting'             => __( 'Deleting...', 'snipdrop' ),
					'error'                => __( 'An error occurred. Please try again.', 'snipdrop' ),
					'connection_error'     => __( 'Unable to connect. Please check your internet connection and try again.', 'snipdrop' ),
					'confirm_delete'       => __( 'Are you sure you want to delete this snippet?', 'snipdrop' ),
					'copied'               => __( 'Snippet copied to My Snippets.', 'snipdrop' ),
					'plugin_required'      => __( 'This snippet requires the following plugin(s) to be active:', 'snipdrop' ),
					'loading'              => __( 'Loading snippets...', 'snipdrop' ),
					'no_snippets'          => __( 'No snippets found.', 'snipdrop' ),
					'no_results'           => __( 'No results found.', 'snipdrop' ),
					'showing'              => __( 'Showing', 'snipdrop' ),
					'of'                   => __( 'of', 'snipdrop' ),
					'snippets'             => __( 'snippets', 'snipdrop' ),
					'page'                 => __( 'Page', 'snipdrop' ),
					'prev'                 => __( 'Previous', 'snipdrop' ),
					'next'                 => __( 'Next', 'snipdrop' ),
					'loading_btn'          => __( 'Loading...', 'snipdrop' ),
					'try_again'            => __( 'Try Again', 'snipdrop' ),
					'sync_library'         => __( 'Sync Library', 'snipdrop' ),
					'error_hint'           => __( 'This may be a temporary issue. Try syncing the library or wait a moment.', 'snipdrop' ),
					'save_config'          => __( 'Save Configuration', 'snipdrop' ),
					'saved'                => __( 'Saved!', 'snipdrop' ),
					'update_snippet'       => __( 'Update Snippet', 'snipdrop' ),
					'author_label'         => __( 'Author:', 'snipdrop' ),
					'source_label'         => __( 'Source:', 'snipdrop' ),
					'edit_now'             => __( 'Edit now?', 'snipdrop' ),
					'import_submit'        => __( 'Upload & Import', 'snipdrop' ),
					'confirm_restore'      => __( 'Restore this revision? Current code will be saved as a new revision.', 'snipdrop' ),
					'compat_compatible'    => __( 'Compatible', 'snipdrop' ),
					'compat_warning'       => __( 'Check Requirements', 'snipdrop' ),
					'compat_incompatible'  => __( 'Incompatible', 'snipdrop' ),
					'compat_issues_title'  => __( 'Compatibility Issues', 'snipdrop' ),
					'compat_enable_block'  => __( 'This snippet cannot be enabled due to compatibility issues:', 'snipdrop' ),
					'compat_code_warn'     => __( 'Compatibility Warnings', 'snipdrop' ),
					'requires_label'       => __( 'Requires:', 'snipdrop' ),
					'hint_php'             => __( 'Do not include opening &lt;?php tags. Your code runs inside a PHP context.', 'snipdrop' ),
					'hint_js'              => __( 'Do not wrap in &lt;script&gt; tags. Your code is automatically wrapped when output.', 'snipdrop' ),
					'hint_css'             => __( 'Do not wrap in &lt;style&gt; tags. Your code is automatically wrapped when output.', 'snipdrop' ),
					'hint_html'            => __( 'Write raw HTML. It will be inserted directly into the page.', 'snipdrop' ),
					'weight_lightweight'   => __( 'Lightweight', 'snipdrop' ),
					'weight_moderate'      => __( 'Moderate', 'snipdrop' ),
					'weight_heavy'         => __( 'Heavy', 'snipdrop' ),
					'weight_tip_light'     => __( 'Minimal performance impact. Simple filter or CSS-only change.', 'snipdrop' ),
					'weight_tip_moderate'  => __( 'Runs on page loads with conditional logic. Test with caching enabled.', 'snipdrop' ),
					'weight_tip_heavy'     => __( 'Makes database queries or HTTP calls. Monitor site performance.', 'snipdrop' ),
					'conflict_warning'     => __( 'Potential Conflict', 'snipdrop' ),
					'conflict_high_risk'   => __( 'High Risk Conflict', 'snipdrop' ),
					'conflict_notice'      => __( 'Hook Conflicts Detected', 'snipdrop' ),
					'conflict_enable_warn' => __( 'This snippet may conflict with other active snippets:', 'snipdrop' ),
					'conflict_proceed'     => __( 'The snippet has been enabled, but please test your site to ensure everything works correctly.', 'snipdrop' ),
					/* translators: %s: revision date */
					'diff_title'           => __( 'Changes since %s', 'snipdrop' ),
					'diff_identical'       => __( 'No changes — the code is identical.', 'snipdrop' ),
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
		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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
		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'snipdrop' ) );
		}

		// Handle form submission.
		if ( isset( $_POST['sndp_save_settings'] ) ) {
			check_admin_referer( 'sndp_settings_nonce', 'sndp_nonce' );

			$settings                        = get_option( 'sndp_settings', array() );
			$settings['safe_mode']           = isset( $_POST['sndp_safe_mode'] );
			$settings['disable_for_admins']  = isset( $_POST['sndp_disable_for_admins'] );
			$settings['auto_disable_errors'] = isset( $_POST['sndp_auto_disable_errors'] );
			$settings['email_notifications'] = isset( $_POST['sndp_email_notifications'] );
			$settings['notification_email']  = isset( $_POST['sndp_notification_email'] ) ? sanitize_email( wp_unslash( $_POST['sndp_notification_email'] ) ) : '';
			$settings['delete_on_uninstall'] = isset( $_POST['sndp_delete_on_uninstall'] );
			update_option( 'sndp_settings', $settings );
			$this->snippets->invalidate_settings_cache();

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

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		// Check if enabling — run full compatibility check first.
		$currently_enabled = $this->snippets->is_enabled( $snippet_id );
		$conflict_warnings = array();

		if ( ! $currently_enabled ) {
			$snippet = $this->library->get_snippet( $snippet_id );
			if ( ! is_wp_error( $snippet ) ) {
				$compat = $this->compatibility->check_snippet( $snippet );

				if ( SNDP_Compatibility::STATUS_INCOMPATIBLE === $compat['status'] ) {
					wp_send_json_error(
						array(
							'message'       => __( 'This snippet is not compatible with your environment.', 'snipdrop' ),
							'compat_status' => $compat['status'],
							'compat_issues' => $compat['issues'],
						)
					);
				}

				// Check for hook conflicts with other active snippets.
				$conflict_check = $this->conflicts->check_conflicts( $snippet_id, $snippet, 'library' );
				if ( $conflict_check['has_conflicts'] ) {
					foreach ( $conflict_check['conflicts'] as $c ) {
						$conflict_warnings[] = sprintf(
							/* translators: 1: Conflicting snippet title, 2: Hook name */
							__( 'Shares the "%2$s" hook with "%1$s"', 'snipdrop' ),
							$c['snippet_title'],
							$c['hook']
						);
					}
				}
			}
		}

		$new_status = $this->snippets->toggle_snippet( $snippet_id );

		// Clear conflict cache after toggling.
		$this->conflicts->clear_cache();

		$response = array(
			'enabled' => $new_status,
			'message' => $new_status
				? __( 'Snippet enabled.', 'snipdrop' )
				: __( 'Snippet disabled.', 'snipdrop' ),
		);

		if ( ! empty( $conflict_warnings ) ) {
			$response['conflict_warnings'] = $conflict_warnings;
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Sync library.
	 *
	 * @since 1.0.0
	 */
	public function ajax_sync_library() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		// Clear cache and fetch fresh.
		$this->library->clear_cache();
		$manifest = $this->library->get_manifest( true );

		if ( is_wp_error( $manifest ) ) {
			wp_send_json_error( array( 'message' => $manifest->get_error_message() ) );
		}

		// Refresh new snippet count cache.
		delete_transient( 'sndp_new_snippet_count' );
		$new_count = $this->get_new_snippet_count();

		/**
		 * Fires after the snippet library has been synced.
		 *
		 * @since 1.0.0
		 * @param array $manifest The synced manifest data.
		 */
		do_action( 'snipdrop_after_sync', $manifest );

		$message = __( 'Library synced successfully.', 'snipdrop' );
		if ( $new_count > 0 ) {
			$message = sprintf(
				/* translators: %d: Number of new snippets */
				__( 'Library synced. %d new snippet(s) available!', 'snipdrop' ),
				$new_count
			);
		}

		wp_send_json_success(
			array(
				'message'        => $message,
				'version'        => $this->library->get_library_version(),
				'total_snippets' => isset( $manifest['total_snippets'] ) ? $manifest['total_snippets'] : 0,
				'last_sync'      => gmdate( 'Y-m-d H:i:s' ),
				'new_count'      => $new_count,
			)
		);
	}

	/**
	 * AJAX: Load snippets with pagination.
	 *
	 * @since 1.0.0
	 */
	public function ajax_load_snippets() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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

		$seven_days_ago = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		// Batch-check compatibility for all snippets on this page.
		$compat_results = $this->compatibility->check_snippets_batch( $result['snippets'] );

		// Get conflict data for all active snippets.
		$conflicts_by_snippet = $this->conflicts->get_conflicts_by_snippet();

		foreach ( $result['snippets'] as &$snippet ) {
			$snippet['is_enabled'] = in_array( $snippet['id'], $enabled_ids, true );
			$snippet['has_error']  = isset( $error_snippets[ $snippet['id'] ] );
			$snippet['error_msg']  = $snippet['has_error'] ? $error_snippets[ $snippet['id'] ] : null;

			$added_date        = isset( $snippet['added'] ) ? $snippet['added'] : '';
			$snippet['is_new'] = ( '' !== $added_date && $added_date >= $seven_days_ago );

			// Compatibility data.
			$sid = isset( $snippet['id'] ) ? $snippet['id'] : '';
			if ( isset( $compat_results[ $sid ] ) ) {
				$snippet['compat_status']  = $compat_results[ $sid ]['status'];
				$snippet['compat_issues']  = $compat_results[ $sid ]['issues'];
				$snippet['compat_require'] = $compat_results[ $sid ]['requirements'];
			} else {
				$snippet['compat_status']  = 'compatible';
				$snippet['compat_issues']  = array();
				$snippet['compat_require'] = array();
			}

			// Performance weight (from library manifest or auto-detected).
			if ( ! empty( $snippet['weight'] ) ) {
				$snippet['perf_weight'] = $snippet['weight'];
			} else {
				$snippet['perf_weight'] = 'lightweight';
			}

			// Conflict data.
			if ( isset( $conflicts_by_snippet[ $sid ] ) ) {
				$snippet['has_conflicts']      = true;
				$snippet['conflict_details']   = $conflicts_by_snippet[ $sid ]['conflicts'];
				$snippet['high_risk_conflict'] = $conflicts_by_snippet[ $sid ]['has_high_risk'];
			} else {
				$snippet['has_conflicts']      = false;
				$snippet['conflict_details']   = array();
				$snippet['high_risk_conflict'] = false;
			}
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

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

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

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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
	 * @since 1.0.0
	 */
	public function render_custom_snippets_page() {
		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'snipdrop' ) );
		}

		$custom_snippets = $this->custom_snippets->get_all();

		include SNDP_PLUGIN_DIR . 'includes/admin/views/custom-snippets-page.php';
	}

	/**
	 * Render add snippet page.
	 *
	 * @since 1.0.0
	 */
	public function render_add_snippet_page() {
		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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
	 * @since 1.0.0
	 */
	public function ajax_get_snippet_settings() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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
	 * @since 1.0.0
	 */
	public function ajax_save_snippet_config() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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
	 * @since 1.0.0
	 */
	public function ajax_copy_to_custom() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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
	 * @since 1.0.0
	 */
	public function ajax_save_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		// Sanitize post types array.
		$post_types = array();
		if ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ) {
			$post_types = array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) );
		}

		$snippet_data = array(
			'id'             => isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '',
			'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'code'           => isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'code_type'      => isset( $_POST['code_type'] ) ? sanitize_text_field( wp_unslash( $_POST['code_type'] ) ) : 'php',
			'status'         => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'inactive',
			'hook'           => isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : 'init',
			'priority'       => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
			'location'       => isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : 'everywhere',
			'user_cond'      => isset( $_POST['user_cond'] ) ? sanitize_text_field( wp_unslash( $_POST['user_cond'] ) ) : 'all',
			'post_types'     => $post_types,
			'page_ids'       => isset( $_POST['page_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['page_ids'] ) ) : '',
			'url_patterns'   => isset( $_POST['url_patterns'] ) ? sanitize_textarea_field( wp_unslash( $_POST['url_patterns'] ) ) : '',
			'taxonomies'     => isset( $_POST['taxonomies'] ) && is_array( $_POST['taxonomies'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['taxonomies'] ) )
				: array(),
			'schedule_start' => isset( $_POST['schedule_start'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_start'] ) ) : '',
			'schedule_end'   => isset( $_POST['schedule_end'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_end'] ) ) : '',
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

		// Run compatibility analysis on custom code.
		$compat_warnings = array();
		$perf_weight     = 'lightweight';
		if ( ! empty( $snippet_data['code'] ) ) {
			$compat_analysis = $this->compatibility->analyze_custom_code( $snippet_data['code'], $snippet_data['code_type'] );
			$compat_warnings = array_merge( $compat_analysis['php_issues'], $compat_analysis['plugin_issues'] );
			$perf_weight     = $this->compatibility->detect_weight( $snippet_data['code'], $snippet_data['code_type'] );
		}

		$snippet_id = $this->custom_snippets->save( $snippet_data );

		$response = array(
			'message'    => __( 'Snippet saved.', 'snipdrop' ),
			'snippet_id' => $snippet_id,
		);

		// Include suspicious code warnings if any.
		if ( ! empty( $warnings ) ) {
			$response['warnings']      = $warnings;
			$response['warnings_html'] = $this->custom_snippets->format_warnings( $warnings );
		}

		// Include compatibility warnings if any.
		if ( ! empty( $compat_warnings ) ) {
			$response['compat_warnings'] = $compat_warnings;
		}

		// Performance weight.
		$response['perf_weight'] = $perf_weight;

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Delete custom snippet.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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
	 * @since 1.0.0
	 */
	public function ajax_toggle_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) : '';
		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		// Check for conflicts when activating a custom snippet.
		$conflict_warnings = array();
		$snippet_data      = $this->custom_snippets->get( $snippet_id );

		if ( $snippet_data && ( empty( $snippet_data['status'] ) || 'inactive' === $snippet_data['status'] ) ) {
			$conflict_check = $this->conflicts->check_conflicts( $snippet_id, $snippet_data, 'custom' );
			if ( $conflict_check['has_conflicts'] ) {
				foreach ( $conflict_check['conflicts'] as $c ) {
					$conflict_warnings[] = sprintf(
						/* translators: 1: Conflicting snippet title, 2: Hook name */
						__( 'Shares the "%2$s" hook with "%1$s"', 'snipdrop' ),
						$c['snippet_title'],
						$c['hook']
					);
				}
			}
		}

		$new_status = $this->custom_snippets->toggle( $snippet_id );

		if ( false === $new_status ) {
			wp_send_json_error( array( 'message' => __( 'Snippet not found.', 'snipdrop' ) ) );
		}

		// Clear conflict cache after toggling.
		$this->conflicts->clear_cache();

		$response = array(
			'status'  => $new_status,
			'message' => 'active' === $new_status
				? __( 'Snippet activated.', 'snipdrop' )
				: __( 'Snippet deactivated.', 'snipdrop' ),
		);

		if ( ! empty( $conflict_warnings ) ) {
			$response['conflict_warnings'] = $conflict_warnings;
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Duplicate custom snippet.
	 *
	 * @since 1.0.0
	 */
	public function ajax_duplicate_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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
	 * @since 1.0.0
	 */
	public function ajax_get_custom_snippet() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
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

	/**
	 * AJAX: Search posts and pages for the page picker.
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_posts() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$query = new \WP_Query(
			array(
				's'              => $search,
				'post_type'      => get_post_types( array( 'public' => true ), 'names' ),
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'relevance',
				'no_found_rows'  => true,
			)
		);

		$results = array();
		foreach ( $query->posts as $post ) {
			$type_obj  = get_post_type_object( $post->post_type );
			$results[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'post_type' => $type_obj ? $type_obj->labels->singular_name : $post->post_type,
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Restore a snippet to a previous revision.
	 *
	 * @since 1.0.0
	 */
	public function ajax_restore_revision() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$snippet_id     = isset( $_POST['snippet_id'] ) ? sanitize_key( wp_unslash( $_POST['snippet_id'] ) ) : '';
		$revision_index = isset( $_POST['revision_index'] ) ? absint( $_POST['revision_index'] ) : 0;

		if ( empty( $snippet_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid snippet ID.', 'snipdrop' ) ) );
		}

		$result = $this->custom_snippets->restore_revision( $snippet_id, $revision_index );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not restore revision.', 'snipdrop' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Revision restored. Reload to see the updated code.', 'snipdrop' ) ) );
	}

	/**
	 * Add SnipDrop item to the admin bar.
	 *
	 * @since 1.0.0
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			return;
		}

		$active_library = count( $this->snippets->get_enabled_snippets() );
		$active_custom  = $this->custom_snippets->get_active_count();
		$total_active   = $active_custom + $active_library;

		$wp_admin_bar->add_node(
			array(
				'id'    => 'snipdrop',
				'title' => sprintf(
					/* translators: %d: active snippet count */
					__( 'SnipDrop (%d)', 'snipdrop' ),
					$total_active
				),
				'href'  => admin_url( 'admin.php?page=snipdrop' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'snipdrop-library',
				'parent' => 'snipdrop',
				'title'  => __( 'Library', 'snipdrop' ),
				'href'   => admin_url( 'admin.php?page=snipdrop' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'snipdrop-my-snippets',
				'parent' => 'snipdrop',
				'title'  => __( 'My Snippets', 'snipdrop' ),
				'href'   => admin_url( 'admin.php?page=snipdrop-custom' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'snipdrop-add-new',
				'parent' => 'snipdrop',
				'title'  => __( 'Add New', 'snipdrop' ),
				'href'   => admin_url( 'admin.php?page=snipdrop-add' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'snipdrop-settings',
				'parent' => 'snipdrop',
				'title'  => __( 'Settings', 'snipdrop' ),
				'href'   => admin_url( 'admin.php?page=snipdrop-settings' ),
			)
		);

		if ( $this->snippets->is_safe_mode() ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'snipdrop-safe-mode',
					'parent' => 'snipdrop',
					'title'  => '<span class="sndp-safe-mode-label">' . __( 'Safe Mode Active', 'snipdrop' ) . '</span>',
					'href'   => admin_url( 'admin.php?page=snipdrop-settings' ),
				)
			);
		}
	}

	/**
	 * AJAX: Perform bulk action on custom snippets.
	 *
	 * @since 1.0.0
	 */
	public function ajax_bulk_action() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$action      = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$snippet_ids = isset( $_POST['snippet_ids'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['snippet_ids'] ) ) : array();

		if ( empty( $action ) || empty( $snippet_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No action or snippets selected.', 'snipdrop' ) ) );
		}

		$processed = 0;

		foreach ( $snippet_ids as $snippet_id ) {
			switch ( $action ) {
				case 'activate':
					if ( $this->custom_snippets->activate( $snippet_id ) ) {
						++$processed;
					}
					break;

				case 'deactivate':
					if ( $this->custom_snippets->deactivate( $snippet_id ) ) {
						++$processed;
					}
					break;

				case 'delete':
					if ( $this->custom_snippets->delete( $snippet_id ) ) {
						++$processed;
					}
					break;
			}
		}

		wp_send_json_success(
			array(
				'message'   => sprintf(
					/* translators: %d: Number of snippets processed */
					__( '%d snippet(s) updated.', 'snipdrop' ),
					$processed
				),
				'processed' => $processed,
			)
		);
	}

	/**
	 * Get count of new snippets added in the last 7 days.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	private function get_new_snippet_count() {
		$cached = get_transient( 'sndp_new_snippet_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$manifest = $this->library->get_manifest();
		if ( is_wp_error( $manifest ) || empty( $manifest['snippets'] ) ) {
			return 0;
		}

		$seven_days_ago = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$count          = 0;

		foreach ( $manifest['snippets'] as $snippet ) {
			$added = isset( $snippet['added'] ) ? $snippet['added'] : '';
			if ( '' !== $added && $added >= $seven_days_ago ) {
				++$count;
			}
		}

		set_transient( 'sndp_new_snippet_count', $count, HOUR_IN_SECONDS );

		return $count;
	}

	/**
	 * Handle export snippets as JSON download.
	 *
	 * @since 1.0.0
	 */
	public function handle_export_snippets() {
		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'snipdrop' ), 403 );
		}

		check_admin_referer( 'sndp_export_snippets', 'sndp_export_nonce' );

		$snippet_ids = array();
		if ( ! empty( $_GET['ids'] ) ) {
			$snippet_ids = array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) ) );
		}

		$export_data = $this->custom_snippets->export( $snippet_ids );

		$filename = 'snipdrop-snippets-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download.
		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * AJAX: Import snippets from uploaded JSON file.
	 *
	 * @since 1.0.0
	 */
	public function ajax_import_snippets() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		if ( empty( $_FILES['import_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'snipdrop' ) ) );
		}

		$file = $_FILES['import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- File upload handled below.

		// Validate file type.
		$filetype = wp_check_filetype( $file['name'], array( 'json' => 'application/json' ) );
		if ( 'json' !== $filetype['ext'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a JSON file.', 'snipdrop' ) ) );
		}

		// Validate file size (max 2MB).
		if ( $file['size'] > 2 * MB_IN_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'File is too large. Maximum size is 2MB.', 'snipdrop' ) ) );
		}

		// Read and parse file.
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			wp_send_json_error( array( 'message' => __( 'Unable to access the filesystem.', 'snipdrop' ) ) );
		}

		$content = $wp_filesystem->get_contents( $file['tmp_name'] );
		if ( empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'The file is empty.', 'snipdrop' ) ) );
		}

		$data = json_decode( $content, true );
		if ( null === $data ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON file. Please check the file format.', 'snipdrop' ) ) );
		}

		$result = $this->custom_snippets->import( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'  => sprintf(
					/* translators: %d: Number of imported snippets */
					__( 'Successfully imported %d snippet(s). All imported snippets are set to inactive for safety.', 'snipdrop' ),
					$result['imported']
				),
				'imported' => $result['imported'],
				'skipped'  => $result['skipped'],
			)
		);
	}
}
