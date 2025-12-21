<?php
/**
 * My Local SEO – AI AJAX: GEO
 * File: inc/ajax/ai-geo.php
 *
 * Endpoints:
 *  - wp_ajax_myls_ai_geo_get_posts_v1
 *  - wp_ajax_myls_ai_geo_duplicate_v1
 *  - wp_ajax_myls_ai_geo_analyze_v1 (PREVIEW ONLY)
 *  - wp_ajax_myls_ai_geo_convert_v1 (CREATE DRAFT WITH REWRITTEN CONTENT)
 */

if ( ! defined('ABSPATH') ) exit;

/** Nonce helper */
if ( ! function_exists('myls_ai_check_nonce') ) {
  function myls_ai_check_nonce( string $action = 'myls_ai_ops' ) : void {
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : ( $_REQUEST['_ajax_nonce'] ?? '' );
    if ( ! wp_verify_nonce( $nonce, $action ) ) {
      wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'bad_nonce'], 403);
    }
  }
}

/**
 * AI provider wrapper.
 * We try common patterns so this works with your existing openai plumbing.
 */
if ( ! function_exists('myls_ai_generate_text') ) {
  function myls_ai_generate_text( string $prompt, array $opts = [] ) : string {

    // If the plugin provides a direct function, use it.
    if ( function_exists('myls_openai_complete') ) {
      $out = myls_openai_complete($prompt, $opts);
      return is_string($out) ? $out : '';
    }

    // Preferred: filter-based completion (lets your openai.php intercept)
    $out = apply_filters('myls_ai_complete', '', array_merge([
      'prompt'      => $prompt,
      'model'       => $opts['model']       ?? null,
      'max_tokens'  => $opts['max_tokens']  ?? null,
      'temperature' => $opts['temperature'] ?? null,
      'context'     => $opts['context']     ?? null,
      'post_id'     => $opts['post_id']     ?? null,
    ], $opts));

    if ( is_string($out) && trim($out) !== '' ) return $out;

    // Secondary fallback filter
    $out2 = apply_filters('myls_ai_generate_text', '', $prompt);
    if ( is_string($out2) && trim($out2) !== '' ) return $out2;

    return '';
  }
}

/** Strip code fences if the model returns them */
if ( ! function_exists('myls_geo_strip_code_fences') ) {
  function myls_geo_strip_code_fences( string $s ) : string {
    $s = preg_replace('/^\s*```[a-zA-Z0-9_-]*\s*\n/u', '', $s);
    $s = preg_replace('/\n?\s*```\\s*$/u', '', $s);
    return str_replace("```", "", $s);
  }
}

/**
 * Extract a best-effort "hero" section from post_content.
 *
 * Why:
 * - Many builders store hero content separately, so sometimes this will be empty.
 * - For block/classic content, we keep everything before the first <h2>.
 *
 * @return array{hero_html:string, body_text:string}
 */
if ( ! function_exists('myls_geo_split_hero_body') ) {
  function myls_geo_split_hero_body( string $content_html ) : array {
    $content_html = (string) $content_html;
    $content_html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content_html);
    $content_html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content_html);

    // Split at first <h2> (common boundary between intro/hero and body sections)
    $pos = stripos($content_html, '<h2');
    if ( $pos !== false && $pos > 0 ) {
      $hero_html = trim(substr($content_html, 0, $pos));
      $body_html = trim(substr($content_html, $pos));
    } else {
      $hero_html = '';
      $body_html = $content_html;
    }

    // If hero is too small, treat as empty (avoid keeping random crumbs)
    if ( strlen( wp_strip_all_tags($hero_html) ) < 40 ) {
      $hero_html = '';
    }

    // Always send plain text body to the model (reduces markup weirdness)
    $body_text = wp_strip_all_tags($body_html);
    $body_text = preg_replace('/\s+/u',' ', trim($body_text));

    // Hero html: keep only safe basic tags (p/strong/em/a) so it doesn't break admin preview
    $hero_allowed = [
      'p'      => [],
      'strong' => [],
      'em'     => [],
      'a'      => ['href'=>true, 'title'=>true],
      'br'     => [],
    ];
    $hero_clean = wp_kses($hero_html, $hero_allowed);

    return [
      'hero_html' => $hero_clean,
      'body_text' => $body_text,
    ];
  }
}

/**
 * Build a "GEO vs SEO" diff (HTML) and simple stats.
 */
if ( ! function_exists('myls_geo_build_diff') ) {
  function myls_geo_build_diff( string $old_text, string $new_text ) : array {
    if ( ! function_exists('wp_text_diff') ) {
      require_once ABSPATH . 'wp-admin/includes/template.php';
    }

    $old_text = (string) $old_text;
    $new_text = (string) $new_text;

    $diff = wp_text_diff($old_text, $new_text, [
      'title'       => 'GEO vs SEO',
      'show_split_view' => true,
    ]);

    $wc_old = str_word_count( wp_strip_all_tags($old_text) );
    $wc_new = str_word_count( wp_strip_all_tags($new_text) );

    return [
      'diff_html' => is_string($diff) ? $diff : '',
      'stats'    => [
        'words_before' => (int) $wc_old,
        'words_after'  => (int) $wc_new,
      ],
    ];
  }
}

/**
 * Get posts for a post type (includes drafts)
 * Returns: { posts: [{id,title}, ...] }
 */
add_action('wp_ajax_myls_ai_geo_get_posts_v1', function(){
  myls_ai_check_nonce();

  $pt = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'page';
  if ( ! post_type_exists($pt) ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'invalid_post_type'], 400);
  }

  $ptype_obj = get_post_type_object($pt);
  $cap = $ptype_obj && isset($ptype_obj->cap->edit_posts) ? $ptype_obj->cap->edit_posts : 'edit_posts';
  if ( ! current_user_can($cap) ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'cap_denied'], 403);
  }

  $ids = get_posts([
    'post_type'       => $pt,
    'post_status'     => ['publish','draft','pending','future','private'],
    'posts_per_page'  => -1,
    'orderby'         => 'title',
    'order'           => 'ASC',
    'fields'          => 'ids',
    'suppress_filters'=> true,
  ]);

  $out = [];
  foreach ($ids as $pid) {
    $out[] = ['id'=>(int)$pid, 'title'=> get_the_title($pid)];
  }

  wp_send_json_success(['marker'=>'geo_v1','status'=>'ok','posts'=>$out]);
});

/**
 * Analyze one post (PREVIEW ONLY)
 * Returns:
 *  - html: cleaned HTML (allowed tags)
 *  - raw:  raw AI output (fences stripped) for debugging
 */
add_action('wp_ajax_myls_ai_geo_analyze_v1', function(){
  myls_ai_check_nonce();

  $post_id     = (int) ($_POST['post_id'] ?? 0);
  $tokens      = max(1, (int) ($_POST['tokens'] ?? 1200));
  $temperature = (float) ($_POST['temperature'] ?? 0.4);
  $mode        = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'partial';
  $withAnchors = ! empty($_POST['with_anchors']);

  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'cap_denied'], 403);
  }

  $p = get_post($post_id);
  $title = get_the_title($post_id);
  $url   = get_permalink($post_id);

  // Template (from request or saved option)
  $template = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
  if ($template === '') $template = (string) get_option('myls_ai_geo_prompt_template', '');
  if ($template === '') {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'missing_template'], 400);
  }

  // Prepare hero/body for the model
  $split = myls_geo_split_hero_body( (string) $p->post_content );
  $hero_html = (string) ($split['hero_html'] ?? '');
  $content_txt = (string) ($split['body_text'] ?? '');

  // In "full" mode, do not preserve hero; treat everything as body text.
  if ( $mode === 'full' ) {
    $hero_html = '';
    $content_txt = preg_replace('/\s+/u',' ', trim( wp_strip_all_tags( (string) $p->post_content ) ));
  }

  $prompt = str_replace(
    ['{{TITLE}}','{{URL}}','{{HERO}}','{{CONTENT}}'],
    [$title, $url, wp_strip_all_tags($hero_html), $content_txt],
    $template
  );

  // Model choice: leave to your openai.php defaults, but allow override if you add it later.
  $model = isset($_POST['model']) && is_string($_POST['model']) ? trim($_POST['model']) : '';

  $ai = myls_ai_generate_text($prompt, [
    'model'       => $model ?: null,
    'max_tokens'  => $tokens,
    'temperature' => $temperature,
    'context'     => 'geo_preview',
    'post_id'     => $post_id,
  ]);

  $raw = myls_geo_strip_code_fences( (string) $ai );

  // Cleaned HTML (allowlist)
  $allowed = [
    'h2'     => ['id'=>true],
    'h3'     => ['id'=>true],
    'p'      => [],
    'ul'     => [],
    'li'     => [],
    'strong' => [],
    'em'     => [],
  ];
  if ( $withAnchors ) {
    $allowed['a'] = ['href'=>true, 'title'=>true];
  }
  $clean = wp_kses($raw, $allowed);

  // Diff (text-only, so it is stable even when tags change)
  $old_text = wp_strip_all_tags( (string) $p->post_content );
  $new_text = wp_strip_all_tags( $clean );
  $diff_pack = myls_geo_build_diff($old_text, $new_text);

  wp_send_json_success([
    'marker'  => 'geo_v1',
    'status'  => 'ok',
    'post_id' => $post_id,
    'title'   => $title,
    'url'     => $url,
    'html'    => $clean,
    'raw'     => $raw,
    'diff_html' => (string) ($diff_pack['diff_html'] ?? ''),
    'stats'     => (array)  ($diff_pack['stats'] ?? []),
  ]);
});

/**
 * Convert one post into a NEW Draft that contains rewritten GEO content.
 *
 * Accepts:
 *  - post_id
 *  - template, tokens, temperature
 *  - mode = partial|full
 *  - with_anchors = 1|0
 */
add_action('wp_ajax_myls_ai_geo_convert_v1', function(){
  myls_ai_check_nonce();

  $post_id     = (int) ($_POST['post_id'] ?? 0);
  $tokens      = max(1, (int) ($_POST['tokens'] ?? 1200));
  $temperature = (float) ($_POST['temperature'] ?? 0.4);
  $mode        = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'partial';
  $withAnchors = ! empty($_POST['with_anchors']);

  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'cap_denied'], 403);
  }

  $src = get_post($post_id);
  if ( ! $src ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'post_not_found'], 404);
  }

  $title = get_the_title($post_id);
  $url   = get_permalink($post_id);

  // Template (from request or saved option)
  $template = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
  if ($template === '') $template = (string) get_option('myls_ai_geo_prompt_template', '');
  if ($template === '') {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'missing_template'], 400);
  }

  // Split hero/body
  $split = myls_geo_split_hero_body( (string) $src->post_content );
  $hero_html = (string) ($split['hero_html'] ?? '');
  $body_text = (string) ($split['body_text'] ?? '');

  if ( $mode === 'full' ) {
    $hero_html = '';
    $body_text = preg_replace('/\s+/u',' ', trim( wp_strip_all_tags( (string) $src->post_content ) ));
  }

  // Prompt
  $prompt = str_replace(
    ['{{TITLE}}','{{URL}}','{{HERO}}','{{CONTENT}}'],
    [$title, $url, wp_strip_all_tags($hero_html), $body_text],
    $template
  );

  $model = isset($_POST['model']) && is_string($_POST['model']) ? trim($_POST['model']) : '';
  $ai = myls_ai_generate_text($prompt, [
    'model'       => $model ?: null,
    'max_tokens'  => $tokens,
    'temperature' => $temperature,
    'context'     => 'geo_convert',
    'post_id'     => $post_id,
  ]);

  $raw = myls_geo_strip_code_fences( (string) $ai );

  // Allowlist + anchor support
  $allowed = [
    'h2'     => ['id'=>true],
    'h3'     => ['id'=>true],
    'p'      => [],
    'ul'     => [],
    'li'     => [],
    'strong' => [],
    'em'     => [],
  ];
  if ( $withAnchors ) {
    $allowed['a'] = ['href'=>true, 'title'=>true];
  }
  $clean_body = wp_kses($raw, $allowed);

  // Build final content
  $final = '';
  if ( $hero_html !== '' ) {
    // Add a jump-links row after hero (helps citations)
    if ( $withAnchors ) {
      $final .= $hero_html . "\n";
      $final .= '<p><a href="#quick-answer">Quick Answer</a> • <a href="#key-facts">Key Facts</a> • <a href="#common-questions">Common Questions</a></p>' . "\n\n";
    } else {
      $final .= $hero_html . "\n\n";
    }
  }
  $final .= $clean_body;

  // Create draft post
  $new_title = trim((string)$src->post_title) . ' (GEO Draft)';
  $new_post = [
    'post_type'      => $src->post_type,
    'post_status'    => 'draft',
    'post_title'     => $new_title,
    'post_content'   => (string) $final,
    'post_excerpt'   => (string) $src->post_excerpt,
    'post_parent'    => (int) $src->post_parent,
    'menu_order'     => (int) $src->menu_order,
    'comment_status' => (string) $src->comment_status,
    'ping_status'    => (string) $src->ping_status,
  ];

  $new_id = wp_insert_post($new_post, true);
  if ( is_wp_error($new_id) ) {
    wp_send_json_error([
      'marker'=>'geo_v1','status'=>'error','message'=>'insert_failed',
      'debug'=>['error'=>$new_id->get_error_message()]
    ], 500);
  }

  // Featured image
  $thumb_id = get_post_thumbnail_id($src->ID);
  if ( $thumb_id ) set_post_thumbnail($new_id, $thumb_id);

  // Taxonomies / terms
  $taxes = get_object_taxonomies($src->post_type, 'names');
  if ( is_array($taxes) ) {
    foreach ($taxes as $tax) {
      $terms = wp_get_object_terms($src->ID, $tax, ['fields'=>'ids']);
      if ( ! is_wp_error($terms) && is_array($terms) ) {
        wp_set_object_terms($new_id, $terms, $tax, false);
      }
    }
  }

  // Copy meta (best effort)
  $all_meta = get_post_meta($src->ID);
  if ( is_array($all_meta) ) {
    foreach ($all_meta as $key => $vals) {
      if ( ! is_string($key) || $key === '' ) continue;
      if ( in_array($key, ['_edit_lock','_edit_last','_wp_old_slug'], true) ) continue;
      if ( $key === '_thumbnail_id' ) continue;
      if ( is_array($vals) ) {
        foreach ($vals as $v) {
          add_post_meta($new_id, $key, maybe_unserialize($v));
        }
      }
    }
  }

  // Diff
  $old_text = wp_strip_all_tags( (string) $src->post_content );
  $new_text = wp_strip_all_tags( (string) $final );
  $diff_pack = myls_geo_build_diff($old_text, $new_text);

  $preview_url = function_exists('get_preview_post_link') ? get_preview_post_link($new_id) : '';
  $edit_url    = function_exists('get_edit_post_link') ? get_edit_post_link($new_id, 'raw') : '';

  wp_send_json_success([
    'marker'         => 'geo_v1',
    'status'         => 'converted',
    'source_id'      => (int) $src->ID,
    'source_title'   => (string) $src->post_title,
    'new_post_id'    => (int) $new_id,
    'new_post_title' => (string) get_the_title($new_id),
    'preview_url'    => (string) $preview_url,
    'edit_url'       => (string) $edit_url,
    'url'            => (string) $url,
    'html'           => (string) $final,
    'raw'            => (string) $raw,
    'diff_html'      => (string) ($diff_pack['diff_html'] ?? ''),
    'stats'          => (array)  ($diff_pack['stats'] ?? []),
  ]);
});

/**
 * Duplicate a single post to Draft (same post_type)
 * Copies:
 *  - title (+ " (GEO Draft)")
 *  - content, excerpt
 *  - featured image
 *  - taxonomies + terms
 *  - meta (best effort; skips volatile WP internals)
 */
add_action('wp_ajax_myls_ai_geo_duplicate_v1', function(){
  myls_ai_check_nonce();

  $post_id = (int) ($_POST['post_id'] ?? 0);
  if ( $post_id <= 0 ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'missing_post_id'], 400);
  }

  $src = get_post($post_id);
  if ( ! $src || empty($src->ID) ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'post_not_found'], 404);
  }

  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'geo_v1','status'=>'error','message'=>'cap_denied'], 403);
  }

  $new_title = trim((string)$src->post_title) . ' (GEO Draft)';

  $new_post = [
    'post_type'      => $src->post_type,
    'post_status'    => 'draft',
    'post_title'     => $new_title,
    'post_content'   => (string) $src->post_content,
    'post_excerpt'   => (string) $src->post_excerpt,
    'post_parent'    => (int) $src->post_parent,
    'menu_order'     => (int) $src->menu_order,
    'comment_status' => (string) $src->comment_status,
    'ping_status'    => (string) $src->ping_status,
  ];

  $new_id = wp_insert_post($new_post, true);
  if ( is_wp_error($new_id) ) {
    wp_send_json_error([
      'marker'=>'geo_v1','status'=>'error','message'=>'insert_failed',
      'debug'=>['error'=>$new_id->get_error_message()]
    ], 500);
  }

  // Featured image
  $thumb_id = get_post_thumbnail_id($src->ID);
  if ( $thumb_id ) set_post_thumbnail($new_id, $thumb_id);

  // Taxonomies / terms
  $taxes = get_object_taxonomies($src->post_type, 'names');
  if ( is_array($taxes) ) {
    foreach ($taxes as $tax) {
      $terms = wp_get_object_terms($src->ID, $tax, ['fields'=>'ids']);
      if ( ! is_wp_error($terms) && is_array($terms) ) {
        wp_set_object_terms($new_id, $terms, $tax, false);
      }
    }
  }

  // Copy meta (best effort)
  $all_meta = get_post_meta($src->ID);
  if ( is_array($all_meta) ) {
    foreach ($all_meta as $key => $vals) {
      if ( ! is_string($key) || $key === '' ) continue;

      // Skip volatile internals
      if ( in_array($key, ['_edit_lock','_edit_last','_wp_old_slug'], true) ) continue;
      if ( $key === '_thumbnail_id' ) continue;

      if ( is_array($vals) ) {
        foreach ($vals as $v) {
          add_post_meta($new_id, $key, maybe_unserialize($v));
        }
      }
    }
  }

  wp_send_json_success([
    'marker'        => 'geo_v1',
    'status'        => 'duplicated',
    'source_id'     => (int) $src->ID,
    'source_title'  => (string) $src->post_title,
    'new_post_id'   => (int) $new_id,
    'new_post_title'=> (string) get_the_title($new_id),
  ]);
});
