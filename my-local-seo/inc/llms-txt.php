<?php
/**
 * My Local SEO – llms.txt
 *
 * Best-practice location is the site root: https://example.com/llms.txt
 * Spec / guidance: https://llmstxt.org/
 *
 * This implementation is intentionally simple (v1):
 * - Adds a rewrite rule for /llms.txt
 * - Serves a Markdown (plain text) response with basic, high-value links
 * - Provides filters so we can expand later without breaking the endpoint
 */

if ( ! defined('ABSPATH') ) exit;

/* -------------------------------------------------------------------------
 * Options
 * ------------------------------------------------------------------------- */

/**
 * Get a boolean-ish option.
 * Accepts: 1, '1', true, 'true', 'yes', 'on'
 */
if ( ! function_exists('myls_llms_opt_bool') ) {
	function myls_llms_opt_bool( string $key, bool $default = true ) : bool {
		$val = get_option( $key, $default ? '1' : '0' );
		if ( is_bool($val) ) return $val;
		$val = is_string($val) ? strtolower(trim($val)) : $val;
		return in_array( $val, [ 1, '1', 'true', 'yes', 'on' ], true );
	}
}

/**
 * Get an integer option with sane clamping.
 */
if ( ! function_exists('myls_llms_opt_int') ) {
	function myls_llms_opt_int( string $key, int $default, int $min = 0, int $max = 500 ) : int {
		$val = (int) get_option( $key, (string) $default );
		if ( $val < $min ) $val = $min;
		if ( $val > $max ) $val = $max;
		return $val;
	}
}

/* -------------------------------------------------------------------------
 * Builders
 * ------------------------------------------------------------------------- */

/**
 * Build a simple list of markdown links for a post type.
 */
if ( ! function_exists('myls_llms_links_for_post_type') ) {
	function myls_llms_links_for_post_type( string $post_type, int $limit = 15 ) : array {
		$limit = max( 1, min( 200, $limit ) );

		$q = new WP_Query([
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'orderby'             => 'menu_order title',
			'order'               => 'ASC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'fields'              => 'ids',
		]);

		if ( empty($q->posts) ) return [];

		$lines = [];
		foreach ( $q->posts as $pid ) {
			$pid   = (int) $pid;
			$title = get_the_title( $pid );
			$url   = get_permalink( $pid );
			if ( ! $title || ! $url ) continue;
			$lines[] = '- [' . wp_strip_all_tags($title) . '](' . esc_url_raw($url) . ')';
		}
		return $lines;
	}
}

/**
 * Build a master list of FAQ links by scanning posts that have MYLS FAQ meta.
 *
 * Storage:
 *  - _myls_faq_items = [ [ 'q' => '...', 'a' => '...' ], ... ]
 *
 * Output:
 *  - [Question](Page URL)
 *
 * Notes:
 *  - We link to the page URL (anchors are not stable today because accordion IDs are UUID-based).
 *  - Global dedupe by question text to avoid repeated Qs across pages.
 */
if ( ! function_exists('myls_llms_collect_master_faq_links') ) {
	function myls_llms_collect_master_faq_links( int $limit = 25 ) : array {
		global $wpdb;

		$limit = max( 1, min( 200, $limit ) );

		// Find posts that actually have the FAQ meta key.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT DISTINCT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				  AND p.post_status = 'publish'
				  AND p.post_type NOT IN ('revision','nav_menu_item','attachment')
				ORDER BY p.post_modified_gmt DESC
				LIMIT %d
				",
				'_myls_faq_items',
				500
			)
		);

		if ( empty( $post_ids ) ) return [];

		$lines       = [];
		$seen_q      = [];
		$total_added = 0;

		foreach ( $post_ids as $post_id ) {
			if ( $total_added >= $limit ) break;

			$post_id = (int) $post_id;
			$url     = get_permalink( $post_id );
			if ( ! $url ) continue;

			$items = get_post_meta( $post_id, '_myls_faq_items', true );
			if ( ! is_array( $items ) || empty( $items ) ) continue;

			// Stable per-page anchors: #faq-1, #faq-2, ...
			// These are emitted by the FAQ accordion shortcode (see modules/shortcodes/faq-schema-accordion.php).
			$idx = 0;
			foreach ( $items as $row ) {
				$pos = $idx + 1; // 1-based position in the stored meta array
				$idx++;
				if ( $total_added >= $limit ) break;
				if ( ! is_array( $row ) ) continue;

				$q = trim( sanitize_text_field( (string) ( $row['q'] ?? '' ) ) );
				$a = (string) ( $row['a'] ?? '' );

				// Skip blanks / empty answer HTML.
				$a_plain = trim( wp_strip_all_tags( $a ) );
				if ( $q === '' || $a_plain === '' ) continue;

				// Global dedupe by normalized question.
				$q_key = strtolower( preg_replace('/\s+/', ' ', $q ) );
				if ( isset( $seen_q[ $q_key ] ) ) continue;
				$seen_q[ $q_key ] = true;

				$anchor = '#faq-' . $pos;
				$lines[] = '- [' . $q . '](' . esc_url_raw( $url . $anchor ) . ')';
				$total_added++;
			}
		}

		return $lines;
	}
}

/**
 * Build "Business details" lines.
 *
 * Source order:
 *  1) MYLS Organization options
 *  2) LocalBusiness location #1 (myls_lb_locations)
 *  3) Site name + home URL
 */
if ( ! function_exists('myls_llms_business_details_lines') ) {
	function myls_llms_business_details_lines() : array {
		$site_name = wp_strip_all_tags( get_bloginfo('name') );
		$home      = home_url( '/' );

		$org_name = trim( (string) get_option('myls_org_name', '') );
		$org_url  = trim( (string) get_option('myls_org_url', '') );
		$org_tel  = trim( (string) get_option('myls_org_tel', '') );
		$street   = trim( (string) get_option('myls_org_street', '') );
		$city     = trim( (string) get_option('myls_org_locality', '') );
		$state    = trim( (string) get_option('myls_org_region', '') );
		$zip      = trim( (string) get_option('myls_org_postal', '') );
		$country  = trim( (string) get_option('myls_org_country', '') );

		// Fallback to LocalBusiness location #1 if org fields are missing.
		$locs = function_exists('myls_lb_get_locations_cached') ? (array) myls_lb_get_locations_cached() : (array) get_option('myls_lb_locations', []);
		$loc0 = ( ! empty($locs) && is_array($locs[0] ?? null) ) ? (array) $locs[0] : [];

		$name = $org_name ?: trim( (string) ( $loc0['name'] ?? '' ) );
		$url  = $org_url ?: $home;
		$tel  = $org_tel ?: trim( (string) ( $loc0['phone'] ?? '' ) );

		if ( ! $street )  $street  = trim( (string) ( $loc0['street']  ?? '' ) );
		if ( ! $city )    $city    = trim( (string) ( $loc0['city']    ?? '' ) );
		if ( ! $state )   $state   = trim( (string) ( $loc0['state']   ?? '' ) );
		if ( ! $zip )     $zip     = trim( (string) ( $loc0['zip']     ?? '' ) );
		if ( ! $country ) $country = trim( (string) ( $loc0['country'] ?? '' ) );

		$name = $name ?: ( $site_name ?: 'Business' );
		$url  = $url ?: $home;

		$addr_parts = array_filter( [ $street, $city, $state, $zip, $country ] );
		$addr       = $addr_parts ? implode( ', ', $addr_parts ) : '';

		$lines = [];
		$lines[] = '- Legal name: ' . $name;
		$lines[] = '- Website: ' . $url;
		if ( $tel )  $lines[] = '- Phone: ' . $tel;
		if ( $addr ) $lines[] = '- Address: ' . $addr;

		return $lines;
	}
}

/**
 * Register rewrite rule early.
 *
 * Note: Activation flush happens elsewhere; during activation we also call this
 * function (if available) before flushing rewrite rules.
 */
if ( ! function_exists('myls_llms_register_rewrite') ) {
	function myls_llms_register_rewrite() : void {
		// Example match: https://example.com/llms.txt
		add_rewrite_rule( '^llms\.txt$', 'index.php?myls_llms=1', 'top' );
	}
	add_action( 'init', 'myls_llms_register_rewrite', 1 );
}

/** Make our query var public. */
add_filter( 'query_vars', function( $vars ) {
	$vars[] = 'myls_llms';
	return $vars;
} );

/**
 * Build a basic llms.txt payload.
 *
 * The llmstxt.org spec recommends:
 *  - H1 title (required)
 *  - blockquote summary
 *  - optional paragraphs
 *  - H2 sections containing lists of markdown links
 */
if ( ! function_exists('myls_llms_generate_content') ) {
	function myls_llms_generate_content() : string {
		$site_name = wp_strip_all_tags( get_bloginfo('name') );
		$tagline   = wp_strip_all_tags( get_bloginfo('description') );

		// Prefer the plugin's Organization description when available.
		$org_desc = trim( (string) get_option('myls_org_description', '') );
		$summary  = $org_desc ? wp_strip_all_tags( $org_desc ) : $tagline;

		$home      = home_url( '/' );
		$sitemap   = home_url( '/sitemap.xml' );
		$robots    = home_url( '/robots.txt' );

		// Attempt to find a “Contact” page by common slugs (optional).
		$contact_url = '';
		foreach ( [ 'contact', 'contact-us', 'contact-us-2', 'get-a-quote', 'request-a-quote' ] as $slug ) {
			$p = get_page_by_path( $slug );
			if ( $p instanceof WP_Post ) {
				$contact_url = get_permalink( $p );
				break;
			}
		}

		$lines = [];
		$lines[] = '# ' . ( $site_name ?: 'Website' );
		if ( $summary ) {
			$lines[] = '';
			$lines[] = '> ' . $summary;
		}

		// Optional: make the file self-describing (Markdown comment).
		$lines[] = '';
		$lines[] = '<!-- Generated by My Local SEO | Last updated: ' . gmdate('Y-m-d') . ' -->';

		$lines[] = '';
		$lines[] = '## Key pages';
		$lines[] = '- [Home](' . esc_url_raw($home) . '): Main homepage.';
		if ( $contact_url ) {
			$lines[] = '- [Contact](' . esc_url_raw($contact_url) . '): Primary contact page.';
		}
		$lines[] = '- [LLM Info](' . esc_url_raw( home_url('/llm-info') ) . '): Detailed business information for AI assistants (HTML).';
		$lines[] = '- [Sitemap](' . esc_url_raw($sitemap) . '): XML sitemap (if enabled).';
		$lines[] = '- [Robots](' . esc_url_raw($robots) . '): robots.txt directives.';

		$lines[] = '';
		$lines[] = '## Optional';
		$lines[] = '- [REST API Index](' . esc_url_raw( home_url('/wp-json/') ) . '): WordPress REST API entry point.';

		// -------------------------------------------------
		// Authority signals
		// -------------------------------------------------
		if ( myls_llms_opt_bool('myls_llms_include_business_details', true) ) {
			$bd = myls_llms_business_details_lines();
			if ( ! empty($bd) ) {
				$lines[] = '';
				$lines[] = '## Business details';
				$lines   = array_merge( $lines, $bd );
			}
		}

		// -------------------------------------------------
		// High ROI sections
		// -------------------------------------------------

		if ( myls_llms_opt_bool('myls_llms_include_services', true) ) {
			$limit = myls_llms_opt_int('myls_llms_services_limit', 15, 1, 200);
			$svc   = myls_llms_links_for_post_type( 'service', $limit );
			if ( ! empty($svc) ) {
				$lines[] = '';
				$lines[] = '## Primary services';
				$lines   = array_merge( $lines, $svc );
			}
		}

		if ( myls_llms_opt_bool('myls_llms_include_service_areas', true) ) {
			$limit = myls_llms_opt_int('myls_llms_service_areas_limit', 20, 1, 300);
			$areas = myls_llms_links_for_post_type( 'service_area', $limit );
			if ( ! empty($areas) ) {
				$lines[] = '';
				$lines[] = '## Service areas';
				$lines   = array_merge( $lines, $areas );
			}
		}

		if ( myls_llms_opt_bool('myls_llms_include_faqs', true) ) {
			$limit = myls_llms_opt_int('myls_llms_faqs_limit', 25, 1, 200);
			$faqs  = myls_llms_collect_master_faq_links( $limit );
			if ( ! empty($faqs) ) {
				$lines[] = '';
				$lines[] = '## Frequently asked questions';
				$lines   = array_merge( $lines, $faqs );
			}
		}

		$content = implode( "\n", $lines ) . "\n";

		/**
		 * Filter: myls_llms_txt_content
		 * Allows other MYLS modules (or themes) to modify/extend the llms.txt output.
		 */
		return (string) apply_filters( 'myls_llms_txt_content', $content );
	}
}

/**
 * Serve llms.txt when requested.
 */
add_action( 'template_redirect', function() {
	$flag = get_query_var('myls_llms');
	if ( (string) $flag !== '1' ) return;

	// Allow disabling the endpoint without removing the rewrite rule.
	if ( ! myls_llms_opt_bool('myls_llms_enabled', true) ) {
		status_header( 404 );
		exit;
	}

	// Prevent theme output.
	if ( function_exists('nocache_headers') ) nocache_headers();
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );

	// Helpful for debugging and confirms plugin ownership.
	header( 'X-My-Local-SEO: llms.txt' );

	// Output.
	echo myls_llms_generate_content();
	exit;
}, 0 );
