<?php
/**
 * Plugin Endpoints — install, activate, list WP plugins.
 *
 * Routes:
 *   GET  /plugins
 *   POST /plugins/install
 *   POST /plugins/activate
 *   POST /plugins/install-and-activate
 *
 * Used by the deploy wizard to install Fluent Forms (and any user-supplied
 * premium plugin ZIPs) without manual WP admin clicks. claude-bot already
 * has the administrator role per class-activator.php, so it inherits the
 * activate_plugins and install_plugins capabilities natively.
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Plugin_Endpoints {

	public static function register( $ns ) {
		$permission = array( 'MKB_REST_Controller', 'check_permission' );

		register_rest_route(
			$ns,
			'/plugins',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_plugins' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/plugins/install',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'install_plugin' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/plugins/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'activate_plugin_endpoint' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/plugins/install-and-activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'install_and_activate' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * GET /plugins
	 *
	 * Returns all installed plugins with active state. Useful for idempotency
	 * checks before running install.
	 */
	public static function list_plugins( $request ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all     = get_plugins();
		$active  = (array) get_option( 'active_plugins', array() );
		$plugins = array();

		foreach ( $all as $file => $data ) {
			$plugins[] = array(
				'file'    => $file,
				'name'    => $data['Name'],
				'version' => $data['Version'],
				'active'  => in_array( $file, $active, true ),
			);
		}

		return MKB_REST_Controller::success( array( 'plugins' => $plugins ) );
	}

	/**
	 * POST /plugins/install
	 *
	 * Body: { slug: "fluentform" }       -- install from wordpress.org by slug
	 *   OR  { zip_url: "https://..." }   -- install from a ZIP URL (premium)
	 *
	 * Returns: { installed: true, plugin: "fluentform/fluentform.php" }
	 */
	public static function install_plugin( $request ) {
		$body    = $request->get_json_params();
		$slug    = isset( $body['slug'] ) ? sanitize_key( $body['slug'] ) : '';
		$zip_url = isset( $body['zip_url'] ) ? esc_url_raw( $body['zip_url'] ) : '';

		if ( ! $slug && ! $zip_url ) {
			return MKB_REST_Controller::error(
				'missing_source',
				'Provide either slug (wordpress.org plugin slug) or zip_url.',
				400
			);
		}

		$result = self::do_install( $slug, $zip_url );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		MKB_History::record(
			'plugin_install',
			$result,
			null,
			array(
				'slug'    => $slug,
				'zip_url' => $zip_url,
			)
		);

		return MKB_REST_Controller::success(
			array(
				'installed' => true,
				'plugin'    => $result,
			)
		);
	}

	/**
	 * POST /plugins/activate
	 *
	 * Body: { plugin: "fluentform/fluentform.php" }
	 */
	public static function activate_plugin_endpoint( $request ) {
		$body   = $request->get_json_params();
		$plugin = isset( $body['plugin'] ) ? sanitize_text_field( $body['plugin'] ) : '';

		if ( empty( $plugin ) ) {
			return MKB_REST_Controller::error( 'missing_plugin', 'plugin (file path) is required.', 400 );
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// is_plugin_active() is idempotent-friendly — short-circuit if already active.
		if ( is_plugin_active( $plugin ) ) {
			return MKB_REST_Controller::success(
				array(
					'activated'      => true,
					'already_active' => true,
					'plugin'         => $plugin,
				)
			);
		}

		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		MKB_History::record( 'plugin_activate', $plugin, null, null );

		return MKB_REST_Controller::success(
			array(
				'activated' => true,
				'plugin'    => $plugin,
			)
		);
	}

	/**
	 * POST /plugins/install-and-activate
	 *
	 * Body: { slug: "fluentform" }  OR  { zip_url: "https://..." }
	 *
	 * Convenience combo for the deploy DAG.
	 */
	public static function install_and_activate( $request ) {
		$body    = $request->get_json_params();
		$slug    = isset( $body['slug'] ) ? sanitize_key( $body['slug'] ) : '';
		$zip_url = isset( $body['zip_url'] ) ? esc_url_raw( $body['zip_url'] ) : '';

		if ( ! $slug && ! $zip_url ) {
			return MKB_REST_Controller::error(
				'missing_source',
				'Provide either slug or zip_url.',
				400
			);
		}

		// Idempotency: if a matching plugin is already active, return early.
		// We do a best-effort match on the plugin slug-folder portion of the file path.
		if ( $slug ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$active = (array) get_option( 'active_plugins', array() );
			foreach ( $active as $active_file ) {
				if ( strpos( $active_file, $slug . '/' ) === 0 ) {
					return MKB_REST_Controller::success(
						array(
							'installed'      => true,
							'activated'      => true,
							'already_active' => true,
							'plugin'         => $active_file,
						)
					);
				}
			}
		}

		$plugin_file = self::do_install( $slug, $zip_url );
		if ( is_wp_error( $plugin_file ) ) {
			return $plugin_file;
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$activate_result = activate_plugin( $plugin_file );
		if ( is_wp_error( $activate_result ) ) {
			return MKB_REST_Controller::success(
				array(
					'installed'        => true,
					'activated'        => false,
					'plugin'           => $plugin_file,
					'activation_error' => $activate_result->get_error_message(),
				)
			);
		}

		MKB_History::record(
			'plugin_install_and_activate',
			$plugin_file,
			null,
			array(
				'slug'    => $slug,
				'zip_url' => $zip_url,
			)
		);

		return MKB_REST_Controller::success(
			array(
				'installed' => true,
				'activated' => true,
				'plugin'    => $plugin_file,
			)
		);
	}

	/**
	 * Shared install routine. Returns the plugin file path (e.g.
	 * "fluentform/fluentform.php") on success, or WP_Error on failure.
	 *
	 * @param string $slug    wordpress.org plugin slug, or empty.
	 * @param string $zip_url Direct ZIP URL, or empty.
	 * @return string|WP_Error
	 */
	private static function do_install( $slug, $zip_url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$download_url = '';
		if ( $slug ) {
			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);
			if ( is_wp_error( $api ) ) {
				return $api;
			}
			$download_url = isset( $api->download_link ) ? $api->download_link : '';
			if ( empty( $download_url ) ) {
				return new WP_Error(
					'mkb_no_download_link',
					sprintf( 'wordpress.org returned no download link for slug %s.', $slug ),
					array( 'status' => 502 )
				);
			}
		} else {
			$download_url = $zip_url;
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $download_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			$messages = $skin->get_error_messages();
			return new WP_Error(
				'mkb_install_failed',
				'Plugin install failed: ' . ( $messages ? implode( '; ', $messages ) : 'unknown error' ),
				array( 'status' => 500 )
			);
		}

		// plugin_info() returns the plugin file path (e.g. "fluentform/fluentform.php").
		$plugin_file = $upgrader->plugin_info();
		if ( empty( $plugin_file ) ) {
			return new WP_Error(
				'mkb_install_no_plugin_file',
				'Plugin installed but no plugin file path could be resolved.',
				array( 'status' => 500 )
			);
		}

		return $plugin_file;
	}
}
