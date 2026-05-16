<?php
/**
 * Shared helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize and normalize a comma-separated list of term IDs into an int array.
 *
 * @param mixed $raw  Raw input (string, array, or null).
 * @return int[]      Unique positive integers.
 */
function walkme_pb_parse_term_ids( $raw ) {
	if ( is_string( $raw ) ) {
		$raw = explode( ',', $raw );
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$ids = array_map( 'absint', $raw );
	$ids = array_filter( $ids );
	return array_values( array_unique( $ids ) );
}

/**
 * Clamp a value into the allowed column counts.
 *
 * @param mixed $cols Raw input.
 * @return int        2, 3, or 4.
 */
function walkme_pb_clamp_columns( $cols ) {
	$cols = absint( $cols );
	if ( ! in_array( $cols, array( 2, 3, 4 ), true ) ) {
		return 3;
	}
	return $cols;
}
