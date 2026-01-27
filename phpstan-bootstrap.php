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
define( 'SNDP_VERSION', '1.1.0' );
define( 'SNDP_PLUGIN_FILE', '/path/to/snipdrop.php' );
define( 'SNDP_PLUGIN_DIR', '/path/to/snipdrop/' );
define( 'SNDP_PLUGIN_URL', 'https://example.com/wp-content/plugins/snipdrop/' );
define( 'SNDP_PLUGIN_BASENAME', 'snipdrop/snipdrop.php' );
