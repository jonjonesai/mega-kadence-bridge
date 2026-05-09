<?php
/**
 * Capabilities Endpoint — discovery surface for AI agents.
 *
 * Routes:
 *   GET /capabilities   Site context + Kadence stack inventory + operating doctrine
 *
 * This is the canonical first call any agent should make. It returns what the
 * site is, what's installed (so the agent can branch on real state instead of
 * guessing), and the Kadence-mastery operating instructions defined in
 * class-instructions.php.
 *
 * Modeled after Novamira's discover-abilities pattern, adapted to MKB's REST +
 * Application Password architecture.
 *
 * @package MegaKadenceBridge
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Capabilities_Endpoints {

	/**
	 * Register the /capabilities route.
	 *
	 * @param string $ns REST namespace.
	 */
	public static function register( $ns ) {
		register_rest_route(
			$ns,
			'/capabilities',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_capabilities' ),
				'permission_callback' => array( 'MKB_REST_Controller', 'check_permission' ),
			)
		);
	}

	/**
	 * GET /capabilities
	 *
	 * @return WP_REST_Response
	 */
	public static function get_capabilities() {
		$payload = array(
			'bridge'               => self::bridge_info(),
			'site'                 => self::site_info(),
			'stack'                => self::stack_info(),
			'multilingual'         => self::multilingual_info(),
			'endpoints'            => self::endpoint_inventory(),
			'kadence_instructions' => MKB_Instructions::build(),
		);

		return MKB_REST_Controller::success( $payload );
	}

	/**
	 * MKB itself — version, namespace, and locked-domain status.
	 *
	 * @return array
	 */
	private static function bridge_info() {
		$locked  = (string) get_option( MKB_LOCKED_DOMAIN_OPTION, '' );
		$current = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return array(
			'name'           => 'Mega Kadence Bridge',
			'version'        => MKB_VERSION,
			'namespace'      => MKB_REST_NAMESPACE,
			'locked_domain'  => '' === $locked ? null : $locked,
			'current_domain' => $current,
			'domain_match'   => '' === $locked ? null : ( $locked === $current ),
		);
	}

	/**
	 * Site context — what an agent needs to know about the WP environment.
	 *
	 * @return array
	 */
	private static function site_info() {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'home_url'    => home_url(),
			'site_url'    => site_url(),
			'admin_url'   => admin_url(),
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'locale'      => get_locale(),
			'timezone'    => wp_timezone_string(),
			'is_https'    => is_ssl(),
			'is_multisite' => is_multisite(),
		);
	}

	/**
	 * Kadence stack inventory — which Kadence pieces and complementary plugins
	 * are actually present on this site. Lets the agent branch on reality.
	 *
	 * @return array
	 */
	private static function stack_info() {
		$active_theme = wp_get_theme();
		$parent_theme = $active_theme->parent();

		// Detect Kadence even when running on a child theme.
		$is_kadence = false;
		$theme_to_check = $parent_theme ? $parent_theme : $active_theme;
		if ( $theme_to_check ) {
			$template = strtolower( (string) $theme_to_check->get_template() );
			$name     = strtolower( (string) $theme_to_check->get( 'Name' ) );
			$is_kadence = ( 'kadence' === $template ) || false !== strpos( $name, 'kadence' );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$plugin_present = function ( $needle ) use ( $all_plugins ) {
			foreach ( $all_plugins as $file => $data ) {
				if ( false !== stripos( $file, $needle ) ) {
					return array(
						'file'    => $file,
						'name'    => isset( $data['Name'] ) ? $data['Name'] : '',
						'version' => isset( $data['Version'] ) ? $data['Version'] : '',
						'active'  => is_plugin_active( $file ),
					);
				}
			}
			return null;
		};

		return array(
			'theme' => array(
				'name'        => $active_theme ? $active_theme->get( 'Name' ) : '',
				'version'     => $active_theme ? $active_theme->get( 'Version' ) : '',
				'template'    => $active_theme ? $active_theme->get_template() : '',
				'is_child'    => (bool) $parent_theme,
				'parent_name' => $parent_theme ? $parent_theme->get( 'Name' ) : null,
				'is_kadence'  => $is_kadence,
			),
			'kadence_blocks'      => $plugin_present( 'kadence-blocks/' ),
			'kadence_blocks_pro'  => $plugin_present( 'kadence-blocks-pro/' ),
			'kadence_pro'         => $plugin_present( 'kadence-pro/' ),
			'kadence_starter'     => $plugin_present( 'kadence-starter-templates/' ),
			'kadence_conversions' => $plugin_present( 'kadence-conversions/' ),
			'kadence_woo_extras'  => $plugin_present( 'kadence-woo-extras/' ),
			'iconic'              => $plugin_present( 'iconic-' ),
			'woocommerce'         => $plugin_present( 'woocommerce/woocommerce.php' ),
			'acf'                 => $plugin_present( 'advanced-custom-fields/' ),
			'acf_pro'             => $plugin_present( 'advanced-custom-fields-pro/' ),
			'wpml'                => $plugin_present( 'sitepress-multilingual-cms/' ),
			'polylang'            => $plugin_present( 'polylang/' ),
			'translatepress'      => $plugin_present( 'translatepress-multilingual/' ),
			'yoast_seo'           => $plugin_present( 'wordpress-seo/' ),
			'rank_math'           => $plugin_present( 'seo-by-rank-math/' ),
		);
	}

	/**
	 * Multilingual context — which translation system is governing content,
	 * and which languages are configured.
	 *
	 * Mirrors Novamira's helpers.php:295-323.
	 *
	 * @return array|null
	 */
	private static function multilingual_info() {
		// WPML.
		if ( function_exists( 'icl_get_languages' ) ) {
			$wpml = icl_get_languages( 'skip_missing=0' );
			if ( is_array( $wpml ) && ! empty( $wpml ) ) {
				return array(
					'plugin'    => 'WPML',
					'languages' => array_values( wp_list_pluck( $wpml, 'language_code' ) ),
				);
			}
		}

		// Polylang.
		if ( function_exists( 'pll_languages_list' ) ) {
			$languages = pll_languages_list();
			if ( is_array( $languages ) && ! empty( $languages ) ) {
				return array(
					'plugin'    => 'Polylang',
					'languages' => array_values( $languages ),
				);
			}
		}

		// TranslatePress.
		if ( class_exists( 'TRP_Translate_Press' ) ) {
			$trp_settings = get_option( 'trp_settings', array() );
			$languages    = isset( $trp_settings['translation-languages'] )
				? (array) $trp_settings['translation-languages']
				: array();
			if ( ! empty( $languages ) ) {
				return array(
					'plugin'    => 'TranslatePress',
					'languages' => array_values( $languages ),
				);
			}
		}

		return null;
	}

	/**
	 * Self-describing endpoint inventory.
	 *
	 * Grouped by category so an agent can scan for the right surface quickly.
	 * Hand-curated rather than introspected from rest_get_server() because the
	 * descriptions need to be agent-readable, not just route paths.
	 *
	 * @return array
	 */
	private static function endpoint_inventory() {
		$ns = '/wp-json/' . MKB_REST_NAMESPACE;

		$endpoints = array(
			'discovery' => array(
				array( 'GET',  $ns . '/capabilities', 'This endpoint. Site context, stack inventory, operating doctrine.' ),
				array( 'GET',  $ns . '/info',          'Compact site/theme/plugin/PHP version snapshot.' ),
			),
			'theme_identity' => array(
				array( 'GET/POST', $ns . '/palette',         'Kadence global color palette.' ),
				array( 'GET/POST', $ns . '/css',             'Site-wide custom CSS (use sparingly).' ),
				array( 'GET',      $ns . '/settings',        'All Kadence-prefixed theme_mods.' ),
				array( 'GET',      $ns . '/settings/all',    'Every theme_mod on the site.' ),
				array( 'GET/POST', $ns . '/theme-mod/{key}', 'Read or write a single theme_mod.' ),
				array( 'POST',     $ns . '/theme-mods/batch','Write multiple theme_mods atomically.' ),
				array( 'GET/POST', $ns . '/option/{key}',    'Read or write a wp_options row.' ),
			),
			'kadence' => array(
				array( 'GET',      $ns . '/blocks',                      'List registered Kadence blocks.' ),
				array( 'GET/POST', $ns . '/kadence-pro/config',          'Kadence Pro feature flags.' ),
				array( 'POST',     $ns . '/kadence-pro/preset/pod',      'Enable POD-recommended Pro modules.' ),
				array( 'GET',      $ns . '/header',                      'Header Builder configuration snapshot.' ),
				array( 'GET',      $ns . '/footer',                      'Footer Builder configuration snapshot.' ),
			),
			'content' => array(
				array( 'GET',      $ns . '/posts',              'List posts/pages with filters.' ),
				array( 'GET/POST', $ns . '/posts/{id}',         'Read or update a single post.' ),
				array( 'POST',     $ns . '/posts/create',       'Create a new post or page.' ),
				array( 'GET',      $ns . '/posts/find',         'Find a post by slug (idempotency helper).' ),
				array( 'POST',     $ns . '/posts/{id}/normalize-blocks', 'Normalize Kadence block markup.' ),
				array( 'POST',     $ns . '/pages/ensure',       'Create page only if it does not exist.' ),
				array( 'GET',      $ns . '/menus',              'List navigation menus.' ),
				array( 'POST',     $ns . '/menus/create',       'Create a navigation menu.' ),
				array( 'POST',     $ns . '/menus/{id}/items',   'Add an item to a menu.' ),
			),
			'media' => array(
				array( 'GET',  $ns . '/media',                  'List the media library.' ),
				array( 'POST', $ns . '/media/upload-from-url',  'Upload a remote image into the library.' ),
			),
			'commerce' => array(
				array( 'GET',      $ns . '/woo/status',              'WooCommerce + Kadence Woo addon status.' ),
				array( 'GET/POST', $ns . '/woo/settings',            'Woo-related Kadence settings.' ),
				array( 'GET',      $ns . '/woo/products',            'List products.' ),
				array( 'GET/POST', $ns . '/woo/products/{id}',       'Read or update a product.' ),
				array( 'POST',     $ns . '/woo/products/create',     'Create a product.' ),
				array( 'GET',      $ns . '/woo/categories',          'List product categories.' ),
				array( 'POST',     $ns . '/woo/categories/create',   'Create a product category.' ),
				array( 'GET',      $ns . '/woo/orders',              'List orders.' ),
			),
			'plugins' => array(
				array( 'GET',  $ns . '/plugins',                'List all installed plugins.' ),
			),
			'cache' => array(
				array( 'GET',  $ns . '/render',                 'Cache-bypassed HTML render of any URL.' ),
				array( 'POST', $ns . '/cache/flush',            'Flush every detected cache layer.' ),
			),
			'reversibility' => array(
				array( 'GET',  $ns . '/history',          'List recent snapshots.' ),
				array( 'GET',  $ns . '/history/{id}',     'Get a single snapshot.' ),
				array( 'POST', $ns . '/rollback/{id}',    'Revert a change.' ),
			),
		);

		// Only advertise commerce endpoints when WooCommerce is actually active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			unset( $endpoints['commerce'] );
		}

		return $endpoints;
	}
}
