<?php
/**
 * Plugin Name:       Mega Kadence Bridge
 * Plugin URI:        https://github.com/jonjonesai/mega-kadence-bridge
 * Description:       REST API bridge that lets Claude (via Claude Code CLI) operate a WordPress site running Kadence Theme, Kadence Blocks, and WooCommerce. Installs a dedicated claude-bot user with an Application Password and exposes endpoints for theme mods, content, palette, CSS, cache, and commerce operations.
 * Version:           1.0.1
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
define( 'MKB_VERSION', '1.0.1' );
define( 'MKB_PLUGIN_FILE', __FILE__ );
define( 'MKB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MKB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MKB_REST_NAMESPACE', 'mega-kadence-bridge/v1' );
define( 'MKB_BOT_USERNAME', 'claude-bot' );
define( 'MKB_APP_PASSWORD_NAME', 'Mega Kadence Bridge' );
define( 'MKB_CREDENTIALS_DIR', WP_CONTENT_DIR . '/.claude-bridge' );
define( 'MKB_CREDENTIALS_FILE', MKB_CREDENTIALS_DIR . '/credentials.json' );

// Autoload plugin classes.
require_once MKB_PLUGIN_DIR . 'includes/class-plugin.php';
require_once MKB_PLUGIN_DIR . 'includes/class-activator.php';
require_once MKB_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once MKB_PLUGIN_DIR . 'includes/class-history.php';
require_once MKB_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once MKB_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once MKB_PLUGIN_DIR . 'includes/endpoints/class-core-endpoints.php';
require_once MKB_PLUGIN_DIR . 'includes/endpoints/class-theme-endpoints.php';
require_once MKB_PLUGIN_DIR . 'includes/endpoints/class-content-endpoints.php';
require_once MKB_PLUGIN_DIR . 'includes/endpoints/class-media-endpoints.php';
require_once MKB_PLUGIN_DIR . 'includes/endpoints/class-kadence-endpoints.php';
require_once MKB_PLUGIN_DIR . 'includes/endpoints/class-woo-endpoints.php';
require_once MKB_PLUGIN_DIR . 'includes/endpoints/class-history-endpoints.php';

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
