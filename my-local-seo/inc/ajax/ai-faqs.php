<?php
/**
 * My Local SEO – AI AJAX: FAQs
 * File: inc/ajax/ai-faqs.php
 *
 * Endpoints:
 *  - wp_ajax_myls_ai_faqs_get_posts_v1
 *  - wp_ajax_myls_ai_faqs_generate_v1
 *  - wp_ajax_myls_ai_faqs_insert_acf_v1
 *  - wp_ajax_myls_ai_faqs_delete_auto_acf_v1
 *
 * Generates conversion-focused FAQ HTML for selected posts, using:
 *  - on-site rendered permalink text (primary)
 *  - reputable public sources (generic service facts), optional external links
 *
 * Also produces downloadable:
 *  - .html (sanitized HTML)
 *  - .docx (Word headings/bullets – created from HTML)
 */

if ( ! defined('ABSPATH') ) exit;

/* -------------------------------------------------------------------------
 * Nonce helper (shared name with other AI endpoints)
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_check_nonce') ) {
  function myls_ai_check_nonce( string $action = 'myls_ai_ops' ) : void {
    $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : (string) ( $_REQUEST['_ajax_nonce'] ?? '' );
    if ( ! wp_verify_nonce( $nonce, $action ) ) {
      wp_send_json_error(['status'=>'error','message'=>'bad_nonce'], 403);
    }
  }
}

/* -------------------------------------------------------------------------
 * AI provider wrapper (prefer existing plugin plumbing if present)
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_generate_text') ) {
  function myls_ai_generate_text( string $prompt, array $opts = [] ) : string {

    // IMPORTANT:
    // Do NOT call myls_openai_complete() directly here.
    // myls_openai_complete() is a FILTER callback with signature ( $out, $args ).
    // Calling it directly with ($prompt, $opts) returns the prompt unchanged and skips the API call.
    // Always route through the filter so the correct OpenAI plumbing executes.

    // Filter-based fallback (allows you to swap providers)
    $out = apply_filters('myls_ai_complete', '', array_merge([
      'prompt'      => $prompt,
      'model'       => $opts['model']       ?? null,
      'max_tokens'  => $opts['max_tokens']  ?? null,
      'temperature' => $opts['temperature'] ?? null,
      'context'     => $opts['context']     ?? null,
      'post_id'     => $opts['post_id']     ?? null,
    ], $opts));

    if ( is_string($out) && trim($out) !== '' ) return $out;

    // Legacy / secondary filter
    $out2 = apply_filters('myls_ai_generate_text', '', $prompt);
    if ( is_string($out2) && trim($out2) !== '' ) return $out2;

    return '';
  }
}

/* -------------------------------------------------------------------------
 * Fetch permalink -> extract readable text
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_fetch_permalink_text') ) {
  function myls_ai_fetch_permalink_text( string $url ) : string {
    $url = trim($url);
    if ($url === '') return '';

    $resp = wp_remote_get($url, [
      'timeout' => 20,
      'headers' => [
        'User-Agent' => 'MYLS-AI-FAQS/1.0; ' . home_url('/'),
      ],
    ]);

    if ( is_wp_error($resp) ) return '';
    $code = (int) wp_remote_retrieve_response_code($resp);
    if ( $code < 200 || $code >= 300 ) return '';

    $html = (string) wp_remote_retrieve_body($resp);
    if ( $html === '' ) return '';

    // Remove scripts/styles/comments
    $html = preg_replace('#<script[^>]*>.*?</script>#si', ' ', $html);
    $html = preg_replace('#<style[^>]*>.*?</style>#si', ' ', $html);
    $html = preg_replace('#<!--.*?-->#s', ' ', $html);

    // Prefer <main> or <article>
    $main = '';
    if ( preg_match('#<main[^>]*>(.*?)</main>#si', $html, $m) ) {
      $main = (string) $m[1];
    } elseif ( preg_match('#<article[^>]*>(.*?)</article>#si', $html, $m) ) {
      $main = (string) $m[1];
    } else {
      $main = $html;
    }

    $text = wp_strip_all_tags($main);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', trim($text));

    // Cap length to keep prompts sane
    if ( strlen($text) > 20000 ) $text = substr($text, 0, 20000);

    return (string) $text;
  }
}

/* -------------------------------------------------------------------------
 * Output cleanup
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_strip_code_fences') ) {
  function myls_ai_strip_code_fences( string $s ) : string {
    $s = preg_replace('/^\s*```[a-zA-Z0-9_-]*\s*\n/u', '', $s);
    $s = preg_replace('/\n?\s*```\\s*$/u', '', $s);
    return str_replace("```", "", (string)$s);
  }
}

/* =============================================================================
 * ACF insertion/deletion for generated FAQs
 *
 * Field Group: "FAQ Schema"
 * Repeater (name: faq_items, key: field_67ecf855edf0b)
 * Subfields:
 *  - question (key: field_67ecf892edf0c)
 *  - answer   (key: field_67ecf8abedf0d)
 *
 * We track which rows were auto-inserted via post meta hashes:
 *   _myls_ai_faqs_auto_hashes (array of sha1 hashes)
 * ============================================================================= */

if ( ! function_exists('myls_ai_faqs_acf_field_map') ) {
  function myls_ai_faqs_acf_field_map() : array {

    // FIELD KEYS (best for update_field)
    $repeater_key = apply_filters('myls_ai_faqs_acf_repeater_key', 'field_67ecf855edf0b');
    $question_key = apply_filters('myls_ai_faqs_acf_question_key', 'field_67ecf892edf0c');
    $answer_key   = apply_filters('myls_ai_faqs_acf_answer_key',   'field_67ecf8abedf0d');

    // FIELD NAME (best for get_field; returns rows keyed by subfield names)
    $repeater_name = apply_filters('myls_ai_faqs_acf_repeater_name', 'faq_items');

    // Marker meta key (hash list of auto rows)
    $auto_meta_key = apply_filters('myls_ai_faqs_auto_meta_key', '_myls_ai_faqs_auto_hashes');

    return [
      'repeater_key'  => is_string($repeater_key)  ? $repeater_key  : 'field_67ecf855edf0b',
      'repeater_name' => is_string($repeater_name) ? $repeater_name : 'faq_items',
      'question_key'  => is_string($question_key)  ? $question_key  : 'field_67ecf892edf0c',
      'answer_key'    => is_string($answer_key)    ? $answer_key    : 'field_67ecf8abedf0d',
      'auto_meta_key' => is_string($auto_meta_key) ? $auto_meta_key : '_myls_ai_faqs_auto_hashes',
    ];
  }
}

if ( ! function_exists('myls_ai_faqs_acf_get_rows') ) {
  function myls_ai_faqs_acf_get_rows( int $post_id, string $repeater_name ) : array {
    if ( ! function_exists('get_field') ) return [];
    $rows = get_field($repeater_name, $post_id);
    return is_array($rows) ? $rows : [];
  }
}

if ( ! function_exists('myls_ai_faqs_acf_update_rows') ) {
  /**
   * Update repeater rows using FIELD KEY (recommended for reliability).
   * $rows_keyed must be formatted with SUBFIELD KEYS.
   */
  function myls_ai_faqs_acf_update_rows( int $post_id, string $repeater_key, array $rows_keyed ) : bool {
    if ( ! function_exists('update_field') ) return false;
    return (bool) update_field($repeater_key, $rows_keyed, $post_id);
  }
}

if ( ! function_exists('myls_ai_faqs_hash_row') ) {
  function myls_ai_faqs_hash_row( string $q, string $a ) : string {
    $q = strtolower(trim(preg_replace('/\s+/u',' ', (string)$q)));
    $a = strtolower(trim(preg_replace('/\s+/u',' ', (string)$a)));
    return sha1($q . '|' . $a);
  }
}

/**
 * Extract Q/A pairs from generated HTML.
 * Expected format:
 *   <p><strong>Question:</strong> ... <strong>Answer:</strong> ...</p>
 */
if ( ! function_exists('myls_ai_faqs_extract_pairs') ) {
  function myls_ai_faqs_extract_pairs( string $html ) : array {
    $html = (string) $html;
    if ($html === '') return [];

    $norm = str_replace(["\r\n","\r"], "\n", $html);
    $norm = preg_replace('/\s+/u', ' ', $norm);

    $pairs = [];

    /* --------------------------------------------------------------------
     * NEW FORMAT (v2):
     * <h3>Question</h3>
     * <p>...</p>
     * <ul><li>...</li></ul>
     * ...
     * Stops at next <h3> or <h2>Sources</h2>
     * -------------------------------------------------------------------- */
    if (stripos($norm, '<h3') !== false) {
      $doc = new DOMDocument();
      $prev = libxml_use_internal_errors(true);
      // Wrap in a body so DOMDocument can parse fragments.
      $doc->loadHTML('<?xml encoding="utf-8" ?><body>' . $html . '</body>');
      libxml_clear_errors();
      libxml_use_internal_errors($prev);

      $body = $doc->getElementsByTagName('body')->item(0);
      if ($body) {
        $children = [];
        foreach ($body->childNodes as $n) {
          if ($n->nodeType === XML_ELEMENT_NODE) $children[] = $n;
        }

        $count = count($children);
        for ($i = 0; $i < $count; $i++) {
          $node = $children[$i];
          $tag  = strtolower($node->nodeName);

          // Stop when Sources section begins.
          if ($tag === 'h2') {
            $t = trim($node->textContent ?? '');
            if (strcasecmp($t, 'Sources') === 0) break;
          }

          if ($tag !== 'h3') continue;

          $q = trim((string)$node->textContent);
          $q = html_entity_decode($q, ENT_QUOTES | ENT_HTML5, 'UTF-8');
          $q = trim(wp_strip_all_tags($q));
          if ($q === '') continue;

          // Collect answer blocks until the next h3 or the Sources h2.
          $answer_html = '';
          for ($j = $i + 1; $j < $count; $j++) {
            $n2 = $children[$j];
            $t2 = strtolower($n2->nodeName);

            if ($t2 === 'h3') break;
            if ($t2 === 'h2') {
              $tt = trim($n2->textContent ?? '');
              if (strcasecmp($tt, 'Sources') === 0) { $j = $count; break; }
            }

            // Keep only allowed tags (p/ul/li/strong/em/a) in the stored answer.
            if (in_array($t2, ['p','ul','li','strong','em','a'], true)) {
              $answer_html .= $doc->saveHTML($n2);
            }
          }

          // Build a plain text variant for hashing.
          $a_plain = wp_strip_all_tags($answer_html, true);
          $a_plain = html_entity_decode($a_plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
          $a_plain = str_replace(["\xC2\xA0", '&nbsp;'], ' ', (string)$a_plain);
          $a_plain = preg_replace('/\s+/u', ' ', (string)$a_plain);
          $a_plain = trim((string)$a_plain);

          if ($a_plain === '') continue;

          $pairs[] = [
            'question'     => $q,
            'answer'       => $a_plain,
            'answer_html'  => $answer_html,
            'hash'         => myls_ai_faqs_hash_row($q, $a_plain),
          ];
        }
      }
    }

    /* --------------------------------------------------------------------
     * LEGACY FORMAT (v1): One-line or two-line <p><strong>Question...</p>
     * -------------------------------------------------------------------- */
    if (empty($pairs)) {
      // 1) Combined format
      $re_one = '#<p[^>]*>\s*(?:<strong>)?\s*Question\s*:\s*(?:</strong>)?\s*(.*?)\s*(?:<strong>)?\s*Answer\s*:\s*(?:</strong>)?\s*(.*?)\s*</p>#i';
      if (preg_match_all($re_one, $norm, $m1, PREG_SET_ORDER)) {
        foreach ($m1 as $row) {
          $q_raw = (string) ($row[1] ?? '');
          $a_raw = (string) ($row[2] ?? '');
          $q = trim(wp_strip_all_tags(html_entity_decode($q_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
          $a = trim(wp_strip_all_tags(html_entity_decode($a_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
          if ($q === '' || $a === '') continue;
          $pairs[] = [
            'question'    => $q,
            'answer'      => $a,
            'answer_html' => '',
            'hash'        => myls_ai_faqs_hash_row($q, $a),
          ];
        }
      }
    }

    // 2) Two-line format fallback
    if (empty($pairs)) {
      $re_q = '#<p[^>]*>\s*(?:<strong>)?\s*(?:Question|Q)\s*:\s*(?:</strong>)?\s*(.*?)\s*</p>#i';
      $re_a = '#<p[^>]*>\s*(?:<strong>)?\s*(?:Answer|A)\s*:\s*(?:</strong>)?\s*(.*?)\s*</p>#i';

      $re_all = '#<p[^>]*>.*?</p>#i';
      if (preg_match_all($re_all, $norm, $blocks)) {
        $pending_q = '';
        foreach ((array) ($blocks[0] ?? []) as $p) {
          if ($pending_q === '' && preg_match($re_q, $p, $mq)) {
            $pending_q = trim(wp_strip_all_tags(html_entity_decode((string) ($mq[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            continue;
          }
          if ($pending_q !== '' && preg_match($re_a, $p, $ma)) {
            $a = trim(wp_strip_all_tags(html_entity_decode((string) ($ma[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($pending_q !== '' && $a !== '') {
              $pairs[] = [
                'question'    => $pending_q,
                'answer'      => $a,
                'answer_html' => '',
                'hash'        => myls_ai_faqs_hash_row($pending_q, $a),
              ];
            }
            $pending_q = '';
          }
        }
      }
    }

    return $pairs;
  }
}

/* =============================================================================
 * AJAX: Insert generated FAQs into ACF repeater
 * Action: myls_ai_faqs_insert_acf_v1
 * Expects:
 *   - post_id
 *   - html
 *   - replace_existing (optional "1")
 * ============================================================================= */
add_action('wp_ajax_myls_ai_faqs_insert_acf_v1', function(){

  myls_ai_check_nonce();

  $post_id = (int) ($_POST['post_id'] ?? 0);
  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'cap_denied'], 403);
  }
  if ( ! function_exists('update_field') || ! function_exists('get_field') ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'acf_not_active'], 400);
  }

  $html    = (string) wp_unslash($_POST['html'] ?? '');
  $replace = ! empty($_POST['replace_existing']);

  $pairs = myls_ai_faqs_extract_pairs($html);
  if ( empty($pairs) ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'missing_faqs_for_acf'], 400);
  }

  $map = myls_ai_faqs_acf_field_map();

  $rep_key    = (string) $map['repeater_key'];   // update_field expects key
  $rep_name   = (string) $map['repeater_name'];  // get_field expects name
  $q_key      = (string) $map['question_key'];
  $a_key      = (string) $map['answer_key'];
  $marker_key = (string) $map['auto_meta_key'];

  // Read existing rows by FIELD NAME (rows keyed by subfield names)
  $existing_by_name = $replace ? [] : myls_ai_faqs_acf_get_rows($post_id, $rep_name);

  // Dedupe hashes from existing rows
  $seen = [];
  foreach ($existing_by_name as $row) {
    if (!is_array($row)) continue;
    $q = isset($row['question']) ? (string) $row['question'] : '';
    $a = isset($row['answer'])   ? (string) $row['answer']   : '';
    if (trim($q) === '' || trim($a) === '') continue;
    $seen[myls_ai_faqs_hash_row($q, $a)] = true;
  }

  // Convert existing rows to KEYED format for update_field(field_key,...)
  $rows_keyed = [];
  foreach ($existing_by_name as $row) {
    if (!is_array($row)) continue;
    $q = isset($row['question']) ? (string) $row['question'] : '';
    $a = isset($row['answer'])   ? (string) $row['answer']   : '';
    if (trim($q) === '' && trim($a) === '') continue;

    $rows_keyed[] = [
      $q_key => sanitize_text_field($q),
      $a_key => wp_kses_post($a),
    ];
  }

  // Marker hashes used for bulk delete
  $auto_hashes = get_post_meta($post_id, $marker_key, true);
  if ( ! is_array($auto_hashes) ) $auto_hashes = [];

  $inserted = 0;
  $skipped  = 0;

  foreach ($pairs as $pair) {
    $q = (string) ($pair['question'] ?? '');
    $a = (string) ($pair['answer'] ?? '');
    $h = (string) ($pair['hash'] ?? '');

    if ($h === '') $h = myls_ai_faqs_hash_row($q, $a);
    if ($h === '' || isset($seen[$h])) { $skipped++; continue; }

    $rows_keyed[] = [
      $q_key => sanitize_text_field($q),
      $a_key => wp_kses_post($a),
    ];

    $seen[$h] = true;
    $auto_hashes[] = $h;
    $inserted++;
  }

  $auto_hashes = array_values(array_unique(array_filter($auto_hashes)));
  update_post_meta($post_id, $marker_key, $auto_hashes);

  $ok = myls_ai_faqs_acf_update_rows($post_id, $rep_key, $rows_keyed);
  if ( ! $ok ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'acf_update_failed'], 500);
  }

  wp_send_json_success([
    'marker'         => 'faqs',
    'status'         => 'ok',
    'post_id'        => $post_id,
    'repeater_key'   => $rep_key,
    'repeater_name'  => $rep_name,
    'inserted_count' => $inserted,
    'skipped_count'  => $skipped,
    'total_rows'     => count($rows_keyed),
  ]);
});

/* =============================================================================
 * AJAX: Delete auto-generated FAQs from ACF repeater
 * Action: myls_ai_faqs_delete_auto_acf_v1
 * ============================================================================= */
add_action('wp_ajax_myls_ai_faqs_delete_auto_acf_v1', function(){

  myls_ai_check_nonce();

  $post_id = (int) ($_POST['post_id'] ?? 0);
  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'cap_denied'], 403);
  }
  if ( ! function_exists('update_field') || ! function_exists('get_field') ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'acf_not_active'], 400);
  }

  $map = myls_ai_faqs_acf_field_map();

  $rep_key    = (string) $map['repeater_key'];
  $rep_name   = (string) $map['repeater_name'];
  $q_key      = (string) $map['question_key'];
  $a_key      = (string) $map['answer_key'];
  $marker_key = (string) $map['auto_meta_key'];

  // Read existing rows by FIELD NAME
  $rows_by_name = myls_ai_faqs_acf_get_rows($post_id, $rep_name);

  // Load marker hashes
  $auto_hashes = get_post_meta($post_id, $marker_key, true);
  if ( ! is_array($auto_hashes) ) $auto_hashes = [];
  $auto_hashes = array_flip(array_values(array_unique(array_filter($auto_hashes))));

  $kept_keyed = [];
  $deleted    = 0;

  foreach ($rows_by_name as $row) {
    if ( ! is_array($row) ) continue;

    $q = isset($row['question']) ? (string) $row['question'] : '';
    $a = isset($row['answer'])   ? (string) $row['answer']   : '';

    $h = (trim($q) !== '' || trim($a) !== '') ? myls_ai_faqs_hash_row($q, $a) : '';

    if ($h && isset($auto_hashes[$h])) {
      $deleted++;
      continue;
    }

    $kept_keyed[] = [
      $q_key => sanitize_text_field($q),
      $a_key => wp_kses_post($a),
    ];
  }

  $ok = myls_ai_faqs_acf_update_rows($post_id, $rep_key, $kept_keyed);
  if ( ! $ok ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'acf_update_failed'], 500);
  }

  // Clear marker meta after delete to avoid stale hashes
  delete_post_meta($post_id, $marker_key);

  wp_send_json_success([
    'marker'        => 'faqs',
    'status'        => 'ok',
    'post_id'       => $post_id,
    'deleted_count' => $deleted,
    'total_rows'    => count($kept_keyed),
  ]);
});

/* -------------------------------------------------------------------------
 * Save downloadable files (.html + .docx)
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_faqs_save_html') ) {
  function myls_ai_faqs_save_html( int $post_id, string $title, string $permalink, string $html ) : string {
    $uploads = wp_upload_dir();
    if ( empty($uploads['basedir']) || empty($uploads['baseurl']) ) return '';

    $dir = trailingslashit($uploads['basedir']) . 'myls-ai-faqs/';
    if ( ! file_exists($dir) ) wp_mkdir_p($dir);

    $safe = sanitize_title($title ?: 'faqs');
    $filename = sprintf('%s-%d-%s.html', $safe, $post_id, date('Ymd-His'));
    $path = $dir . $filename;

    $charset = get_bloginfo('charset') ?: 'UTF-8';
    $doc  = "<!doctype html>\n<html>\n<head>\n<meta charset=\"".esc_attr($charset)."\">\n";
    $doc .= "<title>".esc_html($title)."</title>\n</head>\n<body>\n";
    $doc .= "<h1>".esc_html($title)."</h1>\n<p><strong>Permalink:</strong> ".esc_html($permalink)."</p>\n<hr/>\n";
    $doc .= $html . "\n</body>\n</html>";

    file_put_contents($path, $doc);

    return trailingslashit($uploads['baseurl']) . 'myls-ai-faqs/' . $filename;
  }
}

/* ---------------- DOCX (minimal, from HTML) ---------------- */

if ( ! function_exists('myls_ai_faqs_docx_escape') ) {
  function myls_ai_faqs_docx_escape( string $s ) : string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
  }
}

if ( ! function_exists('myls_ai_faqs_docx_p') ) {
  function myls_ai_faqs_docx_p( string $text, string $style = '', bool $bold = false ) : string {
    $t = myls_ai_faqs_docx_escape($text);
    $pPr = '';
    if ($style !== '') {
      $s = myls_ai_faqs_docx_escape($style);
      $pPr = "<w:pPr><w:pStyle w:val=\"{$s}\"/></w:pPr>";
    }
    $rPr = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
    return "<w:p>{$pPr}<w:r>{$rPr}<w:t xml:space=\"preserve\">{$t}</w:t></w:r></w:p>";
  }
}

if ( ! function_exists('myls_ai_faqs_html_to_lines') ) {
  /**
   * Naive HTML->lines conversion suitable for our minimal DOCX:
   * - h2 -> ## heading
   * - h3 -> ### heading
   * - li -> - item
   * - p -> paragraph
   */
  function myls_ai_faqs_html_to_lines( string $html ) : array {
    $html = (string)$html;

    $replacements = [
      '#<h1[^>]*>(.*?)</h1>#si' => "\n# $1\n",
      '#<h2[^>]*>(.*?)</h2>#si' => "\n## $1\n",
      '#<h3[^>]*>(.*?)</h3>#si' => "\n### $1\n",
      '#<li[^>]*>(.*?)</li>#si' => "\n- $1\n",
      '#</p>\s*<#si'            => "</p>\n<",
    ];
    foreach ($replacements as $pat => $rep) {
      $html = preg_replace($pat, $rep, $html);
    }

    $html = preg_replace('#<p[^>]*>(.*?)</p>#si', "\n$1\n", $html);

    $text = wp_strip_all_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    $text = trim($text);

    return preg_split("/\r\n|\n|\r/", $text);
  }
}

if ( ! function_exists('myls_ai_faqs_docx_content_types_xml') ) {
  function myls_ai_faqs_docx_content_types_xml() : string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
  }
}

if ( ! function_exists('myls_ai_faqs_docx_rels_xml') ) {
  function myls_ai_faqs_docx_rels_xml() : string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
  }
}

if ( ! function_exists('myls_ai_faqs_docx_document_rels_xml') ) {
  function myls_ai_faqs_docx_document_rels_xml() : string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>
XML;
  }
}

if ( ! function_exists('myls_ai_faqs_docx_core_xml') ) {
  function myls_ai_faqs_docx_core_xml( string $title ) : string {
    $t = myls_ai_faqs_docx_escape($title ?: 'Document');
    $created = gmdate('Y-m-d\TH:i:s\Z');
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
 xmlns:dc="http://purl.org/dc/elements/1.1/"
 xmlns:dcterms="http://purl.org/dc/terms/"
 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>{$t}</dc:title>
  <dc:creator>MYLS</dc:creator>
  <cp:lastModifiedBy>MYLS</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">{$created}</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">{$created}</dcterms:modified>
</cp:coreProperties>
XML;
  }
}

if ( ! function_exists('myls_ai_faqs_docx_app_xml') ) {
  function myls_ai_faqs_docx_app_xml() : string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
 xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>MYLS</Application>
</Properties>
XML;
  }
}

if ( ! function_exists('myls_ai_faqs_docx_document_xml') ) {
  function myls_ai_faqs_docx_document_xml( array $lines ) : string {
    $paras = [];

    foreach ($lines as $line) {
      $line = trim((string)$line);

      if ($line === '') {
        $paras[] = myls_ai_faqs_docx_p('');
        continue;
      }

      if (strpos($line, '### ') === 0) {
        $paras[] = myls_ai_faqs_docx_p(substr($line, 4), 'Heading3');
        continue;
      }
      if (strpos($line, '## ') === 0) {
        $paras[] = myls_ai_faqs_docx_p(substr($line, 3), 'Heading2');
        continue;
      }
      if (strpos($line, '# ') === 0) {
        $paras[] = myls_ai_faqs_docx_p(substr($line, 2), 'Heading1', true);
        continue;
      }
      if (strpos($line, '- ') === 0) {
        $paras[] = myls_ai_faqs_docx_p('• ' . substr($line, 2));
        continue;
      }

      $paras[] = myls_ai_faqs_docx_p($line);
    }

    $body = implode("\n", $paras);

    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    {$body}
    <w:sectPr>
      <w:pgSz w:w="12240" w:h="15840"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/>
    </w:sectPr>
  </w:body>
</w:document>
XML;
  }
}

if ( ! function_exists('myls_ai_faqs_docx_write') ) {
  function myls_ai_faqs_docx_write( string $filepath, array $lines ) : bool {
    if ( ! class_exists('ZipArchive') ) return false;

    $zip = new ZipArchive();
    if ( $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true ) return false;

    $zip->addFromString('[Content_Types].xml', myls_ai_faqs_docx_content_types_xml());
    $zip->addFromString('_rels/.rels', myls_ai_faqs_docx_rels_xml());
    $zip->addFromString('docProps/core.xml', myls_ai_faqs_docx_core_xml('FAQs'));
    $zip->addFromString('docProps/app.xml', myls_ai_faqs_docx_app_xml());
    $zip->addFromString('word/_rels/document.xml.rels', myls_ai_faqs_docx_document_rels_xml());
    $zip->addFromString('word/document.xml', myls_ai_faqs_docx_document_xml($lines));

    $zip->close();
    return true;
  }
}

if ( ! function_exists('myls_ai_faqs_save_docx') ) {
  function myls_ai_faqs_save_docx( int $post_id, string $title, string $html ) : string {
    if ( ! class_exists('ZipArchive') ) return '';

    $uploads = wp_upload_dir();
    if ( empty($uploads['basedir']) || empty($uploads['baseurl']) ) return '';

    $dir = trailingslashit($uploads['basedir']) . 'myls-ai-faqs/';
    if ( ! file_exists($dir) ) wp_mkdir_p($dir);

    $safe = sanitize_title($title ?: 'faqs');
    $filename = sprintf('%s-%d-%s.docx', $safe, $post_id, date('Ymd-His'));
    $path = $dir . $filename;

    $lines = myls_ai_faqs_html_to_lines($html);
    $ok = myls_ai_faqs_docx_write($path, $lines);
    if ( ! $ok ) return '';

    return trailingslashit($uploads['baseurl']) . 'myls-ai-faqs/' . $filename;
  }
}

/* -------------------------------------------------------------------------
 * Endpoint: Get posts for a post type (includes drafts)
 * ------------------------------------------------------------------------- */
add_action('wp_ajax_myls_ai_faqs_get_posts_v1', function(){
  myls_ai_check_nonce();

  $pt = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'page';
  if ( ! post_type_exists($pt) ) {
    wp_send_json_error(['status'=>'error','message'=>'invalid_post_type'], 400);
  }

  $ptype_obj = get_post_type_object($pt);
  $cap = $ptype_obj && isset($ptype_obj->cap->edit_posts) ? $ptype_obj->cap->edit_posts : 'edit_posts';
  if ( ! current_user_can($cap) ) {
    wp_send_json_error(['status'=>'error','message'=>'cap_denied'], 403);
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

  wp_send_json_success(['status'=>'ok','posts'=>$out]);
});

/* -------------------------------------------------------------------------
 * Endpoint: Generate FAQs HTML
 * ------------------------------------------------------------------------- */
add_action('wp_ajax_myls_ai_faqs_generate_v1', function(){
  myls_ai_check_nonce();

  $post_id     = (int) ($_POST['post_id'] ?? 0);
  $allow_links = ! empty($_POST['allow_links']);
  $variant     = isset($_POST['variant']) ? strtoupper( sanitize_text_field( wp_unslash($_POST['variant']) ) ) : 'LONG';
  if ( $variant !== 'SHORT' ) $variant = 'LONG';

  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['status'=>'error','message'=>'cap_denied'], 403);
  }

  $p = get_post($post_id);
  if ( ! $p ) {
    wp_send_json_error(['status'=>'error','message'=>'post_not_found'], 404);
  }

  $title = get_the_title($post_id);
  $url   = get_permalink($post_id);

  // Template: prefer request, then v2 option, then legacy option, then built-in safe default.
  // v2 supports LONG/SHORT variants + multi-block answers with <h3> + bullets.
  $template = isset($_POST['template']) ? wp_unslash((string)$_POST['template']) : '';
  if ( trim($template) === '' ) $template = (string) get_option('myls_ai_faqs_prompt_template_v2', '');
  if ( trim($template) === '' ) $template = (string) get_option('myls_ai_faqs_prompt_template', '');

  if ( trim($template) === '' ) {
    $template = "Create FAQs for {{TITLE}} using {{PAGE_TEXT}}. Output clean HTML: <h2>FAQs</h2> then 10 FAQs with <h3>Question</h3> and multi-paragraph answers plus a <ul> checklist, then <h2>Sources</h2> list.";
  }

  // If the provided template is the old one-line format, auto-upgrade it for LONG output.
  // This prevents 'LONG' from still producing single-line Q/A when old templates are saved.
  $is_legacy_one_line = (stripos($template, 'ONE LINE') !== false) || (stripos($template, '<strong>Question') !== false && stripos($template, '<strong>Answer') !== false);
  if ( $is_legacy_one_line && $variant === 'LONG' ) {
    $maybe_v2 = (string) get_option('myls_ai_faqs_prompt_template_v2', '');
    if ( trim($maybe_v2) !== '' ) {
      $template = $maybe_v2;
    }
  }

  // Permalink-based page text
  $page_text = myls_ai_fetch_permalink_text((string)$url);
  if ( $page_text === '' ) {
    $page_text = preg_replace('/\s+/u',' ', trim( wp_strip_all_tags( (string) $p->post_content ) ));
  }

  // Populate prompt vars
  $prompt = str_replace(
    ['{{TITLE}}','{{URL}}','{{PAGE_TEXT}}','{{ALLOW_LINKS}}','{{VARIANT}}'],
    [$title, $url, $page_text, $allow_links ? 'YES' : 'NO', $variant],
    $template
  );

  // Params (fallback to options)
  $tokens = max(1, (int) ($_POST['tokens'] ?? (int) get_option('myls_ai_faqs_tokens', 1200)));
  $temp   = (float) ($_POST['temperature'] ?? (float) get_option('myls_ai_faqs_temperature', 0.4));
  $model  = isset($_POST['model']) && is_string($_POST['model']) ? trim($_POST['model']) : '';

  $ai = myls_ai_generate_text($prompt, [
    'model'       => $model ?: null,
    'max_tokens'  => $tokens,
    'temperature' => $temp,
    'context'     => 'faqs_generate',
    'post_id'     => $post_id,
  ]);

  $raw = myls_ai_strip_code_fences((string)$ai);

  // Sanitize output HTML
  $allowed = [
    'h2'     => [],
    'h3'     => [],
    'p'      => [],
    'ul'     => [],
    'li'     => [],
    'strong' => [],
    'em'     => [],
  ];
  if ( $allow_links ) {
    $allowed['a'] = ['href'=>true, 'target'=>true, 'rel'=>true, 'title'=>true];
  }

  // Remove any style/class/data-* attributes if model adds them
  $raw = preg_replace('/\s+(style|class|data-[a-z0-9_-]+)="[^"]*"/i', '', $raw);

  // If links are not allowed, strip <a> tags entirely
  if ( ! $allow_links ) {
    $raw = preg_replace('#</?a\b[^>]*>#i', '', $raw);
  } else {
    // Ensure rel noopener on any external links
    $raw = preg_replace_callback('#<a\b([^>]*)>#i', function($m){
      $tag = $m[0];
      if ( stripos($tag, 'rel=') === false ) {
        $tag = rtrim(substr($tag,0,-1)) . ' rel="noopener">';
      }
      return $tag;
    }, $raw);
  }

  $clean = wp_kses($raw, $allowed);

  // Save downloads
  $html_url = myls_ai_faqs_save_html($post_id, (string)$title, (string)$url, (string)$clean);
  $doc_url  = myls_ai_faqs_save_docx($post_id, (string)$title, (string)$clean);

  wp_send_json_success([
    'status'   => 'ok',
    'post_id'  => (int) $post_id,
    'title'    => (string) $title,
    'url'      => (string) $url,
    'html'     => (string) $clean,
    'raw'      => (string) $raw,
    'html_url' => (string) $html_url,
    'doc_url'  => (string) $doc_url,
  ]);
});

/* =============================================================================
 * MYLS (Native) insertion/deletion for generated FAQs
 *
 * Stores FAQs in:
 *   _myls_faq_items = [ ['q' => string, 'a' => string(HTML)], ... ]
 *
 * We track which rows were auto-inserted via post meta hashes:
 *   _myls_ai_faqs_auto_hashes_myls (array of sha1 hashes)
 * ============================================================================= */

if ( ! function_exists('myls_ai_faqs_myls_auto_meta_key') ) {
  function myls_ai_faqs_myls_auto_meta_key() : string {
    return (string) apply_filters('myls_ai_faqs_myls_auto_meta_key', '_myls_ai_faqs_auto_hashes_myls');
  }
}

if ( ! function_exists('myls_ai_faqs_myls_get_items') ) {
  function myls_ai_faqs_myls_get_items( int $post_id ) : array {
    $items = get_post_meta($post_id, '_myls_faq_items', true);
    return is_array($items) ? $items : [];
  }
}

if ( ! function_exists('myls_ai_faqs_myls_normalize_items') ) {
  /**
   * Normalize existing MYLS FAQ items into safe shape.
   */
  function myls_ai_faqs_myls_normalize_items( array $items ) : array {
    $out = [];
    foreach ($items as $row) {
      if ( ! is_array($row) ) continue;
      $q = isset($row['q']) ? sanitize_text_field((string)$row['q']) : '';
      $a = isset($row['a']) ? wp_kses_post((string)$row['a']) : '';
      if ( trim($q) === '' && trim(wp_strip_all_tags($a)) === '' ) continue;
      $out[] = [ 'q' => $q, 'a' => $a ];
    }
    return $out;
  }
}

if ( ! function_exists('myls_ai_faqs_myls_hash_existing') ) {
  /**
   * Build a hash set for existing MYLS FAQs.
   * We hash question + plain-text answer so it matches the extraction hash.
   */
  function myls_ai_faqs_myls_hash_existing( array $items ) : array {
    $seen = [];
    foreach ($items as $row) {
      if ( ! is_array($row) ) continue;
      $q = isset($row['q']) ? (string)$row['q'] : '';
      $a = isset($row['a']) ? (string)$row['a'] : '';
      $q = trim(wp_strip_all_tags($q));
      $a_plain = trim(wp_strip_all_tags($a));
      if ($q === '' || $a_plain === '') continue;
      $seen[myls_ai_faqs_hash_row($q, $a_plain)] = true;
    }
    return $seen;
  }
}

if ( ! function_exists('myls_ai_faqs_myls_answer_to_html') ) {
  function myls_ai_faqs_myls_answer_to_html( string $answer_plain ) : string {
    $answer_plain = trim((string)$answer_plain);
    if ($answer_plain === '') return '';
    // Store as HTML for wp_editor; keep it clean.
    $answer_plain = wp_strip_all_tags($answer_plain);
    return wpautop(esc_html($answer_plain));
  }
}

/* =============================================================================
 * AJAX: Insert generated FAQs into MYLS native structure
 * Action: myls_ai_faqs_insert_myls_v1
 * Expects:
 *   - post_id
 *   - html
 *   - replace_existing (optional "1")
 * ============================================================================= */
add_action('wp_ajax_myls_ai_faqs_insert_myls_v1', function(){

  myls_ai_check_nonce();

  $post_id = (int) ($_POST['post_id'] ?? 0);
  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'cap_denied'], 403);
  }

  $html    = (string) wp_unslash($_POST['html'] ?? '');
  $replace = ! empty($_POST['replace_existing']);

  $pairs = myls_ai_faqs_extract_pairs($html);
  if ( empty($pairs) ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'missing_faqs_for_myls'], 400);
  }

  $existing = $replace ? [] : myls_ai_faqs_myls_get_items($post_id);
  $existing = myls_ai_faqs_myls_normalize_items($existing);

  $seen = myls_ai_faqs_myls_hash_existing($existing);

  $auto_key = myls_ai_faqs_myls_auto_meta_key();
  $auto_hashes = get_post_meta($post_id, $auto_key, true);
  if ( ! is_array($auto_hashes) ) $auto_hashes = [];

  $inserted = 0;
  $skipped  = 0;

  foreach ($pairs as $pair) {
    $q = (string) ($pair['question'] ?? '');
    $a_plain = (string) ($pair['answer'] ?? '');
    $a_html  = (string) ($pair['answer_html'] ?? '');
    $h = (string) ($pair['hash'] ?? '');

    $q = trim(wp_strip_all_tags($q));
    $a_plain = html_entity_decode($a_plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $a_plain = str_replace(["\xC2\xA0", '&nbsp;'], ' ', (string)$a_plain);
    $a_plain = preg_replace('/\s+/u', ' ', (string)$a_plain);
    $a_plain = trim(wp_strip_all_tags((string)$a_plain));

    // Prefer HTML answer (preserve bullets/steps) when available.
    $answer_to_store = '';
    if ( $a_html !== '' ) {
      $answer_to_store = wp_kses_post($a_html);
    } else {
      // Legacy path: we only have plain text; convert to simple paragraph HTML.
      $answer_to_store = wp_kses_post( myls_ai_faqs_myls_answer_to_html( (string)$a_plain ) );
    }

    if ($q === '' || $a_plain === '') { $skipped++; continue; }

    if ($h === '') $h = myls_ai_faqs_hash_row($q, $a_plain);
    if ($h === '' || isset($seen[$h])) { $skipped++; continue; }

    $existing[] = [
      'q' => sanitize_text_field($q),
      'a' => $answer_to_store,
    ];

    $seen[$h] = true;
    $auto_hashes[] = $h;
    $inserted++;
  }

  $existing = array_values($existing);
  $auto_hashes = array_values(array_unique(array_filter($auto_hashes)));

  if ( empty($existing) ) {
    delete_post_meta($post_id, '_myls_faq_items');
  } else {
    update_post_meta($post_id, '_myls_faq_items', $existing);
  }

  update_post_meta($post_id, $auto_key, $auto_hashes);

  wp_send_json_success([
    'marker'         => 'faqs',
    'status'         => 'ok',
    'post_id'        => $post_id,
    'inserted_count' => $inserted,
    'skipped_count'  => $skipped,
    'total_rows'     => count($existing),
  ]);
});

/* =============================================================================
 * AJAX: Check whether a post already has MYLS FAQs
 * Action: myls_ai_faqs_check_existing_myls_v1
 * Expects:
 *   - post_id
 * Returns:
 *   - has_faqs (bool)
 *   - count (int)
 * ============================================================================= */
add_action('wp_ajax_myls_ai_faqs_check_existing_myls_v1', function(){

  myls_ai_check_nonce();

  $post_id = (int) ($_POST['post_id'] ?? 0);
  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'cap_denied'], 403);
  }

  $items = myls_ai_faqs_myls_get_items($post_id);
  $items = myls_ai_faqs_myls_normalize_items(is_array($items) ? $items : []);
  $count = count($items);

  wp_send_json_success([
    'marker'   => 'faqs',
    'status'   => 'ok',
    'post_id'  => $post_id,
    'has_faqs' => $count > 0,
    'count'    => $count,
  ]);
});

/* =============================================================================
 * AJAX: Delete auto-generated FAQs from MYLS native structure
 * Action: myls_ai_faqs_delete_auto_myls_v1
 * ============================================================================= */
add_action('wp_ajax_myls_ai_faqs_delete_auto_myls_v1', function(){

  myls_ai_check_nonce();

  $post_id = (int) ($_POST['post_id'] ?? 0);
  if ( $post_id <= 0 || get_post_status($post_id) === false ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'bad_post'], 400);
  }
  if ( ! current_user_can('edit_post', $post_id) ) {
    wp_send_json_error(['marker'=>'faqs','status'=>'error','message'=>'cap_denied'], 403);
  }

  $items = myls_ai_faqs_myls_normalize_items(myls_ai_faqs_myls_get_items($post_id));
  if ( empty($items) ) {
    wp_send_json_success([
      'marker'        => 'faqs',
      'status'        => 'ok',
      'post_id'       => $post_id,
      'deleted_count' => 0,
      'total_rows'    => 0,
    ]);
  }

  $auto_key = myls_ai_faqs_myls_auto_meta_key();
  $auto_hashes = get_post_meta($post_id, $auto_key, true);
  if ( ! is_array($auto_hashes) ) $auto_hashes = [];
  $auto_hashes = array_flip(array_values(array_unique(array_filter($auto_hashes))));

  $kept = [];
  $deleted = 0;

  foreach ($items as $row) {
    $q = isset($row['q']) ? (string)$row['q'] : '';
    $a_plain = isset($row['a']) ? (string)$row['a'] : '';

    // IMPORTANT: Normalize exactly like insert (whitespace + nbsp handling),
    // otherwise hashes won't match and delete-auto will remove 0 rows.
    $q = trim(wp_strip_all_tags((string)$q));
    $q = html_entity_decode((string)$q, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $q = str_replace(["\xC2\xA0", '&nbsp;'], ' ', (string)$q);
    $q = preg_replace('/\s+/u', ' ', (string)$q);
    $q = trim((string)$q);

    $a_plain = html_entity_decode((string)$a_plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $a_plain = str_replace(["\xC2\xA0", '&nbsp;'], ' ', (string)$a_plain);
    $a_plain = preg_replace('/\s+/u', ' ', (string)$a_plain);
    $a_plain = trim(wp_strip_all_tags((string)$a_plain));

    $h = (trim($q) !== '' || $a_plain !== '') ? myls_ai_faqs_hash_row($q, $a_plain) : '';

    if ($h !== '' && isset($auto_hashes[$h])) {
      $deleted++;
      continue;
    }

    $kept[] = [
      'q' => sanitize_text_field($q),
      'a' => wp_kses_post((string)($row['a'] ?? '')),
    ];
  }

  if ( empty($kept) ) {
    delete_post_meta($post_id, '_myls_faq_items');
  } else {
    update_post_meta($post_id, '_myls_faq_items', array_values($kept));
  }

  // Clear marker hashes once we've applied deletion.
  delete_post_meta($post_id, $auto_key);

  wp_send_json_success([
    'marker'        => 'faqs',
    'status'        => 'ok',
    'post_id'       => $post_id,
    'deleted_count' => $deleted,
    'total_rows'    => count($kept),
  ]);
});
