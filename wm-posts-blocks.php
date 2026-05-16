<?php
/**
 * Plugin Name:       WM Posts Blocks
 * Description:       Two Gutenberg blocks — a dynamic Posts Grid and a companion Posts Filter — that communicate cross-page without nesting. Seeds demo content on activation.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Itay Haephrati
 * Author URI:        https://itaycode.com
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

/**
 * Lightweight frontend optimizer.
 *
 * The blocks ship their own modern, semantic markup — they don't depend on
 * the WordPress emoji polyfill, wp-embed (oEmbed for legacy WP-to-WP embeds),
 * or jQuery-Migrate. Skipping these on the public side trims ~30 KB of JS,
 * three render-blocking inline scripts, and reduces main-thread work.
 *
 * Only runs on the frontend and only when our demo page (or any post that
 * contains one of our blocks) is rendering, to stay scoped and unsurprising.
 */
function wm_pb_frontend_optimize() {
	if ( is_admin() ) {
		return;
	}

	// Defer non-essential WP scripts that our blocks don't use.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	// wp-embed.min.js — only needed to embed external WP posts.
	wp_deregister_script( 'wp-embed' );
}
add_action( 'init', 'wm_pb_frontend_optimize' );

/**
 * Add `defer` to our own view scripts. They're event listeners — no need
 * to block paint.
 */
function wm_pb_defer_view_scripts( $tag, $handle ) {
	if ( is_admin() ) {
		return $tag;
	}
	if ( false !== strpos( $handle, 'wm-posts-' ) && false !== strpos( $tag, '/build/' ) ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'wm_pb_defer_view_scripts', 10, 2 );

/**
 * Fix the long-standing core/navigation + core/page-list nesting bug.
 *
 * The nav block renders `<ul class="wp-block-navigation__container">`,
 * and the page-list block (when used inside it) renders its own
 * `<ul class="wp-block-page-list">`. Result: a <ul> directly inside a <ul>,
 * which fails the Lighthouse "lists contain only <li> elements" audit.
 *
 * We unwrap the inner <ul>, leaving its <li> children inside the parent
 * <ul>. Semantically identical, visually identical, but valid.
 */
function wm_pb_fix_page_list_nesting( $block_content, $block ) {
	if ( ! isset( $block['blockName'] ) || 'core/page-list' !== $block['blockName'] ) {
		return $block_content;
	}
	if ( ! preg_match( '/^\s*<ul[^>]*wp-block-page-list[^>]*>(.*)<\/ul>\s*$/s', $block_content, $m ) ) {
		return $block_content;
	}
	return $m[1];
}
add_filter( 'render_block', 'wm_pb_fix_page_list_nesting', 10, 2 );

/**
 * Emit a `<meta name="description">` tag in the document head for singular
 * pages and posts. Uses the post's excerpt if set, otherwise an auto-trim of
 * the content. Helps the SEO audit and gives crawlers a clean summary.
 */
function wm_pb_meta_description() {
	if ( is_admin() || ! is_singular() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post || ! isset( $post->post_type ) ) {
		return;
	}
	$description = '';
	if ( ! empty( $post->post_excerpt ) ) {
		$description = wp_strip_all_tags( $post->post_excerpt );
	} else {
		$description = wp_strip_all_tags( wp_trim_words( $post->post_content, 30, '…' ) );
	}
	if ( '' !== $description ) {
		printf(
			'<meta name="description" content="%s" />' . "\n",
			esc_attr( $description )
		);
	}
}
add_action( 'wp_head', 'wm_pb_meta_description', 1 );
