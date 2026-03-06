<?php
/**
 * Testing Mode — stage snippet changes without affecting live visitors.
 *
 * When enabled, the current snippet state is snapshot-copied into a staging
 * option. All subsequent edits, toggles, and saves target the staging copy.
 * Admins with the SNDP_CAPABILITY see the staged version; everyone else
 * sees production. Changes can be published (copied back to live) or
 * discarded (deleted).
 *
 * Architecture: uses `pre_option_*` filters to transparently swap the data
 * that SNDP_Executor and SNDP_Custom_Snippets read, so the rest of the
 * codebase doesn't need to know about testing mode.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SNDP_Testing_Mode
 */
class SNDP_Testing_Mode {

	/**
	 * Singleton instance.
	 *
	 * @var SNDP_Testing_Mode|null
	 */
	private static $instance = null;

	/**
	 * Option name storing the testing mode state + staged data.
	 *
	 * @var string
	 */
	private $option_name = 'sndp_testing_mode';

	/**
	 * Cached testing mode data (avoids repeated DB reads).
	 *
	 * @var array|null
	 */
	private $data_cache = null;

	/**
	 * Whether we're currently inside a filter callback (prevents recursion).
	 *
	 * @var bool
	 */
	private $filtering = false;

	/**
	 * Get singleton instance.
	 *
	 * @return SNDP_Testing_Mode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — register option filters and admin hooks.
	 */
	private function __construct() {
		// AJAX handlers always need to be registered.
		add_action( 'wp_ajax_sndp_toggle_testing_mode', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_sndp_publish_testing_changes', array( $this, 'ajax_publish' ) );
		add_action( 'wp_ajax_sndp_discard_testing_changes', array( $this, 'ajax_discard' ) );
		add_action( 'wp_ajax_sndp_get_testing_changes', array( $this, 'ajax_get_changes' ) );

		// Only register heavy filters/hooks when testing mode is actually active.
		if ( $this->is_enabled() ) {
			add_filter( 'pre_option_sndp_custom_snippets', array( $this, 'maybe_filter_snippets' ) );
			add_filter( 'pre_option_sndp_global_scripts', array( $this, 'maybe_filter_global_scripts' ) );
			add_filter( 'pre_update_option_sndp_custom_snippets', array( $this, 'maybe_intercept_snippet_save' ), 10, 2 );
			add_filter( 'pre_update_option_sndp_global_scripts', array( $this, 'maybe_intercept_global_save' ), 10, 2 );
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_indicator' ), 999 );
			add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
			add_filter( 'body_class', array( $this, 'frontend_body_class' ) );
			add_action( 'admin_notices', array( $this, 'admin_banner' ) );
		}
	}

	// ------------------------------------------------------------------
	// State accessors
	// ------------------------------------------------------------------

	/**
	 * Get the raw testing mode data from the option.
	 *
	 * @return array
	 */
	public function get_data() {
		if ( null === $this->data_cache ) {
			$this->data_cache = get_option( $this->option_name, array() );
		}
		return $this->data_cache;
	}

	/**
	 * Persist testing mode data and bust the local cache.
	 *
	 * @param array $data Full testing mode data array.
	 */
	private function save_data( $data ) {
		$this->data_cache = $data;
		update_option( $this->option_name, $data, false );
	}

	/**
	 * Whether testing mode is enabled (DB flag).
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$data = $this->get_data();
		return ! empty( $data['enabled'] );
	}

	/**
	 * Whether the current request should receive staged data.
	 *
	 * Only users who can manage snippets see the test version.
	 *
	 * @return bool
	 */
	public function should_use_testing_data() {
		if ( ! $this->is_enabled() ) {
			return false;
		}
		return current_user_can( SNDP_CAPABILITY );
	}

	// ------------------------------------------------------------------
	// Enable / Publish / Discard
	// ------------------------------------------------------------------

	/**
	 * Enable testing mode — snapshot the current live state.
	 *
	 * @return bool
	 */
	public function enable() {
		if ( $this->is_enabled() ) {
			return true;
		}

		// Temporarily disable our own filters to read the real live data.
		$this->filtering = true;
		$live_snippets   = get_option( 'sndp_custom_snippets', array() );
		$live_global     = get_option( 'sndp_global_scripts', array() );
		$this->filtering = false;

		$data = array(
			'enabled'        => true,
			'enabled_at'     => current_time( 'mysql' ),
			'enabled_by'     => get_current_user_id(),
			'snippets'       => $live_snippets,
			'global_scripts' => $live_global,
			'new_snippet_ids' => array(),
		);

		$this->save_data( $data );

		return true;
	}

	/**
	 * Publish all staged changes to live, then disable testing mode.
	 *
	 * @return array Summary with counts.
	 */
	public function publish() {
		if ( ! $this->is_enabled() ) {
			return array( 'published' => 0 );
		}

		$data    = $this->get_data();
		$changes = $this->compute_changes();

		// Temporarily disable filters so we write to the real options.
		$this->filtering = true;

		if ( isset( $data['snippets'] ) ) {
			update_option( 'sndp_custom_snippets', $data['snippets'], false );
		}

		if ( isset( $data['global_scripts'] ) ) {
			update_option( 'sndp_global_scripts', $data['global_scripts'], false );
		}

		$this->filtering = false;

		// Clear testing mode.
		delete_option( $this->option_name );
		$this->data_cache = null;

		$total = count( $changes['snippets'] ) + count( $changes['global'] );

		return array( 'published' => $total );
	}

	/**
	 * Discard all staged changes and disable testing mode.
	 *
	 * New snippets created during testing mode are removed from the live
	 * data as well (they only existed in the staging copy).
	 *
	 * @return bool
	 */
	public function discard() {
		if ( ! $this->is_enabled() ) {
			return true;
		}

		$data = $this->get_data();

		// If any snippets were created during testing mode, remove them from live.
		if ( ! empty( $data['new_snippet_ids'] ) ) {
			$this->filtering  = true;
			$live              = get_option( 'sndp_custom_snippets', array() );
			$this->filtering  = false;

			foreach ( $data['new_snippet_ids'] as $new_id ) {
				unset( $live[ $new_id ] );
			}

			$this->filtering = true;
			update_option( 'sndp_custom_snippets', $live, false );
			$this->filtering = false;
		}

		delete_option( $this->option_name );
		$this->data_cache = null;

		return true;
	}

	// ------------------------------------------------------------------
	// Change detection
	// ------------------------------------------------------------------

	/**
	 * Compute what changed between staged data and live data.
	 *
	 * @return array Keys: 'snippets' (array of changed snippet info), 'global' (array of changed sections).
	 */
	public function compute_changes() {
		$data = $this->get_data();

		// Read real live data (bypass our filters).
		$this->filtering = true;
		$live_snippets   = get_option( 'sndp_custom_snippets', array() );
		$live_global     = get_option( 'sndp_global_scripts', array() );
		$this->filtering = false;

		$staged_snippets = isset( $data['snippets'] ) ? $data['snippets'] : array();
		$staged_global   = isset( $data['global_scripts'] ) ? $data['global_scripts'] : array();

		$snippet_changes = array();

		// Check for modified or new snippets.
		foreach ( $staged_snippets as $id => $staged ) {
			if ( ! isset( $live_snippets[ $id ] ) ) {
				$snippet_changes[] = array(
					'id'     => $id,
					'title'  => $staged['title'] ?? __( 'Untitled', 'snipdrop' ),
					'type'   => 'new',
					'edit'   => admin_url( 'admin.php?page=snipdrop-add&id=' . $id ),
				);
			} elseif ( $staged !== $live_snippets[ $id ] ) {
				$snippet_changes[] = array(
					'id'     => $id,
					'title'  => $staged['title'] ?? __( 'Untitled', 'snipdrop' ),
					'type'   => 'modified',
					'edit'   => admin_url( 'admin.php?page=snipdrop-add&id=' . $id ),
				);
			}
		}

		// Check for deleted snippets.
		foreach ( $live_snippets as $id => $live ) {
			if ( ! isset( $staged_snippets[ $id ] ) ) {
				$snippet_changes[] = array(
					'id'    => $id,
					'title' => $live['title'] ?? __( 'Untitled', 'snipdrop' ),
					'type'  => 'deleted',
					'edit'  => '',
				);
			}
		}

		// Check global scripts.
		$global_changes = array();
		$sections       = array(
			'header'    => __( 'Header Scripts', 'snipdrop' ),
			'body_open' => __( 'Body Scripts', 'snipdrop' ),
			'footer'    => __( 'Footer Scripts', 'snipdrop' ),
		);

		foreach ( $sections as $key => $label ) {
			if ( ( $staged_global[ $key ] ?? '' ) !== ( $live_global[ $key ] ?? '' ) ) {
				$global_changes[] = array(
					'section' => $key,
					'label'   => $label,
				);
			}
		}

		return array(
			'snippets' => $snippet_changes,
			'global'   => $global_changes,
		);
	}

	// ------------------------------------------------------------------
	// pre_option filters — transparent data swap
	// ------------------------------------------------------------------

	/**
	 * Filter: return staged snippets instead of live ones for privileged users.
	 *
	 * @param mixed $value Pre-filter value (false by default from WP).
	 * @return mixed
	 */
	public function maybe_filter_snippets( $value ) {
		if ( $this->filtering ) {
			return $value;
		}

		if ( ! $this->should_use_testing_data() ) {
			return $value;
		}

		$data = $this->get_data();

		return isset( $data['snippets'] ) ? $data['snippets'] : $value;
	}

	/**
	 * Filter: return staged global scripts instead of live ones for privileged users.
	 *
	 * @param mixed $value Pre-filter value.
	 * @return mixed
	 */
	public function maybe_filter_global_scripts( $value ) {
		if ( $this->filtering ) {
			return $value;
		}

		if ( ! $this->should_use_testing_data() ) {
			return $value;
		}

		$data = $this->get_data();

		return isset( $data['global_scripts'] ) ? $data['global_scripts'] : $value;
	}

	// ------------------------------------------------------------------
	// pre_update_option filters — intercept saves into staging
	// ------------------------------------------------------------------

	/**
	 * Intercept snippet saves and redirect them into the staging option.
	 *
	 * @param mixed $new_value New value being saved.
	 * @param mixed $old_value Previous value.
	 * @return mixed The old value (unchanged) if intercepted, or new value if not.
	 */
	public function maybe_intercept_snippet_save( $new_value, $old_value ) {
		if ( $this->filtering ) {
			return $new_value;
		}

		if ( ! $this->should_use_testing_data() ) {
			return $new_value;
		}

		$data = $this->get_data();

		// Track newly created snippet IDs.
		if ( is_array( $new_value ) && is_array( $data['snippets'] ?? array() ) ) {
			$new_ids = array_diff( array_keys( $new_value ), array_keys( $data['snippets'] ) );
			if ( ! empty( $new_ids ) ) {
				$data['new_snippet_ids'] = array_unique( array_merge( $data['new_snippet_ids'] ?? array(), $new_ids ) );
			}
		}

		$data['snippets'] = $new_value;
		$this->save_data( $data );

		// Return old_value so the real option stays unchanged.
		$this->filtering = true;
		$live            = get_option( 'sndp_custom_snippets', array() );
		$this->filtering = false;

		return $live;
	}

	/**
	 * Intercept global scripts saves and redirect into staging.
	 *
	 * @param mixed $new_value New value.
	 * @param mixed $old_value Previous value.
	 * @return mixed
	 */
	public function maybe_intercept_global_save( $new_value, $old_value ) {
		if ( $this->filtering ) {
			return $new_value;
		}

		if ( ! $this->should_use_testing_data() ) {
			return $new_value;
		}

		$data                   = $this->get_data();
		$data['global_scripts'] = $new_value;
		$this->save_data( $data );

		// Return real live value so the real option stays unchanged.
		$this->filtering = true;
		$live            = get_option( 'sndp_global_scripts', array() );
		$this->filtering = false;

		return $live;
	}

	// ------------------------------------------------------------------
	// Admin UI indicators
	// ------------------------------------------------------------------

	/**
	 * Add a "Testing Mode" node to the admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function admin_bar_indicator( $wp_admin_bar ) {
		if ( ! $this->is_enabled() || ! current_user_can( SNDP_CAPABILITY ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'sndp-testing-mode',
				'title' => '<span class="ab-icon dashicons dashicons-visibility"></span> ' . __( 'SnipDrop: Testing Mode', 'snipdrop' ),
				'href'  => admin_url( 'admin.php?page=snipdrop-settings' ),
				'meta'  => array(
					'class' => 'sndp-testing-mode-bar',
				),
			)
		);
	}

	/**
	 * Add body class when testing mode is active.
	 *
	 * @param string $classes Space-separated class string.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( $this->should_use_testing_data() ) {
			$classes .= ' sndp-testing-mode-active';
		}
		return $classes;
	}

	/**
	 * Add body class on frontend when testing mode is active for admin.
	 *
	 * @param array $classes Body classes array.
	 * @return array
	 */
	public function frontend_body_class( $classes ) {
		if ( $this->should_use_testing_data() ) {
			$classes[] = 'sndp-testing-mode-active';
		}
		return $classes;
	}

	/**
	 * Show yellow warning banner on all SnipDrop admin pages when testing mode is active.
	 * The "off" state toggle is embedded directly in each page template's toolbar.
	 */
	public function admin_banner() {
		if ( ! $this->is_enabled() || ! current_user_can( SNDP_CAPABILITY ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'snipdrop' ) === false ) {
			return;
		}

		$data = $this->get_data();
		$user = ! empty( $data['enabled_by'] ) ? get_userdata( $data['enabled_by'] ) : null;
		$date = ! empty( $data['enabled_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $data['enabled_at'] ) ) : '';
		?>
		<div class="notice sndp-testing-mode-banner">
			<div class="sndp-testing-banner-content">
				<span class="sndp-testing-banner-icon">
					<span class="dashicons dashicons-visibility"></span>
				</span>
				<div class="sndp-testing-banner-text">
					<strong><?php esc_html_e( 'Testing Mode is Active', 'snipdrop' ); ?></strong>
					<span>
						<?php
						esc_html_e( 'Your changes are only visible to admins. Visitors see the live version.', 'snipdrop' );
						if ( $date ) {
							/* translators: 1: date/time, 2: user display name */
							printf( ' ' . esc_html__( 'Enabled %1$s by %2$s.', 'snipdrop' ), esc_html( $date ), esc_html( $user ? $user->display_name : __( 'Unknown', 'snipdrop' ) ) );
						}
						?>
					</span>
				</div>
				<div class="sndp-testing-banner-actions">
					<button type="button" class="button button-small" id="sndp-testing-view-changes">
						<?php esc_html_e( 'View Changes', 'snipdrop' ); ?>
					</button>
					<button type="button" class="button button-primary button-small" id="sndp-testing-publish">
						<?php esc_html_e( 'Publish All', 'snipdrop' ); ?>
					</button>
					<button type="button" class="button button-link-delete button-small" id="sndp-testing-discard">
						<?php esc_html_e( 'Discard', 'snipdrop' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// AJAX handlers
	// ------------------------------------------------------------------

	/**
	 * AJAX: Toggle testing mode on or off.
	 */
	public function ajax_toggle() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$enable = isset( $_POST['enable'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['enable'] ) );

		if ( $enable ) {
			$this->enable();
			wp_send_json_success(
				array(
					'message' => __( 'Testing mode enabled. Your changes are now staged.', 'snipdrop' ),
					'enabled' => true,
				)
			);
		} else {
			// When disabling via toggle, just return the changes for confirmation.
			$changes = $this->compute_changes();
			$has_changes = ! empty( $changes['snippets'] ) || ! empty( $changes['global'] );

			if ( ! $has_changes ) {
				// No changes, just disable.
				$this->discard();
				wp_send_json_success(
					array(
						'message' => __( 'Testing mode disabled. No changes to publish.', 'snipdrop' ),
						'enabled' => false,
					)
				);
			} else {
				wp_send_json_success(
					array(
						'message'     => __( 'You have unpublished changes. Publish or discard them.', 'snipdrop' ),
						'has_changes' => true,
						'changes'     => $changes,
					)
				);
			}
		}
	}

	/**
	 * AJAX: Publish all staged changes to live.
	 */
	public function ajax_publish() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$result = $this->publish();

		SNDP_Activity_Log::instance()->log(
			'testing_publish',
			array(
				'context' => 'settings',
				'details' => sprintf(
					/* translators: %d: number of published changes */
					__( 'Published %d testing mode change(s) to live', 'snipdrop' ),
					$result['published']
				),
			)
		);

		wp_send_json_success(
			array(
				/* translators: %d: number of published changes */
				'message'   => sprintf( __( 'Published %d change(s) to live. Testing mode disabled.', 'snipdrop' ), $result['published'] ),
				'published' => $result['published'],
			)
		);
	}

	/**
	 * AJAX: Discard all staged changes.
	 */
	public function ajax_discard() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$this->discard();

		SNDP_Activity_Log::instance()->log(
			'testing_discard',
			array(
				'context' => 'settings',
				'details' => __( 'Discarded all testing mode changes', 'snipdrop' ),
			)
		);

		wp_send_json_success(
			array( 'message' => __( 'All changes discarded. Testing mode disabled.', 'snipdrop' ) )
		);
	}

	/**
	 * AJAX: Get a summary of what changed in testing mode.
	 */
	public function ajax_get_changes() {
		check_ajax_referer( 'sndp_admin_nonce', 'nonce' );

		if ( ! current_user_can( SNDP_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snipdrop' ) ) );
		}

		$changes = $this->compute_changes();

		wp_send_json_success( array( 'changes' => $changes ) );
	}
}
