<?php
/**
 * MYLS – Divi Module: FAQ Schema Accordion
 *
 * Renders your [faq_schema_accordion] shortcode output in Divi as a native module
 * with per-instance styling controls, including support for Divi global color vars:
 *   var(--et-global-color-1), var(--et-global-color-2), etc.
 *
 * NOTE:
 * - This file assumes your shortcode is loaded and that the function
 *   faq_schema_accordion_shortcode() exists.
 * - For Post ID override to work, apply the shortcode patch shown below.
 */

if ( ! defined('ABSPATH') ) exit;

add_action('et_builder_ready', function () {

	// If Divi isn't active, do nothing.
	if ( ! class_exists('ET_Builder_Module') ) return;

	class MYLS_Divi_FAQ_Schema_Accordion extends ET_Builder_Module {

		public $slug       = 'myls_faq_schema_accordion';
		public $vb_support = 'on';

		function init() {
			$this->name             = esc_html__('FAQ Schema Accordion', 'myls');
			$this->plural           = esc_html__('FAQ Schema Accordions', 'myls');
			$this->main_css_element = '%%order_class%%';

			// Optional: categorize the module so it’s easier to find
			// (Divi 4 generally ignores custom categories, but harmless)
			$this->custom_css_tab = true;
		}

		/**
		 * Module fields (we use TEXT for colors so you can enter:
		 *  - #rrggbb
		 *  - rgba(...)
		 *  - var(--et-global-color-1)
		 */
		function get_fields() {

			return [

				// ---------------- Content ----------------
				'heading_mode' => [
					'label'       => esc_html__('Heading Mode', 'myls'),
					'type'        => 'select',
					'options'     => [
						'default' => esc_html__('Default ("Frequently Asked Questions")', 'myls'),
						'custom'  => esc_html__('Custom text', 'myls'),
						'hide'    => esc_html__('Hide heading', 'myls'),
					],
					'default'     => 'default',
					'toggle_slug' => 'content',
				],

				'heading_text' => [
					'label'       => esc_html__('Custom Heading Text', 'myls'),
					'type'        => 'text',
					'default'     => 'Frequently Asked Questions',
					'toggle_slug' => 'content',
					'show_if'     => [ 'heading_mode' => 'custom' ],
				],

				'post_id' => [
					'label'       => esc_html__('Post ID Override (optional)', 'myls'),
					'type'        => 'text',
					'default'     => '',
					'toggle_slug' => 'content',
					'description' => esc_html__('If set, the shortcode will render FAQs from that post (requires the shortcode patch to accept post_id).', 'myls'),
				],

				// ---------------- Style ----------------
				'heading_color' => [
					'label'       => esc_html__('Heading Color', 'myls'),
					'type'        => 'text',
					'default'     => 'var(--et-global-color-1)',
					'toggle_slug' => 'style',
					'description' => esc_html__('Example: var(--et-global-color-1) or #000000', 'myls'),
				],

				'header_bg' => [
					'label'       => esc_html__('Accordion Header Background', 'myls'),
					'type'        => 'text',
					'default'     => 'var(--et-global-color-1)',
					'toggle_slug' => 'style',
				],

				'header_text' => [
					'label'       => esc_html__('Accordion Header Text Color', 'myls'),
					'type'        => 'text',
					'default'     => '#ffffff',
					'toggle_slug' => 'style',
				],

				'header_bg_hover' => [
					'label'       => esc_html__('Accordion Header Hover Background', 'myls'),
					'type'        => 'text',
					'default'     => 'var(--et-global-color-2)',
					'toggle_slug' => 'style',
				],

				'body_bg' => [
					'label'       => esc_html__('Accordion Body Background', 'myls'),
					'type'        => 'text',
					'default'     => '#ffffff',
					'toggle_slug' => 'style',
				],

				'body_text' => [
					'label'       => esc_html__('Accordion Body Text Color', 'myls'),
					'type'        => 'text',
					'default'     => '#111111',
					'toggle_slug' => 'style',
				],

				'radius' => [
					'label'          => esc_html__('Item Border Radius (px)', 'myls'),
					'type'           => 'range',
					'default'        => '10px',
					'range_settings' => [ 'min' => 0, 'max' => 40, 'step' => 1 ],
					'toggle_slug'    => 'style',
				],

				'item_spacing' => [
					'label'          => esc_html__('Item Spacing (px)', 'myls'),
					'type'           => 'range',
					'default'        => '10px',
					'range_settings' => [ 'min' => 0, 'max' => 40, 'step' => 1 ],
					'toggle_slug'    => 'style',
				],

				'button_padding_y' => [
					'label'          => esc_html__('Header Padding Y (px)', 'myls'),
					'type'           => 'range',
					'default'        => '14px',
					'range_settings' => [ 'min' => 0, 'max' => 40, 'step' => 1 ],
					'toggle_slug'    => 'style',
				],

				'button_padding_x' => [
					'label'          => esc_html__('Header Padding X (px)', 'myls'),
					'type'           => 'range',
					'default'        => '16px',
					'range_settings' => [ 'min' => 0, 'max' => 60, 'step' => 1 ],
					'toggle_slug'    => 'style',
				],
			];
		}

		/**
		 * Divi "Design" tab controls (fonts, borders, spacing, shadows).
		 * We disable heading text color here since we manage it via heading_color
		 * to support CSS variables.
		 */
		function get_advanced_fields_config() {

			return [
				'fonts' => [
					'heading' => [
						'label' => esc_html__('Heading', 'myls'),
						'css'   => [
							'main' => "{$this->main_css_element} h2.text-center",
						],
						'hide_text_color' => true,
					],
					'button' => [
						'label' => esc_html__('Accordion Header Text', 'myls'),
						'css'   => [
							'main' => "{$this->main_css_element} .ssseo-accordion .accordion-button",
						],
					],
					'body' => [
						'label' => esc_html__('Accordion Body Text', 'myls'),
						'css'   => [
							'main' => "{$this->main_css_element} .ssseo-accordion .accordion-body",
						],
					],
				],
				'margin_padding' => [
					'css' => [
						'main' => $this->main_css_element,
					],
				],
				'borders' => [
					'default' => [
						'css' => [
							'main' => "{$this->main_css_element} .ssseo-accordion .accordion-item",
						],
					],
				],
				'box_shadow' => [
					'default' => [
						'css' => [
							'main' => "{$this->main_css_element} .ssseo-accordion .accordion-item",
						],
					],
				],
				'background'     => false,
				'filters'        => false,
				'text'           => false,
				'link_options'   => false,
			];
		}

		function render( $attrs, $content = null, $render_slug = null ) {

			// Your shortcode must be loaded.
			if ( ! function_exists('faq_schema_accordion_shortcode') ) {
				return '<div class="myls-divi-faq-error"><em>faq_schema_accordion_shortcode() not found. Make sure your shortcode file is loaded before the Divi module.</em></div>';
			}

			// Build shortcode atts EXACTLY with your heading rules
			$heading_mode = $this->props['heading_mode'] ?? 'default';
			$heading_text = (string)($this->props['heading_text'] ?? 'Frequently Asked Questions');

			$shortcode_atts = [];

			// Your shortcode behavior depends on whether 'heading' key exists
			if ( $heading_mode === 'custom' ) {
				$shortcode_atts['heading'] = $heading_text;
			} elseif ( $heading_mode === 'hide' ) {
				$shortcode_atts['heading'] = '';
			} else {
				// default => OMIT key entirely (so your shortcode prints default heading)
			}

			// Post ID override (requires shortcode patch below)
			$post_id_raw = trim((string)($this->props['post_id'] ?? ''));
			if ( $post_id_raw !== '' && is_numeric($post_id_raw) ) {
				$shortcode_atts['post_id'] = (int) $post_id_raw;
			}

			// Render the shortcode output
			$html = faq_schema_accordion_shortcode( $shortcode_atts );

			if ( trim((string)$html) === '' ) {
				// No rows -> no output
				return '';
			}

			// Style props (allow CSS vars)
			$heading_color  = (string)($this->props['heading_color'] ?? 'var(--et-global-color-1)');
			$header_bg      = (string)($this->props['header_bg'] ?? 'var(--et-global-color-1)');
			$header_text    = (string)($this->props['header_text'] ?? '#ffffff');
			$header_bg_hov  = (string)($this->props['header_bg_hover'] ?? 'var(--et-global-color-2)');
			$body_bg        = (string)($this->props['body_bg'] ?? '#ffffff');
			$body_text      = (string)($this->props['body_text'] ?? '#111111');

			$radius       = isset($this->props['radius']) ? preg_replace('/[^0-9]/', '', (string)$this->props['radius']) : '10';
			$item_spacing = isset($this->props['item_spacing']) ? preg_replace('/[^0-9]/', '', (string)$this->props['item_spacing']) : '10';
			$pad_y        = isset($this->props['button_padding_y']) ? preg_replace('/[^0-9]/', '', (string)$this->props['button_padding_y']) : '14';
			$pad_x        = isset($this->props['button_padding_x']) ? preg_replace('/[^0-9]/', '', (string)$this->props['button_padding_x']) : '16';

			$radius       = is_numeric($radius) ? (int)$radius : 10;
			$item_spacing = is_numeric($item_spacing) ? (int)$item_spacing : 10;
			$pad_y        = is_numeric($pad_y) ? (int)$pad_y : 14;
			$pad_x        = is_numeric($pad_x) ? (int)$pad_x : 16;

			// Heading color (scoped)
			ET_Builder_Element::set_style($render_slug, [
				'selector'    => '%%order_class%% h2.text-center',
				'declaration' => 'color:' . esc_html($heading_color) . ';',
			]);

			// Item radius + spacing (scoped)
			ET_Builder_Element::set_style($render_slug, [
				'selector'    => '%%order_class%% .ssseo-accordion .accordion-item',
				'declaration' => sprintf('border-radius:%dpx; overflow:hidden; margin-bottom:%dpx;', $radius, $item_spacing),
			]);

			// Header button base (scoped)
			ET_Builder_Element::set_style($render_slug, [
				'selector'    => '%%order_class%% .ssseo-accordion .accordion-button',
				'declaration' => sprintf(
					'background:%s; color:%s; padding:%dpx %dpx; font-weight:700; box-shadow:none;',
					esc_html($header_bg),
					esc_html($header_text),
					$pad_y,
					$pad_x
				),
			]);

			// Header hover (scoped)
			ET_Builder_Element::set_style($render_slug, [
				'selector'    => '%%order_class%% .ssseo-accordion .accordion-button:hover',
				'declaration' => sprintf('background:%s; color:%s;', esc_html($header_bg_hov), esc_html($header_text)),
			]);

			// Active header (expanded) (scoped)
			ET_Builder_Element::set_style($render_slug, [
				'selector'    => '%%order_class%% .ssseo-accordion .accordion-button:not(.collapsed)',
				'declaration' => sprintf('background:%s; color:%s; box-shadow:none;', esc_html($header_bg), esc_html($header_text)),
			]);

			// Body (scoped)
			ET_Builder_Element::set_style($render_slug, [
				'selector'    => '%%order_class%% .ssseo-accordion .accordion-body',
				'declaration' => sprintf('background:%s; color:%s;', esc_html($body_bg), esc_html($body_text)),
			]);

			// Remove focus glow (scoped)
			ET_Builder_Element::set_style($render_slug, [
				'selector'    => '%%order_class%% .ssseo-accordion .accordion-button:focus',
				'declaration' => 'box-shadow:none; outline:none;',
			]);

			return '<div class="myls-divi-faq-accordion">' . $html . '</div>';
		}
	}

	new MYLS_Divi_FAQ_Schema_Accordion();
});
