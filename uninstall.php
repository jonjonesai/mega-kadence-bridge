<?php
/**
 * Mega Kadence Bridge — Uninstall Handler
 *
 * Runs when the plugin is deleted from the WP admin. Removes the claude-bot
 * user, revokes the application password, clears stored credentials, and
 * removes the credentials file and its directory.
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove the bot user.
$bot_user_id = (int) get_option( 'mkb_bot_user_id', 0 );
if ( $bot_user_id > 0 ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $bot_user_id );
}

// Delete stored options.
delete_option( 'mkb_bot_user_id' );
delete_option( 'mkb_credentials' );
delete_option( 'mkb_activation_completed' );
delete_option( 'mkb_history' );

// Remove the credentials directory and its contents.
$credentials_dir = WP_CONTENT_DIR . '/.claude-bridge';
if ( is_dir( $credentials_dir ) ) {
	$files = array( 'credentials.json', '.htaccess', 'index.php' );
	foreach ( $files as $file ) {
		$path = $credentials_dir . '/' . $file;
		if ( file_exists( $path ) ) {
			unlink( $path );
		}
	}
	@rmdir( $credentials_dir );
}
