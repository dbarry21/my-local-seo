<?php
/**
 * AI Subtab: SEO → GEO (Rewrite Draft Builder)
 * File: admin/tabs/ai/subtab-geo.php
 *
 * Phase 1:
 *  - Select post(s)
 *  - Analyze Selected (Preview)
 *  - Convert to GEO Draft (creates a NEW draft with rewritten content)
 *
 * Notes:
 *  - This plugin's modular admin renders subtabs late, so relying on
 *    wp_enqueue_script() inside render() is unreliable.
 *  - We print the config + <script src> directly in the footer.
 */

if ( ! defined('ABSPATH') ) exit;

return [
  'id'    => 'geo',
  'label' => 'SEO → GEO (Rewrite Draft Builder)',
  'order' => 40,
  'render'=> function () {

    // -------------------------------------------------
    // Save Prompt + Params
    // -------------------------------------------------
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

    // -------------------------------------------------
    // Tuned default prompt (Local service pages)
    // -------------------------------------------------
    $default_prompt = <<<EOT
You are an expert in GEO (Generative Engine Optimization) for LOCAL SERVICE PAGES.

GOAL:
Rewrite the content into an answer-engine friendly format that is easy to extract, cite, and reuse.

INPUTS:
- Title: {{TITLE}}
- URL: {{URL}}
- Hero content (keep as-is): {{HERO}}
- Existing page content (text): {{CONTENT}}

OUTPUT RULES (VERY IMPORTANT):
- Output CLEAN HTML ONLY. No markdown. No backticks. No code fences.
- Allowed tags only: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>, <a>.
- Add id="..." anchors to ALL <h2> and <h3> headings so engines can cite sections.
- If you include links, only use internal hash anchors like <a href="#key-facts">.
- Be factual and specific. Use ONLY information present in the inputs.
- DO NOT invent: years in business, licenses, awards, service areas, pricing, guarantees, or locations.
- If info is unknown, omit it.

STYLE:
- Short paragraphs (1–3 sentences).
- Prefer bullet lists for facts and criteria.
- Avoid salesy language; keep it helpful and neutral.

REQUIRED STRUCTURE (use these exact section titles):

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

<h2 id="pricing-factors">Pricing Factors</h2>
<p>Explain what affects cost/estimates (ONLY if mentioned). If not mentioned, omit this section.</p>

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
<p>1 short closing paragraph.</p>

OPTIONAL (HIGHLY RECOMMENDED):
At the very top (after the hero), add a short jump-link list:
<p><a href="#quick-answer">Quick Answer</a> • <a href="#key-facts">Key Facts</a> • <a href="#common-questions">Common Questions</a></p>

Now produce the rewritten GEO-friendly content.
EOT;

    $geo_prompt = get_option('myls_ai_geo_prompt_template', $default_prompt);
    $geo_tokens = (int) get_option('myls_ai_geo_tokens', 1200);
    $geo_temp   = (float) get_option('myls_ai_geo_temperature', 0.4);

    // -------------------------------------------------
    // Post type selector
    // -------------------------------------------------
    $pts = get_post_types(['public' => true], 'objects');
    unset($pts['attachment']);
    $default_pt = isset($pts['page']) ? 'page' : ( $pts ? array_key_first($pts) : 'page' );

    $nonce = wp_create_nonce('myls_ai_ops');
    ?>

    <style>
      /* Small, self-contained spinner */
      .myls-geo-spinner{display:none;align-items:center;gap:8px}
      .myls-geo-spinner .dashicons{animation:myls-geo-spin 0.9s linear infinite}
      @keyframes myls-geo-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
      .myls-geo-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    </style>

    <div class="myls-two-col" style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;">

      <!-- LEFT: Prompt + Mode -->
      <div style="border:1px solid #000;padding:16px;border-radius:12px;">
        <h2 class="h4" style="margin-top:0;">GEO Prompt Template</h2>

        <form method="post">
          <?php wp_nonce_field('myls_ai_geo_save_nonce','myls_ai_geo_save_nonce'); ?>

          <div class="mb-3">
            <label class="form-label"><strong>Prompt Template</strong></label>
            <textarea name="myls_ai_geo_prompt_template" class="widefat" rows="16"><?php echo esc_textarea($geo_prompt); ?></textarea>
            <p class="description">
              Variables: <code>{{TITLE}}</code>, <code>{{URL}}</code>, <code>{{HERO}}</code>, <code>{{CONTENT}}</code>
            </p>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Max Tokens</label>
              <input type="number" min="1" name="myls_ai_geo_tokens" class="regular-text form-control" value="<?php echo esc_attr($geo_tokens); ?>" />
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Temperature</label>
              <input type="number" step="0.1" min="0" max="2" name="myls_ai_geo_temperature" class="regular-text form-control" value="<?php echo esc_attr($geo_temp); ?>" />
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
            Add citation-style anchors (ids + jump links)
          </label>
        </fieldset>

        <div class="small text-muted" style="margin-top:10px;">
          <strong>GEO vs SEO Diff:</strong> The preview will include a diff panel showing what changed (structure + wording).
        </div>
      </div>

      <!-- RIGHT: Selector + Preview -->
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
          <button type="button" class="button button-primary" id="myls_ai_geo_analyze">Analyze Selected (Preview)</button>
          <button type="button" class="button button-primary" id="myls_ai_geo_convert">Convert to GEO Draft</button>
          <button type="button" class="button" id="myls_ai_geo_duplicate">Duplicate to Draft (No Rewrite)</button>
          <button type="button" class="button" id="myls_ai_geo_stop" disabled>Stop</button>

          <span class="myls-geo-spinner" id="myls_ai_geo_spinner">
            <span class="dashicons dashicons-update"></span>
            <span class="small text-muted">Processing…</span>
          </span>

          <span id="myls_ai_geo_status" class="small text-muted"></span>
        </div>

        <hr/>

        <h3 class="h5">Preview</h3>
        <div id="myls_ai_geo_preview"
             style="background:#fff;border:1px solid #ccc;border-radius:10px;padding:12px;min-height:160px;max-height:360px;overflow:auto;">
        </div>

        <div class="mt-3">
          <h3 class="h5">GEO vs SEO Diff</h3>
          <div id="myls_ai_geo_diff"
               style="background:#fff;border:1px solid #ccc;border-radius:10px;padding:12px;min-height:120px;max-height:260px;overflow:auto;"></div>
        </div>

        <div class="mt-3">
          <h3 class="h5">Raw Output</h3>
          <pre id="myls_ai_geo_output"
               style="white-space:pre-wrap;background:#f8f9fa;border:1px solid #ddd;border-radius:10px;padding:12px;min-height:140px;max-height:260px;overflow:auto;margin:0;"></pre>
        </div>

        <hr/>

        <h3 class="h5">Results Log</h3>
        <pre id="myls_ai_geo_results"
             style="white-space:pre-wrap;background:#f8f9fa;border:1px solid #ddd;border-radius:10px;padding:12px;min-height:120px;max-height:220px;overflow:auto;margin:0;"></pre>

      </div>
    </div>

    <?php

    // -------------------------------------------------
    // Print config + load JS in footer (reliable in modular subtabs)
    // -------------------------------------------------
    add_action('admin_print_footer_scripts', function() use ($default_pt, $nonce) {
      // Only print on our plugin screen.
      if ( empty($_GET['page']) || $_GET['page'] !== 'my-local-seo' ) return;

      $script_url = rtrim(MYLS_URL, '/') . '/assets/js/myls-ai-geo.js';
      $v = time(); // hard cache-bust while building this feature
      ?>
      <script>
      window.MYLS_AI_GEO = {
        ajaxurl: "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>",
        nonce: "<?php echo esc_js( $nonce ); ?>",
        defaultType: "<?php echo esc_js( $default_pt ); ?>",
        action_get_posts: "myls_ai_geo_get_posts_v1",
        action_duplicate: "myls_ai_geo_duplicate_v1",
        action_analyze: "myls_ai_geo_analyze_v1",
        action_convert: "myls_ai_geo_convert_v1"
      };
      </script>
      <script src="<?php echo esc_url( $script_url . '?v=' . $v ); ?>"></script>
      <?php
    }, 9999);
  }
];
