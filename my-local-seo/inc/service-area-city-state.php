<?php
/**
 * My Local SEO — ACF Field Group: Service Area
 * Fix: Use unique MYLS keys to avoid field-group key collisions with ACF UI / local JSON / other plugins.
 *
 * Field names remain the same (city_state, county, flip_box_image) so existing saved values still load.
 */
if ( ! defined('ABSPATH') ) exit;

add_action( 'acf/include_fields', function() {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return; // ACF not active
	}

	// Unique keys (avoid collisions with other sites importing ACF JSON/UI groups)
	$group_key = 'group_myls_service_area';
	$field_city_state_key = 'field_myls_city_state';
	$field_county_key     = 'field_myls_county';
	$field_flip_img_key   = 'field_myls_flip_box_image';

	acf_add_local_field_group( array(
		'key' => $group_key,
		'title' => 'Service Area',
		'fields' => array(

			array(
				'key' => $field_city_state_key,
				'label' => 'City, State',
				'name' => 'city_state',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 1,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),

			array(
				'key' => $field_county_key,
				'label' => 'County',
				'name' => 'county',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 1,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),

			array(
				'key' => $field_flip_img_key,
				'label' => 'Flip Box Image',
				'name' => 'flip_box_image',
				'type' => 'image',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'return_format' => 'array',
				'library' => 'all',
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'allow_in_bindings' => 0,
				'preview_size' => 'medium',
			),
		),

		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'service_area',
				),
			),
		),

		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	) );

}, 20 );
