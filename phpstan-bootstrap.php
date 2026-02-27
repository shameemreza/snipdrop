<?php
/**
 * PHPStan bootstrap file.
 *
 * Defines plugin constants for static analysis.
 *
 * @package SnipDrop
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Plugin constants.
define( 'SNDP_VERSION', '1.0.0' );
define( 'SNDP_PLUGIN_FILE', __DIR__ . '/snipdrop.php' );
define( 'SNDP_PLUGIN_DIR', __DIR__ . '/' );
define( 'SNDP_PLUGIN_URL', 'https://example.com/wp-content/plugins/snipdrop/' );
define( 'SNDP_PLUGIN_BASENAME', 'snipdrop/snipdrop.php' );
define( 'SNDP_LIBRARY_URL', 'https://raw.githubusercontent.com/shameemreza/snipdrop-library/main/' );
define( 'SNDP_CAPABILITY', 'sndp_manage_snippets' );
