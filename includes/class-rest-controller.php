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
	}

	/**
	 * Standard permission callback — requires manage_options capability.
	 * Used by every write-capable endpoint.
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
		return true;
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
