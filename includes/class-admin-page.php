<?php
/**
 * Admin Page — Settings → Mega Kadence Bridge
 *
 * The site owner's one-stop view of the plugin. Shows:
 *   - Generated credentials in a ".env-ready" copy block
 *   - System status (WordPress version, Kadence detected, WC detected, etc.)
 *   - Regenerate credentials button for rotation
 *   - Quick test links for verifying the bridge is responsive
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Admin_Page {

	const PAGE_SLUG = 'mega-kadence-bridge';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_welcome' ) );
	}

	/**
	 * Register the Settings submenu page.
	 */
	public static function register_page() {
		add_options_page(
			__( 'Mega Kadence Bridge', 'mega-kadence-bridge' ),
			__( 'Mega Kadence Bridge', 'mega-kadence-bridge' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Handle form submissions (regenerate credentials).
	 */
	public static function handle_actions() {
		if ( ! isset( $_POST['mkb_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['mkb_nonce'] ) || ! wp_verify_nonce( $_POST['mkb_nonce'], 'mkb_admin_action' ) ) {
			return;
		}

		$action = sanitize_key( $_POST['mkb_action'] );

		if ( 'regenerate' === $action ) {
			$user_id = (int) get_option( 'mkb_bot_user_id', 0 );
			if ( $user_id > 0 ) {
				// Use the activator's logic to regenerate.
				$reflection = new ReflectionClass( 'MKB_Activator' );
				$method     = $reflection->getMethod( 'ensure_app_password' );
				$method->setAccessible( true );
				$credentials = $method->invoke( null, $user_id );

				if ( ! is_wp_error( $credentials ) ) {
					$store_method = $reflection->getMethod( 'store_credentials' );
					$store_method->setAccessible( true );
					$store_method->invoke( null, $credentials );

					$write_method = $reflection->getMethod( 'write_credentials_file' );
					$write_method->setAccessible( true );
					$write_method->invoke( null, $credentials );

					wp_safe_redirect(
						add_query_arg(
							array(
								'page'    => self::PAGE_SLUG,
								'message' => 'regenerated',
							),
							admin_url( 'options-general.php' )
						)
					);
					exit;
				}
			}
		}
	}

	/**
	 * Show a welcome notice if the plugin was just activated.
	 */
	public static function maybe_show_welcome() {
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		$completed = get_option( 'mkb_activation_completed', 0 );
		if ( ! $completed ) {
			return;
		}

		// Only show once per activation event.
		if ( get_option( 'mkb_welcome_dismissed' ) === (string) $completed ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		echo '<div class="notice notice-success is-dismissible mkb-welcome-notice">';
		echo '<p><strong>' . esc_html__( 'Mega Kadence Bridge is ready!', 'mega-kadence-bridge' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Your Claude wizard has been set up. Click below to view your credentials and start building with Claude Code.', 'mega-kadence-bridge' ) . '</p>';
		echo '<p><a href="' . esc_url( $settings_url ) . '" class="button button-primary">' . esc_html__( 'View Credentials', 'mega-kadence-bridge' ) . '</a></p>';
		echo '</div>';

		update_option( 'mkb_welcome_dismissed', (string) $completed );
	}

	/**
	 * Enqueue admin assets.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mkb-admin',
			MKB_PLUGIN_URL . 'assets/admin.css',
			array(),
			MKB_VERSION
		);

		wp_enqueue_script(
			'mkb-admin',
			MKB_PLUGIN_URL . 'assets/admin.js',
			array(),
			MKB_VERSION,
			true
		);
	}

	/**
	 * Render the Settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$credentials = get_option( 'mkb_credentials', array() );
		$status      = self::get_system_status();
		$message     = isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : '';
		?>
		<div class="wrap mkb-wrap">
			<h1><?php esc_html_e( 'Mega Kadence Bridge', 'mega-kadence-bridge' ); ?></h1>

			<?php if ( 'regenerated' === $message ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Credentials regenerated successfully. Update your local .env file with the new password below.', 'mega-kadence-bridge' ); ?></p>
				</div>
			<?php endif; ?>

			<p class="mkb-tagline">
				<?php esc_html_e( 'Your Claude wizard for building Kadence sites. Copy the credentials below into your local .env file and start talking to Claude Code.', 'mega-kadence-bridge' ); ?>
			</p>

			<div class="mkb-card">
				<h2><?php esc_html_e( 'Your Credentials', 'mega-kadence-bridge' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'These are the keys Claude uses to talk to your site. Copy the block below and paste it into a file called .env in your project folder.', 'mega-kadence-bridge' ); ?>
				</p>

				<?php if ( empty( $credentials ) || empty( $credentials['bridge_pass'] ) ) : ?>
					<div class="notice notice-error">
						<p><?php esc_html_e( 'Credentials could not be found. Try deactivating and reactivating the plugin.', 'mega-kadence-bridge' ); ?></p>
					</div>
				<?php else : ?>
					<?php
					$env_block = sprintf(
						"BRIDGE_URL=%s\nBRIDGE_USER=%s\nBRIDGE_PASS=%s\nBRIDGE_SITE=%s\n",
						$credentials['bridge_url'],
						$credentials['bridge_user'],
						$credentials['bridge_pass'],
						isset( $credentials['site_url'] ) ? $credentials['site_url'] : home_url()
					);
					?>
					<pre class="mkb-env-block" id="mkb-env-block"><?php echo esc_html( $env_block ); ?></pre>
					<p>
						<button type="button" class="button button-primary mkb-copy-button" data-target="mkb-env-block">
							<?php esc_html_e( 'Copy as .env', 'mega-kadence-bridge' ); ?>
						</button>
						<span class="mkb-copy-feedback" role="status" aria-live="polite"></span>
					</p>
				<?php endif; ?>
			</div>

			<div class="mkb-card">
				<h2><?php esc_html_e( 'System Status', 'mega-kadence-bridge' ); ?></h2>
				<table class="mkb-status-table">
					<tbody>
						<?php foreach ( $status as $label => $value ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td><?php echo wp_kses_post( $value ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="mkb-card">
				<h2><?php esc_html_e( 'Maintenance', 'mega-kadence-bridge' ); ?></h2>
				<p>
					<?php esc_html_e( 'If you suspect your credentials have been compromised, regenerate them. This will invalidate the current credentials and create new ones.', 'mega-kadence-bridge' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'mkb_admin_action', 'mkb_nonce' ); ?>
					<input type="hidden" name="mkb_action" value="regenerate" />
					<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Regenerate credentials? Your current .env file will stop working and you will need to update it.', 'mega-kadence-bridge' ) ); ?>');">
						<?php esc_html_e( 'Regenerate Credentials', 'mega-kadence-bridge' ); ?>
					</button>
				</form>
			</div>

			<div class="mkb-card">
				<h2><?php esc_html_e( 'Documentation', 'mega-kadence-bridge' ); ?></h2>
				<ul>
					<li>
						<a href="https://github.com/jonjonesai/mega-kadence-bridge" target="_blank" rel="noopener">
							<?php esc_html_e( 'GitHub Repository', 'mega-kadence-bridge' ); ?>
						</a>
					</li>
					<li>
						<a href="https://github.com/jonjonesai/mega-kadence-bridge/blob/main/README.md" target="_blank" rel="noopener">
							<?php esc_html_e( 'Installation & Usage Guide', 'mega-kadence-bridge' ); ?>
						</a>
					</li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Gather system status information.
	 *
	 * @return array [label => value, ...]
	 */
	private static function get_system_status() {
		$kadence_theme_active = ( 'Kadence' === wp_get_theme()->get( 'Name' ) );
		$kadence_blocks_active = is_plugin_active_check( 'kadence-blocks/kadence-blocks.php' );
		$kadence_pro_active    = class_exists( 'Kadence_Theme_Pro' );
		$kadence_blocks_pro    = class_exists( 'Kadence_Blocks_Pro' );
		$woo_active            = class_exists( 'WooCommerce' );
		$litespeed_active      = class_exists( 'LiteSpeed_Cache_API' );

		return array(
			__( 'WordPress Version', 'mega-kadence-bridge' )     => esc_html( get_bloginfo( 'version' ) ),
			__( 'PHP Version', 'mega-kadence-bridge' )           => esc_html( phpversion() ),
			__( 'Kadence Theme', 'mega-kadence-bridge' )         => self::status_badge( $kadence_theme_active ),
			__( 'Kadence Blocks', 'mega-kadence-bridge' )        => self::status_badge( $kadence_blocks_active ),
			__( 'Kadence Pro', 'mega-kadence-bridge' )           => self::status_badge( $kadence_pro_active, __( 'Optional', 'mega-kadence-bridge' ) ),
			__( 'Kadence Blocks Pro', 'mega-kadence-bridge' )    => self::status_badge( $kadence_blocks_pro, __( 'Optional', 'mega-kadence-bridge' ) ),
			__( 'WooCommerce', 'mega-kadence-bridge' )           => self::status_badge( $woo_active, __( 'Optional', 'mega-kadence-bridge' ) ),
			__( 'LiteSpeed Cache', 'mega-kadence-bridge' )       => self::status_badge( $litespeed_active, __( 'Optional', 'mega-kadence-bridge' ) ),
			__( 'Bridge REST Namespace', 'mega-kadence-bridge' ) => '<code>' . esc_html( MKB_REST_NAMESPACE ) . '</code>',
			__( 'Bot User', 'mega-kadence-bridge' )              => '<code>' . esc_html( MKB_BOT_USERNAME ) . '</code>',
		);
	}

	/**
	 * Render a coloured status badge.
	 *
	 * @param bool   $active      Whether the feature is active.
	 * @param string $inactive_text Label for inactive state.
	 * @return string
	 */
	private static function status_badge( $active, $inactive_text = '' ) {
		if ( $active ) {
			return '<span class="mkb-badge mkb-badge-ok">&#10003; ' . esc_html__( 'Detected', 'mega-kadence-bridge' ) . '</span>';
		}
		$label = $inactive_text ? $inactive_text : __( 'Not Installed', 'mega-kadence-bridge' );
		return '<span class="mkb-badge mkb-badge-neutral">&#8211; ' . esc_html( $label ) . '</span>';
	}
}

/**
 * Helper — check if a plugin is active without loading the full plugin.php file.
 * Wraps WP's is_plugin_active() with the required include.
 */
if ( ! function_exists( 'is_plugin_active_check' ) ) {
	function is_plugin_active_check( $plugin_path ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $plugin_path );
	}
}
