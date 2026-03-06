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
		add_action( 'wp_ajax_sndp_restore_global_revision', array( $this, 'ajax_restore_global_revision' ) );

		// Conditional logic dynamic values.
		add_action( 'wp_ajax_sndp_get_condition_values', array( $this, 'ajax_get_condition_values' ) );

		// AJAX search for products and posts.
		add_action( 'wp_ajax_sndp_search_wc_products', array( $this, 'ajax_search_wc_products' ) );
		add_action( 'wp_ajax_sndp_search_posts', array( $this, 'ajax_search_posts' ) );

		// Header & Footer.
		add_action( 'wp_ajax_sndp_save_global_scripts', array( $this, 'ajax_save_global_scripts' ) );

		// Activity log.
		add_action( 'wp_ajax_sndp_get_activity_log', array( $this, 'ajax_get_activity_log' ) );
		add_action( 'wp_ajax_sndp_clear_activity_log', array( $this, 'ajax_clear_activity_log' ) );

		// Shared modal shell for all SnipDrop pages.
		add_action( 'admin_footer', array( $this, 'render_shared_modal' ) );
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
			__( 'Header & Footer', 'snipdrop' ),
			__( 'Header & Footer', 'snipdrop' ),
			SNDP_CAPABILITY,
			'snipdrop-header-footer',
			array( $this, 'render_header_footer_page' )
		);

		add_submenu_page(
			'snipdrop',
			__( 'Activity Log', 'snipdrop' ),
			__( 'Activity Log', 'snipdrop' ),
			SNDP_CAPABILITY,
			'snipdrop-activity-log',
			array( $this, 'render_activity_log_page' )
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
			'snipdrop_page_snipdrop-header-footer',
			'snipdrop_page_snipdrop-activity-log',
		);

		// Only load on our pages.
		if ( ! in_array( $hook_suffix, $our_pages, true ) ) {
			return;
		}

		// SelectWoo / Select2 for enhanced selects (bundled, no WC dependency).
		wp_enqueue_script(
			'sndp-selectwoo',
			SNDP_PLUGIN_URL . 'assets/vendor/selectWoo/selectWoo.full.min.js',
			array( 'jquery' ),
			'1.0.10',
			true
		);
		wp_enqueue_style(
			'sndp-select2',
			SNDP_PLUGIN_URL . 'assets/vendor/selectWoo/select2.css',
			array(),
			'1.0.10'
		);

		// Styles.
		wp_enqueue_style(
			'sndp-admin',
			SNDP_PLUGIN_URL . 'assets/css/admin.css',
			array( 'sndp-select2' ),
			SNDP_VERSION
		);

		// Code editor and datepicker for custom snippets (editing only).
		$editor_settings = array();
		if ( in_array( $hook_suffix, array( 'snipdrop_page_snipdrop-add', 'snipdrop_page_snipdrop-header-footer' ), true ) ) {
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
			array( 'jquery', 'sndp-selectwoo' ),
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
					'delete_title'         => __( 'Delete Snippet', 'snipdrop' ),
					'delete_desc'          => __( 'Are you sure you want to delete "%s"? This action cannot be undone.', 'snipdrop' ),
					'delete_btn'           => __( 'Delete', 'snipdrop' ),
					'deleting'             => __( 'Deleting...', 'snipdrop' ),
					'bulk_delete_title'    => __( 'Delete Snippets', 'snipdrop' ),
					'bulk_delete_desc'     => __( 'Are you sure you want to delete %d snippet(s)? This action cannot be undone.', 'snipdrop' ),
					'copied'               => __( 'Snippet Copied!', 'snipdrop' ),
					'copy_confirm_title'   => __( 'Copy to My Snippets', 'snipdrop' ),
					'copy_confirm_desc'    => __( 'A copy of "%s" will be added to your custom snippets as inactive.', 'snipdrop' ),
					'copy_btn'             => __( 'Copy Snippet', 'snipdrop' ),
					'copying'              => __( 'Copying...', 'snipdrop' ),
					'copy_success_desc'    => __( 'The snippet has been copied to your custom snippets as inactive. You can edit it to customize the code or activate it.', 'snipdrop' ),
					'copy_edit'            => __( 'Edit Snippet', 'snipdrop' ),
					'copy_stay'            => __( 'Stay Here', 'snipdrop' ),
					'copy_exists_title'    => __( 'Already Copied', 'snipdrop' ),
					'copy_exists_desc'     => __( 'This snippet already exists in your custom snippets as "%s". Would you like to copy it again or edit the existing one?', 'snipdrop' ),
					'copy_again'           => __( 'Copy Again', 'snipdrop' ),
					'copy_edit_existing'   => __( 'Edit Existing', 'snipdrop' ),
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
					'cl_add_rule'          => __( 'Add Rule', 'snipdrop' ),
					'cl_add_group'         => __( 'Add Group', 'snipdrop' ),
					'cl_group_label'       => __( 'Group', 'snipdrop' ),
					'cl_match_all'         => __( 'ALL rules match (AND)', 'snipdrop' ),
					'cl_match_any'         => __( 'ANY rule matches (OR)', 'snipdrop' ),
					'cl_select_type'       => __( 'Select condition...', 'snipdrop' ),
					'cl_enter_value'       => __( 'Enter value...', 'snipdrop' ),
					'cl_remove_rule'       => __( 'Remove rule', 'snipdrop' ),
					'cl_remove_group'      => __( 'Remove group', 'snipdrop' ),
					'cl_groups_match'      => __( 'Between groups:', 'snipdrop' ),
					'imp_select_source'    => __( 'Select a plugin to import from:', 'snipdrop' ),
					'imp_no_plugins'       => __( 'No compatible snippet plugins detected.', 'snipdrop' ),
					'imp_loading'          => __( 'Detecting plugins...', 'snipdrop' ),
					'imp_loading_snippets' => __( 'Loading snippets...', 'snipdrop' ),
					'imp_no_snippets'      => __( 'No snippets found in this plugin.', 'snipdrop' ),
					'imp_select_all'       => __( 'Select All', 'snipdrop' ),
					'imp_deselect_all'     => __( 'Deselect All', 'snipdrop' ),
					'imp_import_selected'  => __( 'Import Selected', 'snipdrop' ),
					/* translators: 1: current number, 2: total number, 3: plugin name */
					'imp_progress'         => __( 'Importing %1$s of %2$s snippets from %3$s...', 'snipdrop' ),
					/* translators: %d: number of imported snippets */
					'imp_complete'         => __( 'Successfully imported %d snippet(s)! All imported as inactive for safety.', 'snipdrop' ),
					'imp_error'            => __( 'Failed to import snippet.', 'snipdrop' ),
					'imp_edit'             => __( 'Edit', 'snipdrop' ),
					'imp_back'             => __( 'Back to Sources', 'snipdrop' ),
					'imp_not_active'       => __( '(Not Active)', 'snipdrop' ),
					'imp_has_data'         => __( '(Deactivated — data found)', 'snipdrop' ),
					'tm_enable_title'         => __( 'Enable Testing Mode', 'snipdrop' ),
					'tm_enable_desc'          => __( 'All snippet changes will be staged and only visible to admins. Visitors will continue to see the current live version until you publish.', 'snipdrop' ),
					'tm_enable_btn'           => __( 'Enable Testing Mode', 'snipdrop' ),
					'tm_deactivate_title'     => __( 'Deactivate Testing Mode', 'snipdrop' ),
					'tm_deactivate_prompt'    => __( 'You have staged changes. Would you like to publish them to live or discard them?', 'snipdrop' ),
					'tm_no_changes_disable'   => __( 'No changes were made during this testing session. Testing mode will be turned off.', 'snipdrop' ),
					'tm_disable_btn'          => __( 'Turn Off', 'snipdrop' ),
					'tm_publish_title'        => __( 'Publish Changes', 'snipdrop' ),
					'tm_publish_confirm'      => __( 'This will push all staged changes to live. Visitors will see the updated snippets immediately.', 'snipdrop' ),
					'tm_publish_btn'          => __( 'Publish All', 'snipdrop' ),
					'tm_discard_title'        => __( 'Discard Changes', 'snipdrop' ),
					'tm_discard_confirm'      => __( 'All staged changes will be permanently deleted. This cannot be undone.', 'snipdrop' ),
					'tm_discard_btn'          => __( 'Discard All', 'snipdrop' ),
					'tm_no_changes'           => __( 'No changes detected in testing mode.', 'snipdrop' ),
					'tm_changes_title'        => __( 'Staged Changes', 'snipdrop' ),
					'tm_snippet_new'          => __( 'New', 'snipdrop' ),
					'tm_snippet_modified'     => __( 'Modified', 'snipdrop' ),
					'tm_snippet_deleted'      => __( 'Deleted', 'snipdrop' ),
					'tm_global_changed'       => __( 'Changed', 'snipdrop' ),
					'enabling'                => __( 'Enabling...', 'snipdrop' ),
					'publishing'              => __( 'Publishing...', 'snipdrop' ),
					'discarding'              => __( 'Discarding...', 'snipdrop' ),
					'cancel'                    => __( 'Cancel', 'snipdrop' ),
					'safe_mode_enable_title'    => __( 'Enable Safe Mode', 'snipdrop' ),
					'safe_mode_enable_desc'     => __( 'All snippets will be disabled immediately. Your site will not execute any custom code until safe mode is turned off.', 'snipdrop' ),
					'safe_mode_enable_btn'      => __( 'Enable Safe Mode', 'snipdrop' ),
					'safe_mode_disable_title'   => __( 'Disable Safe Mode', 'snipdrop' ),
					'safe_mode_disable_desc'    => __( 'All active snippets will start executing again. Make sure any problematic snippets have been fixed or deactivated.', 'snipdrop' ),
					'safe_mode_disable_btn'     => __( 'Disable Safe Mode', 'snipdrop' ),
					'al_no_activity'        => __( 'No activity yet', 'snipdrop' ),
					'al_empty'              => __( 'Events will appear here as you enable, disable, create, edit, or delete snippets. Errors and imports are also logged.', 'snipdrop' ),
					'al_event'              => __( 'Event', 'snipdrop' ),
					'al_details'            => __( 'Details', 'snipdrop' ),
					'al_source'             => __( 'Source', 'snipdrop' ),
					'al_user'               => __( 'User', 'snipdrop' ),
					'al_time'               => __( 'Time', 'snipdrop' ),
					'al_showing'            => __( 'Showing', 'snipdrop' ),
					'al_events'             => __( 'events', 'snipdrop' ),
					'al_load_more'          => __( 'Load More', 'snipdrop' ),
					'al_clear_title'        => __( 'Clear Activity Log', 'snipdrop' ),
					'al_clear_desc'         => __( 'Are you sure you want to clear the entire activity log? This action cannot be undone.', 'snipdrop' ),
					'al_clear_btn'          => __( 'Clear Log', 'snipdrop' ),
				),
				'testing_mode' => SNDP_Testing_Mode::instance()->is_enabled(),
				'condition_types' => SNDP_Conditional_Logic::instance()->get_condition_types(),
				'condition_categories' => SNDP_Conditional_Logic::instance()->get_categories(),
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

		// Clear stale errors for non-PHP snippets (from old eval-all bug).
		foreach ( $error_snippets as $err_id => $err_data ) {
			$full = $this->library->get_snippet( $err_id );
			if ( ! is_wp_error( $full ) ) {
				$ct = isset( $full['code_type'] ) ? $full['code_type'] : 'php';
				if ( 'php' !== $ct ) {
					$this->snippets->clear_snippet_error( $err_id );
					unset( $error_snippets[ $err_id ] );
				}
			}
		}

		include SNDP_PLUGIN_DIR . 'includes/admin/views/admin-page.php';
	}

	/**
	 * Render the Header & Footer scripts page.
	 *
	 * @since 1.0.0
	 */
	public function render_header_footer_page() {
		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'snipdrop' ) );
		}

		$scripts = get_option( 'sndp_global_scripts', array() );
		include SNDP_PLUGIN_DIR . 'includes/admin/views/header-footer-page.php';
	}

	/**
	 * Render the Activity Log page.
	 *
	 * @since 1.0.0
	 */
	public function render_activity_log_page() {
		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'snipdrop' ) );
		}

		$activity_log = SNDP_Activity_Log::instance();
		$log_count    = $activity_log->get_count();
		$log_result   = $activity_log->get_entries( array( 'limit' => 20 ) );
		$log_types    = SNDP_Activity_Log::get_valid_types();

		include SNDP_PLUGIN_DIR . 'includes/admin/views/activity-log-page.php';
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

			SNDP_Activity_Log::instance()->log(
				'settings_changed',
				array(
					'context' => 'settings',
					'details' => __( 'Plugin settings updated', 'snipdrop' ),
				)
			);

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
				$snippet_title_for_log = isset( $snippet['title'] ) ? $snippet['title'] : $snippet_id;

				$compat = $this->compatibility->check_snippet( $snippet );

				if ( SNDP_Compatibility::STATUS_INCOMPATIBLE === $compat['status'] ) {
					SNDP_Activity_Log::instance()->log(
						'error',
						array(
							'snippet_id'    => $snippet_id,
							'snippet_title' => $snippet_title_for_log,
							'context'       => 'library',
							'details'       => __( 'Enable blocked — incompatible with environment', 'snipdrop' ),
						)
					);

					wp_send_json_error(
						array(
							'message'       => __( 'This snippet is not compatible with your environment.', 'snipdrop' ),
							'compat_status' => $compat['status'],
							'compat_issues' => $compat['issues'],
						)
					);
				}

				// Pre-validate PHP syntax before enabling.
				$code_type = isset( $snippet['code_type'] ) ? $snippet['code_type'] : 'php';
				if ( 'php' === $code_type && ! empty( $snippet['code'] ) ) {
					$syntax_error = $this->validate_php_syntax( $snippet['code'] );
					if ( $syntax_error ) {
						SNDP_Activity_Log::instance()->log(
							'error',
							array(
								'snippet_id'    => $snippet_id,
								'snippet_title' => $snippet_title_for_log,
								'snippet_type'  => 'php',
								'context'       => 'library',
								'details'       => $syntax_error,
							)
						);

						wp_send_json_error(
							array(
								'message'      => $syntax_error,
								'syntax_error' => true,
							)
						);
					}
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
		} else {
			// When disabling, clear any existing error for this snippet.
			$this->snippets->clear_snippet_error( $snippet_id );
		}

		$new_status = $this->snippets->toggle_snippet( $snippet_id );

		// Clear conflict cache after toggling.
		$this->conflicts->clear_cache();

		$snippet_title = '';
		$snippet_obj   = $this->library->get_snippet( $snippet_id );
		if ( ! is_wp_error( $snippet_obj ) ) {
			$snippet_title = isset( $snippet_obj['title'] ) ? $snippet_obj['title'] : $snippet_id;
		}

		SNDP_Activity_Log::instance()->log(
			$new_status ? 'enabled' : 'disabled',
			array(
				'snippet_id'    => $snippet_id,
				'snippet_title' => $snippet_title,
				'context'       => 'library',
			)
		);

		$response = array(
			'enabled'       => $new_status,
			'enabled_count' => $this->snippets->get_enabled_count(),
			'message'       => $new_status
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
		$tag      = isset( $_POST['tag'] ) ? sanitize_text_field( wp_unslash( $_POST['tag'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 30;

		$result = $this->library->get_snippets_paginated(
			array(
				'category' => $category,
				'search'   => $search,
				'tag'      => $tag,
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
			$sid = isset( $snippet['id'] ) ? $snippet['id'] : '';

			$snippet['is_enabled'] = in_array( $sid, $enabled_ids, true );
			$snippet['has_error']  = isset( $error_snippets[ $sid ] );
			$snippet['error_msg']  = $snippet['has_error'] ? $error_snippets[ $sid ] : null;

			$added_date        = isset( $snippet['added'] ) ? $snippet['added'] : '';
			$snippet['is_new'] = ( '' !== $added_date && $added_date >= $seven_days_ago );

			// Enrich with full snippet data for configurable/code_type fields.
			$full_snippet = $this->library->get_snippet( $sid );
			if ( ! is_wp_error( $full_snippet ) ) {
				if ( ! empty( $full_snippet['configurable'] ) ) {
					$snippet['configurable'] = true;
					$snippet['settings']     = isset( $full_snippet['settings'] ) ? $full_snippet['settings'] : array();
				}
				$snippet['code_type'] = isset( $full_snippet['code_type'] ) ? $full_snippet['code_type'] : 'php';

				// Clear stale errors for non-PHP snippets (from old eval-all bug).
				if ( $snippet['has_error'] && 'php' !== $snippet['code_type'] ) {
					$this->snippets->clear_snippet_error( $sid );
					$snippet['has_error'] = false;
					$snippet['error_msg'] = null;
				}
			}

			// Compatibility data.
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

		// Check for existing copy.
		$existing = $this->find_existing_copy( $snippet_id );
		$force    = isset( $_POST['force'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['force'] ) );

		if ( $existing && ! $force ) {
			wp_send_json_error(
				array(
					'code'      => 'duplicate',
					'message'   => __( 'This snippet has already been copied.', 'snipdrop' ),
					'edit_url'  => admin_url( 'admin.php?page=snipdrop-add&id=' . $existing['id'] ),
					'title'     => $existing['title'],
				)
			);
		}

		$new_id = $this->custom_snippets->create_from_library( $snippet );

		SNDP_Activity_Log::instance()->log(
			'created',
			array(
				'snippet_id'    => $new_id,
				'snippet_title' => isset( $snippet['title'] ) ? $snippet['title'] : $snippet_id,
				'context'       => 'library',
				'details'       => __( 'Copied from library', 'snipdrop' ),
			)
		);

		wp_send_json_success(
			array(
				'message'    => __( 'Snippet copied to My Snippets.', 'snipdrop' ),
				'snippet_id' => $new_id,
				'edit_url'   => admin_url( 'admin.php?page=snipdrop-add&id=' . $new_id ),
			)
		);
	}

	/**
	 * Find an existing custom snippet that was copied from a library snippet.
	 *
	 * @param string $library_id Library snippet ID.
	 * @return array|false Array with 'id' and 'title' if found, false otherwise.
	 */
	private function find_existing_copy( $library_id ) {
		$source = 'library:' . $library_id;
		$all    = $this->custom_snippets->get_all();

		foreach ( $all as $id => $snippet ) {
			if ( isset( $snippet['source'] ) && $snippet['source'] === $source ) {
				return array(
					'id'    => $id,
					'title' => $snippet['title'] ?? '',
				);
			}
		}

		return false;
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

		// Parse tags from comma-separated string.
		$tags = array();
		if ( isset( $_POST['tags'] ) ) {
			$raw_tags = sanitize_text_field( wp_unslash( $_POST['tags'] ) );
			if ( '' !== $raw_tags ) {
				$tags = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $raw_tags ) ) ) ) );
			}
		}

		// Parse conditional rules from JSON.
		$conditional_rules = array();
		if ( isset( $_POST['conditional_rules'] ) ) {
			$raw_rules = sanitize_text_field( wp_unslash( $_POST['conditional_rules'] ) );
			if ( '' !== $raw_rules ) {
				$decoded = json_decode( $raw_rules, true );
				if ( is_array( $decoded ) ) {
					$conditional_rules = $this->sanitize_conditional_rules( $decoded );
				}
			}
		}

		$snippet_data = array(
			'id'                => isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '',
			'title'             => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'       => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'code'              => isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'code_type'         => isset( $_POST['code_type'] ) ? sanitize_text_field( wp_unslash( $_POST['code_type'] ) ) : 'php',
			'status'            => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'inactive',
			'hook'              => isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : 'init',
			'priority'          => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
			'location'          => isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : 'everywhere',
			'user_cond'         => isset( $_POST['user_cond'] ) ? sanitize_text_field( wp_unslash( $_POST['user_cond'] ) ) : 'all',
			'post_types'        => $post_types,
			'page_ids'          => isset( $_POST['page_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['page_ids'] ) ) : '',
			'url_patterns'      => isset( $_POST['url_patterns'] ) ? sanitize_textarea_field( wp_unslash( $_POST['url_patterns'] ) ) : '',
			'taxonomies'        => isset( $_POST['taxonomies'] ) && is_array( $_POST['taxonomies'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['taxonomies'] ) )
				: array(),
			'schedule_start'    => isset( $_POST['schedule_start'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_start'] ) ) : '',
			'schedule_end'      => isset( $_POST['schedule_end'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_end'] ) ) : '',
			'tags'              => $tags,
			'conditional_rules' => $conditional_rules,
			'shortcode_name'    => isset( $_POST['shortcode_name'] ) ? sanitize_key( wp_unslash( $_POST['shortcode_name'] ) ) : '',
			'insert_paragraph'  => isset( $_POST['insert_paragraph'] ) ? absint( $_POST['insert_paragraph'] ) : 2,
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

		$is_new     = empty( $snippet_data['id'] );
		$snippet_id = $this->custom_snippets->save( $snippet_data );

		SNDP_Activity_Log::instance()->log(
			$is_new ? 'created' : 'updated',
			array(
				'snippet_id'    => $snippet_id,
				'snippet_title' => $snippet_data['title'],
				'snippet_type'  => $snippet_data['code_type'],
				'context'       => 'custom',
			)
		);

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

		$snippet_data  = $this->custom_snippets->get( $snippet_id );
		$snippet_title = $snippet_data ? ( $snippet_data['title'] ?? $snippet_id ) : $snippet_id;

		$this->custom_snippets->delete( $snippet_id );

		SNDP_Activity_Log::instance()->log(
			'deleted',
			array(
				'snippet_id'    => $snippet_id,
				'snippet_title' => $snippet_title,
				'context'       => 'custom',
			)
		);

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
			// Pre-validate PHP syntax before enabling.
			$code_type = isset( $snippet_data['code_type'] ) ? $snippet_data['code_type'] : 'php';
			if ( 'php' === $code_type && ! empty( $snippet_data['code'] ) ) {
				$syntax_error = $this->validate_php_syntax( $snippet_data['code'] );
				if ( $syntax_error ) {
					SNDP_Activity_Log::instance()->log(
						'error',
						array(
							'snippet_id'    => $snippet_id,
							'snippet_title' => $snippet_data['title'] ?? $snippet_id,
							'snippet_type'  => 'php',
							'context'       => 'custom',
							'details'       => $syntax_error,
						)
					);

					wp_send_json_error(
						array(
							'message'      => $syntax_error,
							'syntax_error' => true,
						)
					);
				}
			}

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

		SNDP_Activity_Log::instance()->log(
			'active' === $new_status ? 'enabled' : 'disabled',
			array(
				'snippet_id'    => $snippet_id,
				'snippet_title' => $snippet_data ? ( $snippet_data['title'] ?? $snippet_id ) : $snippet_id,
				'snippet_type'  => $snippet_data ? ( $snippet_data['code_type'] ?? '' ) : '',
				'context'       => 'custom',
			)
		);

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
	 * AJAX: Save global header & footer scripts.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_global_scripts() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$scripts = array(
			'header'    => isset( $_POST['header'] ) ? wp_unslash( $_POST['header'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'body_open' => isset( $_POST['body_open'] ) ? wp_unslash( $_POST['body_open'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'footer'    => isset( $_POST['footer'] ) ? wp_unslash( $_POST['footer'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);

		// Store revision of previous state if anything changed.
		$previous = get_option( 'sndp_global_scripts', array() );
		if ( ! empty( $previous ) && $previous !== $scripts ) {
			$this->custom_snippets->store_global_scripts_revision( $previous );
		}

		update_option( 'sndp_global_scripts', $scripts, false );

		SNDP_Activity_Log::instance()->log(
			'updated',
			array(
				'snippet_title' => __( 'Global Header & Footer Scripts', 'snipdrop' ),
				'context'       => 'global',
			)
		);

		wp_send_json_success( array( 'message' => __( 'Scripts saved.', 'snipdrop' ) ) );
	}

	/**
	 * AJAX: Get dynamic values for a conditional logic type.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_condition_values() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$value_type = isset( $_POST['value_type'] ) ? sanitize_text_field( wp_unslash( $_POST['value_type'] ) ) : '';
		$values     = SNDP_Conditional_Logic::instance()->get_dynamic_values( $value_type );

		wp_send_json_success( array( 'values' => $values ) );
	}

	/**
	 * AJAX: Search WooCommerce products by name.
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_wc_products() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$products = wc_get_products(
			array(
				's'       => $search,
				'limit'   => 20,
				'status'  => 'publish',
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		$results = array();
		foreach ( $products as $product ) {
			$results[] = array(
				'id'    => $product->get_id(),
				'title' => $product->get_name(),
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Search posts/pages by title.
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

		$args = array(
			's'              => $search,
			'post_type'      => get_post_types( array( 'public' => true ) ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$query   = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'    => $post->ID,
				'title' => get_the_title( $post ),
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
	 * AJAX: Restore global scripts to a previous revision.
	 *
	 * @since 1.0.0
	 */
	public function ajax_restore_global_revision() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$revision_index = isset( $_POST['revision_index'] ) ? absint( $_POST['revision_index'] ) : 0;

		$result = $this->custom_snippets->restore_global_scripts_revision( $revision_index );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not restore revision.', 'snipdrop' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Revision restored. Reload to see the updated scripts.', 'snipdrop' ) ) );
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
				'id'     => 'snipdrop-activity-log',
				'parent' => 'snipdrop',
				'title'  => __( 'Activity Log', 'snipdrop' ),
				'href'   => admin_url( 'admin.php?page=snipdrop-activity-log' ),
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
	 * Render a shared modal shell on all SnipDrop admin pages.
	 *
	 * The same modal is used by the diff viewer, testing mode changes list, etc.
	 * On the add-snippet page, the template already contains this modal, so we
	 * skip it there.
	 *
	 * @since 1.0.0
	 */
	public function render_shared_modal() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'snipdrop' ) === false ) {
			return;
		}

		// Diff modal (add-snippet page has its own).
		if ( strpos( $screen->id, 'snipdrop-add' ) === false ) :
			?>
			<div id="sndp-diff-modal" class="sndp-modal">
				<div class="sndp-modal-content sndp-diff-modal-content">
					<div class="sndp-modal-header">
						<h3 id="sndp-diff-modal-title"><?php esc_html_e( 'Details', 'snipdrop' ); ?></h3>
						<button type="button" class="sndp-modal-close">&times;</button>
					</div>
					<div class="sndp-modal-body">
						<div id="sndp-diff-output" class="sndp-diff-output"></div>
					</div>
				</div>
			</div>
			<?php
		endif;

		// Confirmation modal (all pages).
		?>
		<div id="sndp-confirm-modal" class="sndp-modal">
			<div class="sndp-modal-content sndp-confirm-modal-content">
				<button type="button" class="sndp-modal-close">&times;</button>
				<div class="sndp-confirm-icon" id="sndp-confirm-icon"></div>
				<h3 id="sndp-confirm-title"></h3>
				<div id="sndp-confirm-body"></div>
				<div class="sndp-confirm-actions" id="sndp-confirm-actions"></div>
			</div>
		</div>
		<?php
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

		$allowed_actions = array( 'activate', 'deactivate', 'delete' );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid action.', 'snipdrop' ) ) );
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

		if ( $processed > 0 ) {
			SNDP_Activity_Log::instance()->log(
				'bulk_action',
				array(
					'context' => 'custom',
					'details' => sprintf(
						/* translators: 1: action name, 2: number of snippets */
						__( 'Bulk %1$s on %2$d snippet(s)', 'snipdrop' ),
						$action,
						$processed
					),
				)
			);
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

		if ( $result['imported'] > 0 ) {
			SNDP_Activity_Log::instance()->log(
				'imported',
				array(
					'context' => 'custom',
					'details' => sprintf(
						/* translators: %d: Number of imported snippets */
						__( '%d snippet(s) imported from JSON file', 'snipdrop' ),
						$result['imported']
					),
				)
			);
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

	/**
	 * Sanitize conditional rules data structure from user input.
	 *
	 * @since 1.0.0
	 * @param array $rules Raw decoded JSON rules array.
	 * @return array Sanitized rules.
	 */
	private function sanitize_conditional_rules( $rules ) {
		$clean = array(
			'enabled' => ! empty( $rules['enabled'] ),
			'match'   => in_array( $rules['match'] ?? 'all', array( 'all', 'any' ), true ) ? $rules['match'] : 'all',
			'groups'  => array(),
		);

		if ( ! isset( $rules['groups'] ) || ! is_array( $rules['groups'] ) ) {
			return $clean;
		}

		foreach ( $rules['groups'] as $group ) {
			if ( ! is_array( $group ) || empty( $group['rules'] ) || ! is_array( $group['rules'] ) ) {
				continue;
			}

			$clean_group = array(
				'match' => in_array( $group['match'] ?? 'all', array( 'all', 'any' ), true ) ? $group['match'] : 'all',
				'rules' => array(),
			);

			foreach ( $group['rules'] as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['type'] ) ) {
					continue;
				}

				$clean_rule = array(
					'type'     => sanitize_text_field( $rule['type'] ),
					'operator' => sanitize_text_field( $rule['operator'] ?? 'is' ),
				);

				$value = $rule['value'] ?? '';
				if ( is_array( $value ) ) {
					if ( isset( $value['start'] ) || isset( $value['end'] ) ) {
						$clean_rule['value'] = array(
							'start' => sanitize_text_field( $value['start'] ?? '' ),
							'end'   => sanitize_text_field( $value['end'] ?? '' ),
						);
					} else {
						$clean_rule['value'] = array_map( 'sanitize_text_field', $value );
					}
				} else {
					$clean_rule['value'] = sanitize_text_field( $value );
				}

				$clean_group['rules'][] = $clean_rule;
			}

			if ( ! empty( $clean_group['rules'] ) ) {
				$clean['groups'][] = $clean_group;
			}
		}

		return $clean;
	}

	/**
	 * Validate PHP syntax without executing code.
	 *
	 * @since 1.0.0
	 * @param string $code PHP code to validate (without opening <?php tag).
	 * @return string|false Error message on failure, false if syntax is valid.
	 */
	private function validate_php_syntax( $code ) {
		$code = preg_replace( '/^<\?php\s*/', '', $code );
		$code = preg_replace( '/\?>\s*$/', '', $code );

		$full_code = '<?php ' . $code;

		if ( function_exists( 'proc_open' ) ) {
			$php_bin = $this->find_php_cli_binary();

			if ( $php_bin ) {
				$result = $this->run_php_lint( $php_bin, $full_code );
				if ( null !== $result ) {
					return $result;
				}
			}
		}

		// Fallback: token_get_all() catches tokenizer-level parse errors.
		try {
			$tokens = @token_get_all( $full_code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentionally silenced to capture tokenizer errors.
		} catch ( \ParseError $e ) {
			/* translators: %s: PHP syntax error message */
			return sprintf( __( 'Syntax error: %s', 'snipdrop' ), $e->getMessage() );
		}

		return false;
	}

	/**
	 * Find the PHP CLI binary path.
	 *
	 * Handles FPM/CGI contexts where PHP_BINARY points to php-fpm or php-cgi
	 * instead of the CLI binary needed for `php -l`.
	 *
	 * @since 1.0.0
	 * @return string|false PHP CLI binary path, or false if unavailable.
	 */
	private function find_php_cli_binary() {
		if ( defined( 'PHP_BINARY' ) && PHP_BINARY ) {
			$binary = PHP_BINARY;

			// If not an FPM/CGI binary, use it directly.
			if ( ! preg_match( '/php-?(fpm|cgi)/i', $binary ) ) {
				return $binary;
			}

			// Derive CLI path from FPM/CGI: /usr/sbin/php-fpm8.3 → /usr/bin/php8.3
			$cli_path = preg_replace( '#/php-?(fpm|cgi)#i', '/php', $binary );
			$cli_path = str_replace( '/sbin/', '/bin/', $cli_path );
			if ( @is_executable( $cli_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return $cli_path;
			}
		}

		return 'php';
	}

	/**
	 * Run php -l on code and return the result.
	 *
	 * @since 1.0.0
	 * @param string $php_bin  Path to PHP CLI binary.
	 * @param string $code     Full PHP code including opening tag.
	 * @return string|false|null Error message string on syntax error, false if valid,
	 *                           null if the lint process failed (caller should use fallback).
	 */
	private function run_php_lint( $php_bin, $code ) {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = @proc_open( $php_bin . ' -l', $descriptors, $pipes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Required for syntax validation without executing user code.

		if ( ! is_resource( $process ) ) {
			return null;
		}

		fwrite( $pipes[0], $code );
		fclose( $pipes[0] );

		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$exit_code = proc_close( $process );

		if ( 0 === $exit_code ) {
			return false;
		}

		$error_output = ! empty( $stderr ) ? $stderr : $stdout;

		// Non-parse errors (binary not found, permission denied, etc.) — signal caller to use fallback.
		if ( ! preg_match( '/Parse error|syntax error/i', $error_output ) ) {
			return null;
		}

		if ( preg_match( '/Parse error:\s*(.+?)(?:\s+in\s+.+)?$/mi', $error_output, $matches ) ) {
			/* translators: %s: PHP syntax error message */
			return sprintf( __( 'Syntax error: %s', 'snipdrop' ), trim( $matches[1] ) );
		}

		return __( 'The snippet contains a PHP syntax error.', 'snipdrop' );
	}

	/**
	 * AJAX: Get activity log entries.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_activity_log() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$type   = isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : '';
		$page   = isset( $_POST['log_page'] ) ? absint( $_POST['log_page'] ) : 1;
		$limit  = 20;
		$offset = ( $page - 1 ) * $limit;

		$activity_log = SNDP_Activity_Log::instance();
		$result       = $activity_log->get_entries(
			array(
				'type'   => $type,
				'limit'  => $limit,
				'offset' => $offset,
			)
		);

		$entries_html = '';
		if ( ! empty( $result['entries'] ) ) {
			foreach ( $result['entries'] as $entry ) {
				$user       = ! empty( $entry['user_id'] ) ? get_userdata( $entry['user_id'] ) : null;
				$user_name  = $user ? $user->display_name : __( 'System', 'snipdrop' );
				$badge_cls  = SNDP_Activity_Log::get_type_badge_class( $entry['type'] );
				$type_label = SNDP_Activity_Log::get_type_label( $entry['type'] );
				$time_ago   = human_time_diff( strtotime( $entry['timestamp'] ), time() );
				$context    = ! empty( $entry['context'] ) ? ucfirst( $entry['context'] ) : '&mdash;';

				$entries_html .= '<tr>';
				$entries_html .= '<td class="sndp-al-col-event"><span class="sndp-al-badge sndp-al-badge--' . esc_attr( $badge_cls ) . '">' . esc_html( $type_label ) . '</span></td>';
				$entries_html .= '<td class="sndp-al-col-details">';
				if ( ! empty( $entry['snippet_title'] ) ) {
					$entries_html .= '<strong>' . esc_html( $entry['snippet_title'] ) . '</strong>';
					if ( ! empty( $entry['snippet_type'] ) ) {
						$entries_html .= ' <span class="sndp-al-type-tag">' . esc_html( strtoupper( $entry['snippet_type'] ) ) . '</span>';
					}
				} elseif ( ! empty( $entry['details'] ) ) {
					$entries_html .= esc_html( $entry['details'] );
				} else {
					$entries_html .= '&mdash;';
				}
				if ( ! empty( $entry['snippet_title'] ) && ! empty( $entry['details'] ) ) {
					$entries_html .= '<div class="sndp-al-details">' . esc_html( $entry['details'] ) . '</div>';
				}
				$entries_html .= '</td>';
				$entries_html .= '<td class="sndp-al-col-context">' . esc_html( $context ) . '</td>';

				$entries_html .= '<td class="sndp-al-col-user">';
				if ( $user ) {
					$entries_html .= get_avatar( $user->ID, 24, '', '', array( 'class' => 'sndp-al-avatar' ) );
				}
				$entries_html .= esc_html( $user_name ) . '</td>';

				/* translators: %s: Human-readable time difference */
				$entries_html .= '<td class="sndp-al-col-time" title="' . esc_attr( $entry['timestamp'] . ' UTC' ) . '">' . sprintf( esc_html__( '%s ago', 'snipdrop' ), esc_html( $time_ago ) ) . '</td>';
				$entries_html .= '</tr>';
			}
		}

		$total_pages = ceil( $result['total'] / $limit );

		wp_send_json_success(
			array(
				'html'        => $entries_html,
				'total'       => $result['total'],
				'page'        => $page,
				'total_pages' => $total_pages,
			)
		);
	}

	/**
	 * AJAX: Clear activity log.
	 *
	 * @since 1.0.0
	 */
	public function ajax_clear_activity_log() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		SNDP_Activity_Log::instance()->clear();

		wp_send_json_success( array( 'message' => __( 'Activity log cleared.', 'snipdrop' ) ) );
	}
}
