<?php
/**
 * Eval Endpoint — POST /wp-eval, execute PHP (guarded, audit-logged, expert use).
 *
 * Lives in its own file on purpose. Host malware scanners (Hostinger's Imunify,
 * 2026-11-25) blank any file containing eval(). When this route shared
 * class-core-endpoints.php, the scanner emptied that whole file and /info,
 * /render and /cache/flush vanished with it. Now a scanner can only remove this
 * route; the loader and the class_exists() guard skip it cleanly.
 *
 * @package MegaKadenceBridge
 * @since 1.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Eval_Endpoints {

	public static function register( $ns ) {
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
