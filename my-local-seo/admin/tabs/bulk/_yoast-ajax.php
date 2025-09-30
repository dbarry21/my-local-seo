<?php
/**
 * AJAX for Bulk → Yoast Operations (with detailed debug)
 * Path: admin/tabs/bulk/_yoast-ajax.php
 *
 * Handles bulk actions from the Yoast subtab:
 *  - myls_set_index_follow
 *  - myls_set_noindex_nofollow
 *  - myls_reset_canonical
 *  - myls_clear_canonical
 *  - myls_copy_canonical_from_source
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! function_exists('myls_yoast_ajax_check') ) {
	function myls_yoast_ajax_check( $nonce_key = 'myls_bulk_ops' ) {
		if ( empty($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], $nonce_key ) ) {
			wp_send_json_error( [ 'message' => 'Invalid or missing nonce.' ] );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}
	}
}

/**
 * Utility: normalize post IDs and build a title map for debug
 */
function myls_yoast_normalize_ids_with_titles( $ids ) {
	$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	$titles = [];
	foreach ( $ids as $pid ) {
		$titles[$pid] = get_the_title( $pid ) ?: '(no title)';
	}
	return [ $ids, $titles ];
}

/**
 * Utility: update Yoast robots meta with per-item debug
 */
function myls_yoast_update_robots( $post_ids, $value ) {
	$details = [];
	foreach ( $post_ids as $pid ) {
		update_post_meta( $pid, '_yoast_wpseo_meta-robots-index',  $value['index'] );
		update_post_meta( $pid, '_yoast_wpseo_meta-robots-follow', $value['follow'] );
		$details[] = "Updated robots for #{$pid}";
	}
	return $details;
}

/**
 * Utility: update/clear canonical with per-item debug
 *  - $canonical === null → delete meta
 *  - $canonical === ''   → reset to empty string (Yoast will auto-generate)
 *  - else set URL
 */
function myls_yoast_update_canonical( $post_ids, $canonical ) {
	$details = [];
	foreach ( $post_ids as $pid ) {
		if ( $canonical === null ) {
			delete_post_meta( $pid, '_yoast_wpseo_canonical' );
			$details[] = "Cleared canonical for #{$pid}";
		} elseif ( $canonical === '' ) {
			update_post_meta( $pid, '_yoast_wpseo_canonical', '' );
			$details[] = "Reset canonical (empty) for #{$pid}";
		} else {
			update_post_meta( $pid, '_yoast_wpseo_canonical', esc_url_raw( $canonical ) );
			$details[] = "Set canonical for #{$pid} → {$canonical}";
		}
	}
	return $details;
}

/** ---------------- Actions ---------------- */

/** Set to Index, Follow */
add_action( 'wp_ajax_myls_set_index_follow', function() {
	myls_yoast_ajax_check();
	list($post_ids, $titles) = myls_yoast_normalize_ids_with_titles( $_POST['post_ids'] ?? [] );
	$details = myls_yoast_update_robots( $post_ids, [ 'index' => 2, 'follow' => 2 ] ); // 2 = index/follow (Yoast default)
	wp_send_json_success( [
		'message' => 'Set to index, follow.',
		'details' => $details,
	] );
} );

/** Set to Noindex, Nofollow */
add_action( 'wp_ajax_myls_set_noindex_nofollow', function() {
	myls_yoast_ajax_check();
	list($post_ids, $titles) = myls_yoast_normalize_ids_with_titles( $_POST['post_ids'] ?? [] );
	$details = myls_yoast_update_robots( $post_ids, [ 'index' => 1, 'follow' => 1 ] ); // 1 = noindex/nofollow
	wp_send_json_success( [
		'message' => 'Set to noindex, nofollow.',
		'details' => $details,
	] );
} );

/** Reset Canonical (empty string) */
add_action( 'wp_ajax_myls_reset_canonical', function() {
	myls_yoast_ajax_check();
	list($post_ids, $titles) = myls_yoast_normalize_ids_with_titles( $_POST['post_ids'] ?? [] );
	$details = myls_yoast_update_canonical( $post_ids, '' );
	wp_send_json_success( [
		'message' => 'Canonical reset to Yoast default.',
		'details' => $details,
	] );
} );

/** Clear Canonical (delete key) */
add_action( 'wp_ajax_myls_clear_canonical', function() {
	myls_yoast_ajax_check();
	list($post_ids, $titles) = myls_yoast_normalize_ids_with_titles( $_POST['post_ids'] ?? [] );
	$details = myls_yoast_update_canonical( $post_ids, null );
	wp_send_json_success( [
		'message' => 'Canonical cleared.',
		'details' => $details,
	] );
} );

/** Copy Canonical from Source → Targets */
add_action( 'wp_ajax_myls_copy_canonical_from_source', function() {
	myls_yoast_ajax_check();
	list($post_ids, $titles) = myls_yoast_normalize_ids_with_titles( $_POST['post_ids'] ?? [] );
	$source = intval( $_POST['source'] ?? 0 );

	if ( ! $source || ! in_array( get_post_type($source), get_post_types( [ 'public' => true ] ), true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid source post.' ] );
	}

	$canonical = get_post_meta( $source, '_yoast_wpseo_canonical', true );
	if ( $canonical === '' ) {
		$canonical = get_permalink( $source );
	}
	$details = myls_yoast_update_canonical( $post_ids, $canonical );

	wp_send_json_success( [
		'message' => 'Copied canonical from source.',
		'source'  => $source,
		'details' => $details,
	] );
} );
