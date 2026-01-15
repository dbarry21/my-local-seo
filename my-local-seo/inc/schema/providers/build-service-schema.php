<?php
/**
 * My Local SEO – Service Schema Builder (Service, not Product)
 * File: build-service-schema.php
 *
 * Changes per your request:
 * - Uses Organization tab "areaServed" field (myls_org_areas_served or legacy keys)
 * - Uses the *page title* as serviceType
 * - Uses the *primary LocalBusiness schema node* as provider (pulled from @graph if present)
 *   - Falls back to Organization provider if LocalBusiness is not found
 * - Uses post excerpt as serviceOutput (wrapped as a Thing)
 *
 * Hooks into the central @graph via 'myls_schema_graph'.
 */

if ( ! defined('ABSPATH') ) exit;

/** Safe option getter (falls back to default if empty string) */
if ( ! function_exists('myls_opt') ) {
	function myls_opt($key, $default = '') {
		$v = get_option($key, $default);
		return ($v === '' ? $default : $v);
	}
}

/** Normalize multiline "Areas Served" field into array */
if ( ! function_exists('myls_parse_areas_served') ) {
	function myls_parse_areas_served($raw) : array {
		if ( is_array($raw) ) {
			$items = $raw;
		} else {
			$raw = (string) $raw;
			// split on new lines or commas
			$items = preg_split('/\r\n|\r|\n|,/', $raw) ?: [];
		}

		$items = array_map('trim', $items);
		$items = array_filter($items, function($v){
			return $v !== '';
		});

		// De-dupe while keeping order
		$out = [];
		foreach ($items as $v) {
			if ( ! in_array($v, $out, true) ) $out[] = $v;
		}
		return $out;
	}
}

/** Build a best-effort description if excerpt is empty */
if ( ! function_exists('myls_get_best_description') ) {
	function myls_get_best_description(int $post_id) : string {
		$desc = (string) get_the_excerpt($post_id);
		if ( $desc !== '' ) return $desc;

		$post = get_post($post_id);
		if ( ! $post ) return '';

		$content = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$content = trim(preg_replace('/\s+/', ' ', $content));
		return wp_trim_words($content, 45, '…');
	}
}

/** Normalize to a unique url array */
if ( ! function_exists('myls_normalize_url_array') ) {
	function myls_normalize_url_array($maybe_array) : array {
		if ( ! is_array($maybe_array) ) return [];
		$urls = array_filter(array_map('esc_url_raw', $maybe_array));
		$urls = array_values(array_unique($urls));
		return $urls;
	}
}

/**
 * Attempt to find the "primary" LocalBusiness node already in the graph.
 * - Returns the @id if found, otherwise empty string.
 *
 * Why:
 * - You asked to "pull primary local business schema for provider".
 * - Your LocalBusiness builder typically adds that node elsewhere in @graph.
 */
if ( ! function_exists('myls_find_primary_localbusiness_id') ) {
	function myls_find_primary_localbusiness_id(array $graph) : string {

		foreach ( $graph as $node ) {
			if ( ! is_array($node) ) continue;

			$type = $node['@type'] ?? '';
			$id   = $node['@id']   ?? '';

			if ( ! $id ) continue;

			// @type can be string or array
			$types = is_array($type) ? $type : [$type];

			// Match LocalBusiness or common subclasses
			foreach ( $types as $t ) {
				$t = (string) $t;
				if ( $t === 'LocalBusiness' ) return (string) $id;

				// Common local SEO subclasses people use
				$known = [
					'ProfessionalService',
					'HomeAndConstructionBusiness',
					'Plumber',
					'Electrician',
					'RoofingContractor',
					'GeneralContractor',
					'HousePainter',
					'MovingCompany',
					'Locksmith',
					'AutoRepair',
					'RealEstateAgent',
					'Dentist',
					'Physician',
				];
				if ( in_array($t, $known, true) ) return (string) $id;
			}
		}

		return '';
	}
}

add_filter('myls_schema_graph', function(array $graph) {

	// Only on single 'service' posts
	if ( ! is_singular('service') ) return $graph;

	$post_id = (int) get_queried_object_id();
	if ( ! $post_id ) return $graph;

	// --- Organization details (prefer my-local-seo keys, fallback to ssseo-tools, then WP)
	$org_name    = myls_opt('myls_org_name',  myls_opt('ssseo_organization_name', get_bloginfo('name')));
	$org_url     = myls_opt('myls_org_url',   myls_opt('ssseo_organization_url',  home_url()));
	$org_phone   = myls_opt('myls_org_phone', myls_opt('ssseo_organization_phone',''));
	$org_logo_id = absint( myls_opt('myls_org_logo_id', myls_opt('ssseo_organization_logo', 0)) );
	$org_logo    = $org_logo_id ? wp_get_attachment_image_url($org_logo_id, 'full') : '';

	// Address (accept both sets of keys, clean empties)
	$org_address_raw = [
		'streetAddress'   => myls_opt('myls_org_address',     myls_opt('ssseo_organization_address', '')),
		'addressLocality' => myls_opt('myls_org_locality',    myls_opt('ssseo_organization_locality', '')),
		'addressRegion'   => myls_opt('myls_org_region',      myls_opt('ssseo_organization_state', '')),
		'postalCode'      => myls_opt('myls_org_postal_code', myls_opt('ssseo_organization_postal_code', '')),
		'addressCountry'  => myls_opt('myls_org_country',     myls_opt('ssseo_organization_country', '')),
	];
	$org_address = array_filter($org_address_raw);
	if ( ! empty($org_address) ) {
		$org_address = array_merge(['@type' => 'PostalAddress'], $org_address);
	}

	// Social profiles
	$same_as = myls_opt('myls_org_sameas', myls_opt('ssseo_organization_social_profiles', []));
	$same_as = myls_normalize_url_array($same_as);

	/**
	 * Areas Served:
	 * - Prefer MyLS Organization tab field
	 * - Fallback to older/legacy keys if present
	 *
	 * NOTE: Key name may vary in your build; these fallbacks are safe.
	 */
	$areas_raw = myls_opt(
		'myls_org_areas_served',
		myls_opt('ssseo_organization_areas_served', myls_opt('ssseo_areas_served', ''))
	);
	$areas_served = myls_parse_areas_served($areas_raw);

	/**
	 * Build Organization provider fallback
	 * (Used only if we cannot find a LocalBusiness node in the existing @graph)
	 */
	$org_id = trailingslashit($org_url) . '#organization';

	$org_provider = [
		'@type' => 'Organization',
		'@id'   => $org_id,
		'name'  => $org_name,
		'url'   => $org_url,
	];
	if ( $org_phone )           $org_provider['telephone'] = $org_phone;
	if ( $org_logo )            $org_provider['logo']      = $org_logo;
	if ( ! empty($org_address) ) $org_provider['address']  = $org_address;
	if ( ! empty($same_as) )    $org_provider['sameAs']    = $same_as;

	/**
	 * Determine provider:
	 * - If LocalBusiness exists in graph, reference it by @id (preferred)
	 * - Else use Organization object fallback
	 */
	$localbiz_id = myls_find_primary_localbusiness_id($graph);

	$provider = $localbiz_id
		? [ '@id' => $localbiz_id ]
		: $org_provider;

	// Service basics
	$service_url   = get_permalink($post_id);
	$page_title    = get_the_title($post_id);
	$description   = myls_get_best_description($post_id);

	// Featured image fallback to org logo
	$image_url = get_the_post_thumbnail_url($post_id, 'full');
	if ( ! $image_url && $org_logo ) $image_url = $org_logo;

	/**
	 * serviceType:
	 * - per your request: use the *page title* as serviceType
	 */
	$service_type = wp_strip_all_tags($page_title);

	/**
	 * serviceOutput:
	 * - per your request: use post excerpt as serviceOutput
	 * - Schema expects a Thing (or can be text in some consumers), we wrap as Thing for consistency
	 */
	$service_output_text = wp_strip_all_tags((string) get_the_excerpt($post_id));
	$service_output_text = trim(preg_replace('/\s+/', ' ', $service_output_text));

	$service_output = null;
	if ( $service_output_text !== '' ) {
		$service_output = [
			'@type' => 'Thing',
			'name'  => $service_output_text,
		];
	}

	/**
	 * Build Service node
	 */
	$service = [
		'@type'       => 'Service',
		'@id'         => $service_url . '#service',
		'name'        => wp_strip_all_tags($page_title),
		'url'         => $service_url,
		'description' => wp_strip_all_tags($description),
		'provider'    => $provider,
		'serviceType' => $service_type,
		'inLanguage'  => 'en-US',
	];

	if ( $image_url ) $service['image'] = esc_url_raw($image_url);

	// Attach areas served from Organization tab (string list)
	if ( ! empty($areas_served) ) {
		$service['areaServed'] = array_values($areas_served);
	}

	// Attach serviceOutput (excerpt)
	if ( is_array($service_output) ) {
		$service['serviceOutput'] = $service_output;
	}

	// Push into central graph
	$graph[] = $service;

	return $graph;

}, 10, 1);
