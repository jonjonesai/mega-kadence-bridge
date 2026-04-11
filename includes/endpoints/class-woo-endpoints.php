<?php
/**
 * WooCommerce Endpoints — products, categories, orders, Kadence Pro WC settings.
 *
 * Only registered when WooCommerce is active.
 *
 * Routes:
 *   GET  /woo/status
 *   GET|POST /woo/settings
 *   GET  /woo/products
 *   GET  /woo/products/{id}
 *   POST /woo/products/create
 *   POST /woo/products/{id}
 *   GET  /woo/categories
 *   POST /woo/categories/create
 *   GET  /woo/orders
 *   POST /woo/api-keys/generate
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Woo_Endpoints {

	public static function register( $ns ) {
		$permission = array( 'MKB_REST_Controller', 'check_permission' );

		register_rest_route( $ns, '/woo/status', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_status' ),
			'permission_callback' => $permission,
		) );

		register_rest_route( $ns, '/woo/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_settings' ),
				'permission_callback' => $permission,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'set_settings' ),
				'permission_callback' => $permission,
			),
		) );

		register_rest_route( $ns, '/woo/products', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'list_products' ),
			'permission_callback' => $permission,
		) );

		register_rest_route( $ns, '/woo/products/create', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'create_product' ),
			'permission_callback' => $permission,
		) );

		register_rest_route( $ns, '/woo/products/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_product' ),
				'permission_callback' => $permission,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_product' ),
				'permission_callback' => $permission,
			),
		) );

		register_rest_route( $ns, '/woo/categories', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'list_categories' ),
			'permission_callback' => $permission,
		) );

		register_rest_route( $ns, '/woo/categories/create', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'create_category' ),
			'permission_callback' => $permission,
		) );

		register_rest_route( $ns, '/woo/orders', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'list_orders' ),
			'permission_callback' => $permission,
		) );
	}

	public static function get_status( $request ) {
		$status = array(
			'woocommerce_active'     => class_exists( 'WooCommerce' ),
			'kadence_woo_addons'     => false,
			'woocommerce_version'    => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'currency'               => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : null,
			'currency_symbol'        => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : null,
			'product_count'          => (int) wp_count_posts( 'product' )->publish,
		);

		$pro_config = get_option( 'kadence_pro_theme_config', array() );
		if ( is_array( $pro_config ) && ! empty( $pro_config['woocommerce_addons'] ) ) {
			$status['kadence_woo_addons'] = true;
		}

		return MKB_REST_Controller::success( $status );
	}

	public static function get_settings( $request ) {
		$theme_mod_keys = array(
			// Cart behavior (Pro)
			'cart_pop_show_on_add', 'cart_pop_show_free_shipping',
			'cart_pop_free_shipping_price', 'cart_pop_free_shipping_message',
			'cart_pop_free_shipping_calc_tax', 'ajax_add_single_products',
			// Single product (Pro)
			'product_sticky_add_to_cart', 'product_sticky_add_to_cart_placement',
			'product_sticky_mobile_add_to_cart', 'product_sticky_mobile_add_to_cart_placement',
			// Product archive
			'product_archive_shop_filter_popout', 'product_archive_shop_filter_label',
			'product_archive_shop_filter_icon', 'product_archive_shop_filter_style',
			'product_archive_columns', 'product_archive_content_style', 'product_archive_layout',
			// Product elements
			'product_content_element_category', 'product_content_element_title',
			'product_content_element_rating', 'product_content_element_price',
			'product_content_element_add_to_cart', 'product_content_element_payments',
		);

		$settings = array();
		foreach ( $theme_mod_keys as $key ) {
			$settings[ $key ] = get_theme_mod( $key );
		}

		$option_keys = array(
			'woocommerce_shop_page_id', 'woocommerce_cart_page_id',
			'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id',
			'woocommerce_currency', 'woocommerce_default_country',
		);
		foreach ( $option_keys as $key ) {
			$settings[ $key ] = get_option( $key );
		}

		return MKB_REST_Controller::success( array( 'settings' => $settings ) );
	}

	public static function set_settings( $request ) {
		$body     = $request->get_json_params();
		$settings = isset( $body['settings'] ) ? $body['settings'] : array();

		if ( empty( $settings ) || ! is_array( $settings ) ) {
			return MKB_REST_Controller::error( 'invalid_settings', 'settings must be a non-empty object.', 400 );
		}

		$results  = array();
		$previous = array();
		foreach ( $settings as $key => $value ) {
			if ( 0 === strpos( $key, 'woocommerce_' ) ) {
				$previous[ $key ] = get_option( $key );
				update_option( $key, $value );
			} else {
				$previous[ $key ] = get_theme_mod( $key );
				set_theme_mod( $key, $value );
			}
			$results[ $key ] = array(
				'previous' => $previous[ $key ],
				'new'      => $value,
			);
		}

		$snapshot_id = MKB_History::record( 'woo_settings_set', implode( ',', array_keys( $settings ) ), $previous, $settings );

		return MKB_REST_Controller::success(
			array(
				'results'     => $results,
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	public static function list_products( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return MKB_REST_Controller::error( 'woo_inactive', 'WooCommerce is not active.', 400 );
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => $request->get_param( 'status' ) ?: 'publish',
			'posts_per_page' => (int) ( $request->get_param( 'per_page' ) ?: 20 ),
			'paged'          => (int) ( $request->get_param( 'page' ) ?: 1 ),
		);

		$category = $request->get_param( 'category' );
		if ( $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$query    = new WP_Query( $args );
		$products = array();
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}
			$products[] = array(
				'id'            => $post->ID,
				'name'          => $product->get_name(),
				'slug'          => $product->get_slug(),
				'type'          => $product->get_type(),
				'status'        => $product->get_status(),
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'sku'           => $product->get_sku(),
				'stock_status'  => $product->get_stock_status(),
				'url'           => $product->get_permalink(),
				'image'         => wp_get_attachment_url( $product->get_image_id() ),
			);
		}

		return MKB_REST_Controller::success(
			array(
				'products' => $products,
				'total'    => (int) $query->found_posts,
				'pages'    => (int) $query->max_num_pages,
			)
		);
	}

	public static function get_product( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return MKB_REST_Controller::error( 'woo_inactive', 'WooCommerce is not active.', 400 );
		}

		$id      = (int) $request->get_param( 'id' );
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return MKB_REST_Controller::error( 'not_found', 'Product not found.', 404 );
		}

		return MKB_REST_Controller::success(
			array(
				'id'                => $product->get_id(),
				'name'              => $product->get_name(),
				'slug'              => $product->get_slug(),
				'type'              => $product->get_type(),
				'status'            => $product->get_status(),
				'description'       => $product->get_description(),
				'short_description' => $product->get_short_description(),
				'price'             => $product->get_price(),
				'regular_price'     => $product->get_regular_price(),
				'sale_price'        => $product->get_sale_price(),
				'sku'               => $product->get_sku(),
				'stock_status'      => $product->get_stock_status(),
				'stock_quantity'    => $product->get_stock_quantity(),
				'categories'        => wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) ),
				'tags'              => wp_get_post_terms( $id, 'product_tag', array( 'fields' => 'names' ) ),
				'image'             => wp_get_attachment_url( $product->get_image_id() ),
				'gallery'           => array_map( 'wp_get_attachment_url', $product->get_gallery_image_ids() ),
				'url'               => $product->get_permalink(),
			)
		);
	}

	public static function create_product( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return MKB_REST_Controller::error( 'woo_inactive', 'WooCommerce is not active.', 400 );
		}

		$body    = $request->get_json_params();
		$product = new WC_Product_Simple();

		$product->set_name( isset( $body['name'] ) ? $body['name'] : '' );
		$product->set_status( isset( $body['status'] ) ? $body['status'] : 'publish' );
		$product->set_description( isset( $body['description'] ) ? $body['description'] : '' );
		$product->set_short_description( isset( $body['short_description'] ) ? $body['short_description'] : '' );

		if ( isset( $body['regular_price'] ) ) {
			$product->set_regular_price( $body['regular_price'] );
		}
		if ( isset( $body['sale_price'] ) ) {
			$product->set_sale_price( $body['sale_price'] );
		}
		if ( isset( $body['sku'] ) ) {
			$product->set_sku( $body['sku'] );
		}
		if ( isset( $body['stock_quantity'] ) ) {
			$product->set_stock_quantity( (int) $body['stock_quantity'] );
			$product->set_manage_stock( true );
		}
		if ( isset( $body['image_id'] ) ) {
			$product->set_image_id( (int) $body['image_id'] );
		}

		$product_id = $product->save();

		if ( isset( $body['categories'] ) && is_array( $body['categories'] ) ) {
			wp_set_object_terms( $product_id, $body['categories'], 'product_cat' );
		}

		MKB_History::record( 'product_create', (string) $product_id, null, $body );

		return MKB_REST_Controller::success(
			array(
				'id'       => $product_id,
				'url'      => get_permalink( $product_id ),
				'edit_url' => admin_url( "post.php?post={$product_id}&action=edit" ),
			)
		);
	}

	public static function update_product( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return MKB_REST_Controller::error( 'woo_inactive', 'WooCommerce is not active.', 400 );
		}

		$id      = (int) $request->get_param( 'id' );
		$body    = $request->get_json_params();
		$product = wc_get_product( $id );

		if ( ! $product ) {
			return MKB_REST_Controller::error( 'not_found', 'Product not found.', 404 );
		}

		$previous = array(
			'name'          => $product->get_name(),
			'status'        => $product->get_status(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
		);

		if ( isset( $body['name'] ) ) {
			$product->set_name( $body['name'] );
		}
		if ( isset( $body['status'] ) ) {
			$product->set_status( $body['status'] );
		}
		if ( isset( $body['description'] ) ) {
			$product->set_description( $body['description'] );
		}
		if ( isset( $body['short_description'] ) ) {
			$product->set_short_description( $body['short_description'] );
		}
		if ( isset( $body['regular_price'] ) ) {
			$product->set_regular_price( $body['regular_price'] );
		}
		if ( isset( $body['sale_price'] ) ) {
			$product->set_sale_price( $body['sale_price'] );
		}
		if ( isset( $body['sku'] ) ) {
			$product->set_sku( $body['sku'] );
		}
		if ( isset( $body['stock_quantity'] ) ) {
			$product->set_stock_quantity( (int) $body['stock_quantity'] );
		}
		if ( isset( $body['image_id'] ) ) {
			$product->set_image_id( (int) $body['image_id'] );
		}

		$product->save();

		if ( isset( $body['categories'] ) && is_array( $body['categories'] ) ) {
			wp_set_object_terms( $id, $body['categories'], 'product_cat' );
		}

		$snapshot_id = MKB_History::record( 'product_update', (string) $id, $previous, $body );

		return MKB_REST_Controller::success(
			array(
				'id'          => $id,
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	public static function list_categories( $request ) {
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		$data = array();
		foreach ( $categories as $cat ) {
			$data[] = array(
				'id'     => $cat->term_id,
				'name'   => $cat->name,
				'slug'   => $cat->slug,
				'parent' => $cat->parent,
				'count'  => $cat->count,
			);
		}

		return MKB_REST_Controller::success( array( 'categories' => $data ) );
	}

	public static function create_category( $request ) {
		$body = $request->get_json_params();
		$name = isset( $body['name'] ) ? $body['name'] : '';

		if ( empty( $name ) ) {
			return MKB_REST_Controller::error( 'missing_name', 'name is required.', 400 );
		}

		$args = array();
		if ( isset( $body['slug'] ) ) {
			$args['slug'] = sanitize_title( $body['slug'] );
		}
		if ( isset( $body['parent'] ) ) {
			$args['parent'] = (int) $body['parent'];
		}
		if ( isset( $body['description'] ) ) {
			$args['description'] = $body['description'];
		}

		$result = wp_insert_term( $name, 'product_cat', $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		MKB_History::record( 'product_cat_create', $name, null, $body );

		return MKB_REST_Controller::success(
			array(
				'id'   => $result['term_id'],
				'name' => $name,
			)
		);
	}

	public static function list_orders( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return MKB_REST_Controller::error( 'woo_inactive', 'WooCommerce is not active.', 400 );
		}

		$args = array(
			'limit'   => (int) ( $request->get_param( 'per_page' ) ?: 20 ),
			'page'    => (int) ( $request->get_param( 'page' ) ?: 1 ),
			'status'  => $request->get_param( 'status' ) ?: 'any',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$orders = wc_get_orders( $args );
		$data   = array();
		foreach ( $orders as $order ) {
			$data[] = array(
				'id'           => $order->get_id(),
				'status'       => $order->get_status(),
				'total'        => $order->get_total(),
				'currency'     => $order->get_currency(),
				'customer'     => $order->get_billing_email(),
				'date_created' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
				'items_count'  => $order->get_item_count(),
			);
		}

		return MKB_REST_Controller::success( array( 'orders' => $data ) );
	}
}
