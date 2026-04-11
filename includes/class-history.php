<?php
/**
 * History / Snapshot / Rollback System
 *
 * Every write operation through the bridge captures the previous state of
 * the thing being changed. Snapshots are stored in the mkb_history wp_option
 * as a ring buffer of the last N changes.
 *
 * Snapshot structure:
 *   {
 *     "id":            "mkb_<timestamp>_<random>",
 *     "timestamp":     "2026-04-11 10:30:00",
 *     "operation":     "theme_mod_set",
 *     "target":        "header_main_layout",
 *     "previous":      "standard",
 *     "new":           "fullwidth",
 *     "context":       { ... additional info ... }
 *   }
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_History {

	/**
	 * Maximum number of snapshots kept in the ring buffer.
	 */
	const MAX_ENTRIES = 50;

	/**
	 * Option name where history is stored.
	 */
	const OPTION_NAME = 'mkb_history';

	/**
	 * Record a new snapshot.
	 *
	 * @param string $operation Human-readable operation name (theme_mod_set, css_set, etc).
	 * @param string $target    Identifier of the thing changed (setting key, post ID, etc).
	 * @param mixed  $previous  Previous value.
	 * @param mixed  $new       New value.
	 * @param array  $context   Optional additional context.
	 * @return string The generated snapshot ID.
	 */
	public static function record( $operation, $target, $previous, $new, $context = array() ) {
		$id = self::generate_id();

		$entry = array(
			'id'        => $id,
			'timestamp' => current_time( 'mysql' ),
			'operation' => $operation,
			'target'    => $target,
			'previous'  => $previous,
			'new'       => $new,
			'context'   => $context,
		);

		$history = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$history[] = $entry;

		// Trim to MAX_ENTRIES keeping the most recent.
		if ( count( $history ) > self::MAX_ENTRIES ) {
			$history = array_slice( $history, -self::MAX_ENTRIES );
		}

		update_option( self::OPTION_NAME, $history, false );

		return $id;
	}

	/**
	 * Get all snapshots (most recent first).
	 *
	 * @param int $limit Maximum number to return.
	 * @return array
	 */
	public static function all( $limit = 50 ) {
		$history = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $history ) ) {
			return array();
		}
		$reversed = array_reverse( $history );
		return array_slice( $reversed, 0, $limit );
	}

	/**
	 * Find a snapshot by ID.
	 *
	 * @param string $id Snapshot ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		$history = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $history ) ) {
			return null;
		}
		foreach ( $history as $entry ) {
			if ( isset( $entry['id'] ) && $entry['id'] === $id ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Generate a unique snapshot ID.
	 *
	 * @return string
	 */
	private static function generate_id() {
		return 'mkb_' . time() . '_' . wp_generate_password( 8, false );
	}
}
