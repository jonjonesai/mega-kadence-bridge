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
	 * Register all routes.
	 */
	public static function register_routes() {
		$ns = MKB_REST_NAMESPACE;

		// Capabilities discovery (the canonical first call from any AI agent —
		// returns site context, Kadence stack inventory, and operating doctrine).
		MKB_Capabilities_Endpoints::register( $ns );

		// Core endpoints.
		MKB_Core_Endpoints::register( $ns );

		// Theme customization endpoints.
		MKB_Theme_Endpoints::register( $ns );

		// Content (posts, pages) endpoints.
		MKB_Content_Endpoints::register( $ns );

		// Media endpoints.
		MKB_Media_Endpoints::register( $ns );

		// Kadence-specific endpoints (blocks, Pro config).
		MKB_Kadence_Endpoints::register( $ns );

		// WooCommerce endpoints (registered only if WC is active).
		if ( class_exists( 'WooCommerce' ) ) {
			MKB_Woo_Endpoints::register( $ns );
		}

		// History / snapshot / rollback endpoints.
		MKB_History_Endpoints::register( $ns );

		// Plugin install / activate / list endpoints.
		MKB_Plugin_Endpoints::register( $ns );
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
