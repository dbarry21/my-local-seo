<?php
/**
 * My Local SEO – AI AJAX (About the Area v2, saving to _about_the_area)
 * Path: inc/ajax/ai-about.php
 *
 * Endpoints:
 *  - wp_ajax_myls_ai_about_get_posts_v2
 *  - wp_ajax_myls_ai_about_generate_v2
 */
if ( ! defined('ABSPATH') ) exit;

/** Nonce helper */
if ( ! function_exists('myls_ai_check_nonce') ) {
  function myls_ai_check_nonce( string $action = 'myls_ai_ops' ) : void {
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : ( $_REQUEST['_ajax_nonce'] ?? '' );
    if ( ! wp_verify_nonce( $nonce, $action ) ) {
      wp_send_json_error(['marker'=>'about_v2','status'=>'error','message'=>'bad_nonce'], 403);
    }
  }
}

/** AI provider wrapper (must pass through model/max_tokens) */
if ( ! function_exists('myls_ai_generate_text') ) {
  function myls_ai_generate_text( string $prompt, array $opts = [] ) : string {
    // Preferred provider
    $out = apply_filters('myls_ai_complete', '', array_merge([
      'prompt'      => $prompt,
      'model'       => $opts['model']       ?? null,  // ensure downstream provider can use this
      'max_tokens'  => $opts['max_tokens']  ?? null,  // and this
      'temperature' => $opts['temperature'] ?? null,
      'context'     => $opts['context']     ?? null,
      'post_id'     => $opts['post_id']     ?? null,
    ], $opts));
    if ( is_string($out) && $out !== '' ) return $out;

    // Fallback provider
    $resp = apply_filters('myls_ai_generate_text', '', $prompt);
    if ( is_string($resp) && $resp !== '' ) return $resp;

    return '';
  }
}

/** Utilities */
if ( ! function_exists('myls_about_strip_code_fences') ) {
  function myls_about_strip_code_fences( string $s ) : string {
    // remove leading ```lang\n
    $s = preg_replace('/^\s*```[a-zA-Z0-9_-]*\s*\n/u', '', $s);
    // remove trailing ```
    $s = preg_replace('/\n?\s*```\\s*$/u', '', $s);
    // remove any remaining ```
    return str_replace("```", "", $s);
  }
}

/**
 * Convert leftover Markdown formatting to HTML.
 * AI models sometimes return **bold** or *italic* even when told to use HTML.
 */
if ( ! function_exists('myls_about_markdown_to_html') ) {
  function myls_about_markdown_to_html( string $s ) : string {
    // **bold** or __bold__  →  <strong>bold</strong>
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $s);

    // *italic* or _italic_  →  <em>italic</em>  (but not inside URLs or HTML attrs)
    // Only match single * not preceded/followed by space (avoids list markers)
    $s = preg_replace('/(?<!\w)\*(?!\s)(.+?)(?<!\s)\*(?!\w)/s', '<em>$1</em>', $s);

    // ### Heading 3  →  <h3>Heading 3</h3>
    $s = preg_replace('/^###\s*(.+)$/m', '<h3>$1</h3>', $s);

    // Markdown list items at start of line: - item or * item  →  <li>item</li>
    // (only if not already inside HTML tags)
    $s = preg_replace('/^[\-\*]\s+(.+)$/m', '<li>$1</li>', $s);

    // Wrap consecutive <li> blocks in <ul> if not already wrapped
    $s = preg_replace_callback('/(<li>.*?<\/li>\s*)+/s', function($m) {
      $block = trim($m[0]);
      // Don't double-wrap
      if (strpos($block, '<ul>') !== false) return $block;
      return '<ul>' . $block . '</ul>';
    }, $s);

    return $s;
  }
}

/**
 * Full AI response cleanup: strip fences, convert markdown to HTML.
 */
if ( ! function_exists('myls_about_clean_ai_response') ) {
  function myls_about_clean_ai_response( string $s ) : string {
    $s = myls_about_strip_code_fences( $s );
    $s = myls_about_markdown_to_html( $s );
    return $s;
  }
}
if ( ! function_exists('myls_about_has_h3') ) {
  function myls_about_has_h3( string $s ) : bool {
    return (bool) preg_match('/<h3[^>]*>/i', $s);
  }
}
if ( ! function_exists('myls_about_word_count') ) {
  function myls_about_word_count( string $s ) : int {
    $plain = wp_strip_all_tags($s);
    $plain = preg_replace('/\s+/u',' ', trim($plain));
    if ($plain === '') return 0;
    return count( preg_split('/\s+/u', $plain) );
  }
}

/** v2: list posts */
add_action('wp_ajax_myls_ai_about_get_posts_v2', function(){
  myls_ai_check_nonce();

  $pt = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'service_area';
  if ( ! post_type_exists($pt) ) {
    wp_send_json_error(['marker'=>'about_v2','status'=>'error','message'=>'invalid_post_type'], 400);
  }
  $ptype_obj = get_post_type_object($pt);
  $cap = $ptype_obj && isset($ptype_obj->cap->edit_posts) ? $ptype_obj->cap->edit_posts : 'edit_posts';
  if ( ! current_user_can($cap) ) {
    wp_send_json_error(['marker'=>'about_v2','status'=>'error','message'=>'cap_denied'], 403);
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

  wp_send_json_success(['marker'=>'about_v2','status'=>'ok','posts'=>$out]);
});

/** v2: generate + save one post (SAVE TO _about_the_area) */
add_action('wp_ajax_myls_ai_about_generate_v2', function(){
  myls_ai_check_nonce();

  $post_id     = (int) ($_POST['post_id'] ?? 0);
  $skip_filled = !empty($_POST['skip_filled']);
  // bump default to allow full sectioned HTML
  $tokens      = max(1, (int) ($_POST['tokens'] ?? 1600));
  $temperature = (float) ($_POST['temperature'] ?? 0.7);
  // allow a model override here if your provider supports it; default to gpt-4o
  $model       = isset($_POST['model']) && is_string($_POST['model']) ? trim($_POST['model']) : 'gpt-4o';

  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['marker'=>'about_v2','status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'about_v2','status'=>'error','message'=>'cap_denied'], 403);
  }

  // If skipping when already filled
  $existing = (string) get_post_meta($post_id, '_about_the_area', true);
  if ( $skip_filled && trim($existing) !== '' ) {
    wp_send_json_success([
      'marker'=>'about_v2','status'=>'skipped','post_id'=>$post_id,'reason'=>'already_filled'
    ]);
  }

  // Get city/state best-effort
  $city_state = '';
  if ( function_exists('get_field') ) $city_state = (string) get_field('city_state', $post_id);
  if ( $city_state === '' ) $city_state = (string) get_post_meta($post_id, 'city_state', true);
  if ( $city_state === '' ) $city_state = get_the_title($post_id);

  // Strong, shape-enforcing prompt
  $base_prompt = <<<EOT
You are an expert local SEO copywriter. Produce a concise, skimmable “About the Area” section for {{CITY_STATE}} as CLEAN HTML ONLY (no markdown, no backticks). Output must follow EXACTLY this structure:

<p>Short 2–3 sentence intro about {{CITY_STATE}}.</p>
<h3>Neighborhoods</h3>
<ul>
  <li>2–4 notable neighborhoods and 1 detail each</li>
</ul>
<h3>Things to Do</h3>
<ul>
  <li>3–5 highlights: parks, museums, venues, dining districts, or seasonal events</li>
</ul>
<h3>Getting Around</h3>
<p>1–2 sentences naming key roads/highways and typical drive times to nearby hubs.</p>
<p>1–2 sentence closing.</p>

Requirements:
- 450–600 words total.
- Use only <p>, <h3>, <ul>, <li>. No inline styles and no classes.
- Don’t mention or sell any business; area context only.
EOT;

  $template = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
  if ($template === '') {
    $template = get_option('myls_ai_about_prompt_template', $base_prompt);
  }
  // ensure {{CITY_STATE}} present
  if ( strpos($template, '{{CITY_STATE}}') === false ) {
    $template .= "\n\n(Location: {{CITY_STATE}})";
  }
  $prompt_1 = str_replace('{{CITY_STATE}}', $city_state, $template);

  // ----- First pass
  $html_1 = myls_ai_generate_text($prompt_1, [
    'model'       => $model,
    'max_tokens'  => $tokens,
    'temperature' => $temperature,
    'context'     => 'about_the_area',
    'post_id'     => $post_id,
  ]);

  if ( is_wp_error($html_1) ) {
    wp_send_json_error(['marker'=>'about_v2','status'=>'error','message'=>'ai_failed','error'=>$html_1->get_error_message()], 500);
  }

  $html_1 = myls_about_clean_ai_response( (string)$html_1 );

  // Check length & structure
  $ok_len  = myls_about_word_count($html_1) >= 380;   // tolerate a bit under 400
  $ok_h3   = myls_about_has_h3($html_1);

  $final_html = $html_1;

  // ----- Retry once if too short or missing <h3>
  if ( ! $ok_len || ! $ok_h3 ) {
    $retry_prompt = <<<EOT
The previous draft was too short or missing required sections. Rewrite for {{CITY_STATE}} with these hard requirements:

- 450–600 words.
- EXACTLY this structure and tags, CLEAN HTML only (no markdown/backticks):
<p>2–3 sentence intro.</p>
<h3>Neighborhoods</h3>
<ul><li>2–4 neighborhoods, 1 detail each</li></ul>
<h3>Things to Do</h3>
<ul><li>3–5 highlights with brief specifics</li></ul>
<h3>Getting Around</h3>
<p>1–2 sentences on key roads/highways and typical drive times.</p>
<p>1–2 sentence closing.</p>

Allowed tags: <p>, <h3>, <ul>, <li> only.
No business promotion. No inline styles. No classes. CLEAN HTML only.
EOT;
    $retry_prompt = str_replace('{{CITY_STATE}}', $city_state, $retry_prompt);

    $html_2 = myls_ai_generate_text($retry_prompt, [
      'model'       => $model,
      'max_tokens'  => max($tokens, 1600),
      'temperature' => max(0.3, min(1.0, $temperature)), // nudge up a bit
      'context'     => 'about_the_area',
      'post_id'     => $post_id,
    ]);

    if ( is_wp_error($html_2) ) {
      wp_send_json_error(['marker'=>'about_v2','status'=>'error','message'=>'ai_failed_retry','error'=>$html_2->get_error_message()], 500);
    }

    $html_2 = myls_about_clean_ai_response( (string)$html_2 );

    // choose the better one
    $cand = myls_about_word_count($html_2) >= myls_about_word_count($html_1) ? $html_2 : $html_1;
    $final_html = $cand;
  }

  // Sanitize and save to _about_the_area
  $allowed = wp_kses_allowed_html('post');
  $clean   = wp_kses( wp_unslash($final_html), $allowed );

  $saved = (bool) update_post_meta($post_id, '_about_the_area', $clean);

  // Verify by reading same key
  $verify = (string) get_post_meta($post_id, '_about_the_area', true);

  if ( ! $saved || trim( wp_strip_all_tags($verify) ) === '' ) {
    wp_send_json_error([
      'marker'=>'about_v2','status'=>'error','message'=>'save_failed_or_empty_after_write',
      'debug'=>[
        'saved'=>$saved?'true':'false',
        'verify_len'=>strlen($verify),
        'wcount'=>myls_about_word_count($final_html),
        'has_h3'=>myls_about_has_h3($final_html) ? '1' : '0',
      ]
    ], 500);
  }

  wp_send_json_success([
    'marker'     => 'about_v2',
    'status'     => 'saved',
    'post_id'    => $post_id,
    'city_state' => $city_state,
    'length'     => strlen($clean),
    'preview'    => mb_substr( wp_strip_all_tags($verify), 0, 100 ) . ( strlen( wp_strip_all_tags($verify) ) > 100 ? '…' : '' ),
  ]);
});
