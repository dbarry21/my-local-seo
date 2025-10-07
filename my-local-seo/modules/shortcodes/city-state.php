<?php 
// ----- [city_state] shortcode -----------------------------------------------
// Works with/without ACF. Accepts arrays. Parent/ancestor fallback. Normalization.
// Attributes:
//  - post_id:     (int) post id to read from; default current post
//  - from:        (string) 'self' | 'parent' | 'ancestor'; default 'self'
//  - field:       (string) field/meta name; default 'city_state'
//  - delimiter:   (string) expected delimiter between city and state; default ','
//  - normalize:   (bool/int) when truthy, normalize spacing to "City, ST"; default 0
//  - state_upper: (bool/int) when truthy, uppercase the state part; default 0
//  - fallback:    (string) value if nothing found; default ''
if ( ! function_exists('ssseo_register_city_state_shortcode') ) {
  add_action('init', 'ssseo_register_city_state_shortcode');
  function ssseo_register_city_state_shortcode() {
    add_shortcode('city_state', 'ssseo_shortcode_city_state');
  }
}

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
    ], $atts, $tag );

    // Resolve base post
    $post_id = (int) $atts['post_id'];
    if ( $post_id <= 0 ) {
      $post_id = get_the_ID();
    }
    if ( ! $post_id ) {
      return esc_html( $atts['fallback'] );
    }

    // Determine target post (self/parent/ancestor)
    $target_id = $post_id;
    if ( $atts['from'] === 'parent' || $atts['from'] === 'ancestor' ) {
      $ancestor = ($atts['from'] === 'parent')
        ? (int) get_post_field('post_parent', $post_id)
        : (int) ssseo_city_value_find_nearest_ancestor_with_value( $post_id, $atts['field'] );
      if ( $ancestor > 0 ) {
        $target_id = $ancestor;
      }
    }

    // Cache key (per post + params)
    $ckey = 'ssseo_city_state:' . md5( implode('|', [
      $target_id, $atts['field'], $atts['delimiter'],
      (int) !empty($atts['normalize']), (int) !empty($atts['state_upper'])
    ]) );
    $cached = wp_cache_get( $ckey, 'ssseo' );
    if ( is_string($cached) ) {
      return $cached !== '' ? $cached : esc_html( $atts['fallback'] );
    }

    // Read value robustly
    $raw = ssseo_city_value_read_field( $target_id, $atts['field'] );
    $val = ssseo_city_state_normalize(
      $raw,
      $atts['delimiter'],
      !empty($atts['normalize']),
      !empty($atts['state_upper'])
    );

    // Filter for customization
    $val = apply_filters('ssseo_city_state_output', $val, [
      'raw'       => $raw,
      'target_id' => $target_id,
      'atts'      => $atts,
    ]);

    $safe = is_string($val) ? trim($val) : '';
    wp_cache_set( $ckey, $safe, 'ssseo', 5 * MINUTE_IN_SECONDS );

    if ( $safe === '' ) {
      return esc_html( $atts['fallback'] );
    }
    return esc_html( $safe );
  }
}

if ( ! function_exists('ssseo_register_city_only_shortcode') ) {
    add_action('init', 'ssseo_register_city_only_shortcode');
    function ssseo_register_city_only_shortcode() {
        add_shortcode('city_only', 'ssseo_shortcode_city_only');
    }
}

/** Helper: robust field read (ACF unformatted → formatted → raw meta); tolerates arrays. */
if ( ! function_exists('ssseo_city_value_read_field') ) {
  function ssseo_city_value_read_field( $post_id, $field_name ) {
    $normalize_any = function($v){
      if ( is_array($v) ) {
        $flat = array_filter(array_map(function($x){
          if ( is_scalar($x) ) return (string)$x;
          if ( is_array($x) || is_object($x) ) return wp_json_encode($x);
          return '';
        }, $v));
        return implode(', ', $flat);
      }
      return is_scalar($v) ? (string)$v : '';
    };

    if ( function_exists('get_field') ) {
      $v = get_field( $field_name, $post_id, false ); // unformatted
      $s = $normalize_any($v);
      if ( $s !== '' ) return $s;

      $v2 = get_field( $field_name, $post_id, true ); // formatted
      $s2 = $normalize_any($v2);
      if ( $s2 !== '' ) return $s2;
    }

    $m = get_post_meta( $post_id, $field_name, true );
    return $normalize_any($m);
  }
}

/** Helper: find nearest ancestor with non-empty field value. */
if ( ! function_exists('ssseo_city_value_find_nearest_ancestor_with_value') ) {
  function ssseo_city_value_find_nearest_ancestor_with_value( $post_id, $field_name = 'city_state' ) {
    $seen = [];
    $pid  = (int) $post_id;
    while ( $pid > 0 && ! isset($seen[$pid]) ) {
      $seen[$pid] = true;
      $parent_id = (int) get_post_field('post_parent', $pid);
      if ( $parent_id <= 0 ) break;
      $val = ssseo_city_value_read_field( $parent_id, $field_name );
      if ( trim((string)$val) !== '' ) return $parent_id;
      $pid = $parent_id;
    }
    return 0;
  }
}

/** Helper: normalize "City, ST" formatting & optionally uppercase state. */
if ( ! function_exists('ssseo_city_state_normalize') ) {
  function ssseo_city_state_normalize( $value, $delimiter = ',', $do_normalize = false, $state_upper = false ) {
    $value = trim( (string) $value );
    if ( $value === '' ) return '';

    // If normalization requested, ensure single space after delimiter.
    if ( $do_normalize && $delimiter !== '' ) {
      // Replace any spaces around delimiter with a single ", "
      $quoted = preg_quote($delimiter, '/');
      $value  = preg_replace('/\s*' . $quoted . '\s*/', $delimiter . ' ', $value);
    }

    // Optionally uppercase 2-letter state at end (handles both "City, ST" and "City ST")
    if ( $state_upper ) {
      if ( preg_match('/^(.*?)(?:\s*' . preg_quote($delimiter, '/') . '\s*|\s+)([A-Za-z]{2})$/', $value, $m) ) {
        $city  = rtrim($m[1]);
        $state = strtoupper($m[2]);
        $sep   = ($delimiter !== '') ? $delimiter . ' ' : ' ';
        $value = $city . $sep . $state;
      }
    }

    return $value;
  }
}


if ( ! function_exists('ssseo_shortcode_city_only') ) {
    function ssseo_shortcode_city_only( $atts = [], $content = null, $tag = '' ) {
        $atts = shortcode_atts([
            'post_id'   => 0,
            'from'      => 'self',     // self|parent|ancestor
            'field'     => 'city_state',
            'delimiter' => ',',
            'fallback'  => '',
        ], $atts, $tag );

        // Resolve base post id
        $post_id = (int) $atts['post_id'];
        if ( $post_id <= 0 ) {
            $post_id = get_the_ID();
        }
        if ( ! $post_id ) {
            return esc_html( $atts['fallback'] );
        }

        // Determine which post to read from (self/parent/ancestor)
        $target_id = $post_id;
        if ( $atts['from'] === 'parent' || $atts['from'] === 'ancestor' ) {
            $ancestor = ($atts['from'] === 'parent')
                ? (int) get_post_field('post_parent', $post_id)
                : ssseo_city_only_find_nearest_ancestor_with_value( $post_id, $atts['field'] );
            if ( $ancestor > 0 ) {
                $target_id = $ancestor;
            }
        }

        // Cache key (per post+args)
        $ckey = 'ssseo_city_only:' . md5( implode('|', [
            $target_id, $atts['field'], $atts['delimiter']
        ]) );

        $cached = wp_cache_get( $ckey, 'ssseo' );
        if ( is_string($cached) ) {
            return $cached !== '' ? $cached : esc_html( $atts['fallback'] );
        }

        // 1) Resolve raw city_state (ACF or meta), tolerating arrays
        $raw = ssseo_city_only_read_field_value( $target_id, $atts['field'] );

        // 2) Parse "City" from raw value
        $city = ssseo_city_only_parse_city( $raw, $atts['delimiter'] );

        // Allow customization via filter
        $city = apply_filters( 'ssseo_city_only_output', $city, [
            'raw'       => $raw,
            'target_id' => $target_id,
            'atts'      => $atts
        ]);

        // Cache (cache even empty string so we don't re-compute)
        $safe = is_string($city) ? $city : '';
        wp_cache_set( $ckey, $safe, 'ssseo', 5 * MINUTE_IN_SECONDS );

        if ( $safe === '' ) {
            return esc_html( $atts['fallback'] );
        }

        return esc_html( $safe );
    }
}

/** Read a field value robustly (ACF unformatted -> formatted -> raw meta). Accept arrays. */
if ( ! function_exists('ssseo_city_only_read_field_value') ) {
    function ssseo_city_only_read_field_value( $post_id, $field_name ) {
        $normalize = function($v) {
            if ( is_array($v) ) {
                // Convert array scalars to strings; serialize nested arrays/objects
                $flat = array_filter(array_map(function($x){
                    if ( is_scalar($x) ) return (string) $x;
                    if ( is_array($x) || is_object($x) ) return wp_json_encode($x);
                    return '';
                }, $v));
                return implode(', ', $flat);
            }
            return is_scalar($v) ? (string) $v : '';
        };

        // ACF: unformatted first (more predictable)
        if ( function_exists('get_field') ) {
            $v = get_field( $field_name, $post_id, false );
            $s = $normalize( $v );
            if ( $s !== '' ) return $s;

            // Then formatted
            $v2 = get_field( $field_name, $post_id, true );
            $s2 = $normalize( $v2 );
            if ( $s2 !== '' ) return $s2;
        }

        // Raw meta
        $m = get_post_meta( $post_id, $field_name, true );
        return $normalize( $m );
    }
}

/** Parse the city from a "City, ST" (or "City ST") string. */
if ( ! function_exists('ssseo_city_only_parse_city') ) {
    function ssseo_city_only_parse_city( $value, $delimiter = ',' ) {
        $value = trim( (string) $value );
        if ( $value === '' ) return '';

        // 1) If delimiter is present, take everything before first delimiter
        if ( $delimiter !== '' && strpos( $value, $delimiter ) !== false ) {
            $parts = explode( $delimiter, $value, 2 );
            return trim( $parts[0] );
        }

        // 2) Tolerate "City ST" (no comma). If the string ends with a two-letter state, strip it.
        //    e.g. "San Diego CA" -> "San Diego"
        if ( preg_match( '/^(.*?)[\s,]+([A-Z]{2})$/', $value, $m ) ) {
            // Be careful not to cut legit two-letter city names; require space/comma before state
            $maybe_city = trim( $m[1] );
            if ( $maybe_city !== '' ) return $maybe_city;
        }

        // 3) Fallback: return the full string as "city"
        return $value;
    }
}

/** Find nearest ancestor that has a non-empty field value (ACF/meta). */
if ( ! function_exists('ssseo_city_only_find_nearest_ancestor_with_value') ) {
    function ssseo_city_only_find_nearest_ancestor_with_value( $post_id, $field_name = 'city_state' ) {
        $seen = [];
        $pid  = (int) $post_id;
        while ( $pid > 0 && ! isset($seen[$pid]) ) {
            $seen[$pid] = true;
            $parent_id = (int) get_post_field( 'post_parent', $pid );
            if ( $parent_id <= 0 ) break;

            $val = ssseo_city_only_read_field_value( $parent_id, $field_name );
            if ( trim((string)$val) !== '' ) {
                return $parent_id;
            }
            $pid = $parent_id;
        }
        return 0;
    }
}