<?php
/**
 * Main Plugin Bootstrap
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton that wires up the admin page and REST routes.
 */
final class MKB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MKB_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return MKB_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {
		// Load text domain for translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register REST routes.
		add_action( 'rest_api_init', array( 'MKB_REST_Controller', 'register_routes' ) );

		// Register the Settings page.
		if ( is_admin() ) {
			MKB_Admin_Page::init();
		}
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'mega-kadence-bridge',
			false,
			dirname( plugin_basename( MKB_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize MKB_Plugin singleton.' );
	}
}
