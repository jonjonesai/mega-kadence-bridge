<?php
/**
 * Production Endpoints — one-shot "make this store production-ready" operations.
 *
 * These are the deterministic, table-driven hardening steps the store-drop-skill
 * runs once per fresh install to take a Kadence + WooCommerce store from
 * "deployed" to "production-ready": pretty permalinks, no thumbnail-spam on
 * upload, LiteSpeed image optimization, and form/login spam protection.
 *
 * Routes:
 *   POST /permalinks                Set pretty permalink structure + flush rewrites
 *   POST /media/disable-thumbnails  Zero core media sizes (Settings > Media)
 *   POST /litespeed/optimize-images Enable LSCWP image optimization + WebP
 *   POST /spam-protection           FF honeypot + login-lockdown plugin
 *   POST /production-pass           Orchestrate all four, combined report
 *
 * Doctrine (HARDENING.md Tier 2): every destructive write snapshots the prior
 * state through MKB_History first, and every endpoint is idempotent — it checks
 * current state and returns { "changed": false } when already in the target
 * state, so re-running a Production Pass is safe.
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Production_Endpoints {

	/**
	 * Default WP permalink structure — category + post name, the SEO-friendly
	 * shape store-drop ships by default.
	 */
	const DEFAULT_PERMALINK_STRUCTURE = '/%category%/%postname%/';

	public static function register( $ns ) {
		$permission = array( 'MKB_REST_Controller', 'check_permission' );

		register_rest_route(
			$ns,
			'/permalinks',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'set_permalinks' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/media/disable-thumbnails',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'disable_thumbnails' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/litespeed/optimize-images',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'litespeed_optimize_images' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/spam-protection',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'spam_protection' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/production-pass',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'production_pass' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * POST /permalinks
	 *
	 * Body: { "structure": "/%category%/%postname%/" }  (default if omitted)
	 *
	 * Sets the permalink_structure option AND flushes rewrite rules so pretty
	 * URLs actually resolve. Idempotent: if the structure already matches, it
	 * returns changed:false without touching the option or flushing.
	 *
	 * Snapshots the previous structure as an option_set operation, so it is
	 * rollback-able via POST /rollback/{id}.
	 */
	public static function set_permalinks( $request ) {
		$body      = $request->get_json_params();
		$structure = isset( $body['structure'] ) && '' !== trim( (string) $body['structure'] )
			? sanitize_text_field( $body['structure'] )
			: self::DEFAULT_PERMALINK_STRUCTURE;

		$current = (string) get_option( 'permalink_structure', '' );

		if ( $current === $structure ) {
			return MKB_REST_Controller::success(
				array(
					'changed'   => false,
					'structure' => $current,
					'message'   => 'Permalink structure already set; no change.',
				)
			);
		}

		// Snapshot before the destructive write.
		$snapshot_id = MKB_History::record(
			'option_set',
			'permalink_structure',
			$current,
			$structure,
			array( 'endpoint' => 'permalinks' )
		);

		update_option( 'permalink_structure', $structure );

		// Make pretty URLs resolve. flush_rewrite_rules() needs the rewrite
		// rules rebuilt from the live WP_Rewrite singleton.
		if ( ! function_exists( 'flush_rewrite_rules' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		global $wp_rewrite;
		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$wp_rewrite->set_permalink_structure( $structure );
		}
		flush_rewrite_rules( true );

		return MKB_REST_Controller::success(
			array(
				'changed'     => true,
				'structure'   => $structure,
				'previous'    => $current,
				'snapshot_id' => $snapshot_id,
				'message'     => 'Permalink structure set and rewrite rules flushed.',
			)
		);
	}

	/**
	 * POST /media/disable-thumbnails
	 *
	 * Zeroes the eight core WP media-size options (Settings > Media): thumbnail,
	 * medium, medium_large, and large width/height. With a dimension at 0 WP
	 * skips generating that copy on upload, so a fresh store stops accumulating
	 * the generic resized variants that bloat disk + inodes on shared hosting.
	 *
	 * Deliberately scoped to the CORE sizes only — it does NOT disable the sizes
	 * WooCommerce and Kadence register in code (woocommerce_thumbnail,
	 * woocommerce_single, etc.), because the storefront needs those to render
	 * product images at sensible dimensions. This mirrors the manual
	 * Settings > Media workflow, automated.
	 *
	 * Idempotent: each option already at 0 is skipped; returns changed:false
	 * when nothing needed zeroing. Snapshots each changed option under its real
	 * name so POST /rollback/{id} restores it via the option_set handler.
	 */
	public static function disable_thumbnails( $request ) {
		// Core media sizes shown on Settings > Media.
		$size_options = array(
			'thumbnail_size_w',
			'thumbnail_size_h',
			'medium_size_w',
			'medium_size_h',
			'medium_large_size_w',
			'medium_large_size_h',
			'large_size_w',
			'large_size_h',
		);

		$snapshots = array();
		$zeroed    = array();
		foreach ( $size_options as $opt ) {
			$val = (int) get_option( $opt, 0 );
			if ( 0 === $val ) {
				continue; // Already zero — nothing to change or snapshot.
			}
			// Snapshot the real option before zeroing it so rollback works.
			$snapshots[ $opt ] = MKB_History::record(
				'option_set',
				$opt,
				$val,
				0,
				array( 'endpoint' => 'media/disable-thumbnails' )
			);
			update_option( $opt, 0 );
			$zeroed[] = $opt;
		}

		if ( empty( $zeroed ) ) {
			return MKB_REST_Controller::success(
				array(
					'changed' => false,
					'message' => 'Core media sizes already zeroed; no change.',
				)
			);
		}

		return MKB_REST_Controller::success(
			array(
				'changed'      => true,
				'sizes_zeroed' => $zeroed,
				'snapshot_ids' => $snapshots,
				'message'      => 'Core media sizes zeroed (Settings > Media). WooCommerce/Kadence registered sizes left intact.',
			)
		);
	}

	/**
	 * POST /litespeed/optimize-images
	 *
	 * If LiteSpeed Cache is active, enables image optimization auto-request and
	 * WebP delivery through LSCWP's own config API. Keys verified against LSCWP
	 * src/base.cls.php: 'img_optm-auto', 'img_optm-cron', 'img_optm-webp'
	 * (stored as litespeed.conf.<key> options; written via the first-party
	 * \LiteSpeed\Conf updater so side effects + cron wiring fire correctly).
	 *
	 * If LiteSpeed is NOT active, returns changed:false with a clean skip note —
	 * never an error. Idempotent: skips keys already at the target value.
	 */
	public static function litespeed_optimize_images( $request ) {
		$active = self::litespeed_active();
		if ( ! $active ) {
			return MKB_REST_Controller::success(
				array(
					'changed' => false,
					'message' => 'LiteSpeed not active',
				)
			);
		}

		// Target config: auto-request optimization, run it on cron, serve WebP.
		$targets = array(
			'img_optm-auto' => true,
			'img_optm-cron' => true,
			'img_optm-webp' => true,
		);

		$conf = self::litespeed_conf();

		$applied  = array();
		$skipped  = array();
		$previous = array();

		foreach ( $targets as $key => $want ) {
			$option_name = 'litespeed.conf.' . $key;
			$current     = $conf ? $conf->conf( $key ) : get_option( $option_name, null );
			$previous[ $key ] = $current;

			// LSCWP stores booleans as 1/0; normalize for comparison.
			$current_bool = (bool) $current;
			if ( $current_bool === (bool) $want ) {
				$skipped[] = $key;
				continue;
			}
			$applied[ $key ] = $want;
		}

		if ( empty( $applied ) ) {
			return MKB_REST_Controller::success(
				array(
					'changed' => false,
					'message' => 'LiteSpeed image optimization + WebP already enabled.',
					'skipped' => $skipped,
				)
			);
		}

		// Snapshot the prior config values before writing. Recorded under a
		// dedicated op type (not option_set) so POST /rollback/{id} honestly
		// returns rollback_unsupported instead of writing the captured map to a
		// non-existent option and faking success — LSCWP spreads these across
		// litespeed.conf.<key> rows plus its own cron/purge side effects, so the
		// undo path is the LiteSpeed UI, per the note below.
		$snapshot_id = MKB_History::record(
			'litespeed_conf_set',
			'mkb_litespeed_img_optm',
			$previous,
			$applied,
			array(
				'endpoint' => 'litespeed/optimize-images',
				'note'     => 'Rollback note: turning on image optimization is not something you normally revert. If needed, restore via LiteSpeed > Image Optimization or \\LiteSpeed\\Conf::cls()->update() with the previous values.',
			)
		);

		// Write through LSCWP's own updater when available so it persists to the
		// right option rows and fires the plugin's purge/cron side effects.
		$written_via = 'option_fallback';
		if ( $conf && method_exists( $conf, 'update' ) ) {
			foreach ( $applied as $key => $want ) {
				$conf->update( $key, $want );
			}
			$written_via = 'litespeed_conf_update';
		} else {
			// Fallback: write the option rows directly. LSCWP reads these on load.
			foreach ( $applied as $key => $want ) {
				update_option( 'litespeed.conf.' . $key, $want ? 1 : 0 );
			}
		}

		return MKB_REST_Controller::success(
			array(
				'changed'     => true,
				'applied'     => $applied,
				'skipped'     => $skipped,
				'written_via' => $written_via,
				'snapshot_id' => $snapshot_id,
				'message'     => 'LiteSpeed image optimization + WebP enabled.',
			)
		);
	}

	/**
	 * Detect whether LiteSpeed Cache is active.
	 *
	 * @return bool
	 */
	private static function litespeed_active() {
		return defined( 'LSCWP_V' )
			|| class_exists( '\\LiteSpeed\\Core' )
			|| class_exists( 'LiteSpeed_Cache' )
			|| class_exists( 'LiteSpeed_Cache_API' );
	}

	/**
	 * Resolve the LSCWP Conf singleton if the class is loaded.
	 *
	 * @return object|null \LiteSpeed\Conf instance, or null.
	 */
	private static function litespeed_conf() {
		if ( class_exists( '\\LiteSpeed\\Conf' ) && method_exists( '\\LiteSpeed\\Conf', 'cls' ) ) {
			return \LiteSpeed\Conf::cls();
		}
		return null;
	}

	/**
	 * POST /spam-protection
	 *
	 * (a) Enables FluentForms honeypot by read-modify-writing the
	 *     _fluentform_global_form_settings option's misc.honeypotStatus = 'yes'
	 *     (verified against FluentForm HoneyPot::isEnabled). Other settings are
	 *     preserved. Skips cleanly if FluentForms isn't active.
	 * (b) Installs + activates limit-login-attempts-reloaded from wp.org if not
	 *     already active, reusing the plugin-install capability.
	 *
	 * Does NOT touch reCAPTCHA. Idempotent on both parts.
	 */
	public static function spam_protection( $request ) {
		$report = array(
			'fluentforms_honeypot' => self::enable_ff_honeypot(),
			'login_lockdown'       => self::ensure_login_lockdown(),
		);

		$changed = ( ! empty( $report['fluentforms_honeypot']['changed'] ) )
			|| ( ! empty( $report['login_lockdown']['changed'] ) );

		return MKB_REST_Controller::success(
			array(
				'changed' => $changed,
				'steps'   => $report,
			)
		);
	}

	/**
	 * Enable FluentForms honeypot via read-modify-write. Returns a step report.
	 *
	 * @return array
	 */
	private static function enable_ff_honeypot() {
		$ff_active = defined( 'FLUENTFORM' )
			|| class_exists( 'FluentForm\\Framework\\Foundation\\Application' )
			|| function_exists( 'wpFluentForm' );

		if ( ! $ff_active ) {
			return array(
				'changed' => false,
				'skipped' => true,
				'message' => 'FluentForms not active; honeypot skipped.',
			);
		}

		$option   = '_fluentform_global_form_settings';
		$settings = get_option( $option, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$current = isset( $settings['misc']['honeypotStatus'] ) ? $settings['misc']['honeypotStatus'] : null;

		if ( 'yes' === $current ) {
			return array(
				'changed' => false,
				'message' => 'FluentForms honeypot already enabled.',
			);
		}

		// Snapshot the whole option before the read-modify-write so rollback
		// restores all sibling settings exactly.
		$snapshot_id = MKB_History::record(
			'option_set',
			$option,
			$settings,
			null,
			array( 'endpoint' => 'spam-protection', 'field' => 'misc.honeypotStatus' )
		);

		if ( ! isset( $settings['misc'] ) || ! is_array( $settings['misc'] ) ) {
			$settings['misc'] = array();
		}
		$settings['misc']['honeypotStatus'] = 'yes';

		update_option( $option, $settings );

		return array(
			'changed'     => true,
			'message'     => 'FluentForms honeypot enabled.',
			'snapshot_id' => $snapshot_id,
		);
	}

	/**
	 * Install + activate limit-login-attempts-reloaded if not already active.
	 * Reuses the same plugins_api + Plugin_Upgrader path as
	 * MKB_Plugin_Endpoints. Returns a step report.
	 *
	 * @return array
	 */
	private static function ensure_login_lockdown() {
		$slug = 'limit-login-attempts-reloaded';

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Idempotency: already active?
		$active = (array) get_option( 'active_plugins', array() );
		foreach ( $active as $active_file ) {
			if ( 0 === strpos( $active_file, $slug . '/' ) ) {
				return array(
					'changed'        => false,
					'already_active' => true,
					'plugin'         => $active_file,
					'message'        => 'Login-lockdown plugin already active.',
				);
			}
		}

		// Already installed but not active? Find and activate it.
		$installed = get_plugins();
		foreach ( $installed as $file => $data ) {
			if ( 0 === strpos( $file, $slug . '/' ) ) {
				$result = activate_plugin( $file );
				if ( is_wp_error( $result ) ) {
					return array(
						'changed' => false,
						'plugin'  => $file,
						'error'   => $result->get_error_message(),
						'message' => 'Login-lockdown plugin installed but activation failed.',
					);
				}
				MKB_History::record( 'plugin_activate', $file, null, null, array( 'endpoint' => 'spam-protection' ) );
				return array(
					'changed' => true,
					'plugin'  => $file,
					'message' => 'Login-lockdown plugin activated.',
				);
			}
		}

		// Not installed — install from wp.org then activate.
		$plugin_file = self::install_from_wporg( $slug );
		if ( is_wp_error( $plugin_file ) ) {
			return array(
				'changed' => false,
				'error'   => $plugin_file->get_error_message(),
				'message' => 'Login-lockdown plugin install failed.',
			);
		}

		$activate = activate_plugin( $plugin_file );
		if ( is_wp_error( $activate ) ) {
			return array(
				'changed' => true,
				'plugin'  => $plugin_file,
				'error'   => $activate->get_error_message(),
				'message' => 'Login-lockdown plugin installed but activation failed.',
			);
		}

		MKB_History::record(
			'plugin_install_and_activate',
			$plugin_file,
			null,
			array( 'slug' => $slug ),
			array( 'endpoint' => 'spam-protection' )
		);

		return array(
			'changed' => true,
			'plugin'  => $plugin_file,
			'message' => 'Login-lockdown plugin installed and activated.',
		);
	}

	/**
	 * Install a wordpress.org plugin by slug. Returns the plugin file path or
	 * WP_Error. Mirrors MKB_Plugin_Endpoints::do_install (slug branch).
	 *
	 * @param string $slug wordpress.org plugin slug.
	 * @return string|WP_Error
	 */
	private static function install_from_wporg( $slug ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$download_url = isset( $api->download_link ) ? $api->download_link : '';
		if ( empty( $download_url ) ) {
			return new WP_Error(
				'mkb_no_download_link',
				sprintf( 'wordpress.org returned no download link for slug %s.', $slug ),
				array( 'status' => 502 )
			);
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $download_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			$messages = $skin->get_error_messages();
			return new WP_Error(
				'mkb_install_failed',
				'Plugin install failed: ' . ( $messages ? implode( '; ', $messages ) : 'unknown error' ),
				array( 'status' => 500 )
			);
		}

		$plugin_file = $upgrader->plugin_info();
		if ( empty( $plugin_file ) ) {
			return new WP_Error(
				'mkb_install_no_plugin_file',
				'Plugin installed but no plugin file path could be resolved.',
				array( 'status' => 500 )
			);
		}

		return $plugin_file;
	}

	/**
	 * POST /production-pass
	 *
	 * Orchestrator — runs all four production-hardening steps and returns a
	 * combined per-step report. Each step is independently idempotent and
	 * snapshotted, so the orchestrator inherits those properties.
	 */
	public static function production_pass( $request ) {
		$steps = array();

		$steps['permalinks']          = self::unwrap( self::set_permalinks( $request ) );
		$steps['disable_thumbnails']  = self::unwrap( self::disable_thumbnails( $request ) );
		$steps['litespeed_images']    = self::unwrap( self::litespeed_optimize_images( $request ) );
		$steps['spam_protection']     = self::unwrap( self::spam_protection( $request ) );

		$any_changed = false;
		foreach ( $steps as $step ) {
			if ( ! empty( $step['changed'] ) ) {
				$any_changed = true;
				break;
			}
		}

		return MKB_REST_Controller::success(
			array(
				'changed' => $any_changed,
				'steps'   => $steps,
			)
		);
	}

	/**
	 * Normalize a step result (WP_REST_Response or WP_Error) into a plain array
	 * for embedding in the orchestrator's combined report.
	 *
	 * @param mixed $result Return value of a step callback.
	 * @return array
	 */
	private static function unwrap( $result ) {
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => $result->get_error_code(),
				'message' => $result->get_error_message(),
			);
		}
		if ( $result instanceof WP_REST_Response ) {
			return (array) $result->get_data();
		}
		return (array) $result;
	}
}
