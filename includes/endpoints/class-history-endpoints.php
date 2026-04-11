<?php
/**
 * History Endpoints — snapshot / rollback.
 *
 * Routes:
 *   GET /history                   List recent snapshots
 *   GET /history/{id}              Get a specific snapshot
 *   POST /rollback/{id}            Revert a change
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_History_Endpoints {

	public static function register( $ns ) {
		$permission = array( 'MKB_REST_Controller', 'check_permission' );

		register_rest_route(
			$ns,
			'/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_history' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/history/(?P<id>mkb_[a-zA-Z0-9_]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_snapshot' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/rollback/(?P<id>mkb_[a-zA-Z0-9_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rollback' ),
				'permission_callback' => $permission,
			)
		);
	}

	public static function list_history( $request ) {
		$limit   = (int) ( $request->get_param( 'limit' ) ?: 50 );
		$entries = MKB_History::all( $limit );

		return MKB_REST_Controller::success(
			array(
				'entries' => $entries,
				'count'   => count( $entries ),
			)
		);
	}

	public static function get_snapshot( $request ) {
		$id    = $request->get_param( 'id' );
		$entry = MKB_History::find( $id );
		if ( ! $entry ) {
			return MKB_REST_Controller::error( 'not_found', 'Snapshot not found.', 404 );
		}
		return MKB_REST_Controller::success( array( 'snapshot' => $entry ) );
	}

	/**
	 * POST /rollback/{id}
	 *
	 * Reverts a change by re-applying the snapshot's "previous" value.
	 * Supports a subset of operations — the most common ones. For complex
	 * ones (batch, wp_eval), rollback is not supported and returns 400.
	 */
	public static function rollback( $request ) {
		$id    = $request->get_param( 'id' );
		$entry = MKB_History::find( $id );

		if ( ! $entry ) {
			return MKB_REST_Controller::error( 'not_found', 'Snapshot not found.', 404 );
		}

		$operation = $entry['operation'];
		$target    = $entry['target'];
		$previous  = $entry['previous'];

		switch ( $operation ) {
			case 'theme_mod_set':
				if ( null === $previous ) {
					remove_theme_mod( $target );
				} else {
					set_theme_mod( $target, $previous );
				}
				break;

			case 'option_set':
				update_option( $target, $previous );
				break;

			case 'palette_set':
				update_option( 'kadence_global_palette', $previous );
				set_theme_mod( 'kadence_global_palette', $previous );
				break;

			case 'css_set':
				wp_update_custom_css_post( $previous );
				break;

			case 'post_update':
				$post_id = (int) $target;
				if ( $post_id && is_array( $previous ) ) {
					$update = array( 'ID' => $post_id );
					if ( isset( $previous['title'] ) ) {
						$update['post_title'] = $previous['title'];
					}
					if ( isset( $previous['content'] ) ) {
						$update['post_content'] = $previous['content'];
					}
					if ( isset( $previous['excerpt'] ) ) {
						$update['post_excerpt'] = $previous['excerpt'];
					}
					if ( isset( $previous['status'] ) ) {
						$update['post_status'] = $previous['status'];
					}
					wp_update_post( $update );

					// Restore any per-key meta snapshots.
					foreach ( $previous as $key => $value ) {
						if ( 0 === strpos( $key, 'meta.' ) ) {
							$meta_key = substr( $key, 5 );
							update_post_meta( $post_id, $meta_key, $value );
						}
					}
				}
				break;

			case 'kadence_pro_config_set':
			case 'kadence_pro_preset_pod':
				update_option( 'kadence_pro_theme_config', $previous );
				break;

			default:
				return MKB_REST_Controller::error(
					'rollback_unsupported',
					"Rollback is not supported for operation '{$operation}'. Manual restoration required.",
					400
				);
		}

		// Record the rollback itself as a new history entry.
		MKB_History::record(
			'rollback',
			$target,
			$entry['new'],
			$previous,
			array( 'reverted_snapshot_id' => $id, 'original_operation' => $operation )
		);

		return MKB_REST_Controller::success(
			array(
				'reverted' => $id,
				'operation' => $operation,
				'target'    => $target,
				'restored_to' => $previous,
			)
		);
	}
}
