<?php
/**
 * Shortcode: [faq_schema_accordion heading="Concrete Washing Questions" post_id="123"]
 *
 * Heading rules (kept exactly as you described):
 * - heading omitted  => defaults to "Frequently Asked Questions" (prints)
 * - heading=""       => hides heading
 * - heading="Text"   => prints centered H2 with that text
 *
 * New:
 * - post_id="123"    => render FAQ repeater from a specific post/page (ACF field: faq_items)
 *
 * Notes:
 * - Requires ACF repeater: faq_items { question, answer }
 * - Outputs Bootstrap 5 accordion markup
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! function_exists('faq_schema_accordion_shortcode') ) {

	function faq_schema_accordion_shortcode( $atts ) {

		// ACF required
		if ( ! function_exists('have_rows') || ! function_exists('get_sub_field') ) {
			return '<p><em>ACF not active or missing repeater support.</em></p>';
		}

		// Detect whether heading was explicitly provided (behavior depends on key presence)
		$heading_was_provided = is_array($atts) && array_key_exists('heading', $atts);

		// Defaults + new post_id
		$atts = shortcode_atts(
			[
				'heading' => 'Frequently Asked Questions',
				'post_id' => 0,
			],
			(array) $atts,
			'faq_schema_accordion'
		);

		// Heading rules (unchanged)
		$heading_raw  = (string) ($atts['heading'] ?? '');
		$heading_text = trim( wp_strip_all_tags( $heading_raw ) );

		$print_heading = true;
		if ( $heading_was_provided && trim($heading_raw) === '' ) {
			$print_heading = false; // explicit empty hides
		}

		// Determine post_id (allow override)
		$post_id = 0;

		if ( isset($atts['post_id']) && is_numeric($atts['post_id']) && (int)$atts['post_id'] > 0 ) {
			$post_id = (int) $atts['post_id'];
		} elseif ( isset($GLOBALS['post']) && is_object($GLOBALS['post']) && ! empty($GLOBALS['post']->ID) ) {
			$post_id = (int) $GLOBALS['post']->ID;
		} else {
			$post_id = (int) get_the_ID();
		}

		if ( ! $post_id ) {
			return '<p><em>Unable to determine post ID.</em></p>';
		}

		// Bail if no rows
		if ( ! have_rows('faq_items', $post_id) ) {
			return '';
		}

		// Unique container ID (safe for multiple instances on the same page)
		$accordion_id = 'faqAccordion_' . wp_generate_uuid4();

		ob_start();
		?>

		<div class="myls-faq-schema-accordion-wrap">
			<?php if ( $print_heading && $heading_text !== '' ) : ?>
				<h2 class="text-center"><?php echo esc_html($heading_text); ?></h2>
			<?php endif; ?>

			<div class="accordion ssseo-accordion" id="<?php echo esc_attr($accordion_id); ?>">
				<?php
				$index = 0;

				while ( have_rows('faq_items', $post_id) ) {
					the_row();

					// Question: plain text
					$question = trim( sanitize_text_field( (string) get_sub_field('question') ) );

					// Answer: allow safe HTML
					$answer_raw = (string) get_sub_field('answer');
					$answer     = trim( wp_kses_post( $answer_raw ) );

					if ( $question === '' || $answer === '' ) {
						continue;
					}

					$heading_id  = "{$accordion_id}_heading_{$index}";
					$collapse_id = "{$accordion_id}_collapse_{$index}";
					?>
					<div class="ssseo-accordion-item accordion-item">
						<h2 class="ssseo-accordion-header accordion-header" id="<?php echo esc_attr($heading_id); ?>">
							<button
								class="accordion-button collapsed"
								type="button"
								data-bs-toggle="collapse"
								data-bs-target="#<?php echo esc_attr($collapse_id); ?>"
								aria-expanded="false"
								aria-controls="<?php echo esc_attr($collapse_id); ?>"
							>
								<?php echo esc_html($question); ?>
							</button>
						</h2>

						<div
							id="<?php echo esc_attr($collapse_id); ?>"
							class="accordion-collapse collapse"
							aria-labelledby="<?php echo esc_attr($heading_id); ?>"
							data-bs-parent="#<?php echo esc_attr($accordion_id); ?>"
						>
							<div class="accordion-body">
								<?php echo $answer; ?>
							</div>
						</div>
					</div>
					<?php
					$index++;
				}
				?>
			</div>
		</div>

		<?php
		return ob_get_clean();
	}
}

add_shortcode('faq_schema_accordion', 'faq_schema_accordion_shortcode');
