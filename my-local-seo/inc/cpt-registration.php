<?php
// File: inc/cpt-registration.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * My Local SEO – CPT Registration (reads per-CPT options saved by tab-cpt.php)
 *
 * Options written by tab-cpt.php:
 *   Enabled:      myls_enable_{id}_cpt               ('1' or '0')
 *   Rewrite slug: myls_enable_{id}_cpt_slug          (string, no slashes)
 *   Archive:      myls_enable_{id}_cpt_hasarchive    ('', '1', 'true', '0', 'false', or custom slug)
 *
 * Archive rule:
 *   - blank => false
 *   - '1'/'true' => true
 *   - '0'/'false' => false
 *   - any other non-empty string => sanitized slug string
 *
 * NOTE: Keep registration centralized here. Do NOT register CPTs in modules.
 */

/** Helpers: read per-CPT options saved by tab-cpt.php */
function myls_cpt_enabled( string $id, bool $default = false ): bool {
	$val = get_option( "myls_enable_{$id}_cpt", $default ? '1' : '0' );
	return in_array( $val, ['1', 1, true, 'true', 'on', 'yes'], true );
}
function myls_cpt_slug( string $id, string $fallback ): string {
	$raw = get_option( "myls_enable_{$id}_cpt_slug", '' );
	$raw = is_string( $raw ) ? trim( $raw ) : '';
	return $raw !== '' ? sanitize_title( $raw ) : $fallback;
}
function myls_cpt_has_archive( string $id, $fallback = false ) {
	$raw = get_option( "myls_enable_{$id}_cpt_hasarchive", '' );

	// blank => false
	if ( is_string( $raw ) && trim( $raw ) === '' ) return false;

	// boolean-ish
	if ( in_array( $raw, [0, '0', false, 'false', 'off', 'no'], true ) ) return false;
	if ( in_array( $raw, [1, '1', true, 'true', 'on', 'yes'], true ) ) return true;

	// custom slug
	if ( is_string( $raw ) ) {
		$slug = sanitize_title( $raw );
		return $slug !== '' ? $slug : false;
	}

	return (bool) $fallback;
}

/** Register enabled CPTs early */
add_action( 'init', 'myls_register_enabled_cpts', 0 );
function myls_register_enabled_cpts() {

	// Catalog defaults (placeholders are the same as tab-cpt.php defaults)
	$catalog = [
		// Working CPTs you already had
		'service' => [
			'default_enabled' => true,
			'default_slug'    => 'service',
			'default_archive' => false,
			'labels'          => [
				'name'          => __( 'Services', 'myls' ),
				'singular_name' => __( 'Service', 'myls' ),
				'all_items'     => __( 'All Services', 'myls' ),
				'add_new_item'  => __( 'Add New Service', 'myls' ),
				'edit_item'     => __( 'Edit Service', 'myls' ),
			],
			'menu_icon'       => 'dashicons-hammer',
		],

		'service_area' => [
			'default_enabled' => true,
			'default_slug'    => 'service-area',
			'default_archive' => false,
			'labels'          => [
				'name'          => __( 'Service Areas', 'myls' ),
				'singular_name' => __( 'Service Area', 'myls' ),
				'all_items'     => __( 'All Service Areas', 'myls' ),
				'add_new_item'  => __( 'Add New Service Area', 'myls' ),
				'edit_item'     => __( 'Edit Service Area', 'myls' ),
			],
			'menu_icon'       => 'dashicons-location',
		],

		// Recreated: Product
		'product' => [
			'default_enabled' => false,       // toggle ON in tab
			'default_slug'    => 'product',   // placeholder in UI can be 'product'
			'default_archive' => false,       // leave blank in UI to disable
			'labels'          => [
				'name'          => __( 'Products', 'myls' ),
				'singular_name' => __( 'Product', 'myls' ),
				'all_items'     => __( 'All Products', 'myls' ),
				'add_new_item'  => __( 'Add New Product', 'myls' ),
				'edit_item'     => __( 'Edit Product', 'myls' ),
			],
			'menu_icon'       => 'dashicons-products',
		],

		// Recreated: Video
		'video' => [
			'default_enabled' => false,       // toggle ON in tab
			'default_slug'    => 'video',     // placeholder in UI is 'video'
			'default_archive' => false,       // UI placeholder may show 'videos' but blank disables
			'labels'          => [
				'name'          => __( 'Videos', 'myls' ),
				'singular_name' => __( 'Video', 'myls' ),
				'all_items'     => __( 'All Videos', 'myls' ),
				'add_new_item'  => __( 'Add New Video', 'myls' ),
				'edit_item'     => __( 'Edit Video', 'myls' ),
			],
			'menu_icon'       => 'dashicons-video-alt3',
		],
	];

	foreach ( $catalog as $type => $cfg ) {
		if ( ! myls_cpt_enabled( $type, $cfg['default_enabled'] ) ) continue;

		$slug        = myls_cpt_slug( $type, $cfg['default_slug'] );
		$has_archive = myls_cpt_has_archive( $type, $cfg['default_archive'] );

		$args = [
			'labels'        => $cfg['labels'],
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'has_archive'   => $has_archive,
			'rewrite'       => [ 'slug' => $slug ],
			'menu_icon'     => $cfg['menu_icon'],
			'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'revisions' ],
			'map_meta_cap'  => true,
		];

		register_post_type( $type, $args );
	}
}

/** Flush rewrites when any CPT option changes (tab saves individual options) */
add_action( 'updated_option', function( $option, $old, $new ) {
	if ( strpos( $option, 'myls_enable_' ) === 0 ) {
		// ensure CPTs exist for flush run
		myls_register_enabled_cpts();
		flush_rewrite_rules();
	}
}, 10, 3 );

/** Activation helper (called from main plugin file) */
function myls_activate_register_cpts_and_flush() {
	myls_register_enabled_cpts();
	flush_rewrite_rules();
}
