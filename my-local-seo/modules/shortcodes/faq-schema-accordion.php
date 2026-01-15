<?php
/**
 * Shortcode: [faq_schema_accordion heading="Concrete Washing Questions"]
 *
 * - heading omitted  => defaults to "Frequently Asked Questions"
 * - heading=""       => hides heading
 * - heading="Text"   => prints centered H2 with that text
 */
function faq_schema_accordion_shortcode( $atts ) {

	// Make sure ACF is active
	if ( ! function_exists( 'have_rows' ) || ! function_exists( 'get_sub_field' ) ) {
		return '<p><em>ACF not active or missing repeater support.</em></p>';
	}

	// Detect whether heading was explicitly provided
	$heading_was_provided = is_array( $atts ) && array_key_exists( 'heading', $atts );

	// Apply defaults
	$atts = shortcode_atts(
		[
			'heading' => 'Frequently Asked Questions',
		],
		$atts,
		'faq_schema_accordion'
	);

	// Heading rules:
	// - if user provided heading="" => do not print
	// - if user provided heading="..." => print it
	// - if user did NOT provide heading => print default
	$heading_raw  = (string) ( $atts['heading'] ?? '' );
	$heading_text = trim( wp_strip_all_tags( $heading_raw ) );

	$print_heading = true;
	if ( $heading_was_provided && trim( $heading_raw ) === '' ) {
		$print_heading = false; // explicit empty hides
	}

	// Determine current post ID
	$post_id = 0;
	if ( isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) && ! empty( $GLOBALS['post']->ID ) ) {
		$post_id = (int) $GLOBALS['post']->ID;
	}
	if ( ! $post_id ) {
		$post_id = (int) get_the_ID();
	}
	if ( ! $post_id ) {
		return '<p><em>Unable to determine post ID.</em></p>';
	}

	// Bail if no rows
	if ( ! have_rows( 'faq_items', $post_id ) ) {
		return '';
	}

	// Unique container ID
	$accordion_id = 'faqAccordion_' . wp_generate_uuid4();

	ob_start();
	?>

	<?php if ( $print_heading && $heading_text !== '' ) : ?>
		<h2 class="text-center"><?php echo esc_html( $heading_text ); ?></h2>
	<?php endif; ?>

	<div class="accordion ssseo-accordion" id="<?php echo esc_attr( $accordion_id ); ?>">
		<?php
		$index = 0;

		while ( have_rows( 'faq_items', $post_id ) ) {
			the_row();

			$question = trim( sanitize_text_field( (string) get_sub_field( 'question' ) ) );
			$answer   = trim( wp_kses_post( (string) get_sub_field( 'answer' ) ) );

			if ( $question === '' || $answer === '' ) {
				continue;
			}

			$heading_id  = "{$accordion_id}_heading_{$index}";
			$collapse_id = "{$accordion_id}_collapse_{$index}";
			?>
			<div class="ssseo-accordion-item accordion-item">
				<h2 class="ssseo-accordion-header accordion-header" id="<?php echo esc_attr( $heading_id ); ?>">
					<button
						class="accordion-button collapsed"
						type="button"
						data-bs-toggle="collapse"
						data-bs-target="#<?php echo esc_attr( $collapse_id ); ?>"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $collapse_id ); ?>"
					>
						<?php echo esc_html( $question ); ?>
					</button>
				</h2>

				<div
					id="<?php echo esc_attr( $collapse_id ); ?>"
					class="accordion-collapse collapse"
					aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
					data-bs-parent="#<?php echo esc_attr( $accordion_id ); ?>"
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

	<?php
	return ob_get_clean();
}
add_shortcode( 'faq_schema_accordion', 'faq_schema_accordion_shortcode' );
