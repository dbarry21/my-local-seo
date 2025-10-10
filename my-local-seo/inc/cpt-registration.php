<?php
/**
 * My Local SEO – Conditional CPT Registration
 * Path: inc/cpt-registration.php
 *
 * Registers: service, service_area, product, video
 * Reads options saved by the My Local SEO CPT tab:
 *   - Enabled:      myls_enable_{id}_cpt            ('1' or '0')
 *   - Rewrite slug: myls_enable_{id}_cpt_slug       (string, no slashes)
 *   - Archive:      myls_enable_{id}_cpt_hasarchive ('', '1', 'true', '0', 'false', or custom slug)
 */

if ( ! defined('ABSPATH') ) exit;

/** Normalize has_archive from plugin option */
function myls_normalize_archive_option( $raw ) {
	// blank => false
	if ( is_string($raw) && trim($raw) === '' ) return false;

	// boolean-ish
	if ( in_array($raw, [0,'0',false,'false','off','no'], true) ) return false;
	if ( in_array($raw, [1,'1',true,'true','on','yes'], true) ) return true;

	// custom slug
	if ( is_string($raw) ) {
		$slug = sanitize_title( $raw );
		return $slug !== '' ? $slug : false;
	}

	return false;
}

/** Main registrar */
function myls_register_custom_post_types() {
	$cpts = [
		'service' => [
			'option_key'      => 'myls_enable_service_cpt',
			'default_slug'    => 'service',
			'default_archive' => 'services',
			'menu_position'   => 21,
			'labels'          => ['name'=>'Services','singular'=>'Service'],
			'hierarchical'    => false,
			'supports'        => [ 'title','editor','thumbnail','excerpt','custom-fields','revisions','page-attributes' ],
			'capability_type' => 'post',
			'menu_icon'       => 'dashicons-hammer',
			'taxonomies'      => [ 'category','post_tag' ],
		],
		'service_area' => [
			'option_key'      => 'myls_enable_service_area_cpt',
			'default_slug'    => 'service-area',
			'default_archive' => 'service-areas',
			'menu_position'   => 22,
			'labels'          => ['name'=>'Service Areas','singular'=>'Service Area'],
			'hierarchical'    => true, // ensures Parent chooser shows
			'supports'        => [ 'title','editor','thumbnail','excerpt','custom-fields','page-attributes','revisions' ],
			'capability_type' => 'page',
			'menu_icon'       => 'dashicons-location',
			'taxonomies'      => [],
		],
		'product' => [
			'option_key'      => 'myls_enable_product_cpt',
			'default_slug'    => 'product',
			'default_archive' => 'products',
			'menu_position'   => 23,
			'labels'          => ['name'=>'Products','singular'=>'Product'],
			'hierarchical'    => false,
			'supports'        => [ 'title','editor','thumbnail','excerpt','custom-fields','revisions','page-attributes' ],
			'capability_type' => 'post',
			'menu_icon'       => 'dashicons-cart',
			'taxonomies'      => [ 'category','post_tag' ],
		],
		'video' => [
			'option_key'      => 'myls_enable_video_cpt',
			'default_slug'    => 'video',
			'default_archive' => 'videos',
			'menu_position'   => 24,
			'labels'          => ['name'=>'Videos','singular'=>'Video'],
			'hierarchical'    => false,
			// Comments are handy for discussion; custom-fields for YouTube ID, transcript, etc.
			'supports'        => [ 'title','editor','thumbnail','excerpt','custom-fields','comments','revisions','page-attributes' ],
			'capability_type' => 'post',
			'menu_icon'       => 'dashicons-video-alt3',
			'taxonomies'      => [ 'category','post_tag' ],
		],
	];

	foreach ( $cpts as $post_type => $config ) {
		$enabled = get_option( $config['option_key'], '0' );
		if ( $enabled !== '1' ) continue;

		// Slug + archive from plugin options
		$slug_opt  = trim( (string) get_option( $config['option_key'] . '_slug', '' ) );
		$arch_opt  = get_option( $config['option_key'] . '_hasarchive', '' );

		$slug        = $slug_opt !== '' ? sanitize_title($slug_opt) : $config['default_slug'];
		$has_archive = myls_normalize_archive_option( $arch_opt );
		if ( $has_archive === true ) $has_archive = $config['default_archive']; // toggle → default archive slug

		$labels = [
			'name'                  => _x( $config['labels']['name'], 'Post Type General Name', 'myls' ),
			'singular_name'         => _x( $config['labels']['singular'], 'Post Type Singular Name', 'myls' ),
			'menu_name'             => __( $config['labels']['name'], 'myls' ),
			'name_admin_bar'        => __( $config['labels']['singular'], 'myls' ),
			'archives'              => __( $config['labels']['singular'] . ' Archives', 'myls' ),
			'attributes'            => __( $config['labels']['singular'] . ' Attributes', 'myls' ),
			'parent_item_colon'     => __( 'Parent ' . $config['labels']['singular'] . ':', 'myls' ),
			'all_items'             => __( 'All ' . $config['labels']['name'], 'myls' ),
			'add_new_item'          => __( 'Add New ' . $config['labels']['singular'], 'myls' ),
			'add_new'               => __( 'Add New', 'myls' ),
			'new_item'              => __( 'New ' . $config['labels']['singular'], 'myls' ),
			'edit_item'             => __( 'Edit ' . $config['labels']['singular'], 'myls' ),
			'update_item'           => __( 'Update ' . $config['labels']['singular'], 'myls' ),
			'view_item'             => __( 'View ' . $config['labels']['singular'], 'myls' ),
			'view_items'            => __( 'View ' . $config['labels']['name'], 'myls' ),
			'search_items'          => __( 'Search ' . $config['labels']['singular'], 'myls' ),
			'not_found'             => __( 'Not found', 'myls' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'myls' ),
			'featured_image'        => __( 'Featured Image', 'myls' ),
			'set_featured_image'    => __( 'Set featured image', 'myls' ),
			'remove_featured_image' => __( 'Remove featured image', 'myls' ),
			'use_featured_image'    => __( 'Use as featured image', 'myls' ),
			'insert_into_item'      => __( 'Insert into ' . $config['labels']['singular'], 'myls' ),
			'uploaded_to_this_item' => __( 'Uploaded to this ' . $config['labels']['singular'], 'myls' ),
			'items_list'            => __( $config['labels']['name'] . ' list', 'myls' ),
			'items_list_navigation' => __( $config['labels']['name'] . ' list navigation', 'myls' ),
			'filter_items_list'     => __( 'Filter ' . $config['labels']['name'] . ' list', 'myls' ),
		];

		$args = [
			'label'               => __( $config['labels']['singular'], 'myls' ),
			'description'         => __( $config['labels']['singular'] . ' Description', 'myls' ),
			'labels'              => $labels,
			'supports'            => $config['supports'],
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => $config['menu_position'],
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => $has_archive,                 // false, custom slug, or default archive via toggle
			'hierarchical'        => (bool) $config['hierarchical'],
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'show_in_rest'        => true,
			'rewrite'             => [ 'slug' => $slug, 'with_front' => false ],
			'capability_type'     => $config['capability_type'],
			'map_meta_cap'        => true,
			'menu_icon'           => $config['menu_icon'] ?? 'dashicons-admin-post',
			'taxonomies'          => $config['taxonomies'] ?? [],
		];

		register_post_type( $post_type, $args );
	}
}
add_action( 'init', 'myls_register_custom_post_types', 0 );

/** Metabox: About the Area (service_area) — parity with SSSEO Tools (same meta key) */
add_action('add_meta_boxes', function() {
	add_meta_box(
		'myls_about_the_area',
		'About the Area',
		function( $post ) {
			$content = get_post_meta( $post->ID, '_about_the_area', true );
			wp_nonce_field( 'myls_save_about_the_area', 'myls_about_the_area_nonce' );
			wp_editor( $content, 'myls_about_the_area_editor', [
				'textarea_name' => 'about_the_area',
				'media_buttons' => true,
				'textarea_rows' => 8,
			] );
		},
		'service_area',
		'normal',
		'high'
	);
});

add_action('save_post', function( $post_id ) {
	if ( ! isset($_POST['myls_about_the_area_nonce']) ) return;
	if ( ! wp_verify_nonce( $_POST['myls_about_the_area_nonce'], 'myls_save_about_the_area' ) ) return;
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( ! current_user_can('edit_post', $post_id) ) return;

	if ( isset($_POST['about_the_area']) ) {
		update_post_meta( $post_id, '_about_the_area', wp_kses_post( $_POST['about_the_area'] ) );
	}
});

/** Auto-flush rewrites when CPT options are changed in the CPT tab */
add_action( 'updated_option', function( $option, $old, $new ) {
	if ( strpos( $option, 'myls_enable_' ) === 0 ) {
		myls_register_custom_post_types(); // ensure types exist in the context of the flush
		flush_rewrite_rules();
	}
}, 10, 3 );

/** Activation helper (call from main plugin file) */
function myls_activate_register_cpts_and_flush() {
	myls_register_custom_post_types();
	flush_rewrite_rules();
}

/** Deactivation helper (optional) */
function myls_deactivate_flush_rewrites() {
	flush_rewrite_rules();
}
