<?php
/**
 * Shortcode: [service_grid]
 *
 * Features:
 * - Centers incomplete rows (great for last row with fewer items)
 * - Optional excerpt support (on by default in this refactor)
 * - Optional fixed image height w/ object-fit cover
 * - Optional "featured first card" layout (bigger first card)
 * - Optional force 2-column layout
 *
 * Usage:
 *   [service_grid]                                       // defaults (centered, excerpts on)
 *   [service_grid show_excerpt="0"]                      // disable excerpt
 *   [service_grid excerpt_words="18"]                    // change excerpt length
 *   [service_grid center="0"]                            // disable row centering
 *   [service_grid image_crop="1" image_height="220"]     // uniform image crop
 *   [service_grid featured_first="1"]                    // first item larger
 *   [service_grid two_col="1"]                           // force 2-column layout
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! function_exists('ssseo_service_grid_shortcode_v2') ) {
  function ssseo_service_grid_shortcode_v2( $atts = [] ) {

    $a = shortcode_atts( [
      'posts_per_page' => -1,
      'orderby'        => 'menu_order title',
      'order'          => 'ASC',

      // Layout classes
      // NOTE: g-4 remains, but CSS will override gutters to 10px via --bs-gutter-x/y.
      'row_class'      => 'row g-4 justify-content-center',
      'col_class'      => 'col-md-6 col-lg-3 mb-4',
      'box_class'      => 'service-box h-100',
      'image_size'     => 'large',

      // Centering toggles
      'center'         => '1',  // 1|0 : adds justify-content-center to row
      'text_center'    => '0',  // 1|0 : adds text-center to box

      // Button
      'button'         => '1',
      'button_text'    => 'Learn More',
      'button_class'   => 'btn btn-primary mt-2',
      'button_target'  => '',   // e.g. _blank
      'button_rel'     => '',   // e.g. nofollow

      // Excerpt (NOW ON BY DEFAULT)
      'show_excerpt'   => '1',  // <-- changed default to 1
      'excerpt_words'  => '20',

      // New: layout/style toggles (CSS-driven, no inline styles)
      'image_crop'     => '0',  // 1|0 : add class that crops images via CSS
      'image_height'   => '220',// used as CSS variable (--myls-img-h) in px
      'featured_first' => '0',  // 1|0 : first card spans wider columns (markup-level)
      'two_col'        => '0',  // 1|0 : forces 2-col layout classes for all items
    ], $atts, 'service_grid' );

    // Normalize row classes if centering disabled
    $row_class = trim((string) $a['row_class']);
    if ( $a['center'] !== '1' ) {
      $row_class = trim(preg_replace('/\bjustify-content-center\b/', '', $row_class));
    } else {
      if ( ! preg_match('/\bjustify-content-center\b/', $row_class) ) {
        $row_class .= ' justify-content-center';
      }
      $row_class = trim($row_class);
    }

    // Box class
    $box_class = trim((string) $a['box_class']);
    if ( $a['text_center'] === '1' && ! preg_match('/\btext-center\b/', $box_class) ) {
      $box_class .= ' text-center';
    }

    // Wrapper classes + CSS var for image height
    $wrap_classes = ['myls-service-grid'];
    if ( $a['image_crop'] === '1' ) $wrap_classes[] = 'myls-sg-crop';
    if ( $a['featured_first'] === '1' ) $wrap_classes[] = 'myls-sg-featured-first';
    if ( $a['two_col'] === '1' ) $wrap_classes[] = 'myls-sg-two-col';

    $img_h = max( 80, (int) $a['image_height'] ); // safety min

    // Query services
    $q = new WP_Query( [
      'post_type'      => 'service',
      'posts_per_page' => intval( $a['posts_per_page'] ),
      'post_status'    => 'publish',
      'orderby'        => $a['orderby'],
      'order'          => $a['order'],
      'no_found_rows'  => true,
    ] );

    ob_start();

    if ( $q->have_posts() ) {

      // Use a CSS variable for image height without hardcoding in CSS
      echo '<div class="' . esc_attr( implode(' ', $wrap_classes ) ) . '" style="--myls-img-h:' . esc_attr($img_h) . 'px;">';
      echo '<div class="' . esc_attr( $row_class ) . '">';

      $i = 0;

      while ( $q->have_posts() ) {
        $q->the_post();
        $i++;

        $post_id   = get_the_ID();
        $title     = get_the_title();
        $permalink = get_permalink();
        $thumb_url = get_the_post_thumbnail_url( $post_id, $a['image_size'] );

        // Determine column class per-item (supports featured first + two_col override)
        $col_class = trim((string) $a['col_class']);

        if ( $a['two_col'] === '1' ) {
          $col_class = 'col-md-6 mb-4';
        }

        if ( $a['featured_first'] === '1' && $i === 1 ) {
          // First card spans wider: 2/3 width on lg, half on md
          $col_class = 'col-md-6 col-lg-6 mb-4';
        }

        echo '<div class="' . esc_attr( $col_class ) . '">';
        echo   '<div class="' . esc_attr( $box_class ) . '">';

        if ( $thumb_url ) {
          echo '<a href="' . esc_url( $permalink ) . '" class="myls-sg-img-link">';
          echo '<img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $title ) . '" class="img-fluid mb-3 rounded myls-sg-img" loading="lazy" decoding="async">';
          echo '</a>';
        }

        echo '<h4 class="mb-2 myls-sg-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h4>';

        // Excerpt (on by default)
        if ( $a['show_excerpt'] === '1' ) {
          $excerpt = get_the_excerpt( $post_id );

          // If empty excerpt, fallback to trimmed content
          if ( ! $excerpt ) {
            $excerpt = wp_trim_words(
              wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $post_id ) ) ),
              max( 1, intval( $a['excerpt_words'] ) )
            );
          }

          // Output only if we have content
          if ( $excerpt ) {
            echo '<p class="mb-2 myls-sg-excerpt">' . esc_html( $excerpt ) . '</p>';
          }
        }

        if ( $a['button'] === '1' ) {
          echo '<a href="' . esc_url( $permalink ) . '"'
            . ( $a['button_class'] ? ' class="' . esc_attr( $a['button_class'] ) . ' myls-sg-btn"' : ' class="myls-sg-btn"' )
            . ( $a['button_target'] ? ' target="' . esc_attr( $a['button_target'] ) . '"' : '' )
            . ( $a['button_rel'] ? ' rel="' . esc_attr( $a['button_rel'] ) . '"' : '' )
            . '>' . esc_html( $a['button_text'] ) . '</a>';
        }

        echo   '</div>';
        echo '</div>';
      }

      echo '</div>';
      echo '</div>';

      wp_reset_postdata();

    } else {
      echo '<p>No services found.</p>';
    }

    return ob_get_clean();
  }
}

add_shortcode( 'service_grid', 'ssseo_service_grid_shortcode_v2' );
