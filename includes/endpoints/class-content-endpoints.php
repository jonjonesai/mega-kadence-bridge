<?php
/**
 * Content Endpoints — posts, pages, menus.
 *
 * Routes:
 *   GET  /posts                        list posts with filters
 *   GET  /posts/{id}                   get single post
 *   POST /posts/{id}                   update post
 *   POST /posts/create                 create new post/page
 *   GET  /posts/find?slug=...          find post by slug (idempotency helper)
 *   POST /pages/ensure                 create page if it doesn't exist, return existing if it does
 *   POST /posts/{id}/normalize-blocks  re-serialize blocks to eliminate editor recovery prompts
 *   GET  /menus                        list nav menus
 *   POST /menus/create                 create new menu
 *   POST /menus/{id}                   update menu
 *   POST /menus/{id}/items             add menu item
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Content_Endpoints {

	public static function register( $ns ) {
		$permission = array( 'MKB_REST_Controller', 'check_permission' );

		// GET /posts — list
		register_rest_route(
			$ns,
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_posts' ),
				'permission_callback' => $permission,
			)
		);

		// POST /posts/create
		register_rest_route(
			$ns,
			'/posts/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_post' ),
				'permission_callback' => $permission,
			)
		);

		// GET /posts/find (idempotency helper)
		register_rest_route(
			$ns,
			'/posts/find',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'find_post' ),
				'permission_callback' => $permission,
				'args'                => array(
					'slug' => array( 'sanitize_callback' => 'sanitize_title' ),
					'type' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// POST /pages/ensure — create-or-return
		register_rest_route(
			$ns,
			'/pages/ensure',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ensure_page' ),
				'permission_callback' => $permission,
			)
		);

		// GET|POST /posts/{id}
		register_rest_route(
			$ns,
			'/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_post' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'update_post' ),
					'permission_callback' => $permission,
				),
			)
		);

		// POST /posts/{id}/normalize-blocks
		register_rest_route(
			$ns,
			'/posts/(?P<id>\d+)/normalize-blocks',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'normalize_blocks' ),
				'permission_callback' => $permission,
			)
		);

		// GET /menus
		register_rest_route(
			$ns,
			'/menus',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_menus' ),
				'permission_callback' => $permission,
			)
		);

		// POST /menus/create
		register_rest_route(
			$ns,
			'/menus/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_menu' ),
				'permission_callback' => $permission,
			)
		);

		// POST /menus/{id}/items
		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)/items',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'add_menu_item' ),
				'permission_callback' => $permission,
			)
		);
	}

	// ---------------------------------------------------------------------
	// POSTS / PAGES
	// ---------------------------------------------------------------------

	public static function list_posts( $request ) {
		$query = new WP_Query(
			array(
				'post_type'      => $request->get_param( 'type' ) ?: 'post',
				'post_status'    => $request->get_param( 'status' ) ?: 'any',
				'posts_per_page' => (int) ( $request->get_param( 'per_page' ) ?: 20 ),
				'paged'          => (int) ( $request->get_param( 'page' ) ?: 1 ),
				'orderby'        => $request->get_param( 'orderby' ) ?: 'date',
				'order'          => $request->get_param( 'order' ) ?: 'DESC',
				's'              => $request->get_param( 'search' ) ?: '',
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'slug'   => $post->post_name,
				'status' => $post->post_status,
				'type'   => $post->post_type,
				'url'    => get_permalink( $post->ID ),
				'date'   => $post->post_date,
			);
		}

		return MKB_REST_Controller::success(
			array(
				'posts' => $posts,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
			)
		);
	}

	public static function get_post( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post ) {
			return MKB_REST_Controller::error( 'not_found', 'Post not found.', 404 );
		}

		return MKB_REST_Controller::success(
			array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'slug'     => $post->post_name,
				'content'  => $post->post_content,
				'excerpt'  => $post->post_excerpt,
				'status'   => $post->post_status,
				'type'     => $post->post_type,
				'url'      => get_permalink( $post->ID ),
				'modified' => $post->post_modified,
				'parent'   => $post->post_parent,
				'meta'     => get_post_meta( $post->ID ),
			)
		);
	}

	public static function update_post( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params();

		$post = get_post( $id );
		if ( ! $post ) {
			return MKB_REST_Controller::error( 'not_found', 'Post not found.', 404 );
		}

		// Snapshot previous state for rollback.
		$previous = array(
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'status'  => $post->post_status,
		);

		$update_data = array( 'ID' => $id );
		$changed     = array();

		if ( isset( $body['title'] ) ) {
			$update_data['post_title'] = $body['title'];
			$changed[]                 = 'title';
		}
		if ( isset( $body['content'] ) ) {
			$update_data['post_content'] = $body['content'];
			$changed[]                   = 'content';
		}
		if ( isset( $body['excerpt'] ) ) {
			$update_data['post_excerpt'] = $body['excerpt'];
			$changed[]                   = 'excerpt';
		}
		if ( isset( $body['status'] ) ) {
			$update_data['post_status'] = $body['status'];
			$changed[]                  = 'status';
		}

		$result = wp_update_post( $update_data, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update meta if provided. Per-key snapshot so rollback is reversible.
		if ( isset( $body['meta'] ) && is_array( $body['meta'] ) ) {
			foreach ( $body['meta'] as $meta_key => $meta_value ) {
				$previous_meta = get_post_meta( $id, $meta_key, true );
				update_post_meta( $id, $meta_key, $meta_value );
				$previous[ 'meta.' . $meta_key ] = $previous_meta;
				$changed[]                       = 'meta.' . $meta_key;
			}
		}

		$snapshot_id = MKB_History::record( 'post_update', (string) $id, $previous, $body );

		return MKB_REST_Controller::success(
			array(
				'id'          => $id,
				'changed'     => $changed,
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	public static function create_post( $request ) {
		$body = $request->get_json_params();

		$post_data = array(
			'post_title'   => isset( $body['title'] ) ? $body['title'] : '',
			'post_content' => isset( $body['content'] ) ? $body['content'] : '',
			'post_excerpt' => isset( $body['excerpt'] ) ? $body['excerpt'] : '',
			'post_status'  => isset( $body['status'] ) ? $body['status'] : 'draft',
			'post_type'    => isset( $body['type'] ) ? $body['type'] : 'post',
		);
		if ( isset( $body['parent'] ) ) {
			$post_data['post_parent'] = (int) $body['parent'];
		}
		if ( isset( $body['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $body['slug'] );
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( isset( $body['featured_image_id'] ) ) {
			set_post_thumbnail( $post_id, (int) $body['featured_image_id'] );
		}
		if ( isset( $body['meta'] ) && is_array( $body['meta'] ) ) {
			foreach ( $body['meta'] as $k => $v ) {
				update_post_meta( $post_id, $k, $v );
			}
		}

		MKB_History::record( 'post_create', (string) $post_id, null, $post_data );

		return MKB_REST_Controller::success(
			array(
				'id'       => $post_id,
				'url'      => get_permalink( $post_id ),
				'edit_url' => admin_url( "post.php?post={$post_id}&action=edit" ),
			)
		);
	}

	/**
	 * GET /posts/find?slug=...&type=page
	 *
	 * Idempotency helper. Claude often knows the slug ("about") but not the ID.
	 */
	public static function find_post( $request ) {
		$slug = $request->get_param( 'slug' );
		$type = $request->get_param( 'type' );

		if ( empty( $slug ) ) {
			return MKB_REST_Controller::error( 'missing_slug', 'slug query parameter is required.', 400 );
		}

		$args = array(
			'name'           => $slug,
			'post_type'      => $type ? $type : array( 'page', 'post' ),
			'post_status'    => 'any',
			'posts_per_page' => 1,
		);
		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return MKB_REST_Controller::error( 'not_found', 'No post found with that slug.', 404 );
		}

		$post = $query->posts[0];
		return MKB_REST_Controller::success(
			array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'slug'   => $post->post_name,
				'status' => $post->post_status,
				'type'   => $post->post_type,
				'url'    => get_permalink( $post->ID ),
			)
		);
	}

	/**
	 * POST /pages/ensure — create the page only if it doesn't already exist.
	 *
	 * Body: { slug: "about", title: "About", content: "...", status: "publish" }
	 * Returns existing page info (with a flag) or the newly created one.
	 */
	public static function ensure_page( $request ) {
		$body = $request->get_json_params();
		$slug = isset( $body['slug'] ) ? sanitize_title( $body['slug'] ) : '';

		if ( empty( $slug ) ) {
			return MKB_REST_Controller::error( 'missing_slug', 'slug is required.', 400 );
		}

		// Check if it exists.
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			return MKB_REST_Controller::success(
				array(
					'id'       => $existing->ID,
					'slug'     => $existing->post_name,
					'title'    => $existing->post_title,
					'url'      => get_permalink( $existing->ID ),
					'edit_url' => admin_url( "post.php?post={$existing->ID}&action=edit" ),
					'created'  => false,
				)
			);
		}

		// Create it.
		$post_data = array(
			'post_title'   => isset( $body['title'] ) ? $body['title'] : ucfirst( $slug ),
			'post_content' => isset( $body['content'] ) ? $body['content'] : '',
			'post_excerpt' => isset( $body['excerpt'] ) ? $body['excerpt'] : '',
			'post_status'  => isset( $body['status'] ) ? $body['status'] : 'publish',
			'post_type'    => 'page',
			'post_name'    => $slug,
		);
		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( isset( $body['meta'] ) && is_array( $body['meta'] ) ) {
			foreach ( $body['meta'] as $k => $v ) {
				update_post_meta( $post_id, $k, $v );
			}
		}

		MKB_History::record( 'page_ensure', $slug, null, $post_data );

		return MKB_REST_Controller::success(
			array(
				'id'       => $post_id,
				'slug'     => $slug,
				'title'    => $post_data['post_title'],
				'url'      => get_permalink( $post_id ),
				'edit_url' => admin_url( "post.php?post={$post_id}&action=edit" ),
				'created'  => true,
			)
		);
	}

	// ---------------------------------------------------------------------
	// MENUS
	// ---------------------------------------------------------------------

	public static function list_menus( $request ) {
		$menus     = wp_get_nav_menus();
		$menu_data = array();

		foreach ( $menus as $menu ) {
			$items      = wp_get_nav_menu_items( $menu->term_id );
			$item_data  = array();
			if ( $items ) {
				foreach ( $items as $item ) {
					$item_data[] = array(
						'id'        => $item->ID,
						'title'     => $item->title,
						'url'       => $item->url,
						'parent'    => (int) $item->menu_item_parent,
						'type'      => $item->type,
						'object'    => $item->object,
						'object_id' => (int) $item->object_id,
					);
				}
			}
			$menu_data[] = array(
				'id'    => $menu->term_id,
				'name'  => $menu->name,
				'slug'  => $menu->slug,
				'items' => $item_data,
			);
		}

		return MKB_REST_Controller::success(
			array(
				'menus'     => $menu_data,
				'locations' => get_nav_menu_locations(),
			)
		);
	}

	public static function create_menu( $request ) {
		$body = $request->get_json_params();
		$name = isset( $body['name'] ) ? $body['name'] : '';

		if ( empty( $name ) ) {
			return MKB_REST_Controller::error( 'missing_name', 'Menu name is required.', 400 );
		}

		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}

		if ( isset( $body['location'] ) ) {
			$locations                   = get_nav_menu_locations();
			$locations[ $body['location'] ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		MKB_History::record( 'menu_create', $name, null, array( 'menu_id' => $menu_id ) );

		return MKB_REST_Controller::success(
			array(
				'id'   => $menu_id,
				'name' => $name,
			)
		);
	}

	public static function add_menu_item( $request ) {
		$menu_id = (int) $request->get_param( 'id' );
		$body    = $request->get_json_params();

		$item_data = array(
			'menu-item-title'  => isset( $body['title'] ) ? $body['title'] : '',
			'menu-item-url'    => isset( $body['url'] ) ? $body['url'] : '',
			'menu-item-status' => 'publish',
			'menu-item-type'   => isset( $body['type'] ) ? $body['type'] : 'custom',
		);
		if ( isset( $body['object'] ) ) {
			$item_data['menu-item-object'] = $body['object'];
		}
		if ( isset( $body['object_id'] ) ) {
			$item_data['menu-item-object-id'] = (int) $body['object_id'];
		}
		if ( isset( $body['parent'] ) ) {
			$item_data['menu-item-parent-id'] = (int) $body['parent'];
		}

		$item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		MKB_History::record(
			'menu_item_add',
			(string) $menu_id,
			null,
			array_merge( $item_data, array( 'item_id' => $item_id ) )
		);

		return MKB_REST_Controller::success(
			array(
				'id'      => $item_id,
				'menu_id' => $menu_id,
			)
		);
	}

	/**
	 * POST /posts/{id}/normalize-blocks
	 *
	 * Re-serializes block content through WordPress's block parser.
	 * This roundtrip (parse_blocks → serialize_blocks) normalizes the
	 * saved markup to match what the block editor's save() functions
	 * would produce, eliminating "Block contains invalid content"
	 * / "Attempt Block Recovery" prompts in wp-admin.
	 */
	public static function normalize_blocks( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post ) {
			return MKB_REST_Controller::error( 'not_found', 'Post not found.', 404 );
		}

		$original = $post->post_content;

		if ( empty( $original ) ) {
			return MKB_REST_Controller::error( 'empty_content', 'Post has no content to normalize.', 400 );
		}

		$blocks     = parse_blocks( $original );
		$normalized = serialize_blocks( $blocks );

		if ( $normalized === $original ) {
			return MKB_REST_Controller::success(
				array(
					'id'      => $id,
					'changed' => false,
					'message' => 'Content already normalized.',
				)
			);
		}

		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => $normalized,
			)
		);

		return MKB_REST_Controller::success(
			array(
				'id'              => $id,
				'changed'         => true,
				'original_length' => strlen( $original ),
				'new_length'      => strlen( $normalized ),
			)
		);
	}
}
