<?php
/**
 * Plugin Name:       Mega Kadence Bridge
 * Plugin URI:        https://github.com/jonjonesai/mega-kadence-bridge
 * Description:       REST API bridge that lets an AI agent (Claude, Cursor, any client) become a master of Kadence on this WordPress site. Exposes Kadence-fluent endpoints for theme mods, palette, blocks, header/footer, content, media, WooCommerce, plugins, and history — plus a /capabilities discovery endpoint that teaches the agent how to operate Kadence correctly. POD stores are one application; any Kadence site is in scope.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jon Jones AI
 * Author URI:        https://github.com/jonjonesai
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mega-kadence-bridge
 * GitHub Plugin URI: jonjonesai/mega-kadence-bridge
 *
 * @package MegaKadenceBridge
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MKB_VERSION', '1.2.1' );
define( 'MKB_PLUGIN_FILE', __FILE__ );
define( 'MKB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MKB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MKB_REST_NAMESPACE', 'mega-kadence-bridge/v1' );
define( 'MKB_BOT_USERNAME', 'claude-bot' );
define( 'MKB_APP_PASSWORD_NAME', 'Mega Kadence Bridge' );
define( 'MKB_CREDENTIALS_DIR', WP_CONTENT_DIR . '/.claude-bridge' );
define( 'MKB_CREDENTIALS_FILE', MKB_CREDENTIALS_DIR . '/credentials.json' );
define( 'MKB_LOCKED_DOMAIN_OPTION', 'mkb_locked_domain' );

// Autoload plugin classes.
require_once MKB_PLUGIN_DIR . 'includes/class-plugin.php';
require_once MKB_PLUGIN_DIR . 'includes/class-activator.php';
require_once MKB_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once MKB_PLUGIN_DIR . 'includes/class-history.php';
require_once MKB_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once MKB_PLUGIN_DIR . 'includes/class-instructions.php';
require_once MKB_PLUGIN_DIR . 'includes/class-rest-controller.php';

// Endpoint files — loaded defensively so a missing/corrupted file (e.g. host
// auto-updater truncated a file, OPcache evicted a compiled copy, GitHub
// release zip was incomplete) degrades MKB to a partial state instead of
// killing the entire WP REST stack with a `require_once` fatal. The
// class_exists() guards in MKB_REST_Controller::register_routes() then skip
// the missing endpoint and surface it as an admin notice.
$mkb_endpoint_files = array(
	'class-capabilities-endpoints.php',
	'class-core-endpoints.php',
	'class-theme-endpoints.php',
	'class-content-endpoints.php',
	'class-media-endpoints.php',
	'class-kadence-endpoints.php',
	'class-woo-endpoints.php',
	'class-history-endpoints.php',
	'class-plugin-endpoints.php',
);
foreach ( $mkb_endpoint_files as $mkb_endpoint_file ) {
	$mkb_endpoint_path = MKB_PLUGIN_DIR . 'includes/endpoints/' . $mkb_endpoint_file;
	if ( file_exists( $mkb_endpoint_path ) ) {
		require_once $mkb_endpoint_path;
	} else {
		error_log( '[mega-kadence-bridge] Missing endpoint file: ' . $mkb_endpoint_path );
	}
}
unset( $mkb_endpoint_file, $mkb_endpoint_path, $mkb_endpoint_files );

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'MKB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MKB_Deactivator', 'deactivate' ) );

// Boot the plugin on plugins_loaded.
add_action(
	'plugins_loaded',
	function () {
		MKB_Plugin::instance();
	}
);

// Initialize plugin-update-checker for GitHub releases, if bundled.
$puc_init = MKB_PLUGIN_DIR . 'includes/lib/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $puc_init ) ) {
	require_once $puc_init;
	$update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/jonjonesai/mega-kadence-bridge/',
		__FILE__,
		'mega-kadence-bridge'
	);
	$update_checker->getVcsApi()->enableReleaseAssets();
	$update_checker->setBranch( 'main' );
}
