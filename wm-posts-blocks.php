<?php
/**
 * Plugin Name:       WM Posts Blocks
 * Description:       Two Gutenberg blocks — a dynamic Posts Grid and a companion Posts Filter — that communicate cross-page without nesting. Seeds demo content on activation.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Itay (WM assessment)
 * License:           GPL-2.0-or-later
 * Text Domain:       wm-posts-blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WM_PB_VERSION', '1.0.0' );
define( 'WM_PB_FILE', __FILE__ );
define( 'WM_PB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WM_PB_URL', plugin_dir_url( __FILE__ ) );
define( 'WM_PB_BUILD_DIR', WM_PB_DIR . 'build/' );

require_once WM_PB_DIR . 'includes/helpers.php';
require_once WM_PB_DIR . 'includes/class-renderer.php';
require_once WM_PB_DIR . 'includes/class-rest-api.php';
require_once WM_PB_DIR . 'includes/class-activator.php';

/**
 * Register all three blocks from their build/ directories.
 *
 * block.json's `render` field handles dynamic rendering, but we override
 * with explicit render_callbacks because we share a single Renderer for
 * both initial SSR and AJAX (REST) updates.
 */
function wm_pb_register_blocks() {
	if ( ! is_dir( WM_PB_BUILD_DIR ) ) {
		return;
	}

	register_block_type(
		WM_PB_BUILD_DIR . 'posts-grid',
		array(
			'render_callback' => array( 'Wm_PB_Renderer', 'render_grid' ),
		)
	);

	register_block_type(
		WM_PB_BUILD_DIR . 'posts-pagination',
		array(
			'render_callback' => array( 'Wm_PB_Renderer', 'render_pagination_placeholder' ),
		)
	);

	register_block_type(
		WM_PB_BUILD_DIR . 'posts-filter',
		array(
			'render_callback' => array( 'Wm_PB_Renderer', 'render_filter' ),
		)
	);
}
add_action( 'init', 'wm_pb_register_blocks' );

add_action( 'rest_api_init', array( 'Wm_PB_REST_API', 'register_routes' ) );

register_activation_hook( __FILE__, array( 'Wm_PB_Activator', 'activate' ) );
