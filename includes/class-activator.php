<?php
/**
 * Activator — runs once when the plugin is activated.
 *
 * Responsibilities:
 *   1. Create the dedicated claude-bot WP user (administrator role).
 *   2. Generate an Application Password for that user.
 *   3. Store the plain-text password in a wp_option so the Settings page
 *      can display it to the site owner.
 *   4. Write credentials to wp-content/.claude-bridge/credentials.json as
 *      a backup (protected from direct HTTP access).
 *   5. Create protection files (.htaccess, index.php) in the credentials dir.
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		$user_id = self::ensure_bot_user();
		if ( is_wp_error( $user_id ) ) {
			deactivate_plugins( plugin_basename( MKB_PLUGIN_FILE ) );
			wp_die(
				esc_html( $user_id->get_error_message() ),
				'Mega Kadence Bridge Activation Error',
				array( 'back_link' => true )
			);
		}

		$credentials = self::ensure_app_password( $user_id );
		if ( is_wp_error( $credentials ) ) {
			deactivate_plugins( plugin_basename( MKB_PLUGIN_FILE ) );
			wp_die(
				esc_html( $credentials->get_error_message() ),
				'Mega Kadence Bridge Activation Error',
				array( 'back_link' => true )
			);
		}

		self::store_credentials( $credentials );
		self::write_credentials_file( $credentials );

		// Flush rewrite rules so REST routes are available immediately.
		flush_rewrite_rules();

		// Mark activation complete so the admin page can show a welcome notice.
		update_option( 'mkb_activation_completed', time() );
	}

	/**
	 * Ensure the claude-bot user exists with administrator role.
	 *
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	private static function ensure_bot_user() {
		$existing = get_user_by( 'login', MKB_BOT_USERNAME );
		if ( $existing ) {
			// Ensure the existing user has administrator role.
			if ( ! in_array( 'administrator', (array) $existing->roles, true ) ) {
				$existing->set_role( 'administrator' );
			}
			update_option( 'mkb_bot_user_id', $existing->ID );
			return $existing->ID;
		}

		$random_email = MKB_BOT_USERNAME . '+' . wp_generate_password( 8, false ) . '@localhost.invalid';

		$user_id = wp_insert_user(
			array(
				'user_login'   => MKB_BOT_USERNAME,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $random_email,
				'display_name' => 'Claude Bot',
				'first_name'   => 'Claude',
				'last_name'    => 'Bot',
				'description'  => 'Your personal Kadence wizard. This user lets Claude Code safely control your site through the Mega Kadence Bridge plugin. Do not delete — this is how Claude helps you build and edit your store.',
				'role'         => 'administrator',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_option( 'mkb_bot_user_id', $user_id );
		return $user_id;
	}

	/**
	 * Ensure an Application Password exists for the bot user.
	 * Revokes any existing password with the same name and creates a fresh one.
	 *
	 * @param int $user_id Bot user ID.
	 * @return array|WP_Error Credentials array or WP_Error on failure.
	 */
	private static function ensure_app_password( $user_id ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return new WP_Error(
				'no_app_passwords',
				'WordPress Application Passwords are not available. This plugin requires WordPress 5.6 or newer.'
			);
		}

		// Revoke any existing Mega Kadence Bridge app password so we start fresh.
		$existing_passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );
		if ( is_array( $existing_passwords ) ) {
			foreach ( $existing_passwords as $pwd ) {
				if ( isset( $pwd['name'] ) && $pwd['name'] === MKB_APP_PASSWORD_NAME ) {
					WP_Application_Passwords::delete_application_password( $user_id, $pwd['uuid'] );
				}
			}
		}

		// Create a new application password.
		$created = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array(
				'name'   => MKB_APP_PASSWORD_NAME,
				'app_id' => 'mega-kadence-bridge',
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		list( $plain_password, $item ) = $created;

		return array(
			'bridge_url'   => rest_url( MKB_REST_NAMESPACE ),
			'bridge_user'  => MKB_BOT_USERNAME,
			'bridge_pass'  => $plain_password,
			'site_url'     => home_url(),
			'user_id'      => $user_id,
			'generated_at' => current_time( 'mysql' ),
			'password_uuid' => isset( $item['uuid'] ) ? $item['uuid'] : null,
		);
	}

	/**
	 * Store credentials in a wp_option so the Settings page can display them.
	 *
	 * @param array $credentials Credentials array.
	 */
	private static function store_credentials( $credentials ) {
		update_option( 'mkb_credentials', $credentials, false );
	}

	/**
	 * Write credentials to a protected JSON file in wp-content/.claude-bridge/
	 *
	 * This is the backup path for SSH-based workflows. The file is protected
	 * by .htaccess deny rules and an index.php fallback for non-Apache hosts.
	 *
	 * @param array $credentials Credentials array.
	 */
	private static function write_credentials_file( $credentials ) {
		if ( ! wp_mkdir_p( MKB_CREDENTIALS_DIR ) ) {
			return;
		}

		// Write the protective .htaccess (Apache/LiteSpeed).
		$htaccess_path = MKB_CREDENTIALS_DIR . '/.htaccess';
		if ( ! file_exists( $htaccess_path ) ) {
			file_put_contents(
				$htaccess_path,
				"Order deny,allow\nDeny from all\n"
			);
		}

		// Write an index.php fallback for servers that ignore .htaccess.
		$index_path = MKB_CREDENTIALS_DIR . '/index.php';
		if ( ! file_exists( $index_path ) ) {
			file_put_contents(
				$index_path,
				"<?php\n// Silence is golden.\n"
			);
		}

		// Write the credentials file with restrictive permissions.
		$json = wp_json_encode( $credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false !== $json ) {
			file_put_contents( MKB_CREDENTIALS_FILE, $json );
			@chmod( MKB_CREDENTIALS_FILE, 0600 );
		}
	}
}
