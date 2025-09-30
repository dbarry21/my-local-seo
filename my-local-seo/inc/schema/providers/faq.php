<?php
/**
 * MYLS – FAQPage JSON-LD in <head> (all public post types)
 * - Auto-detects ACF repeater `faq_items` (question/answer)
 * - Optional page-level selector `page_schemas` containing 'faq'
 * - Respects global toggle: myls_faq_enabled === '1'
 */

if ( ! defined('ABSPATH') ) exit;

/** Build mainEntity from ACF rows */
if ( ! function_exists('myls_faq_collect_main_entity') ) {
	function myls_faq_collect_main_entity( int $post_id ) : array {
		if ( ! function_exists('have_rows') || ! function_exists('get_sub_field') ) return [];

		$main = [];
		if ( have_rows('faq_items', $post_id) ) {
			while ( have_rows('faq_items', $post_id) ) {
				the_row();
				$q = sanitize_text_field( trim( (string) get_sub_field('question') ) );
				$a = wp_kses_post(        trim( (string) get_sub_field('answer')   ) );
				if ( $q !== '' && $a !== '' ) {
					$main[] = [
						'@type'          => 'Question',
						'name'           => $q,
						'acceptedAnswer' => [
							'@type' => 'Answer',
							'text'  => $a,
						],
					];
				}
			}
		}
		return $main;
	}
}

/** Should we render on this post? */
if ( ! function_exists('myls_faq_should_render') ) {
	function myls_faq_should_render( WP_Post $post ) : bool {
		// Global toggle
		if ( get_option('myls_faq_enabled','0') !== '1' ) return false;

		// Only for public post types
		$public_types = get_post_types([ 'public' => true ]);
		if ( ! in_array( get_post_type($post), $public_types, true ) ) return false;

		// Page-level selector (optional)
		if ( function_exists('get_field') ) {
			$selected = (array) get_field('page_schemas', $post->ID);
			if ( in_array('faq', $selected, true) ) return true;
		}

		// Or: has ACF rows
		return function_exists('have_rows') && have_rows('faq_items', $post->ID);
	}
}

/** Build full schema array */
if ( ! function_exists('myls_faq_build_schema') ) {
	function myls_faq_build_schema( WP_Post $post ) : ?array {
		$main = myls_faq_collect_main_entity( $post->ID );
		if ( empty($main) ) return null;

		$permalink = get_permalink( $post );
		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'@id'        => trailingslashit( $permalink ) . '#faq',
			'mainEntity' => $main,
		];

		return apply_filters( 'myls_faq_schema', $schema, $post );
	}
}

/** Print into <head> */
if ( ! function_exists('myls_faq_print_head_schema') ) {
	function myls_faq_print_head_schema() {
		if ( is_admin() || wp_doing_ajax() || ! is_singular() ) return;

		$post = get_queried_object();
		if ( ! ($post instanceof WP_Post) ) return;
		if ( ! myls_faq_should_render( $post ) ) return;

		$schema = myls_faq_build_schema( $post );
		if ( empty($schema) ) return;

		echo "\n<!-- MYLS FAQPage JSON-LD (head) -->\n";
		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";
		echo "</script>\n";
	}
	add_action( 'wp_head', 'myls_faq_print_head_schema', 20 );
}
