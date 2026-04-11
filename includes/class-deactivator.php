<?php
/**
 * Deactivator — runs on plugin deactivation.
 *
 * Note: We do NOT delete the claude-bot user or its application password on
 * deactivation, because a site owner may deactivate and reactivate and expect
 * their credentials to still work. Full cleanup happens in uninstall.php.
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Deactivator {

	public static function deactivate() {
		// Flush rewrite rules to remove our REST routes cleanly.
		flush_rewrite_rules();
	}
}
