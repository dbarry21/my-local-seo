<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * LocalBusiness Schema Provider + Emitter
 * ------------------------------------------------------------
 * This file:
 *  1) Defines the LocalBusiness provider that RETURNS an array (no echo)
 *  2) Adds a head emitter that CALLS the provider and ECHOES JSON-LD
 *
 * Drop-in path suggestion: inc/schema/providers/localbusiness.php
 * Make sure this file is included on the front-end before wp_head().
 */

/**
 * Provider: LocalBusiness (assigned posts/pages only)
 * Return array (JSON-LD) or null. No output here.
 *
 * @param WP_Post $post
 * @return array|null
 */
function myls_schema_localbusiness_for_post( WP_Post $post ) : ?array {
	if ( ! ( $post instanceof WP_Post ) ) return null;

	$locs = (array) get_option('myls_lb_locations', []);
	if ( empty($locs) ) return null;

	$post_id = (int) $post->ID;

	// Find first location that explicitly assigns this post ID.
	foreach ( $locs as $loc ) {
		$pages = array_map( 'absint', (array) ( $loc['pages'] ?? [] ) );
		if ( $pages && in_array( $post_id, $pages, true ) ) {
			return myls_lb_build_schema_from_location( $loc, $post );
		}
	}
	return null; // strict: nothing unless assigned
}

/**
 * Builder used by the provider (same as in the emitter version).
 *
 * @param array   $loc  A single location array from myls_lb_locations option.
 * @param WP_Post $post The current singular post object.
 * @return array JSON-LD array for LocalBusiness
 */
if ( ! function_exists('myls_lb_build_schema_from_location') ) {
	function myls_lb_build_schema_from_location( array $loc, WP_Post $post ) : array {
		$org_name = get_option( 'myls_org_name', get_bloginfo( 'name' ) );
		$logo_id  = (int) get_option( 'myls_org_logo_id', 0 );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

		$hours = [];
		foreach ( (array) ( $loc['hours'] ?? [] ) as $h ) {
			$d = trim( (string) ( $h['day']   ?? '' ) );
			$o = trim( (string) ( $h['open']  ?? '' ) );
			$c = trim( (string) ( $h['close'] ?? '' ) );
			if ( $d && $o && $c ) {
				$hours[] = [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $d,
					'opens'     => $o,
					'closes'    => $c,
				];
			}
		}

		return array_filter( [
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'@id'      => trailingslashit( get_permalink( $post ) ) . '#localbusiness',
			'name'     => sanitize_text_field( $loc['name'] ?? $org_name ),
			'image'    => array_filter( [ esc_url( $loc['image_url'] ?? '' ) ] ),
			'telephone'=> sanitize_text_field( $loc['phone'] ?? '' ),
			'priceRange' => sanitize_text_field( $loc['price'] ?? '' ),
			'address'  => array_filter( [
				'@type'           => 'PostalAddress',
				'streetAddress'   => sanitize_text_field( $loc['street'] ?? '' ),
				'addressLocality' => sanitize_text_field( $loc['city'] ?? '' ),
				'addressRegion'   => sanitize_text_field( $loc['state'] ?? '' ),
				'postalCode'      => sanitize_text_field( $loc['zip'] ?? '' ),
				'addressCountry'  => sanitize_text_field( $loc['country'] ?? 'US' ),
			] ),
			'geo'      => ( ! empty( $loc['lat'] ) || ! empty( $loc['lng'] ) ) ? array_filter( [
				'@type'    => 'GeoCoordinates',
				'latitude' => sanitize_text_field( $loc['lat'] ?? '' ),
				'longitude'=> sanitize_text_field( $loc['lng'] ?? '' ),
			] ) : null,
			'openingHoursSpecification' => $hours ?: null,
			'publisher'=> array_filter( [
				'@type' => 'Organization',
				'name'  => $org_name,
				'logo'  => $logo_url ? [
					'@type' => 'ImageObject',
					'url'   => esc_url( $logo_url ),
				] : null,
			] ),
		] );
	}
}

/**
 * Robust assignment checker (used by meta flag AND emitter).
 * Accepts either 'pages' array or 'assigned_pages' CSV string for resilience.
 *
 * @param int $post_id
 * @return bool
 */
if ( ! function_exists('myls_localbusiness_is_assigned_to_post') ) {
	function myls_localbusiness_is_assigned_to_post( int $post_id ) : bool {
		if ( $post_id <= 0 ) return false;

		$locs = (array) get_option( 'myls_lb_locations', [] );
		if ( empty( $locs ) ) return false;

		foreach ( $locs as $loc ) {
			$raw = $loc['pages'] ?? $loc['assigned_pages'] ?? [];
			if ( is_string( $raw ) ) {
				$raw = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
			}
			$pages = array_map( 'absint', (array) $raw );
			if ( ! empty( $pages ) && in_array( $post_id, $pages, true ) ) {
				return true;
			}
		}
		return false;
	}
}

/**
 * Emit a meta flag in <head> indicating whether LocalBusiness applies.
 * Example output:
 *   <meta name="myls-localbusiness" content="true">
 * or
 *   <meta name="myls-localbusiness" content="false">
 */
add_action( 'wp_head', function () {
	if ( ! is_singular() ) return;
	$obj = get_queried_object();
	if ( ! ( $obj instanceof WP_Post ) ) return;

	$assigned = myls_localbusiness_is_assigned_to_post( (int) $obj->ID ) ? 'true' : 'false';
	echo "\n<meta name=\"myls-localbusiness\" content=\"{$assigned}\" />\n";
}, 2 );

/**
 * JSON-LD EMITTER (head)
 * ------------------------------------------------------------
 * Calls the LocalBusiness provider on singular pages and prints
 * <script type="application/ld+json">...</script> if data is returned.
 *
 * Guards:
 * - Skips admin, feeds, REST, previews.
 * - Respects a kill switch constant or filter if you want to disable.
 *
 * If you already have a global schema aggregator/queue, replace this
 * with your aggregator call to avoid double-output.
 */
add_action( 'wp_head', function () {
	// Basic front-end guards
	if ( is_admin() || is_feed() || ( defined('REST_REQUEST') && REST_REQUEST ) ) return;
	if ( is_preview() ) return;
	if ( ! is_singular() ) return;

	// Optional kill-switch (define in wp-config.php or plugin bootstrap if needed)
	if ( defined('MYLS_DISABLE_LOCALBUSINESS_EMIT') && MYLS_DISABLE_LOCALBUSINESS_EMIT ) return;
	if ( false === apply_filters( 'myls_allow_localbusiness_emit', true ) ) return;

	$post = get_queried_object();
	if ( ! ( $post instanceof WP_Post ) ) return;

	$data = myls_schema_localbusiness_for_post( $post );
	if ( empty( $data ) || ! is_array( $data ) ) return;

	// Print JSON-LD
	echo "\n<script type=\"application/ld+json\">" .
		wp_kses_post( wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) .
		"</script>\n";
}, 12);
