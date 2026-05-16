<?php
/**
 * Plugin uninstall: remove all demo content tagged with the `_walkme_demo` meta.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Collect demo post/page/attachment IDs.
$post_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
		'_walkme_demo'
	)
);

if ( ! empty( $post_ids ) ) {
	foreach ( $post_ids as $pid ) {
		wp_delete_post( (int) $pid, true );
	}
}

// Collect demo term IDs.
$term_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s",
		'_walkme_demo'
	)
);

if ( ! empty( $term_ids ) ) {
	foreach ( $term_ids as $tid ) {
		$term = get_term( (int) $tid );
		if ( $term && ! is_wp_error( $term ) ) {
			wp_delete_term( (int) $tid, $term->taxonomy );
		}
	}
}

delete_option( 'walkme_pb_seeded' );
