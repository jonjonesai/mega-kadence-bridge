<?php
/**
 * Core Endpoints — site-wide, non-Kadence-specific operations.
 *
 * Routes:
 *   GET  /info              Site information
 *   GET  /render            Cache-bypassed HTML render of any URL
 *   POST /cache/flush       Flush all known cache layers
 *   GET  /plugins           List active plugins
 *   POST /wp-eval           Execute PHP (guarded, for expert use)
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Core_Endpoints {

	public static function register( $ns ) {
		register_rest_route(
			$ns,
			'/info',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_info' ),
				'permission_callback' => array( 'MKB_REST_Controller', 'check_permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/render',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'render_page' ),
				'permission_callback' => array( 'MKB_REST_Controller', 'check_permission' ),
				'args'                => array(
					'url' => array(
						'default'           => '/',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/cache/flush',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'flush_cache' ),
				'permission_callback' => array( 'MKB_REST_Controller', 'check_permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/plugins',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_plugins' ),
				'permission_callback' => array( 'MKB_REST_Controller', 'check_permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/wp-eval',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'wp_eval' ),
				'permission_callback' => array( 'MKB_REST_Controller', 'check_permission' ),
			)
		);
	}

	/**
	 * GET /info
	 */
	public static function get_info( $request ) {
		$theme = wp_get_theme();

		return MKB_REST_Controller::success(
			array(
				'name'               => get_bloginfo( 'name' ),
				'description'        => get_bloginfo( 'description' ),
				'url'                => home_url(),
				'admin_url'          => admin_url(),
				'wp_version'         => get_bloginfo( 'version' ),
				'php_version'        => phpversion(),
				'theme'              => $theme->get( 'Name' ),
				'theme_version'      => $theme->get( 'Version' ),
				'is_multisite'       => is_multisite(),
				'timezone'           => wp_timezone_string(),
				'date_format'        => get_option( 'date_format' ),
				'time_format'        => get_option( 'time_format' ),
				'permalink_structure' => get_option( 'permalink_structure' ),
				'bridge_version'     => MKB_VERSION,
				'kadence_pro_active' => class_exists( 'Kadence_Theme_Pro' ),
				'kadence_blocks_pro' => class_exists( 'Kadence_Blocks_Pro' ),
				'woocommerce_active' => class_exists( 'WooCommerce' ),
			)
		);
	}

	/**
	 * GET /render — fetch a page's rendered HTML, bypassing cache when possible.
	 */
	public static function render_page( $request ) {
		$url      = $request->get_param( 'url' );
		$full_url = home_url( $url );

		// Append a cache-busting query param to discourage page cache hits.
		$cache_buster = add_query_arg( 'mkb_nocache', time(), $full_url );

		$response = wp_remote_get(
			$cache_buster,
			array(
				'timeout'   => 30,
				'sslverify' => false,
				'headers'   => array(
					'Cache-Control' => 'no-cache',
					'Pragma'        => 'no-cache',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return MKB_REST_Controller::error(
				'render_failed',
				$response->get_error_message(),
				500
			);
		}

		return MKB_REST_Controller::success(
			array(
				'url'    => $full_url,
				'status' => wp_remote_retrieve_response_code( $response ),
				'html'   => wp_remote_retrieve_body( $response ),
			)
		);
	}

	/**
	 * POST /cache/flush — clear every cache layer we can detect.
	 */
	public static function flush_cache( $request ) {
		$flushed = array();

		wp_cache_flush();
		$flushed[] = 'wp_object_cache';

		// LiteSpeed Cache (Hostinger default).
		if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
			LiteSpeed_Cache_API::purge_all();
			$flushed[] = 'litespeed';
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$flushed[] = 'litespeed_hook';
		}

		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$flushed[] = 'wp_super_cache';
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$flushed[] = 'w3_total_cache';
		}

		// WP Fastest Cache.
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache();
			$flushed[] = 'wp_fastest_cache';
		}

		// Autoptimize.
		if ( class_exists( 'autoptimizeCache' ) ) {
			autoptimizeCache::clearall();
			$flushed[] = 'autoptimize';
		}

		// Cache Enabler.
		if ( has_action( 'cache_enabler_clear_complete_cache' ) ) {
			do_action( 'cache_enabler_clear_complete_cache' );
			$flushed[] = 'cache_enabler';
		}

		// Clear all transients.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
		$flushed[] = 'transients';

		return MKB_REST_Controller::success(
			array(
				'flushed'   => $flushed,
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * GET /plugins — list all plugins with active status.
	 */
	public static function list_plugins( $request ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$plugins = array();
		foreach ( $all_plugins as $path => $plugin ) {
			$plugins[] = array(
				'name'    => $plugin['Name'],
				'version' => $plugin['Version'],
				'active'  => in_array( $path, $active_plugins, true ),
				'path'    => $path,
			);
		}

		return MKB_REST_Controller::success(
			array(
				'plugins'      => $plugins,
				'total'        => count( $plugins ),
				'active_count' => count( $active_plugins ),
			)
		);
	}

	/**
	 * POST /wp-eval — execute arbitrary PHP. Guarded and audit-logged.
	 */
	public static function wp_eval( $request ) {
		$body = $request->get_json_params();
		$code = isset( $body['code'] ) ? $body['code'] : '';

		if ( empty( $code ) ) {
			return MKB_REST_Controller::error( 'missing_code', 'PHP code is required.', 400 );
		}

		// Audit the eval attempt even if it fails.
		MKB_History::record(
			'wp_eval',
			'<inline>',
			null,
			substr( $code, 0, 500 ),
			array( 'user_id' => get_current_user_id() )
		);

		ob_start();
		$result = eval( $code ); // phpcs:ignore Squiz.PHP.Eval
		$output = ob_get_clean();

		return MKB_REST_Controller::success(
			array(
				'result' => $result,
				'output' => $output,
			)
		);
	}
}
