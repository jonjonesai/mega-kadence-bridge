<?php
/**
 * REST Controller
 *
 * Central registration point for all bridge REST routes. Delegates actual
 * handling to the endpoint classes in includes/endpoints/.
 *
 * Authentication model:
 *   All endpoints require the request to be authenticated as a user with
 *   `manage_options` capability. In practice this is the claude-bot user
 *   authenticating via its Application Password (HTTP Basic Auth, handled
 *   natively by WordPress 5.6+).
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_REST_Controller {

	/**
	 * Endpoint classes that should be registered, in order.
	 *
	 * Each is wrapped in a class_exists() guard at registration time so that a
	 * single missing or corrupted endpoint file degrades MKB to a partial state
	 * instead of killing the entire WP REST stack with a fatal. A missing class
	 * is logged and surfaced via an admin notice; other endpoints still register.
	 *
	 * @since 1.2.1
	 * @var array<string,string> Map of class name => human label for logs/notices.
	 */
	private static $endpoint_classes = array(
		'MKB_Capabilities_Endpoints' => 'capabilities',
		'MKB_Core_Endpoints'         => 'core',
		'MKB_Theme_Endpoints'        => 'theme',
		'MKB_Content_Endpoints'      => 'content',
		'MKB_Media_Endpoints'        => 'media',
		'MKB_Kadence_Endpoints'      => 'kadence',
		'MKB_History_Endpoints'      => 'history',
		'MKB_Plugin_Endpoints'       => 'plugin',
	);

	/**
	 * Endpoint classes that failed their class_exists() check this request.
	 *
	 * Populated by register_routes() and read by the admin-notice hook so the
	 * site operator sees which endpoint files need re-uploading.
	 *
	 * @since 1.2.1
	 * @var string[]
	 */
	private static $missing_endpoints = array();

	/**
	 * Register all routes.
	 *
	 * Hardened against partial-install corruption: if any endpoint class is
	 * missing (e.g. host auto-updater truncated a file, OPcache evicted a
	 * compiled copy, GitHub release zip was incomplete), that endpoint is
	 * skipped and recorded. The rest of the REST stack — including unrelated
	 * plugins' endpoints — continues to register cleanly.
	 */
	public static function register_routes() {
		$ns = MKB_REST_NAMESPACE;

		foreach ( self::$endpoint_classes as $class => $label ) {
			if ( ! class_exists( $class ) ) {
				self::$missing_endpoints[] = $label;
				error_log(
					sprintf(
						'[mega-kadence-bridge] Endpoint class %s not loaded — %s routes skipped. Reinstall the plugin or restore includes/endpoints/.',
						$class,
						$label
					)
				);
				continue;
			}
			call_user_func( array( $class, 'register' ), $ns );
		}

		// WooCommerce endpoints — only when WC is active AND the class loaded.
		if ( class_exists( 'WooCommerce' ) ) {
			if ( class_exists( 'MKB_Woo_Endpoints' ) ) {
				MKB_Woo_Endpoints::register( $ns );
			} else {
				self::$missing_endpoints[] = 'woo';
				error_log(
					'[mega-kadence-bridge] Endpoint class MKB_Woo_Endpoints not loaded — woo routes skipped. Reinstall the plugin or restore includes/endpoints/.'
				);
			}
		}
	}

	/**
	 * Admin-notice hook — surface missing endpoint classes to the site operator
	 * so a partial corruption doesn't sit silent. Hooked in class-plugin.php.
	 *
	 * @since 1.2.1
	 */
	public static function maybe_render_missing_endpoint_notice() {
		if ( empty( self::$missing_endpoints ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s <code>%s</code>. %s</p></div>',
			esc_html__( 'Mega Kadence Bridge:', 'mega-kadence-bridge' ),
			esc_html__( 'Some endpoint files are missing or corrupted —', 'mega-kadence-bridge' ),
			esc_html( implode( ', ', self::$missing_endpoints ) ),
			esc_html__( 'Reinstall the plugin from GitHub to restore full functionality. Other REST endpoints on this site are unaffected.', 'mega-kadence-bridge' )
		);
	}

	/**
	 * Standard permission callback — requires manage_options capability AND
	 * a domain that matches the locked domain (set on activation).
	 *
	 * Used by every endpoint. The domain check is the second gate: it stops
	 * a stale credentials.json from working against a snapshot/staging clone
	 * of the site without an operator re-locking on the new domain.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'mkb_rest_forbidden',
				__( 'You do not have permission to use the Mega Kadence Bridge.', 'mega-kadence-bridge' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$lock = self::check_domain_lock();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		return true;
	}

	/**
	 * Verify the current request is hitting the same domain the bridge was
	 * locked to during activation.
	 *
	 * Returns true if the lock has not been initialized yet (graceful migration
	 * for sites upgrading from < 1.2.0) — the next activation cycle will set it.
	 *
	 * @since 1.2.0
	 * @return true|WP_Error
	 */
	public static function check_domain_lock() {
		$locked  = (string) get_option( MKB_LOCKED_DOMAIN_OPTION, '' );
		$current = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		// Pre-1.2.0 install with no lock recorded yet — fail open and let the
		// admin page (or the next activation) record one. This avoids breaking
		// existing credentials immediately on upgrade.
		if ( '' === $locked ) {
			return true;
		}

		if ( $locked === $current ) {
			return true;
		}

		return new WP_Error(
			'mkb_domain_mismatch',
			sprintf(
				/* translators: 1: domain the bridge was locked to, 2: current domain */
				__(
					'Mega Kadence Bridge is locked to %1$s but this request is hitting %2$s. The site was likely cloned, restored from a snapshot, or moved. An administrator must re-lock the bridge to the new domain (Settings → Mega Kadence Bridge → Re-lock to current domain) before AI requests will be honored. This prevents stale credentials from operating an unintended site.',
					'mega-kadence-bridge'
				),
				$locked,
				$current
			),
			array(
				'status'         => 403,
				'locked_domain'  => $locked,
				'current_domain' => $current,
			)
		);
	}

	/**
	 * Helper — build a standard success response envelope.
	 *
	 * @param array $data Response data.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	public static function success( $data, $status = 200 ) {
		return new WP_REST_Response(
			array_merge(
				array( 'success' => true ),
				$data
			),
			$status
		);
	}

	/**
	 * Helper — build a standard error response.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable error message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error
	 */
	public static function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
