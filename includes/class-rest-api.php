<?php
/**
 * REST endpoint used by the Posts Grid view script to refresh content
 * in response to filter changes or pagination clicks. Returns rendered
 * HTML so the server stays the single source of truth for markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Walkme_PB_REST_API {

	const NAMESPACE = 'walkme/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_posts' ),
				'permission_callback' => '__return_true', // public read.
				'args'                => array(
					'columns'      => array(
						'type'    => 'integer',
						'default' => 3,
					),
					'per_page'     => array(
						'type'    => 'integer',
						'default' => 6,
					),
					'page'         => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'query_id'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'categories'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'tags'         => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	public static function handle_posts( WP_REST_Request $request ) {
		$attrs = array(
			'columns'      => $request->get_param( 'columns' ),
			'postsPerPage' => $request->get_param( 'per_page' ),
			'queryId'      => $request->get_param( 'query_id' ),
			'currentPage'  => $request->get_param( 'page' ),
			'categories'   => $request->get_param( 'categories' ),
			'tags'         => $request->get_param( 'tags' ),
		);

		$html = Walkme_PB_Renderer::render_grid( $attrs );

		return rest_ensure_response(
			array(
				'html' => $html,
				'page' => max( 1, absint( $attrs['currentPage'] ) ),
			)
		);
	}
}
