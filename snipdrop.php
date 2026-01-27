<?php
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
 * Version:           1.1.0
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
define( 'SNDP_VERSION', '1.1.0' );
define( 'SNDP_PLUGIN_FILE', __FILE__ );
define( 'SNDP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNDP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SNDP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Library URL for fetching snippets.
define( 'SNDP_LIBRARY_URL', 'https://raw.githubusercontent.com/shameemreza/snipdrop-library/main/' );

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

		// Admin classes.
		if ( is_admin() ) {
			require_once SNDP_PLUGIN_DIR . 'includes/admin/class-sndp-admin.php';
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

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . SNDP_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
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

		// Initialize snippet executor.
		SNDP_Executor::instance();

		// Initialize error handler.
		SNDP_Error_Handler::instance();

		// Initialize admin.
		if ( is_admin() ) {
			SNDP_Admin::instance();
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

		// Set activation transient for welcome notice.
		set_transient( 'sndp_activated', true, 30 );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

/**
 * Returns the main instance of SnipDrop.
 *
 * @since 1.0.0
 * @return SnipDrop
 */
function snipdrop() {
	return SnipDrop::instance();
}

// Initialize the plugin.
snipdrop();
