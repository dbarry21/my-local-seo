<?php
/**
 * AI Subtab: SEO → GEO (Rewrite Draft Builder) + DOCX + HTML download
 * File: admin/tabs/ai/subtab-geo.php
 *
 * This subtab was accidentally removed when adding the FAQs subtab.
 * This restored version keeps the same 2-column look/feel and uses the existing
 * GEO AJAX endpoints:
 *  - myls_ai_geo_get_posts_v1
 *  - myls_ai_geo_analyze_v2
 *  - myls_ai_geo_convert_v1
 *  - myls_ai_geo_duplicate_v1
 */

if ( ! defined('ABSPATH') ) exit;

return [
  'id'    => 'geo',
  'label' => 'SEO → GEO (Rewrite Draft Builder)',
  'order' => 40,
  'render'=> function () {

    // ---------------------------------------------------------------------
    // Save prompt + params
    // ---------------------------------------------------------------------
    if (
      isset($_POST['myls_ai_geo_save']) &&
      check_admin_referer('myls_ai_geo_save_nonce', 'myls_ai_geo_save_nonce')
    ) {
      update_option(
        'myls_ai_geo_prompt_template',
        wp_kses_post( wp_unslash($_POST['myls_ai_geo_prompt_template'] ?? '') )
      );
      update_option('myls_ai_geo_tokens', max(1, (int) ($_POST['myls_ai_geo_tokens'] ?? 1200)));
      update_option('myls_ai_geo_temperature', (float) ($_POST['myls_ai_geo_temperature'] ?? 0.4));

      echo '<div class="updated notice"><p>Saved GEO prompt template & params.</p></div>';
    }

    // ---------------------------------------------------------------------
    // Default prompt (supports optional How-Tos + FAQs)
    // ---------------------------------------------------------------------
    $default_prompt = <<<EOT
You are an expert in GEO (Generative Engine Optimization) for LOCAL SERVICE PAGES.

INPUTS:
- Title: {{TITLE}}
- URL: {{URL}}
- Rendered page text (from the permalink): {{PAGE_TEXT}}
- Include How-Tos + FAQs?: {{INCLUDE_FAQ_HOWTO}}

NON-NEGOTIABLE RULES:
- Use ONLY information present in inputs OR reputable public sources that apply generally to this service.
- DO NOT invent business-specific facts (years in business, exact service areas, licenses, awards, pricing, guarantees, staff, etc.) unless explicitly present.
- If a business-specific fact is unknown, omit it.
- If you use a public source, include it in a short "Sources" list at the end (external links must use target="_blank").

OUTPUT (VERY IMPORTANT):
- Return CLEAN HTML ONLY. No markdown. No backticks. No code fences.
- Allowed tags only: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>, <a>.
- NO style/class/data attributes. (id on h2/h3 is allowed.)
- Internal jump links allowed (href="#...").

ANCHORS:
- Add id="..." to ALL <h2> and <h3>.

JUMP LINKS (recommended near the top):
<p><a href="#quick-answer">Quick Answer</a> • <a href="#key-facts">Key Facts</a> • <a href="#common-questions">Common Questions</a></p>

REQUIRED GEO STRUCTURE (use these exact titles):

<h2 id="quick-answer">Quick Answer</h2>
<p>2–4 sentences that clearly describe what service this page offers and who it is for.</p>

<h2 id="when-to-call">When to Call</h2>
<ul>
  <li>5–8 bullets describing common situations/symptoms/problems mentioned in the content.</li>
</ul>

<h2 id="what-you-get">What You Get</h2>
<ul>
  <li>5–10 bullets of deliverables, scope, and inclusions from the content.</li>
</ul>

<h2 id="how-it-works">How It Works</h2>
<p>Brief step-by-step explanation in plain language.</p>
<ul>
  <li>Step 1…</li>
  <li>Step 2…</li>
  <li>Step 3…</li>
</ul>

<h2 id="key-facts">Key Facts</h2>
<ul>
  <li>8–12 bullets capturing concrete facts, constraints, and specifics from the content.</li>
</ul>

<h2 id="common-questions">Common Questions</h2>
<h3 id="faq-1">Question 1</h3>
<p>Short direct answer.</p>
<h3 id="faq-2">Question 2</h3>
<p>Short direct answer.</p>
<h3 id="faq-3">Question 3</h3>
<p>Short direct answer.</p>

<h2 id="summary">Summary</h2>
<p>1 short closing paragraph with one simple call to action.</p>

HOW-TO + FAQ ADD-ON (ONLY IF {{INCLUDE_FAQ_HOWTO}} = YES):
Append AFTER the Summary:

<h2 id="how-tos">How Tos</h2>
(Include 1–3 how-tos total. Each how-to must be a <h3> + bullet steps.)

<h2 id="faqs">FAQs</h2>
(7–10 items. Each item must be ONE LINE with Question and Answer on the same line.)
Use this exact format for each item:
<p><strong>Question:</strong> ... <strong>Answer:</strong> ...</p>

After FAQs, add:
<h2 id="sources">Sources</h2>
<ul>
  <li><a href="https://example.com" target="_blank">Source Name</a></li>
</ul>

Now produce the final HTML.
EOT;

    $geo_prompt = get_option('myls_ai_geo_prompt_template', '');
    if ( ! is_string($geo_prompt) || trim($geo_prompt) === '' ) {
      $geo_prompt = $default_prompt;
    }

    $geo_tokens = (int) get_option('myls_ai_geo_tokens', 1200);
    $geo_temp   = (float) get_option('myls_ai_geo_temperature', 0.4);

    // Post types
    $pts = get_post_types(['public' => true], 'objects');
    unset($pts['attachment']);
    $default_pt = isset($pts['page']) ? 'page' : ( $pts ? array_key_first($pts) : 'page' );

    $nonce = wp_create_nonce('myls_ai_ops');
    ?>

    <style>
      .myls-geo-spinner{display:none;align-items:center;gap:8px}
      .myls-geo-spinner .dashicons{animation:myls-geo-spin 0.9s linear infinite}
      @keyframes myls-geo-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
      .myls-geo-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}

      /* Selection-friendly panes */
      .myls-selectbox{
        position:relative;
        border:1px solid #ccc;
        border-radius:10px;
        background:#fff;
        padding:12px;
        overflow:auto;
        user-select:text;
        -webkit-user-select:text;
      }
      .myls-selectbox:focus-within{
        outline:2px solid rgba(0,0,0,0.25);
        outline-offset:2px;
      }
    </style>

    <div class="myls-two-col" style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;">

      <!-- LEFT: Prompt/Settings -->
      <div style="border:1px solid #000;padding:16px;border-radius:12px;">
        <h2 class="h4" style="margin-top:0;">GEO Prompt Template</h2>

        <form method="post">
          <?php wp_nonce_field('myls_ai_geo_save_nonce','myls_ai_geo_save_nonce'); ?>

          <div class="mb-3">
            <label class="form-label"><strong>Prompt Template</strong></label>
            <textarea id="myls_ai_geo_prompt_template" name="myls_ai_geo_prompt_template" class="widefat" rows="22"><?php echo esc_textarea($geo_prompt); ?></textarea>
            <p class="description">Variables: <code>{{TITLE}}</code>, <code>{{URL}}</code>, <code>{{PAGE_TEXT}}</code>, <code>{{INCLUDE_FAQ_HOWTO}}</code></p>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Max Tokens</label>
              <input id="myls_ai_geo_tokens" type="number" min="1" name="myls_ai_geo_tokens" class="regular-text form-control" value="<?php echo esc_attr($geo_tokens); ?>" />
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Temperature</label>
              <input id="myls_ai_geo_temperature" type="number" step="0.1" min="0" max="2" name="myls_ai_geo_temperature" class="regular-text form-control" value="<?php echo esc_attr($geo_temp); ?>" />
            </div>
          </div>

          <p><button type="submit" name="myls_ai_geo_save" class="button button-primary">Save Template & Params</button></p>
        </form>

        <hr/>

        <h3 class="h5" style="margin-top:0;">Rewrite Mode</h3>
        <fieldset style="margin:0;">
          <label style="display:block;margin:0 0 6px 0;">
            <input type="radio" name="myls_ai_geo_mode" value="partial" checked>
            <strong>Partial rewrite</strong> (keep hero/top section, replace body)
          </label>
          <label style="display:block;margin:0 0 6px 0;">
            <input type="radio" name="myls_ai_geo_mode" value="full">
            <strong>Full rewrite</strong> (replace entire post content)
          </label>
          <label style="display:block;margin:0;">
            <input type="checkbox" id="myls_ai_geo_with_anchors" checked>
            Allow links + anchors
          </label>
        </fieldset>
      </div>

      <!-- RIGHT: Picker + Preview -->
      <div style="border:1px solid #000;padding:16px;border-radius:12px;">

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h2 class="h4" style="margin:0;">Select Posts</h2>
          <div class="small text-muted">Processed: <span id="myls_ai_geo_count">0</span></div>
        </div>

        <div class="row g-3 align-items-end mt-1">
          <div class="col-md-4">
            <label class="form-label">Post Type</label>
            <select id="myls_ai_geo_pt" class="form-select">
              <?php foreach ($pts as $pt_key => $obj): ?>
                <option value="<?php echo esc_attr($pt_key); ?>" <?php selected($pt_key, $default_pt); ?>>
                  <?php echo esc_html($obj->labels->singular_name . " ({$pt_key})"); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-8">
            <label class="form-label">Posts</label>
            <select id="myls_ai_geo_posts" class="form-select" multiple size="8" style="min-height:200px;"></select>
          </div>
        </div>

        <div class="mt-3 myls-geo-actions">
          <button type="button" class="button" id="myls_ai_geo_select_all">Select All</button>
          <button type="button" class="button" id="myls_ai_geo_clear">Clear</button>
          <span class="small text-muted" id="myls_ai_geo_loaded_hint"></span>
        </div>

        <div class="mt-3 myls-geo-actions">
          <label style="display:flex;align-items:center;gap:8px;margin:0;">
            <input type="checkbox" id="myls_ai_geo_include_faq_howto">
            <strong>Include How-Tos + FAQs</strong>
          </label>

          <button type="button" class="button button-primary" id="myls_ai_geo_analyze">Analyze Selected (Preview)</button>
          <button type="button" class="button button-primary" id="myls_ai_geo_convert">Convert to GEO Draft</button>
          <button type="button" class="button" id="myls_ai_geo_duplicate">Duplicate to Draft (No Rewrite)</button>

          <button type="button" class="button" id="myls_ai_geo_docx" disabled>Download .docx</button>
          <button type="button" class="button" id="myls_ai_geo_html" disabled>Download .html</button>

          <button type="button" class="button" id="myls_ai_geo_stop" disabled>Stop</button>

          <span class="myls-geo-spinner" id="myls_ai_geo_spinner">
            <span class="dashicons dashicons-update"></span>
            <span class="small text-muted">Processing…</span>
          </span>

          <span id="myls_ai_geo_status" class="small text-muted"></span>
        </div>

        <hr/>

        <h3 class="h5">Preview (GEO HTML)</h3>
        <div id="myls_ai_geo_preview" class="myls-selectbox" tabindex="0" style="min-height:180px;max-height:420px;"></div>

        <div class="mt-3">
          <h3 class="h5">Raw Output (Combined)</h3>
          <pre id="myls_ai_geo_output" class="myls-selectbox" tabindex="0" style="white-space:pre-wrap;background:#f8f9fa;border:1px solid #ddd;min-height:140px;max-height:260px;margin:0;"></pre>
        </div>

        <div class="mt-3">
          <h3 class="h5">Results Log</h3>
          <pre id="myls_ai_geo_results" class="myls-selectbox" tabindex="0" style="white-space:pre-wrap;background:#f8f9fa;border:1px solid #ddd;min-height:120px;max-height:220px;margin:0;"></pre>
        </div>
      </div>
    </div>

    <?php

    // Print config + load JS in footer (reliable in modular subtabs)
    add_action('admin_print_footer_scripts', function() use ($default_pt, $nonce) {
      if ( empty($_GET['page']) || $_GET['page'] !== 'my-local-seo' ) return;

      $script_url = rtrim(MYLS_URL, '/') . '/assets/js/myls-ai-geo.js';
      $v = time();
      ?>
      <script>
      window.MYLS_AI_GEO = {
        ajaxurl: "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>",
        nonce: "<?php echo esc_js( $nonce ); ?>",
        defaultType: "<?php echo esc_js( $default_pt ); ?>",
        action_get_posts: "myls_ai_geo_get_posts_v1",
        action_duplicate: "myls_ai_geo_duplicate_v1",
        action_analyze: "myls_ai_geo_analyze_v2",
        action_convert: "myls_ai_geo_convert_v1"
      };
      </script>
      <script src="<?php echo esc_url( $script_url . '?v=' . $v ); ?>"></script>
      <?php
    }, 9999);
  }
];
