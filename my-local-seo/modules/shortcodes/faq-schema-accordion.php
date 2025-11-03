<?php
/**
 * Shortcode: [faq_schema_accordion]
 * 
 * Outputs a Bootstrap 5 accordion of your ACF repeater 'faq_items'.
 */
function faq_schema_accordion_shortcode( $atts ) {
    // Make sure ACF is active
    if ( ! function_exists( 'have_rows' ) || ! function_exists( 'get_sub_field' ) ) {
        return '<p><em>ACF not active or missing repeater support.</em></p>';
    }

    // Determine current post ID
    global $post;
    $post_id = $post->ID ?? get_the_ID();
    if ( ! $post_id ) {
        return '<p><em>Unable to determine post ID.</em></p>';
    }

    // Bail if no rows
    if ( ! have_rows( 'faq_items', $post_id ) ) {
        return '<p></p>';
    }

    // Unique container ID
    $accordion_id = 'faqAccordion_' . uniqid();

    ob_start();
    ?>
    <h3>Frequently Asked Questions</h3>
    <div class="accordion ssseo-accordion" id="<?php echo esc_attr( $accordion_id ); ?>">
        <?php
        $index = 0;
        while ( have_rows( 'faq_items', $post_id ) ) {
            the_row();

            // Pull sub-fields by their ACF *field names* (no punctuation)
            $raw_q = get_sub_field( 'question' );
            $raw_a = get_sub_field( 'answer' );

            // Sanitize
            $question = trim( sanitize_text_field( $raw_q ) );
            $answer   = trim( wp_kses_post(      $raw_a ) );

            // Skip empty
            if ( ! $question || ! $answer ) {
                continue;
            }

            // IDs for collapse targets
            $heading_id  = "{$accordion_id}_heading_{$index}";
            $collapse_id = "{$accordion_id}_collapse_{$index}";
        ?>
            <div class="ssseo-accordion-item">
                <h2 class="ssseo-accordion-header" id="<?php echo esc_attr( $heading_id ); ?>">
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
        } // end while
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'faq_schema_accordion', 'faq_schema_accordion_shortcode' );