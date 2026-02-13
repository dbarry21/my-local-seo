<?php
if ( ! function_exists('ssseo_shortcode_city_state') ) {
  function ssseo_shortcode_city_state( $atts = [], $content = null, $tag = '' ) {

    $atts = shortcode_atts([
      'post_id'     => 0,
      'from'        => 'self',      // self|parent|ancestor
      'field'       => 'city_state',
      'delimiter'   => ',',
      'normalize'   => 0,
      'state_upper' => 0,
      'fallback'    => '',

      // ✅ NEW: formatting helpers
      'prefix'      => '',
      'suffix'      => '',
    ], $atts, $tag );

    $post_id = (int) $atts['post_id'];
    if ( $post_id <= 0 ) $post_id = get_the_ID();
    if ( ! $post_id ) return esc_html( (string) $atts['fallback'] );

    $target_id = $post_id;
    if ( $atts['from'] === 'parent' || $atts['from'] === 'ancestor' ) {
      $ancestor = ($atts['from'] === 'parent')
        ? (int) get_post_field('post_parent', $post_id)
        : (int) ssseo_city_value_find_nearest_ancestor_with_value( $post_id, $atts['field'] );

      if ( $ancestor > 0 ) $target_id = $ancestor;
    }

    // Cache only the BASE normalized city/state (not prefix/suffix)
    $ckey = 'ssseo_city_state:' . md5( implode('|', [
      $target_id, $atts['field'], $atts['delimiter'],
      (int) !empty($atts['normalize']), (int) !empty($atts['state_upper'])
    ]) );

    $cached = wp_cache_get( $ckey, 'ssseo' );
    if ( is_string($cached) ) {
      $base = trim($cached);
      if ( $base === '' ) return esc_html( (string) $atts['fallback'] );

      $out = (string) $atts['prefix'] . $base . (string) $atts['suffix'];
      return esc_html( $out );
    }

    $raw = ssseo_city_value_read_field( $target_id, $atts['field'] );

    $val = ssseo_city_state_normalize(
      $raw,
      $atts['delimiter'],
      !empty($atts['normalize']),
      !empty($atts['state_upper'])
    );

    $val = apply_filters('ssseo_city_state_output', $val, [
      'raw'       => $raw,
      'target_id' => $target_id,
      'atts'      => $atts,
    ]);

    $base = is_string($val) ? trim($val) : '';
    wp_cache_set( $ckey, $base, 'ssseo', 5 * MINUTE_IN_SECONDS );

    if ( $base === '' ) return esc_html( (string) $atts['fallback'] );

    $out = (string) $atts['prefix'] . $base . (string) $atts['suffix'];
    return esc_html( $out );
  }
}
