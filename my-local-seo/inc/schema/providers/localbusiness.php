<?php
if (!defined('ABSPATH')) exit;

/**
 * Provider: LocalBusiness (assigned posts/pages only)
 * Return array (JSON-LD) or null. No output here.
 */

function myls_schema_localbusiness_for_post(WP_Post $post) : ?array {
	if (!($post instanceof WP_Post)) return null;

	$locs = (array) get_option('myls_lb_locations', []);
	if (!$locs) return null;

	$post_id = (int) $post->ID;

	// Find first location that explicitly assigns this post ID
	foreach ($locs as $loc) {
		$pages = array_map('absint', (array)($loc['pages'] ?? []));
		if ($pages && in_array($post_id, $pages, true)) {
			return myls_lb_build_schema_from_location($loc, $post); // reuse the builder
		}
	}
	return null; // strict: nothing unless assigned
}

/**
 * Builder used by the provider (same as in the emitter version).
 */
if (!function_exists('myls_lb_build_schema_from_location')) {
	function myls_lb_build_schema_from_location(array $loc, WP_Post $post) : array {
		$org_name = get_option('myls_org_name', get_bloginfo('name'));
		$logo_id  = (int) get_option('myls_org_logo_id', 0);
		$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

		$hours = [];
		foreach ((array)($loc['hours'] ?? []) as $h) {
			$d = trim((string)($h['day']   ?? ''));
			$o = trim((string)($h['open']  ?? ''));
			$c = trim((string)($h['close'] ?? ''));
			if ($d && $o && $c) {
				$hours[] = [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $d,
					'opens'     => $o,
					'closes'    => $c,
				];
			}
		}

		return array_filter([
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'@id'      => trailingslashit(get_permalink($post)) . '#localbusiness',
			'name'     => sanitize_text_field($loc['name'] ?? $org_name),
			'image'    => array_filter([ esc_url($loc['image_url'] ?? '') ]),
			'telephone'=> sanitize_text_field($loc['phone'] ?? ''),
			'priceRange' => sanitize_text_field($loc['price'] ?? ''),
			'address'  => array_filter([
				'@type'           => 'PostalAddress',
				'streetAddress'   => sanitize_text_field($loc['street'] ?? ''),
				'addressLocality' => sanitize_text_field($loc['city'] ?? ''),
				'addressRegion'   => sanitize_text_field($loc['state'] ?? ''),
				'postalCode'      => sanitize_text_field($loc['zip'] ?? ''),
				'addressCountry'  => sanitize_text_field($loc['country'] ?? 'US'),
			]),
			'geo'      => (!empty($loc['lat']) || !empty($loc['lng'])) ? array_filter([
				'@type'    => 'GeoCoordinates',
				'latitude' => sanitize_text_field($loc['lat'] ?? ''),
				'longitude'=> sanitize_text_field($loc['lng'] ?? ''),
			]) : null,
			'openingHoursSpecification' => $hours ?: null,
			'publisher'=> array_filter([
				'@type' => 'Organization',
				'name'  => $org_name,
				'logo'  => $logo_url ? [
					'@type' => 'ImageObject',
					'url'   => esc_url($logo_url),
				] : null,
			]),
		]);
	}
}
