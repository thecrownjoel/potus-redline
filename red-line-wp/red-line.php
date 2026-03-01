<?php
/**
 * Plugin Name: Red Line
 * Plugin URI:  https://www.whitehouse.gov
 * Description: Bot-proof, verified-citizen communication channel. Direct hotline between the White House and the American people.
 * Version:     1.0.0
 * Author:      Executive Office of the President
 * License:     GPL-2.0-or-later
 * Text Domain: red-line
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'REDLINE_VERSION', '1.0.0' );
define( 'REDLINE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REDLINE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Includes.
require_once REDLINE_PLUGIN_DIR . 'includes/class-activator.php';
require_once REDLINE_PLUGIN_DIR . 'includes/class-devices.php';
require_once REDLINE_PLUGIN_DIR . 'includes/class-alerts.php';
require_once REDLINE_PLUGIN_DIR . 'includes/class-polls.php';
require_once REDLINE_PLUGIN_DIR . 'includes/class-desk.php';
require_once REDLINE_PLUGIN_DIR . 'includes/class-analytics.php';
require_once REDLINE_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once REDLINE_PLUGIN_DIR . 'includes/class-mock-data.php';

if ( is_admin() ) {
	require_once REDLINE_PLUGIN_DIR . 'admin/class-admin.php';
}

// Activation hook.
register_activation_hook( __FILE__, array( 'RedLine_Activator', 'activate' ) );

// Initialize REST API.
add_action( 'rest_api_init', array( 'RedLine_REST_API', 'register_routes' ) );

// Initialize admin.
if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'RedLine_Admin', 'init' ) );
}
