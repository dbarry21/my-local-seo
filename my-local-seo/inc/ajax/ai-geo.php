<?php
/**
 * My Local SEO – AI AJAX: GEO
 * File: inc/ajax/ai-geo.php
 *
 * v4:
 * - DOCX renders like Preview (HTML converted to Word paragraphs/headings/bullets)
 * - Generates a clean .html download for copy/paste into WP editor
 */

if ( ! defined('ABSPATH') ) exit;

/** Nonce helper */
if ( ! function_exists('myls_ai_check_nonce') ) {
  function myls_ai_check_nonce( string $action = 'myls_ai_ops' ) : void {
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : ( $_REQUEST['_ajax_nonce'] ?? '' );
    if ( ! wp_verify_nonce( $nonce, $action ) ) {
      wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'bad_nonce'], 403);
    }
  }
}

/** AI provider wrapper */
if ( ! function_exists('myls_ai_generate_text') ) {
  function myls_ai_generate_text( string $prompt, array $opts = [] ) : string {
    if ( function_exists('myls_openai_complete') ) {
      $out = myls_openai_complete($prompt, $opts);
      return is_string($out) ? $out : '';
    }

    $out = apply_filters('myls_ai_complete', '', array_merge([
      'prompt'      => $prompt,
      'model'       => $opts['model']       ?? null,
      'max_tokens'  => $opts['max_tokens']  ?? null,
      'temperature' => $opts['temperature'] ?? null,
      'context'     => $opts['context']     ?? null,
      'post_id'     => $opts['post_id']     ?? null,
    ], $opts));

    if ( is_string($out) && trim($out) !== '' ) return $out;

    $out2 = apply_filters('myls_ai_generate_text', '', $prompt);
    if ( is_string($out2) && trim($out2) !== '' ) return $out2;

    return '';
  }
}

/** Strip code fences */
if ( ! function_exists('myls_geo_strip_code_fences') ) {
  function myls_geo_strip_code_fences( string $s ) : string {
    $s = preg_replace('/^\s*```[a-zA-Z0-9_-]*\s*\n/u', '', $s);
    $s = preg_replace('/\n?\s*```\\s*$/u', '', $s);
    return str_replace("```", "", (string)$s);
  }
}

/** Hero/body split (unchanged) */
if ( ! function_exists('myls_geo_split_hero_body') ) {
  function myls_geo_split_hero_body( string $content_html ) : array {
    $content_html = (string) $content_html;
    $content_html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content_html);
    $content_html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content_html);

    $pos = stripos($content_html, '<h2');
    if ( $pos !== false && $pos > 0 ) {
      $hero_html = trim(substr($content_html, 0, $pos));
      $body_html = trim(substr($content_html, $pos));
    } else {
      $hero_html = '';
      $body_html = $content_html;
    }

    if ( strlen( wp_strip_all_tags($hero_html) ) < 40 ) $hero_html = '';

    $body_text = wp_strip_all_tags($body_html);
    $body_text = preg_replace('/\s+/u',' ', trim($body_text));

    $hero_allowed = [
      'p'      => [],
      'strong' => [],
      'em'     => [],
      'a'      => ['href'=>true, 'target'=>true],
      'br'     => [],
    ];

    return [
      'hero_html' => wp_kses($hero_html, $hero_allowed),
      'body_text' => $body_text,
    ];
  }
}

/** Permalink fetch -> text */
if ( ! function_exists('myls_geo_fetch_permalink_text') ) {
  function myls_geo_fetch_permalink_text( string $url ) : string {
    $url = trim($url);
    if ($url === '') return '';

    $resp = wp_remote_get($url, [
      'timeout' => 20,
      'headers' => ['User-Agent' => 'MYLS-AI-GEO/1.0; ' . home_url('/')],
    ]);
    if ( is_wp_error($resp) ) return '';

    $code = (int) wp_remote_retrieve_response_code($resp);
    if ( $code < 200 || $code >= 300 ) return '';

    $html = (string) wp_remote_retrieve_body($resp);
    if ( $html === '' ) return '';

    $html = preg_replace('#<script[^>]*>.*?</script>#si', ' ', $html);
    $html = preg_replace('#<style[^>]*>.*?</style>#si', ' ', $html);
    $html = preg_replace('#<!--.*?-->#s', ' ', $html);

    $main = '';
    if ( preg_match('#<main[^>]*>(.*?)</main>#si', $html, $m) ) $main = (string) $m[1];
    elseif ( preg_match('#<article[^>]*>(.*?)</article>#si', $html, $m) ) $main = (string) $m[1];
    else $main = $html;

    $text = wp_strip_all_tags($main);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', trim($text));

    if ( strlen($text) > 20000 ) $text = substr($text, 0, 20000);
    return (string) $text;
  }
}

/** Clean GEO HTML: allow href/target, no style attrs */
if ( ! function_exists('myls_geo_clean_geo_html') ) {
  function myls_geo_clean_geo_html( string $html, bool $withAnchors ) : string {
    $allowed = [
      'h2'     => ['id'=>true],
      'h3'     => ['id'=>true],
      'p'      => [],
      'ul'     => [],
      'li'     => [],
      'strong' => [],
      'em'     => [],
      'a'      => ['href'=>true, 'target'=>true],
    ];

    if ( ! $withAnchors && $html !== '' ) {
      $html = preg_replace('/\s+id="[^"]*"/i', '', $html);
    }

    $clean = wp_kses((string)$html, $allowed);
    $clean = preg_replace('/\sstyle="[^"]*"/i', '', $clean);
    return (string) $clean;
  }
}

/** Detect howto+faq */
if ( ! function_exists('myls_geo_output_has_howto_faq') ) {
  function myls_geo_output_has_howto_faq( string $html ) : bool {
    $h = strtolower((string)$html);
    return (strpos($h, 'id="how-tos"') !== false || strpos($h, 'id="howtos"') !== false)
      && (strpos($h, 'id="faqs"') !== false)
      && (preg_match('/<p>\s*<strong>Question:<\/strong>.*?<strong>Answer:<\/strong>.*?<\/p>/i', $html) === 1);
  }
}

/** Prompt to generate ONLY the missing append block */
if ( ! function_exists('myls_geo_build_howto_faq_append_prompt') ) {
  function myls_geo_build_howto_faq_append_prompt( string $title, string $url, string $page_text ) : string {
    return <<<EOT
You are writing an add-on section for a LOCAL SERVICE PAGE.

Inputs:
- Title: {$title}
- URL: {$url}
- Rendered page text: {$page_text}

Task:
Create ONLY the following HTML blocks (nothing else) so they can be appended:

<h2 id="how-tos">How Tos</h2>
(Include 1–3 how-tos total.)
Each how-to:
<h3 id="how-to-1">How to ...</h3>
<ul>
  <li>Step 1...</li>
  <li>Step 2...</li>
  <li>Step 3...</li>
</ul>

<h2 id="faqs">FAQs</h2>
Write 7–10 items. EACH item must be ONE LINE:
<p><strong>Question:</strong> ... <strong>Answer:</strong> ...</p>

<h2 id="sources">Sources</h2>
<ul>
  <li><a href="https://..." target="_blank">Source Name</a></li>
</ul>

Rules:
- Output CLEAN HTML ONLY. No markdown/backticks.
- Allowed tags: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>, <a>.
- NO style/class/data attributes.
- Use only page text + reputable public sources. Do not invent business-specific facts.
EOT;
  }
}

/* =============================================================================
 * NEW: Save clean HTML file for WP copy/paste
 * ============================================================================= */
if ( ! function_exists('myls_geo_build_html_file') ) {
  function myls_geo_build_html_file( int $post_id, string $title, string $geo_html ) : string {
    $uploads = wp_upload_dir();
    if ( empty($uploads['basedir']) || empty($uploads['baseurl']) ) return '';

    $dir = trailingslashit($uploads['basedir']) . 'myls-geo-docs/';
    if ( ! file_exists($dir) ) wp_mkdir_p($dir);

    $safe = sanitize_title($title ?: 'geo');
    $filename = sprintf('%s-%d-%s.html', $safe, $post_id, date('Ymd-His'));
    $path = $dir . $filename;

    $html = "<!-- MYLS GEO HTML Export -->\n" . trim((string)$geo_html) . "\n";

    $ok = file_put_contents($path, $html);
    if ( ! $ok ) return '';

    return trailingslashit($uploads['baseurl']) . 'myls-geo-docs/' . $filename;
  }
}

/* =============================================================================
 * DOCX: Convert allowed HTML to Word paragraphs (NO tags shown)
 * - h2/h3 => heading styles
 * - p => normal
 * - ul/li => bullet paragraphs (simple "• " prefix, avoids complex numbering XML)
 * - strong/em => bold/italic runs (basic)
 * - a => show as "Text (URL)" in Word so it’s readable
 * ============================================================================= */

if ( ! function_exists('myls_geo_docx_escape') ) {
  function myls_geo_docx_escape( string $s ) : string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
  }
}

if ( ! function_exists('myls_geo_docx_content_types_xml') ) {
  function myls_geo_docx_content_types_xml() : string {
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

if ( ! function_exists('myls_geo_docx_rels_xml') ) {
  function myls_geo_docx_rels_xml() : string {
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

if ( ! function_exists('myls_geo_docx_document_rels_xml') ) {
  function myls_geo_docx_document_rels_xml() : string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>
XML;
  }
}

if ( ! function_exists('myls_geo_docx_core_xml') ) {
  function myls_geo_docx_core_xml( string $title ) : string {
    $t = myls_geo_docx_escape($title ?: 'Document');
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

if ( ! function_exists('myls_geo_docx_app_xml') ) {
  function myls_geo_docx_app_xml() : string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
 xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>MYLS</Application>
</Properties>
XML;
  }
}

if ( ! function_exists('myls_geo_docx_run') ) {
  function myls_geo_docx_run( string $text, bool $bold = false, bool $italic = false ) : string {
    $t = myls_geo_docx_escape($text);
    $rPr = '';
    if ($bold || $italic) {
      $rPr = '<w:rPr>' . ($bold ? '<w:b/>' : '') . ($italic ? '<w:i/>' : '') . '</w:rPr>';
    }
    return "<w:r>{$rPr}<w:t xml:space=\"preserve\">{$t}</w:t></w:r>";
  }
}

if ( ! function_exists('myls_geo_docx_p_runs') ) {
  function myls_geo_docx_p_runs( array $runs, string $style = '', bool $center = false ) : string {
    $pPr = '';
    $styleXml = $style !== '' ? '<w:pStyle w:val="' . myls_geo_docx_escape($style) . '"/>' : '';
    $jc = $center ? '<w:jc w:val="center"/>' : '';
    if ($styleXml || $jc) $pPr = '<w:pPr>' . $styleXml . $jc . '</w:pPr>';
    return "<w:p>{$pPr}" . implode('', $runs) . "</w:p>";
  }
}

if ( ! function_exists('myls_geo_docx_parse_inline_runs') ) {
  function myls_geo_docx_parse_inline_runs( DOMNode $node, bool $inheritBold = false, bool $inheritItalic = false ) : array {
    $runs = [];

    foreach ($node->childNodes as $child) {
      if ($child->nodeType === XML_TEXT_NODE) {
        $txt = (string) $child->nodeValue;
        if ($txt !== '') $runs[] = myls_geo_docx_run($txt, $inheritBold, $inheritItalic);
        continue;
      }

      if ($child->nodeType !== XML_ELEMENT_NODE) continue;

      $tag = strtolower($child->nodeName);

      if ($tag === 'strong') {
        $runs = array_merge($runs, myls_geo_docx_parse_inline_runs($child, true, $inheritItalic));
      } elseif ($tag === 'em') {
        $runs = array_merge($runs, myls_geo_docx_parse_inline_runs($child, $inheritBold, true));
      } elseif ($tag === 'a') {
        $href = '';
        if ($child->attributes && $child->attributes->getNamedItem('href')) {
          $href = (string) $child->attributes->getNamedItem('href')->nodeValue;
        }
        $text = trim($child->textContent ?? '');
        if ($text === '') $text = $href;
        // Word: show "Text (URL)" so it reads clearly; HTML download is for WP.
        $runs[] = myls_geo_docx_run($text, $inheritBold, $inheritItalic);
        if ($href !== '' && strpos($href, '#') !== 0) {
          $runs[] = myls_geo_docx_run(' (' . $href . ')', false, false);
        }
      } else {
        $runs = array_merge($runs, myls_geo_docx_parse_inline_runs($child, $inheritBold, $inheritItalic));
      }
    }

    return $runs;
  }
}

if ( ! function_exists('myls_geo_docx_document_xml_from_geo_html') ) {
  function myls_geo_docx_document_xml_from_geo_html(
    string $page_title,
    string $seo_title,
    string $permalink,
    string $focus_kw,
    string $geo_html
  ) : string {

    $paras = [];

    // Centered heading block
    $paras[] = myls_geo_docx_p_runs([myls_geo_docx_run('Page Title: ' . $page_title, true, false)], 'Heading2', true);
    if (trim($seo_title) !== '') {
      $paras[] = myls_geo_docx_p_runs([myls_geo_docx_run('SEO Title: ' . $seo_title, false, false)], '', true);
    }
    $paras[] = myls_geo_docx_p_runs([myls_geo_docx_run('Permalink: ' . $permalink, false, false)], '', true);
    if (trim($focus_kw) !== '') {
      $paras[] = myls_geo_docx_p_runs([myls_geo_docx_run('Focus Keyword: ' . $focus_kw, true, false)], '', true);
    }

    // spacer
    $paras[] = myls_geo_docx_p_runs([myls_geo_docx_run('')], '', false);

    // Parse GEO HTML into Word paragraphs
    $html = '<div>' . (string)$geo_html . '</div>';

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $dom->getElementsByTagName('div')->item(0);
    if ($root) {
      foreach ($root->childNodes as $node) {
        if ($node->nodeType !== XML_ELEMENT_NODE) continue;

        $tag = strtolower($node->nodeName);

        if ($tag === 'h2') {
          $runs = myls_geo_docx_parse_inline_runs($node);
          if (!$runs) $runs = [myls_geo_docx_run(trim($node->textContent ?? ''), true, false)];
          $paras[] = myls_geo_docx_p_runs($runs, 'Heading2', false);
          continue;
        }

        if ($tag === 'h3') {
          $runs = myls_geo_docx_parse_inline_runs($node);
          if (!$runs) $runs = [myls_geo_docx_run(trim($node->textContent ?? ''), true, false)];
          $paras[] = myls_geo_docx_p_runs($runs, 'Heading3', false);
          continue;
        }

        if ($tag === 'p') {
          $runs = myls_geo_docx_parse_inline_runs($node);
          if (!$runs) $runs = [myls_geo_docx_run(trim($node->textContent ?? ''), false, false)];
          $paras[] = myls_geo_docx_p_runs($runs, '', false);
          continue;
        }

        if ($tag === 'ul') {
          foreach ($node->childNodes as $li) {
            if ($li->nodeType !== XML_ELEMENT_NODE) continue;
            if (strtolower($li->nodeName) !== 'li') continue;
            $runs = myls_geo_docx_parse_inline_runs($li);
            array_unshift($runs, myls_geo_docx_run('• ', false, false));
            $paras[] = myls_geo_docx_p_runs($runs, '', false);
          }
          continue;
        }
      }
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

if ( ! function_exists('myls_geo_docx_write') ) {
  function myls_geo_docx_write(
    string $filepath,
    string $doc_title,
    string $page_title,
    string $seo_title,
    string $permalink,
    string $focus_kw,
    string $geo_html
  ) : bool {

    if ( ! class_exists('ZipArchive') ) return false;

    $zip = new ZipArchive();
    if ( $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true ) return false;

    $zip->addFromString('[Content_Types].xml', myls_geo_docx_content_types_xml());
    $zip->addFromString('_rels/.rels', myls_geo_docx_rels_xml());
    $zip->addFromString('docProps/core.xml', myls_geo_docx_core_xml($doc_title));
    $zip->addFromString('docProps/app.xml', myls_geo_docx_app_xml());
    $zip->addFromString('word/_rels/document.xml.rels', myls_geo_docx_document_rels_xml());
    $zip->addFromString('word/document.xml', myls_geo_docx_document_xml_from_geo_html(
      $page_title, $seo_title, $permalink, $focus_kw, $geo_html
    ));

    $zip->close();
    return true;
  }
}

if ( ! function_exists('myls_geo_build_docx_from_geo_html') ) {
  function myls_geo_build_docx_from_geo_html(
    int $post_id,
    string $doc_title,
    string $page_title,
    string $seo_title,
    string $permalink,
    string $focus_kw,
    string $geo_html
  ) : string {

    if ( ! class_exists('ZipArchive') ) return '';

    $uploads = wp_upload_dir();
    if ( empty($uploads['basedir']) || empty($uploads['baseurl']) ) return '';

    $dir = trailingslashit($uploads['basedir']) . 'myls-geo-docs/';
    if ( ! file_exists($dir) ) wp_mkdir_p($dir);

    $safe = sanitize_title($doc_title ?: 'geo');
    $filename = sprintf('%s-%d-%s.docx', $safe, $post_id, date('Ymd-His'));
    $path = $dir . $filename;

    $ok = myls_geo_docx_write($path, $doc_title, $page_title, $seo_title, $permalink, $focus_kw, $geo_html);
    if ( ! $ok ) return '';

    return trailingslashit($uploads['baseurl']) . 'myls-geo-docs/' . $filename;
  }
}

/* =============================================================================
 * Endpoint: Get posts
 * ============================================================================= */
add_action('wp_ajax_myls_ai_geo_get_posts_v1', function(){
  myls_ai_check_nonce();

  $pt = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'page';
  if ( ! post_type_exists($pt) ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'invalid_post_type'], 400);

  $ptype_obj = get_post_type_object($pt);
  $cap = $ptype_obj && isset($ptype_obj->cap->edit_posts) ? $ptype_obj->cap->edit_posts : 'edit_posts';
  if ( ! current_user_can($cap) ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'cap_denied'], 403);

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
  foreach ($ids as $pid) $out[] = ['id'=>(int)$pid, 'title'=> get_the_title($pid)];
  wp_send_json_success(['marker'=>'geo_v4','status'=>'ok','posts'=>$out]);
});

/* =============================================================================
 * Analyze v2
 * ============================================================================= */
add_action('wp_ajax_myls_ai_geo_analyze_v2', function(){
  myls_ai_check_nonce();

  $post_id     = (int) ($_POST['post_id'] ?? 0);
  $tokens      = max(1, (int) ($_POST['tokens'] ?? (int) get_option('myls_ai_geo_tokens', 1200)));
  $temperature = (float) ($_POST['temperature'] ?? (float) get_option('myls_ai_geo_temperature', 0.4));
  $mode        = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'partial';
  $withAnchors = ! empty($_POST['with_anchors']);
  $includeFaqHowto = ! empty($_POST['include_faq_howto']);

  if ( $post_id <= 0 || get_post_status($post_id) === false ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'bad_post'], 400);
  if ( ! current_user_can('edit_post', $post_id) ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'cap_denied'], 403);

  $p = get_post($post_id);
  $page_title = get_the_title($post_id);
  $url = get_permalink($post_id);

  $template = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
  if ( ! is_string($template) || trim($template) === '' ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'missing_template'], 400);

  $yoast_seo_title = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
  $yoast_focus_kw  = (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true);

  $page_text = myls_geo_fetch_permalink_text((string)$url);
  if ($page_text === '') {
    $fallback = myls_geo_split_hero_body((string)$p->post_content);
    $page_text = (string) ($fallback['body_text'] ?? '');
    if ($page_text === '') $page_text = preg_replace('/\s+/u',' ', trim( wp_strip_all_tags((string)$p->post_content) ));
  }

  $split = myls_geo_split_hero_body((string)$p->post_content);
  $hero_html = (string) ($split['hero_html'] ?? '');
  $content_txt = (string) ($split['body_text'] ?? '');

  if ( $mode === 'full' ) {
    $hero_html = '';
    $content_txt = preg_replace('/\s+/u',' ', trim( wp_strip_all_tags((string)$p->post_content) ));
  }

  $prompt = str_replace(
    ['{{TITLE}}','{{URL}}','{{PAGE_TEXT}}','{{HERO}}','{{CONTENT}}','{{INCLUDE_FAQ_HOWTO}}'],
    [$page_title, $url, $page_text, wp_strip_all_tags($hero_html), $content_txt, ($includeFaqHowto ? 'YES' : 'NO')],
    $template
  );

  $model = isset($_POST['model']) && is_string($_POST['model']) ? trim($_POST['model']) : '';

  $raw = myls_geo_strip_code_fences(myls_ai_generate_text($prompt, [
    'model'       => $model ?: null,
    'max_tokens'  => $tokens,
    'temperature' => $temperature,
    'context'     => 'geo_preview_v4',
    'post_id'     => $post_id,
  ]));

  $geo_html = myls_geo_clean_geo_html((string)$raw, $withAnchors);

  // Auto-repair append
  if ( $includeFaqHowto && ! myls_geo_output_has_howto_faq($geo_html) ) {
    $append_prompt = myls_geo_build_howto_faq_append_prompt($page_title, $url, $page_text);
    $raw2 = myls_geo_strip_code_fences(myls_ai_generate_text($append_prompt, [
      'model'       => $model ?: null,
      'max_tokens'  => max(400, (int) floor($tokens * 0.5)),
      'temperature' => $temperature,
      'context'     => 'geo_append_howto_faq_v4',
      'post_id'     => $post_id,
    ]));
    $append_html = myls_geo_clean_geo_html((string)$raw2, true);
    $geo_html .= "\n\n" . $append_html;
  }

  // Generate exports
  $doc_url  = myls_geo_build_docx_from_geo_html($post_id, $page_title, $page_title, $yoast_seo_title, $url, $yoast_focus_kw, $geo_html);
  $html_url = myls_geo_build_html_file($post_id, $page_title, $geo_html);

  wp_send_json_success([
    'marker'       => 'geo_v4',
    'status'       => 'ok',
    'post_id'      => (int)$post_id,
    'title'        => (string)$page_title,
    'url'          => (string)$url,
    'yoast_title'  => (string)$yoast_seo_title,
    'yoast_focus'  => (string)$yoast_focus_kw,
    'geo_html'     => (string)$geo_html,
    'html'         => (string)$geo_html,
    'raw_combined' => (string)$raw,
    'raw'          => (string)$raw,
    'doc_url'      => (string)$doc_url,
    'html_url'     => (string)$html_url,
  ]);
});

/* =============================================================================
 * Convert v1 (draft creation) – unchanged from your last version except returns html_url too
 * ============================================================================= */
add_action('wp_ajax_myls_ai_geo_convert_v1', function(){
  myls_ai_check_nonce();

  $post_id     = (int) ($_POST['post_id'] ?? 0);
  $tokens      = max(1, (int) ($_POST['tokens'] ?? (int) get_option('myls_ai_geo_tokens', 1200)));
  $temperature = (float) ($_POST['temperature'] ?? (float) get_option('myls_ai_geo_temperature', 0.4));
  $mode        = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'partial';
  $withAnchors = ! empty($_POST['with_anchors']);
  $includeFaqHowto = ! empty($_POST['include_faq_howto']);

  if ( $post_id <= 0 || get_post_status($post_id) === false ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'bad_post'], 400);
  if ( ! current_user_can('edit_post', $post_id) ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'cap_denied'], 403);

  $src = get_post($post_id);
  if ( ! $src ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'post_not_found'], 404);

  $page_title = get_the_title($post_id);
  $url = get_permalink($post_id);

  $template = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
  if ( ! is_string($template) || trim($template) === '' ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'missing_template'], 400);

  $yoast_seo_title = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
  $yoast_focus_kw  = (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true);

  $page_text = myls_geo_fetch_permalink_text((string)$url);
  if ($page_text === '') $page_text = preg_replace('/\s+/u',' ', trim( wp_strip_all_tags((string)$src->post_content) ));

  $split = myls_geo_split_hero_body((string)$src->post_content);
  $hero_html = (string) ($split['hero_html'] ?? '');
  $body_text = (string) ($split['body_text'] ?? '');

  if ( $mode === 'full' ) {
    $hero_html = '';
    $body_text = preg_replace('/\s+/u',' ', trim( wp_strip_all_tags((string)$src->post_content) ));
  }

  $prompt = str_replace(
    ['{{TITLE}}','{{URL}}','{{PAGE_TEXT}}','{{HERO}}','{{CONTENT}}','{{INCLUDE_FAQ_HOWTO}}'],
    [$page_title, $url, $page_text, wp_strip_all_tags($hero_html), $body_text, ($includeFaqHowto ? 'YES' : 'NO')],
    $template
  );

  $model = isset($_POST['model']) && is_string($_POST['model']) ? trim($_POST['model']) : '';

  $raw = myls_geo_strip_code_fences(myls_ai_generate_text($prompt, [
    'model'       => $model ?: null,
    'max_tokens'  => $tokens,
    'temperature' => $temperature,
    'context'     => 'geo_convert_v4',
    'post_id'     => $post_id,
  ]));

  $geo_html = myls_geo_clean_geo_html((string)$raw, $withAnchors);

  if ( $includeFaqHowto && ! myls_geo_output_has_howto_faq($geo_html) ) {
    $append_prompt = myls_geo_build_howto_faq_append_prompt($page_title, $url, $page_text);
    $raw2 = myls_geo_strip_code_fences(myls_ai_generate_text($append_prompt, [
      'model'       => $model ?: null,
      'max_tokens'  => max(400, (int) floor($tokens * 0.5)),
      'temperature' => $temperature,
      'context'     => 'geo_append_howto_faq_convert_v4',
      'post_id'     => $post_id,
    ]));
    $append_html = myls_geo_clean_geo_html((string)$raw2, true);
    $geo_html .= "\n\n" . $append_html;
  }

  // Final content (preserve hero in partial mode)
  $final = '';
  if ( $hero_html !== '' ) {
    $final .= $hero_html . "\n";
    if ($withAnchors) $final .= '<p><a href="#quick-answer">Quick Answer</a> • <a href="#key-facts">Key Facts</a> • <a href="#common-questions">Common Questions</a></p>' . "\n\n";
    else $final .= "\n";
  }
  $final .= $geo_html;

  $new_title = trim((string)$src->post_title) . ' (GEO Draft)';
  $new_id = wp_insert_post([
    'post_type'      => $src->post_type,
    'post_status'    => 'draft',
    'post_title'     => $new_title,
    'post_content'   => (string)$final,
    'post_excerpt'   => (string)$src->post_excerpt,
    'post_parent'    => (int)$src->post_parent,
    'menu_order'     => (int)$src->menu_order,
    'comment_status' => (string)$src->comment_status,
    'ping_status'    => (string)$src->ping_status,
  ], true);

  if ( is_wp_error($new_id) ) {
    wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'insert_failed','debug'=>['error'=>$new_id->get_error_message()]], 500);
  }

  $thumb_id = get_post_thumbnail_id($src->ID);
  if ( $thumb_id ) set_post_thumbnail($new_id, $thumb_id);

  $taxes = get_object_taxonomies($src->post_type, 'names');
  if ( is_array($taxes) ) {
    foreach ($taxes as $tax) {
      $terms = wp_get_object_terms($src->ID, $tax, ['fields'=>'ids']);
      if ( ! is_wp_error($terms) && is_array($terms) ) wp_set_object_terms($new_id, $terms, $tax, false);
    }
  }

  $all_meta = get_post_meta($src->ID);
  if ( is_array($all_meta) ) {
    foreach ($all_meta as $key => $vals) {
      if ( ! is_string($key) || $key === '' ) continue;
      if ( in_array($key, ['_edit_lock','_edit_last','_wp_old_slug'], true) ) continue;
      if ( $key === '_thumbnail_id' ) continue;
      if ( is_array($vals) ) foreach ($vals as $v) add_post_meta($new_id, $key, maybe_unserialize($v));
    }
  }

  $preview_url = function_exists('get_preview_post_link') ? get_preview_post_link($new_id) : '';
  $edit_url    = function_exists('get_edit_post_link') ? get_edit_post_link($new_id, 'raw') : '';

  $doc_url  = myls_geo_build_docx_from_geo_html($post_id, $page_title, $page_title, $yoast_seo_title, $url, $yoast_focus_kw, $final);
  $html_url = myls_geo_build_html_file($post_id, $page_title, $final);

  wp_send_json_success([
    'marker'       => 'geo_v4',
    'status'       => 'converted',
    'source_id'    => (int)$src->ID,
    'new_post_id'  => (int)$new_id,
    'edit_url'     => (string)$edit_url,
    'preview_url'  => (string)$preview_url,
    'doc_url'      => (string)$doc_url,
    'html_url'     => (string)$html_url,
  ]);
});

/* =============================================================================
 * Duplicate v1 (unchanged)
 * ============================================================================= */
add_action('wp_ajax_myls_ai_geo_duplicate_v1', function(){
  myls_ai_check_nonce();

  $post_id = (int) ($_POST['post_id'] ?? 0);
  if ( $post_id <= 0 ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'missing_post_id'], 400);

  $src = get_post($post_id);
  if ( ! $src ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'post_not_found'], 404);
  if ( ! current_user_can('edit_post', $post_id) ) wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'cap_denied'], 403);

  $new_id = wp_insert_post([
    'post_type'    => $src->post_type,
    'post_status'  => 'draft',
    'post_title'   => trim((string)$src->post_title) . ' (GEO Draft)',
    'post_content' => (string)$src->post_content,
    'post_excerpt' => (string)$src->post_excerpt,
    'post_parent'  => (int)$src->post_parent,
    'menu_order'   => (int)$src->menu_order,
  ], true);

  if ( is_wp_error($new_id) ) {
    wp_send_json_error(['marker'=>'geo_v4','status'=>'error','message'=>'insert_failed','debug'=>['error'=>$new_id->get_error_message()]], 500);
  }

  wp_send_json_success([
    'marker'      => 'geo_v4',
    'status'      => 'duplicated',
    'source_id'   => (int)$src->ID,
    'new_post_id' => (int)$new_id,
  ]);
});
