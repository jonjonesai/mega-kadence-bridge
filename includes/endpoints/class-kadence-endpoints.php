<?php
/**
 * Kadence Endpoints — blocks discovery, Pro feature flags, header, footer.
 *
 * Routes:
 *   GET /blocks                     List all registered Kadence blocks
 *   GET|POST /kadence-pro/config    Kadence Pro feature flags
 *   GET /header                     Current header configuration snapshot
 *   GET /footer                     Current footer configuration snapshot
 *   POST /kadence-pro/preset/pod    Enable POD-recommended Pro modules in one call
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Kadence_Endpoints {

	public static function register( $ns ) {
		$permission = array( 'MKB_REST_Controller', 'check_permission' );

		register_rest_route(
			$ns,
			'/blocks',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_blocks' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/kadence-pro/config',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_pro_config' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'set_pro_config' ),
					'permission_callback' => $permission,
				),
			)
		);

		register_rest_route(
			$ns,
			'/kadence-pro/preset/pod',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'apply_pod_preset' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/header',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_header' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/footer',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_footer' ),
				'permission_callback' => $permission,
			)
		);
	}

	public static function list_blocks( $request ) {
		$registry = WP_Block_Type_Registry::get_instance();
		$all      = $registry->get_all_registered();
		$kadence  = array();

		foreach ( $all as $name => $block ) {
			if ( 0 !== strpos( $name, 'kadence/' ) ) {
				continue;
			}
			$kadence[ $name ] = array(
				'title'       => isset( $block->title ) ? $block->title : '',
				'description' => isset( $block->description ) ? $block->description : '',
				'category'    => isset( $block->category ) ? $block->category : '',
				'attributes'  => isset( $block->attributes ) ? array_keys( $block->attributes ) : array(),
				'supports'    => isset( $block->supports ) ? $block->supports : array(),
			);
		}

		return MKB_REST_Controller::success(
			array(
				'blocks' => $kadence,
				'total'  => count( $kadence ),
			)
		);
	}

	public static function get_pro_config( $request ) {
		$config = get_option( 'kadence_pro_theme_config', array() );

		// Default schema — all modules disabled until turned on.
		$defaults = array(
			'conditional_headers' => false,
			'elements'            => false,
			'adv_pages'           => false,
			'header_addons'       => false,
			'mega_menu'           => false,
			'woocommerce_addons'  => false,
			'scripts'             => false,
			'infinite'            => false,
			'localgravatars'      => false,
			'archive_meta'        => false,
			'dark_mode'           => false,
		);

		$merged = is_array( $config ) ? array_merge( $defaults, $config ) : $defaults;

		return MKB_REST_Controller::success(
			array(
				'config'     => $merged,
				'pro_active' => class_exists( 'Kadence_Theme_Pro' ),
			)
		);
	}

	public static function set_pro_config( $request ) {
		$body = $request->get_json_params();
		$new  = isset( $body['config'] ) ? $body['config'] : array();

		if ( ! is_array( $new ) ) {
			return MKB_REST_Controller::error( 'invalid_config', 'config must be an object keyed by module name.', 400 );
		}

		$previous = get_option( 'kadence_pro_theme_config', array() );
		$merged   = array_merge( is_array( $previous ) ? $previous : array(), $new );
		update_option( 'kadence_pro_theme_config', $merged );

		$snapshot_id = MKB_History::record( 'kadence_pro_config_set', 'kadence_pro_theme_config', $previous, $merged );

		return MKB_REST_Controller::success(
			array(
				'previous'    => $previous,
				'new'         => $merged,
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	/**
	 * POST /kadence-pro/preset/pod
	 *
	 * Enables the Kadence Pro modules most useful for a POD store in one call.
	 * Intended to be invoked by the deploy-pod-store recipe.
	 */
	public static function apply_pod_preset( $request ) {
		$preset = array(
			'header_addons'      => true,
			'mega_menu'          => true,
			'elements'           => true,
			'conditional_headers' => true,
			'woocommerce_addons' => true,
			'scripts'            => true,
			'dark_mode'          => false,
			'infinite'           => false,
		);

		$previous = get_option( 'kadence_pro_theme_config', array() );
		$merged   = array_merge( is_array( $previous ) ? $previous : array(), $preset );
		update_option( 'kadence_pro_theme_config', $merged );

		$snapshot_id = MKB_History::record( 'kadence_pro_preset_pod', 'kadence_pro_theme_config', $previous, $merged );

		return MKB_REST_Controller::success(
			array(
				'applied'     => $preset,
				'final'       => $merged,
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	public static function get_header( $request ) {
		$keys = array(
			// Layout and rows
			'header_main_layout', 'header_main_background', 'header_main_bottom_border',
			'header_main_height', 'header_main_padding',
			// 9-slot items
			'header_top_left_items', 'header_top_center_items', 'header_top_right_items',
			'header_main_left_items', 'header_main_center_items', 'header_main_right_items',
			'header_bottom_left_items', 'header_bottom_center_items', 'header_bottom_right_items',
			// Sticky / transparent
			'header_sticky', 'header_sticky_shrink', 'header_sticky_main_shrink',
			'transparent_header_enable', 'transparent_header_device',
			// Logo
			'logo_width', 'logo_layout', 'use_mobile_logo',
			// Mobile
			'header_mobile_items', 'mobile_trigger_style', 'mobile_navigation_style',
		);

		$settings = array();
		foreach ( $keys as $key ) {
			$settings[ $key ] = get_theme_mod( $key );
		}

		return MKB_REST_Controller::success( array( 'header' => $settings ) );
	}

	public static function get_footer( $request ) {
		$keys = array(
			'footer_top_items', 'footer_middle_items', 'footer_bottom_items',
			'footer_top_columns', 'footer_middle_columns', 'footer_bottom_columns',
			'footer_top_layout', 'footer_middle_layout', 'footer_bottom_layout',
			'footer_top_background', 'footer_middle_background', 'footer_bottom_background',
			'footer_top_height', 'footer_middle_height', 'footer_bottom_height',
			'footer_html_content', 'footer_social_items',
		);

		$settings = array();
		foreach ( $keys as $key ) {
			$settings[ $key ] = get_theme_mod( $key );
		}

		return MKB_REST_Controller::success( array( 'footer' => $settings ) );
	}
}
