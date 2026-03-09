<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * SnipDrop - Ready-to-Use Code Snippets
 *
 * @package           SnipDrop
 * @author            Shameem Reza
 * @copyright         2026 Shameem Reza
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       SnipDrop
 * Plugin URI:        https://github.com/shameemreza/snipdrop
 * Description:       Ready-to-use code snippets for WordPress and WooCommerce. Just enable and go - no coding required.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Shameem Reza
 * Author URI:        https://shameem.dev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       snipdrop
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'SNDP_VERSION', '1.0.0' );
define( 'SNDP_PLUGIN_FILE', __FILE__ );
define( 'SNDP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNDP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SNDP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Library URL for fetching snippets.
define( 'SNDP_LIBRARY_URL', 'https://raw.githubusercontent.com/shameemreza/snipdrop-library/main/' );

// Custom capability for managing snippets.
define( 'SNDP_CAPABILITY', 'sndp_manage_snippets' );

/**
 * Main SnipDrop class.
 *
 * @since 1.0.0
 */
final class SnipDrop {

	/**
	 * Single instance of the class.
	 *
	 * @var SnipDrop
	 */
	private static $instance = null;

	/**
	 * Main SnipDrop Instance.
	 *
	 * Ensures only one instance of SnipDrop is loaded.
	 *
	 * @since 1.0.0
	 * @return SnipDrop
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		// Core classes.
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-library.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-snippets.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-custom-snippets.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-executor.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-error-handler.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-compatibility.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-conflicts.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-conditional-logic.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-testing-mode.php';
		require_once SNDP_PLUGIN_DIR . 'includes/class-sndp-activity-log.php';

		// Admin classes.
		if ( is_admin() ) {
			require_once SNDP_PLUGIN_DIR . 'includes/admin/class-sndp-admin.php';
			require_once SNDP_PLUGIN_DIR . 'includes/admin/class-sndp-importer.php';
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Activation/Deactivation hooks.
		register_activation_hook( SNDP_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( SNDP_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Initialize plugin.
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );

		// Map custom capability to manage_options as fallback.
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . SNDP_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		// Scheduled log cleanup.
		add_action( 'sndp_cleanup_logs', array( $this, 'run_log_cleanup' ) );
	}

	/**
	 * Cron callback: clean up old error log files.
	 *
	 * @since 1.0.0
	 */
	public function run_log_cleanup() {
		SNDP_Error_Handler::instance()->clear_old_logs();
	}

	/**
	 * Map sndp_manage_snippets to manage_options for users who lack the
	 * explicit capability. This fires only when sndp_manage_snippets is
	 * checked, not on every current_user_can() call.
	 *
	 * @since 1.0.0
	 * @param string[] $caps    Required primitive capabilities for the requested cap.
	 * @param string   $cap     The capability being checked.
	 * @param int      $user_id The user ID.
	 * @param array    $args    Additional arguments passed to the capability check.
	 * @return string[]
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( SNDP_CAPABILITY === $cap ) {
			$user = get_userdata( $user_id );
			if ( $user && $user->has_cap( 'manage_options' ) ) {
				$caps = array( 'manage_options' );
			}
		}
		return $caps;
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=snipdrop' ) ) . '">' . esc_html__( 'Snippets', 'snipdrop' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=snipdrop-settings' ) ) . '">' . esc_html__( 'Settings', 'snipdrop' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Initialize library.
		SNDP_Library::instance();

		// Initialize activity log.
		SNDP_Activity_Log::instance();

		// Initialize testing mode (must run before executor so filters are in place).
		SNDP_Testing_Mode::instance();

		// Initialize snippet executor.
		SNDP_Executor::instance();

		// Initialize error handler.
		SNDP_Error_Handler::instance();

		// Initialize admin.
		if ( is_admin() ) {
			SNDP_Admin::instance();
			SNDP_Importer::instance();
		}
	}

	/**
	 * Plugin activation.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Set default options.
		$defaults = array(
			'enabled_snippets' => array(),
			'last_sync'        => 0,
			'safe_mode'        => false,
			'secret_key'       => wp_generate_password( 32, false ),
		);

		if ( ! get_option( 'sndp_settings' ) ) {
			add_option( 'sndp_settings', $defaults );
		}

		// Grant custom capability to administrator role.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( SNDP_CAPABILITY );
		}

		// Schedule weekly log cleanup.
		if ( ! wp_next_scheduled( 'sndp_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'weekly', 'sndp_cleanup_logs' );
		}

		// Set activation transient for welcome notice.
		set_transient( 'sndp_activated', true, 30 );
	}

	/**
	 * Plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'sndp_scheduled_sync' );
		wp_clear_scheduled_hook( 'sndp_cleanup_logs' );

		// Remove custom capability from administrator role.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( SNDP_CAPABILITY );
		}
	}
}

/**
 * Returns the main instance of SnipDrop.
 *
 * @since 1.0.0
 * @return SnipDrop
 */
function snipdrop() { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
	return SnipDrop::instance();
}

// Initialize the plugin.
snipdrop();
