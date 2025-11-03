<?php
/**
 * Module: Service Area Grid (map + excerpt, alternating)
 * Shortcode: [service_area_grid include_drafts="0" button_text="Schedule Estimate"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! shortcode_exists('myls_map_embed') && ! shortcode_exists('ssseo_map_embed') ) {
	add_shortcode('myls_map_embed', function(){ return '<em>Maps shortcode not available.</em>'; });
}

add_shortcode('service_area_grid', function( $atts ) {
	$a = shortcode_atts([
		'posts_per_page' => -1,
		'parent_id'      => '',
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'class'          => '',
		'show_title'     => '1',
		'map_ratio'      => '16x9',
		'include_drafts' => '0',
		'button_text'    => '', // NEW ATTRIBUTE
	], $atts, 'service_area_grid' );

	$want_drafts = in_array( strtolower( (string) $a['include_drafts'] ), ['1','true','yes'], true );
	$post_status = $want_drafts ? ['publish','draft'] : ['publish'];

	$args = [
		'post_type'        => 'service_area',
		'post_status'      => $post_status,
		'posts_per_page'   => (int) $a['posts_per_page'],
		'orderby'          => [],
		'order'            => strtoupper( $a['order'] ) === 'DESC' ? 'DESC' : 'ASC',
		'no_found_rows'    => true,
		'suppress_filters' => false,
	];

	if ( $a['parent_id'] !== '' && is_numeric( $a['parent_id'] ) ) {
		$args['post_parent'] = (int) $a['parent_id'];
	}

	// Parse orderby
	$tokens = preg_split('/[\s,]+/', trim((string)$a['orderby']));
	if ( $tokens && is_array($tokens) ) {
		foreach ( $tokens as $tok ) {
			$tok = trim($tok);
			if ( $tok ) $args['orderby'][$tok] = $args['order'];
		}
	}
	if ( empty($args['orderby']) ) {
		$args['orderby'] = ['menu_order' => $args['order'], 'title' => $args['order']];
	}

	$q = new WP_Query($args);
	if ( ! $q->have_posts() ) {
		return '<div class="container ssseo-service-area-grid '. esc_attr($a['class']) .'"><div class="alert alert-info">No service areas found.</div></div>';
	}

	$map_tag = shortcode_exists('myls_map_embed') ? 'myls_map_embed' : 'ssseo_map_embed';

	ob_start(); ?>
	<div class="container ssseo-service-area-grid <?php echo esc_attr($a['class']); ?>">
		<?php $i = 0;
		while ( $q->have_posts() ) : $q->the_post();
			$post_id = get_the_ID();
			$odd = ($i % 2 === 0);

			$map_col_classes  = $odd ? 'order-1 order-md-1' : 'order-1 order-md-2';
			$text_col_classes = $odd ? 'order-2 order-md-2' : 'order-2 order-md-1';

			$html_excerpt = '';
			if ( function_exists('get_field') ) $html_excerpt = (string) get_field('html_excerpt', $post_id);
			if ( $html_excerpt === '' ) $html_excerpt = (string) get_post_meta($post_id, 'html_excerpt', true);
			if ( $html_excerpt === '' ) {
				$html_excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id)
					: wp_trim_words( wp_strip_all_tags( get_the_content(null, false, $post_id) ), 36 );
			}
			$html_excerpt = wp_kses_post( wpautop( do_shortcode( $html_excerpt ) ) );

			$map_html = do_shortcode(
				sprintf('[%s field="city_state" ratio="%s" width="100%%"]', esc_attr($map_tag), esc_attr($a['map_ratio']))
			);

			$title_text = esc_html( get_the_title($post_id) );
			$is_published = ( get_post_status($post_id) === 'publish' );

			$title_html = $is_published
				? sprintf('<a href="%s">%s</a>', esc_url( get_permalink($post_id) ), $title_text)
				: $title_text;
			?>
			<div class="row g-4 align-items-center ssseo-row mb-4">
				<div class="col-md-6 <?php echo esc_attr($map_col_classes); ?>">
					<?php echo $map_html; ?>
				</div>
				<div class="col-md-6 <?php echo esc_attr($text_col_classes); ?>">
					<?php if ( $a['show_title'] === '1' ) : ?>
						<h3 class="h4 mb-3"><?php echo $title_html; ?></h3>
					<?php endif; ?>

					<div class="ssseo-excerpt mb-3">
						<?php echo $html_excerpt; ?>
					</div>

					<?php if ( ! empty($a['button_text']) ) : ?>
	<div class="ssseo-cta-btn-wrapper text-center">
		<?php if ( $is_published ) : ?>
			<a href="<?php echo esc_url( get_permalink($post_id) ); ?>" class="btn btn-primary ssseo-cta-btn">
				<?php echo esc_html( $a['button_text'] ); ?>
			</a>
		<?php else : ?>
			<span class="btn btn-secondary disabled ssseo-cta-btn">
				<?php echo esc_html( $a['button_text'] ); ?>
			</span>
		<?php endif; ?>
	</div>
<?php endif; ?>

				</div>
			</div>
			<?php
			$i++;
		endwhile;
		wp_reset_postdata();
		?>
	</div>
	<?php
	return ob_get_clean();
});
