<?php
/**
 * Media Endpoints — upload from URL, list media.
 *
 * Routes:
 *   POST /media/upload-from-url
 *   GET  /media
 *
 * @package MegaKadenceBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Media_Endpoints {

	public static function register( $ns ) {
		$permission = array( 'MKB_REST_Controller', 'check_permission' );

		register_rest_route(
			$ns,
			'/media/upload-from-url',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'upload_from_url' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/media',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_media' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * POST /media/upload-from-url
	 *
	 * Body: { url: "https://...", title: "...", alt: "...", attach_to: post_id }
	 */
	public static function upload_from_url( $request ) {
		$body      = $request->get_json_params();
		$url       = isset( $body['url'] ) ? esc_url_raw( $body['url'] ) : '';
		$title     = isset( $body['title'] ) ? sanitize_text_field( $body['title'] ) : '';
		$alt       = isset( $body['alt'] ) ? sanitize_text_field( $body['alt'] ) : '';
		$attach_to = isset( $body['attach_to'] ) ? (int) $body['attach_to'] : 0;

		if ( empty( $url ) ) {
			return MKB_REST_Controller::error( 'missing_url', 'url is required.', 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $url, $attach_to, $title, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		MKB_History::record(
			'media_upload',
			(string) $attachment_id,
			null,
			array(
				'url'   => $url,
				'title' => $title,
				'alt'   => $alt,
			)
		);

		return MKB_REST_Controller::success(
			array(
				'id'  => $attachment_id,
				'url' => wp_get_attachment_url( $attachment_id ),
			)
		);
	}

	/**
	 * GET /media
	 */
	public static function list_media( $request ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => (int) ( $request->get_param( 'per_page' ) ?: 20 ),
			'paged'          => (int) ( $request->get_param( 'page' ) ?: 1 ),
		);

		$mime = $request->get_param( 'mime_type' );
		if ( $mime ) {
			$args['post_mime_type'] = $mime;
		}

		$query = new WP_Query( $args );
		$media = array();
		foreach ( $query->posts as $attachment ) {
			$media[] = array(
				'id'        => $attachment->ID,
				'title'     => $attachment->post_title,
				'url'       => wp_get_attachment_url( $attachment->ID ),
				'mime_type' => $attachment->post_mime_type,
				'date'      => $attachment->post_date,
			);
		}

		return MKB_REST_Controller::success(
			array(
				'media' => $media,
				'total' => (int) $query->found_posts,
			)
		);
	}
}
