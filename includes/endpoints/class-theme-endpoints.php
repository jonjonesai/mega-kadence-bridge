<?php
/**
 * Theme Endpoints — Kadence theme_mods, WordPress options, palette, custom CSS.
 *
 * Routes:
 *   GET|POST /theme-mod/{key}
 *   POST     /theme-mods/batch
 *   GET|POST /option/{key}
 *   GET|POST /palette
 *   GET|POST /css
 *   GET      /settings                (dumps all Kadence-prefixed theme_mods)
 *   GET      /settings/all            (dumps every theme_mod)
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Theme_Endpoints {

	public static function register( $ns ) {
		$permission = array( 'MKB_REST_Controller', 'check_permission' );

		register_rest_route(
			$ns,
			'/theme-mod/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_theme_mod' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'set_theme_mod' ),
					'permission_callback' => $permission,
				),
			)
		);

		register_rest_route(
			$ns,
			'/theme-mods/batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'set_theme_mods_batch' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/option/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_option' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'set_option' ),
					'permission_callback' => $permission,
				),
			)
		);

		register_rest_route(
			$ns,
			'/palette',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_palette' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'set_palette' ),
					'permission_callback' => $permission,
				),
			)
		);

		register_rest_route(
			$ns,
			'/css',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_css' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'set_css' ),
					'permission_callback' => $permission,
				),
			)
		);

		register_rest_route(
			$ns,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_kadence_settings' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/settings/all',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_all_settings' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * GET /theme-mod/{key}
	 */
	public static function get_theme_mod( $request ) {
		$key = $request->get_param( 'key' );
		return MKB_REST_Controller::success(
			array(
				'key'   => $key,
				'value' => get_theme_mod( $key ),
			)
		);
	}

	/**
	 * POST /theme-mod/{key}
	 */
	public static function set_theme_mod( $request ) {
		$key  = $request->get_param( 'key' );
		$body = $request->get_json_params();

		if ( ! isset( $body['value'] ) ) {
			return MKB_REST_Controller::error( 'missing_value', 'value is required in the request body.', 400 );
		}

		$previous = get_theme_mod( $key );
		set_theme_mod( $key, $body['value'] );

		$snapshot_id = MKB_History::record( 'theme_mod_set', $key, $previous, $body['value'] );

		return MKB_REST_Controller::success(
			array(
				'key'         => $key,
				'previous'    => $previous,
				'new'         => $body['value'],
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	/**
	 * POST /theme-mods/batch
	 */
	public static function set_theme_mods_batch( $request ) {
		$body = $request->get_json_params();
		$mods = isset( $body['mods'] ) ? $body['mods'] : array();

		if ( empty( $mods ) || ! is_array( $mods ) ) {
			return MKB_REST_Controller::error( 'invalid_mods', 'mods must be a non-empty object keyed by setting name.', 400 );
		}

		$results  = array();
		$previous = array();
		foreach ( $mods as $key => $value ) {
			$previous[ $key ] = get_theme_mod( $key );
			set_theme_mod( $key, $value );
			$results[ $key ] = array(
				'previous' => $previous[ $key ],
				'new'      => $value,
			);
		}

		$snapshot_id = MKB_History::record(
			'theme_mods_batch_set',
			implode( ',', array_keys( $mods ) ),
			$previous,
			$mods
		);

		return MKB_REST_Controller::success(
			array(
				'results'     => $results,
				'count'       => count( $results ),
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	/**
	 * GET /option/{key}
	 */
	public static function get_option( $request ) {
		$key = $request->get_param( 'key' );
		return MKB_REST_Controller::success(
			array(
				'key'   => $key,
				'value' => get_option( $key ),
			)
		);
	}

	/**
	 * POST /option/{key}
	 */
	public static function set_option( $request ) {
		$key  = $request->get_param( 'key' );
		$body = $request->get_json_params();

		if ( ! array_key_exists( 'value', $body ) ) {
			return MKB_REST_Controller::error( 'missing_value', 'value is required in the request body.', 400 );
		}

		$previous = get_option( $key );
		update_option( $key, $body['value'] );

		$snapshot_id = MKB_History::record( 'option_set', $key, $previous, $body['value'] );

		return MKB_REST_Controller::success(
			array(
				'key'         => $key,
				'previous'    => $previous,
				'new'         => $body['value'],
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	/**
	 * GET /palette
	 */
	public static function get_palette( $request ) {
		$palette = get_option( 'kadence_global_palette' );
		if ( ! $palette ) {
			$palette = get_theme_mod( 'kadence_global_palette' );
		}
		return MKB_REST_Controller::success( array( 'palette' => $palette ) );
	}

	/**
	 * POST /palette
	 */
	public static function set_palette( $request ) {
		$body    = $request->get_json_params();
		$palette = isset( $body['palette'] ) ? $body['palette'] : null;

		if ( empty( $palette ) || ! is_array( $palette ) ) {
			return MKB_REST_Controller::error( 'invalid_palette', 'palette must be an array.', 400 );
		}

		$previous       = get_option( 'kadence_global_palette' );
		$palette_string = wp_json_encode( $palette );
		update_option( 'kadence_global_palette', $palette_string );
		set_theme_mod( 'kadence_global_palette', $palette_string );

		$snapshot_id = MKB_History::record( 'palette_set', 'kadence_global_palette', $previous, $palette );

		return MKB_REST_Controller::success(
			array(
				'previous'    => $previous,
				'new'         => $palette,
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	/**
	 * GET /css
	 */
	public static function get_css( $request ) {
		return MKB_REST_Controller::success( array( 'css' => wp_get_custom_css() ) );
	}

	/**
	 * POST /css
	 */
	public static function set_css( $request ) {
		$body   = $request->get_json_params();
		$css    = isset( $body['css'] ) ? $body['css'] : '';
		$append = ! empty( $body['append'] );

		$previous = wp_get_custom_css();
		$new_css  = $append ? ( $previous . "\n\n" . $css ) : $css;

		$result = wp_update_custom_css_post( $new_css );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$snapshot_id = MKB_History::record( 'css_set', 'custom_css', $previous, $new_css );

		return MKB_REST_Controller::success(
			array(
				'previous'    => $previous,
				'new'         => $new_css,
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	/**
	 * GET /settings — dump all Kadence-prefixed theme_mods.
	 */
	public static function get_kadence_settings( $request ) {
		$theme_mods = get_theme_mods();
		if ( ! is_array( $theme_mods ) ) {
			$theme_mods = array();
		}

		$kadence_prefixes = array(
			'header_', 'footer_', 'content_', 'base_', 'heading_', 'buttons_',
			'site_', 'page_', 'post_', 'product_', 'mobile_', 'transparent_',
			'cart_', 'ajax_', 'archive_', 'search_', 'sidebar_', 'boxed_',
			'dropdown_', 'scroll_', 'comments_', 'nav_', 'custom_', 'logo_',
			'breadcrumb_',
		);

		$filtered = array();
		foreach ( $theme_mods as $key => $value ) {
			foreach ( $kadence_prefixes as $prefix ) {
				if ( strpos( $key, $prefix ) === 0 ) {
					$filtered[ $key ] = $value;
					break;
				}
			}
		}

		$filtered['kadence_global_palette']    = get_option( 'kadence_global_palette' );
		$filtered['kadence_pro_theme_config']  = get_option( 'kadence_pro_theme_config' );

		return MKB_REST_Controller::success(
			array(
				'settings' => $filtered,
				'total'    => count( $filtered ),
			)
		);
	}

	/**
	 * GET /settings/all — dump every theme_mod, no filtering.
	 */
	public static function get_all_settings( $request ) {
		$theme_mods = get_theme_mods();
		if ( ! is_array( $theme_mods ) ) {
			$theme_mods = array();
		}
		return MKB_REST_Controller::success(
			array(
				'settings' => $theme_mods,
				'total'    => count( $theme_mods ),
			)
		);
	}
}
