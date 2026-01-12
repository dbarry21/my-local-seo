<?php
/**
 * MYLS — llms.txt Endpoint (Fresh File)
 *
 * Serves: https://example.com/llms.txt
 *
 * - WP-native rewrite + query_var (no direct REQUEST_URI regex needed)
 * - Pulls org data from MYLS options first, then falls back to legacy SSSEO options
 * - If key org fields are missing, it will try to fetch Organization/LocalBusiness JSON-LD from org_url
 * - Meta description fallback if description still empty
 * - Outputs services + service_areas post types (publish only)
 * - Caches remote schema fetch in a transient to avoid hammering the homepage
 *
 * Install:
 *  1) Save as: my-local-seo/inc/llms-txt.php
 *  2) Include it from main plugin file:
 *       require_once MYLS_PATH . 'inc/llms-txt.php';
 *  3) Flush permalinks once (Settings → Permalinks → Save)
 */

if ( ! defined('ABSPATH') ) exit;

/** -------------------------------------------------------------------------
 *  Rewrite: /llms.txt
 *  ---------------------------------------------------------------------- */
add_action('init', function () {
	add_rewrite_rule('^llms\.txt$', 'index.php?myls_llms_txt=1', 'top');
}, 10);

add_filter('query_vars', function ($vars) {
	$vars[] = 'myls_llms_txt';
	return $vars;
}, 10);

add_action('template_redirect', function () {
	if ( ! get_query_var('myls_llms_txt') ) return;

	nocache_headers();
	header('Content-Type: text/plain; charset=utf-8');

	echo myls_llms_build_txt();

	exit;
}, 0);

/** -------------------------------------------------------------------------
 *  Builder
 *  ---------------------------------------------------------------------- */
function myls_llms_build_txt(): string {

	$output = [];

	// === ORGANIZATION FIELDS (Prefer MYLS, fallback to legacy SSSEO) ===
	$org_url        = myls_llms_get_opt('myls_org_url', 'ssseo_org_url', home_url('/'));
	$org_name       = myls_llms_get_opt('myls_org_name', 'ssseo_org_name', '');
	$org_legal_name = myls_llms_get_opt('myls_org_legal_name', 'ssseo_org_legal_name', '');
	$org_desc       = myls_llms_get_opt('myls_org_description', 'ssseo_org_description', '');
	$org_logo       = myls_llms_get_opt('myls_org_logo', 'ssseo_org_logo', '');
	$org_email      = myls_llms_get_opt('myls_org_email', 'ssseo_org_email', get_option('admin_email'));
	$org_phone      = myls_llms_get_opt('myls_org_phone', 'ssseo_org_phone', '');
	$org_founded    = myls_llms_get_opt('myls_org_founding_date', 'ssseo_org_founding_date', '');
	$org_social     = myls_llms_get_opt('myls_org_social', 'ssseo_org_social', []);
	$org_hours      = myls_llms_get_opt('myls_org_hours', 'ssseo_org_hours', []);

	$org_url = $org_url ? esc_url_raw($org_url) : home_url('/');
	$org_email = sanitize_email($org_email);

	// Normalize arrays
	$org_social = is_array($org_social) ? $org_social : ( $org_social ? (array) $org_social : [] );
	$org_hours  = is_array($org_hours)  ? $org_hours  : ( $org_hours  ? (array) $org_hours  : [] );

	// === FETCH SCHEMA IF IMPORTANT FIELDS ARE EMPTY ===
	$schema = [];
	if (empty($org_name) || empty($org_legal_name) || empty($org_logo) || empty($org_desc) || empty($org_phone)) {
		$schema = myls_llms_fetch_schema_from_url($org_url);
	}

	if ( ! empty($schema['Organization']) ) {
		$orgSchema = $schema['Organization'];

		if (empty($org_name)) {
			$org_name = $orgSchema['name'] ?? '';
		}
		if (empty($org_legal_name)) {
			$org_legal_name = $orgSchema['legalName'] ?? ($orgSchema['name'] ?? '');
		}
		if (empty($org_logo)) {
			if (is_array($orgSchema['logo'] ?? null) && isset($orgSchema['logo']['url'])) {
				$org_logo = $orgSchema['logo']['url'];
			} elseif (is_string($orgSchema['logo'] ?? null)) {
				$org_logo = $orgSchema['logo'];
			}
		}
		if (empty($org_desc)) {
			$org_desc = $orgSchema['description'] ?? '';
		}
		if (empty($org_social) && ! empty($orgSchema['sameAs'])) {
			$org_social = is_array($orgSchema['sameAs']) ? $orgSchema['sameAs'] : [$orgSchema['sameAs']];
		}
	}

	// === META DESCRIPTION FALLBACK ===
	if (empty($org_desc)) {
		$meta_desc = myls_llms_extract_meta_description($org_url);
		if (!empty($meta_desc)) $org_desc = $meta_desc;
	}

	// Clean final strings for text output
	$org_name       = myls_llms_clean_line($org_name);
	$org_legal_name = myls_llms_clean_line($org_legal_name);
	$org_desc       = myls_llms_clean_line($org_desc);
	$org_logo       = myls_llms_clean_line($org_logo);
	$org_phone      = myls_llms_clean_line($org_phone);
	$org_founded    = myls_llms_clean_line($org_founded);

	// === LOCATIONS (schema LocalBusiness first; fallback to stored entries) ===
	$locations = [];

	if ( ! empty($schema['LocalBusiness']) ) {

		$schemaLocations = (is_array($schema['LocalBusiness']) && isset($schema['LocalBusiness'][0]))
			? $schema['LocalBusiness']
			: [$schema['LocalBusiness']];

		foreach ($schemaLocations as $loc) {
			$locations[] = [
				'location_name'  => myls_llms_clean_line($loc['name'] ?? 'Office'),
				'street_address' => myls_llms_clean_line($loc['address']['streetAddress'] ?? ''),
				'locality'       => myls_llms_clean_line($loc['address']['addressLocality'] ?? ''),
				'region'         => myls_llms_clean_line($loc['address']['addressRegion'] ?? ''),
				'postal_code'    => myls_llms_clean_line($loc['address']['postalCode'] ?? ''),
				'country'        => myls_llms_clean_line($loc['address']['addressCountry'] ?? ''),
				'latitude'       => myls_llms_clean_line($loc['geo']['latitude'] ?? ''),
				'longitude'      => myls_llms_clean_line($loc['geo']['longitude'] ?? ''),
				'phone'          => myls_llms_clean_line($loc['telephone'] ?? ''),
				'opening_hours'  => myls_llms_parse_opening_hours($loc['openingHours'] ?? null),
			];
		}

		// Fallback phone from first LocalBusiness
		if (empty($org_phone)) {
			foreach ($locations as $loc) {
				if (!empty($loc['phone'])) { $org_phone = $loc['phone']; break; }
			}
		}
	}

	if (empty($locations)) {
		// Prefer MYLS localbusiness entries, fallback SSSEO legacy
		$locations = myls_llms_get_opt('myls_localbusiness_entries', 'ssseo_localbusiness_entries', []);
		$locations = is_array($locations) ? $locations : [];
	}

	// === SERVICES + SERVICE AREAS ===
	$services = get_posts([
		'post_type'      => 'service',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	]);

	$service_areas = get_posts([
		'post_type'      => 'service_area',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	]);

	// === OUTPUT ===
	$output[] = "# llms.txt dynamically generated by My Local SEO";
	$output[] = "version: 1.0";
	$output[] = "";

	$output[] = "organization:";
	if (!empty($org_name))       $output[] = "  name: {$org_name}";
	if (!empty($org_legal_name)) $output[] = "  legal_name: {$org_legal_name}";
	$output[] = "  url: " . myls_llms_clean_line($org_url);
	if (!empty($org_logo))       $output[] = "  logo: {$org_logo}";
	if (!empty($org_desc))       $output[] = "  description: {$org_desc}";
	if (!empty($org_email))      $output[] = "  email: " . myls_llms_clean_line($org_email);
	if (!empty($org_phone))      $output[] = "  phone: {$org_phone}";
	if (!empty($org_founded))    $output[] = "  founding_date: {$org_founded}";
	$output[] = "  is_local: true";
	$output[] = "";

	$output[] = "locations:";
	if (!empty($locations)) {
		foreach ($locations as $loc) {
			$name    = myls_llms_clean_line($loc['location_name'] ?? ($loc['name'] ?? 'Office'));
			$addr    = myls_llms_clean_line($loc['street_address'] ?? ($loc['address'] ?? ''));
			$local   = myls_llms_clean_line($loc['locality'] ?? '');
			$region  = myls_llms_clean_line($loc['region'] ?? '');
			$postal  = myls_llms_clean_line($loc['postal_code'] ?? '');
			$country = myls_llms_clean_line($loc['country'] ?? 'US');
			$lat     = myls_llms_clean_line($loc['latitude'] ?? '');
			$lng     = myls_llms_clean_line($loc['longitude'] ?? '');
			$phone   = myls_llms_clean_line($loc['phone'] ?? $org_phone);

			$output[] = "  - name: " . ($name ?: 'Office');
			$output[] = "    address: {$addr}";
			$output[] = "    locality: {$local}";
			$output[] = "    region: {$region}";
			$output[] = "    postal_code: {$postal}";
			$output[] = "    country: {$country}";
			$output[] = "    latitude: {$lat}";
			$output[] = "    longitude: {$lng}";
			$output[] = "    phone: {$phone}";
			$output[] = "";
		}
	} else {
		$output[] = "  - name: Office";
		$output[] = "    address:";
		$output[] = "    locality:";
		$output[] = "    region:";
		$output[] = "    postal_code:";
		$output[] = "    country: US";
		$output[] = "    latitude:";
		$output[] = "    longitude:";
		$output[] = "    phone: {$org_phone}";
		$output[] = "";
	}

	$output[] = "services:";
	foreach ($services as $svc) {
		$title = myls_llms_clean_line($svc->post_title ?? '');
		if ($title) $output[] = "  - name: {$title}";
	}

	$output[] = "service_areas:";
	foreach ($service_areas as $area) {
		$title = myls_llms_clean_line($area->post_title ?? '');
		if ($title) $output[] = "  - name: {$title}";
	}

	$output[] = "";
	$output[] = "languages:";
	$output[] = "  - en";

	$output[] = "audience:";
	$output[] = "  - individuals";
	$output[] = "  - local customers";
	$output[] = "  - professionals";

	$output[] = "accessibility:";
	$output[] = "  wheelchair_accessible: true";
	$output[] = "  ada_compliant: true";

	// Hours: prefer first location opening hours, fallback to org_hours option
	$hours = [];
	if (!empty($locations) && is_array($locations) && !empty($locations[0]['opening_hours'])) {
		$hours = $locations[0]['opening_hours'];
	} elseif (!empty($org_hours)) {
		$hours = $org_hours;
	}

	if (!empty($hours) && is_array($hours)) {
		$output[] = "hours:";
		foreach ($hours as $entry) {
			$day    = strtolower(trim((string)($entry['day'] ?? '')));
			$opens  = trim((string)($entry['opens'] ?? ''));
			$closes = trim((string)($entry['closes'] ?? ''));
			if ($day && $opens && $closes) {
				$output[] = "  {$day}: " . myls_llms_clean_line("{$opens}-{$closes}");
			}
		}
	}

	if (!empty($org_social)) {
		$output[] = "social_profiles:";
		foreach ((array)$org_social as $url) {
			$url = myls_llms_clean_line($url);
			if ($url) $output[] = "  - {$url}";
		}
	}

	$output[] = "";
	$output[] = "ai_usage:";
	$output[] = "  ai_chat_compatible: true";
	$output[] = "  accepts_ai_contact: true";
	$output[] = "  chatbot_url:";

	// Allow last-minute customization by other code
	$output = apply_filters('myls_llms_txt_lines', $output);

	return implode("\n", $output) . "\n";
}

/** -------------------------------------------------------------------------
 *  Helpers
 *  ---------------------------------------------------------------------- */

/**
 * Prefer MYLS option key; fallback to legacy SSSEO; else default.
 */
function myls_llms_get_opt(string $myls_key, string $legacy_key, $default = '') {
	$val = get_option($myls_key, null);
	if ($val !== null && $val !== '' && $val !== false) return $val;

	$legacy = get_option($legacy_key, null);
	if ($legacy !== null && $legacy !== '' && $legacy !== false) return $legacy;

	return $default;
}

/**
 * Clean a value to a single safe text line (for YAML-like plain text output).
 */
function myls_llms_clean_line($value): string {
	if (is_array($value)) $value = implode(', ', array_filter(array_map('strval', $value)));
	$value = (string) $value;
	$value = wp_strip_all_tags($value);
	$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
	$value = preg_replace('/[\r\n\t]+/', ' ', $value);
	$value = preg_replace('/\s{2,}/', ' ', $value);
	return trim($value);
}

/** -------------------------------------------------------------------------
 *  Schema fetcher (cached)
 *  ---------------------------------------------------------------------- */
function myls_llms_fetch_schema_from_url(string $url): array {

	$url = esc_url_raw($url);
	if (!$url) return [];

	$cache_key = 'myls_llms_schema_' . md5($url);
	$cached = get_transient($cache_key);
	if (is_array($cached)) return $cached;

	$resp = wp_remote_get($url, [
		'timeout' => 10,
		'headers' => [
			'User-Agent' => 'MYLS-llms.txt/1.0; ' . home_url('/'),
		],
	]);

	if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) {
		set_transient($cache_key, [], 6 * HOUR_IN_SECONDS);
		return [];
	}

	$html = wp_remote_retrieve_body($resp);
	if (!$html) {
		set_transient($cache_key, [], 6 * HOUR_IN_SECONDS);
		return [];
	}

	if (!preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
		set_transient($cache_key, [], 6 * HOUR_IN_SECONDS);
		return [];
	}

	$data = [];

	foreach ($matches[1] as $json) {
		$obj = json_decode(trim($json), true);
		if (!$obj) continue;

		$items = isset($obj['@type']) ? [$obj] : ($obj['@graph'] ?? []);
		if (!is_array($items)) $items = [];

		foreach ($items as $item) {
			if (!is_array($item)) continue;

			$type = $item['@type'] ?? '';
			if (is_array($type)) $type = $type[0] ?? '';
			$type = (string) $type;

			if (in_array($type, ['Organization', 'LocalBusiness'], true)) {
				if (!isset($data[$type])) $data[$type] = [];
				$data[$type][] = $item;
			}
		}
	}

	// If only one of each, collapse to single object (match your old behavior)
	foreach (['Organization', 'LocalBusiness'] as $type) {
		if (isset($data[$type]) && is_array($data[$type]) && count($data[$type]) === 1) {
			$data[$type] = $data[$type][0];
		}
	}

	set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
	return $data;
}

/** -------------------------------------------------------------------------
 *  Meta description fetcher (cached lightly)
 *  ---------------------------------------------------------------------- */
function myls_llms_extract_meta_description(string $url): string {

	$url = esc_url_raw($url);
	if (!$url) return '';

	$cache_key = 'myls_llms_meta_desc_' . md5($url);
	$cached = get_transient($cache_key);
	if (is_string($cached)) return $cached;

	$resp = wp_remote_get($url, ['timeout' => 10]);
	if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) {
		set_transient($cache_key, '', 6 * HOUR_IN_SECONDS);
		return '';
	}

	$html = wp_remote_retrieve_body($resp);
	if (!$html) {
		set_transient($cache_key, '', 6 * HOUR_IN_SECONDS);
		return '';
	}

	$desc = '';
	if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
		$desc = trim($m[1]);
	}

	$desc = myls_llms_clean_line($desc);
	set_transient($cache_key, $desc, 12 * HOUR_IN_SECONDS);

	return $desc;
}

/** -------------------------------------------------------------------------
 *  Hours parser (same behavior as old file, but tolerant)
 *  ---------------------------------------------------------------------- */
function myls_llms_parse_opening_hours($input): array {

	if (!$input) return [];

	$lines = is_array($input) ? $input : preg_split('/\r\n|\r|\n/', (string)$input);
	if (!is_array($lines)) $lines = [];

	$result = [];

	foreach ($lines as $line) {
		$line = trim((string)$line);
		if ($line === '') continue;

		// Matches: "Mon,Tue 08:00-17:00" or "Monday 08:00-17:00"
		if (preg_match('/^(?P<days>[A-Za-z,\-]+)\s+(?P<times>\d{2}:\d{2}-\d{2}:\d{2})$/', $line, $m)) {
			$times = $m['times'] ?? '';
			[$opens, $closes] = array_pad(explode('-', $times, 2), 2, '');

			foreach (explode(',', (string)($m['days'] ?? '')) as $d) {
				$d = strtolower(trim($d));
				if (!$d) continue;

				$result[] = [
					'day'    => $d,
					'opens'  => trim($opens),
					'closes' => trim($closes),
				];
			}
		}
	}

	return $result;
}
