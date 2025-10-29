<?php
/**
 * Module: Service Area Grid (map + excerpt, alternating)
 * Shortcode: [service_area_grid include_drafts="0"]
 *
 * - Queries post_type=service_area (published by default; include_drafts="1" also pulls drafts)
 * - Default sort: menu_order, title (ASC)
 * - Each row: 2 columns (Bootstrap)
 *      - One column renders map via [myls_map_embed field="city_state"] (ratio 16x9)
 *      - Other column renders **linked Post Title** + ACF field 'html_excerpt' (with shortcode + wpautop)
 * - Rows alternate columns (map left/text right, then text left/map right)
 * - Mobile: always 1 column, map first
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Safe stub if map shortcode isn't loaded yet (MYLS first, then legacy) */
if ( ! shortcode_exists('myls_map_embed') && ! shortcode_exists('ssseo_map_embed') ) {
	add_shortcode('myls_map_embed', function(){ return '<em>Maps shortcode not available.</em>'; });
}

add_shortcode('service_area_grid', function( $atts ) {
	$a = shortcode_atts([
		'posts_per_page' => -1,            // -1 = all
		'parent_id'      => '',            // empty = any parent; set "0" for top-level only; or numeric parent ID
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'class'          => '',            // extra classes for outer container
		'show_title'     => '1',           // show the post title above excerpt
		'map_ratio'      => '16x9',        // pass through to map shortcode
		'include_drafts' => '0',           // NEW: "1" to include draft posts
	], $atts, 'service_area_grid' );

	// Determine post_status
	$want_drafts = in_array( strtolower( (string) $a['include_drafts'] ), ['1','true','yes'], true );
	$post_status = $want_drafts ? ['publish','draft'] : ['publish'];

	// Build WP_Query args
	$args = [
		'post_type'        => 'service_area',
		'post_status'      => $post_status,
		'posts_per_page'   => (int) $a['posts_per_page'],
		'orderby'          => [],
		'order'            => strtoupper( $a['order'] ) === 'DESC' ? 'DESC' : 'ASC',
		'no_found_rows'    => true,
		'suppress_filters' => false,
	];

	// Parent filter
	if ( $a['parent_id'] !== '' ) {
		if ( is_numeric( $a['parent_id'] ) ) {
			$args['post_parent'] = (int) $a['parent_id'];
		}
	}

	// Orderby parsing: allow "menu_order title"
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

	// Prefer MYLS map shortcode; fallback to legacy if needed
	$map_tag = shortcode_exists('myls_map_embed') ? 'myls_map_embed' : 'ssseo_map_embed';

	ob_start(); ?>
	<div class="container ssseo-service-area-grid <?php echo esc_attr($a['class']); ?>">
		<?php
		$i = 0;
		while ( $q->have_posts() ) : $q->the_post();
			$post_id = get_the_ID();

			// Row alternation
			$odd = ($i % 2 === 0); // zero-based
			$map_col_classes  = $odd ? 'order-1 order-md-1' : 'order-1 order-md-2';
			$text_col_classes = $odd ? 'order-2 order-md-2' : 'order-2 order-md-1';

			// html_excerpt (ACF or meta) with fallbacks
			$html_excerpt = '';
			if ( function_exists('get_field') ) {
				$html_excerpt = (string) get_field('html_excerpt', $post_id);
			}
			if ( $html_excerpt === '' ) {
				$html_excerpt = (string) get_post_meta($post_id, 'html_excerpt', true);
			}
			if ( $html_excerpt === '' ) {
				$html_excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id)
					: wp_trim_words( wp_strip_all_tags( get_the_content(null, false, $post_id) ), 36 );
			}
			$html_excerpt = wp_kses_post( wpautop( do_shortcode( $html_excerpt ) ) );

			// Map embed via shortcode (uses field="city_state")
			$map_sc = sprintf(
				'[%s field="city_state" ratio="%s" width="100%%"]',
				esc_attr($map_tag),
				esc_attr($a['map_ratio'])
			);
			$map_html = do_shortcode( $map_sc );

			// Linked title (no "(Draft)" label)
			$title_html = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_permalink($post_id) ),
				esc_html( get_the_title($post_id) )
			);
			?>
			<div class="row g-4 align-items-center ssseo-row mb-4">
				<div class="col-md-6 <?php echo esc_attr($map_col_classes); ?>">
					<?php echo $map_html; // sanitized by shortcode ?>
				</div>
				<div class="col-md-6 <?php echo esc_attr($text_col_classes); ?>">
					<?php if ( $a['show_title'] === '1' ) : ?>
						<h3 class="h4 mb-3"><?php echo $title_html; ?></h3>
					<?php endif; ?>
					<div class="ssseo-excerpt">
						<?php echo $html_excerpt; ?>
					</div>
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
