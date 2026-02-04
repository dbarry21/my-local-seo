<?php
/**
 * AI Subtab: FAQs Builder
 * File: admin/tabs/ai/subtab-faqs.php
 *
 * - Mirrors the GEO subtab layout/UX (2-column)
 * - Loads posts by type (includes drafts)
 * - Generates conversion-focused FAQs from:
 *    (A) on-site rendered page text (permalink fetch)
 *    (B) reputable public sources (generic service info, not business-specific)
 * - Optional external source links (target="_blank")
 * - Provides preview + download .html + download .docx
 *
 * Notes:
 * - Subtabs are auto-discovered by admin/tabs/tab-ai.php via admin/tabs/ai/subtab-*.php
 * - This subtab prints its config and loads its JS in admin_print_footer_scripts
 */

if ( ! defined('ABSPATH') ) exit;

return [
  'id'    => 'faqs',
  'label' => 'FAQs (Builder)',
  'order' => 50,
  'render'=> function () {

    // ---------------------------------------------------------------------
    // Default prompt (saved in option after first save; never "missing_template")
    // NOTE: This default is tuned for longer, AI Overview-friendly answers.
    // ---------------------------------------------------------------------
    // v2 prompt key (supports LONG/SHORT variants + AI Overview structure).
    // We keep v1 for backward compatibility, but auto-migrate if it contains the old one-line format.
    $default_prompt = get_option('myls_ai_faqs_prompt_template_v2', '');
    if ( ! is_string($default_prompt) || trim($default_prompt) === '' ) {
      $default_prompt = <<<EOT
You are an expert local SEO + conversion copywriter creating HIGH-QUALITY, IN-DEPTH FAQs for a LOCAL SERVICE PAGE.

INPUTS:
- Page Title: {{TITLE}}
- Permalink URL: {{URL}}
- Rendered page text (from the permalink): {{PAGE_TEXT}}
- Allow external source links?: {{ALLOW_LINKS}}
- Output Variant: {{VARIANT}}  (LONG or SHORT)

INTERNAL GUIDANCE (DO NOT OUTPUT THIS SECTION):
- Assume the reader is comparing 2–3 local providers and wants clarity, risks, timelines, and what to ask before hiring — not hype.
- Optimize for AI Overviews: write direct, self-contained answers with clear definitions, scannable structure, and actionable steps.
- Use “helpful next step” phrasing (implicit CTA) without sounding salesy: e.g., “A good next step is…”, “When you’re ready, ask for…”, “If you’re comparing quotes, request…”.
- Never claim business-specific facts unless explicitly present in {{PAGE_TEXT}}.

RULES (CRITICAL):
- Use {{PAGE_TEXT}} as the PRIMARY source for specifics.
- You MAY use reputable public sources for general, service-level facts (safety, maintenance, typical timelines, homeowner best practices).
- Do NOT invent business-specific facts unless explicitly present in the page text (years in business, pricing, exact service areas, guarantees, awards, licenses, ownership claims, “free estimates”, etc.).
- Keep answers written for HOMEOWNERS considering this service.
- Avoid fluff, filler, or vague generalities. Prefer concrete guidance.
- If you use public sources, include a "Sources" list at the end.

OUTPUT (STRICT):
- Return CLEAN HTML ONLY. No markdown. No backticks. No code fences.
- Allowed tags: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>, <a>.
- NO style/class/data attributes. (No id attributes needed for this FAQ output.)
- Links:
  - If {{ALLOW_LINKS}} = YES, you may include external links ONLY in the Sources list and they MUST have target="_blank".
  - If {{ALLOW_LINKS}} = NO, do not include any external links.

VARIANT RULES:
- If {{VARIANT}} = LONG:
  - Create 10–15 FAQs.
  - Each answer should typically be 90–170 words (when appropriate).
  - Each answer MUST include at least ONE bulleted list (<ul><li>…</li></ul>).
  - Include 2–3 short paragraphs before the list when it helps clarity.
  - Add one implicit-CTA “next step” sentence near the end of the answer (not every time if it feels forced, but most FAQs should have it).
- If {{VARIANT}} = SHORT:
  - Create 8–12 FAQs.
  - Each answer should typically be 45–90 words.
  - Each answer MUST include either:
    - a short bulleted list (2–4 bullets), OR
    - a tight “what to ask / what to check” checklist.
  - Include an implicit-CTA “next step” sentence in about half the FAQs.

AI OVERVIEW ELIGIBILITY REQUIREMENTS (VERY IMPORTANT):
- For each answer:
  - Start with a direct, one-sentence “best answer” that can stand alone.
  - Follow with short supporting context (1–2 short <p> blocks).
  - Include a checklist or steps in <ul>.
  - Avoid jargon; if unavoidable, define it in plain English.
  - When making a recommendation, frame it as a homeowner safeguard (“to avoid surprises…”, “to reduce risk…”).
- Prefer specificity that is safe:
  - Use “often”, “typically”, “in many cases” for general timelines/cost factors unless {{PAGE_TEXT}} provides specifics.
  - Never give exact pricing unless present in {{PAGE_TEXT}}.

REQUIRED STRUCTURE:
<h2>FAQs</h2>

For EACH FAQ, use EXACTLY this structure:

<h3>QUESTION TEXT</h3>
<p><strong>Answer:</strong> First sentence is the direct best answer. Keep it crisp and complete.</p>
<p>Short supporting explanation tailored to homeowners considering this service.</p>
<p>Additional clarity on expectations, common pitfalls, timelines, materials, or prep (as relevant).</p>
<ul>
  <li>Actionable step / checklist item</li>
  <li>Actionable step / checklist item</li>
  <li>Actionable step / checklist item</li>
</ul>
<p><em>Helpful next step:</em> A subtle, non-salesy suggestion for what the homeowner should do next (ask for, check, compare, request, confirm).</p>

After the FAQs, output:
<h2>Sources</h2>
<ul>
  <li><a href="https://example.com" target="_blank">Source Name</a></li>
</ul>

If you did not use any public sources, still output Sources with:
<ul><li><em>(No external sources used.)</em></li></ul>

Now produce the final HTML.
EOT;
    }

    // ---------------------------------------------------------------------
    // One-time migration from legacy option key (v1)
    // ---------------------------------------------------------------------
    if ( ! is_string($default_prompt) || trim($default_prompt) === '' ) {
      $legacy = get_option('myls_ai_faqs_prompt_template', '');
      if ( is_string($legacy) && trim($legacy) !== '' ) {
        // If legacy prompt enforces one-line Q/A, replace with v2 default.
        $is_legacy_one_line = (stripos($legacy, 'ONE LINE') !== false) || (stripos($legacy, '<strong>Question') !== false && stripos($legacy, '<strong>Answer') !== false);
        $migrated = $is_legacy_one_line ? $default_prompt : $legacy;
        update_option('myls_ai_faqs_prompt_template_v2', $migrated);
        $default_prompt = $migrated;
      }
    }

    // ---------------------------------------------------------------------
    // Save prompt + params
    // ---------------------------------------------------------------------
    if (
      isset($_POST['myls_ai_faqs_save']) &&
      check_admin_referer('myls_ai_faqs_save_nonce', 'myls_ai_faqs_save_nonce')
    ) {
      update_option('myls_ai_faqs_prompt_template', wp_kses_post(wp_unslash($_POST['myls_ai_faqs_prompt_template'] ?? '')));
      // Save into v2 key as the active template (keeps v1 for compatibility).
      update_option('myls_ai_faqs_prompt_template_v2', wp_kses_post(wp_unslash($_POST['myls_ai_faqs_prompt_template'] ?? '')));
      update_option('myls_ai_faqs_tokens', max(1, (int) ($_POST['myls_ai_faqs_tokens'] ?? 1200)));
      update_option('myls_ai_faqs_temperature', (float) ($_POST['myls_ai_faqs_temperature'] ?? 0.4));
      $v = strtoupper( sanitize_text_field( wp_unslash($_POST['myls_ai_faqs_variant_default'] ?? 'LONG') ) );
      if ( $v !== 'SHORT' ) $v = 'LONG';
      update_option('myls_ai_faqs_variant_default', $v);
      echo '<div class="updated notice"><p>Saved FAQs prompt template & params.</p></div>';
    }

    $prompt = get_option('myls_ai_faqs_prompt_template_v2', $default_prompt);
    if ( ! is_string($prompt) || trim($prompt) === '' ) $prompt = $default_prompt;

    $tokens = (int) get_option('myls_ai_faqs_tokens', 1200);
    $temp   = (float) get_option('myls_ai_faqs_temperature', 0.4);
    $variant_default = strtoupper( (string) get_option('myls_ai_faqs_variant_default', 'LONG') );
    if ( $variant_default !== 'SHORT' ) $variant_default = 'LONG';

    // ---------------------------------------------------------------------
    // Post type selector
    // ---------------------------------------------------------------------
    $pts = get_post_types(['public' => true], 'objects');
    unset($pts['attachment']);
    $default_pt = isset($pts['page']) ? 'page' : ( $pts ? array_key_first($pts) : 'page' );

    $nonce = wp_create_nonce('myls_ai_ops');
    ?>

<style>
  /* Selectable panes with local Ctrl+A selection (JS handles Ctrl+A) */
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
  .myls-selectbox.is-focused{
    outline:2px solid rgba(0,0,0,0.35);
    outline-offset:2px;
  }
  .myls-geo-spinner{display:none;align-items:center;gap:8px}
  .myls-geo-spinner .dashicons{animation:myls-geo-spin 0.9s linear infinite}
  @keyframes myls-geo-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
  .myls-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
</style>

<div class="myls-two-col" style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;">

  <!-- LEFT: Prompt -->
  <div style="border:1px solid #000;padding:16px;border-radius:12px;">
    <h2 class="h4" style="margin-top:0;">FAQs Prompt Template</h2>

    <form method="post">
      <?php wp_nonce_field('myls_ai_faqs_save_nonce','myls_ai_faqs_save_nonce'); ?>

      <div class="mb-3">
        <label class="form-label"><strong>Prompt Template</strong></label>
        <textarea id="myls_ai_faqs_prompt_template" name="myls_ai_faqs_prompt_template" class="widefat" rows="22"><?php echo esc_textarea($prompt); ?></textarea>
        <p class="description">
          Variables: <code>{{TITLE}}</code>, <code>{{URL}}</code>, <code>{{PAGE_TEXT}}</code>, <code>{{ALLOW_LINKS}}</code>, <code>{{VARIANT}}</code>
        </p>
      </div>

      <div class="row">
        <div class="col-6 mb-3">
          <label class="form-label">Max Tokens</label>
          <input id="myls_ai_faqs_tokens" type="number" min="1" name="myls_ai_faqs_tokens" class="regular-text form-control" value="<?php echo esc_attr($tokens); ?>" />
        </div>
        <div class="col-6 mb-3">
          <label class="form-label">Temperature</label>
          <input id="myls_ai_faqs_temperature" type="number" step="0.1" min="0" max="2" name="myls_ai_faqs_temperature" class="regular-text form-control" value="<?php echo esc_attr($temp); ?>" />
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Default Variant</label>
        <select name="myls_ai_faqs_variant_default" class="form-select" style="max-width:220px;">
          <option value="LONG" <?php selected('LONG', $variant_default); ?>>LONG (10–15 FAQs • Longer answers)</option>
          <option value="SHORT" <?php selected('SHORT', $variant_default); ?>>SHORT (8–12 FAQs • Shorter answers)</option>
        </select>
        <div class="small text-muted mt-1">Controls the default output style when generating FAQs.</div>
      </div>

      <p><button type="submit" name="myls_ai_faqs_save" class="button button-primary">Save Template & Params</button></p>
    </form>
  </div>

  <!-- RIGHT: Selector + Preview -->
  <div style="border:1px solid #000;padding:16px;border-radius:12px;">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h2 class="h4" style="margin:0;">Select Posts</h2>
      <div class="small text-muted">Processed: <span id="myls_ai_faqs_count">0</span></div>
    </div>

    <div class="row g-3 align-items-end mt-1">
      <div class="col-md-4">
        <label class="form-label">Post Type</label>
        <select id="myls_ai_faqs_pt" class="form-select">
          <?php foreach ($pts as $pt_key => $obj): ?>
            <option value="<?php echo esc_attr($pt_key); ?>" <?php selected($pt_key, $default_pt); ?>>
              <?php echo esc_html($obj->labels->singular_name . " ({$pt_key})"); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Search filter (client-side) -->
        <div class="mt-2">
          <label class="form-label" for="myls_ai_faqs_search">Search</label>
          <input
            type="text"
            id="myls_ai_faqs_search"
            class="form-control"
            placeholder="Type to filter posts…"
            autocomplete="off"
          />
          <div class="small text-muted mt-1">Filters the loaded post list (title or ID).</div>
        </div>
      </div>

      <div class="col-md-8">
        <label class="form-label">Posts</label>
        <select id="myls_ai_faqs_posts" class="form-select" multiple size="8" style="min-height:200px;"></select>
      </div>
    </div>

    <div class="mt-3 myls-actions">
      <button type="button" class="button" id="myls_ai_faqs_select_all">Select All</button>
      <button type="button" class="button" id="myls_ai_faqs_clear">Clear</button>
      <span class="small text-muted" id="myls_ai_faqs_loaded_hint"></span>
    </div>

    <div class="mt-3 myls-actions">
      <label style="display:flex;align-items:center;gap:8px;margin:0;">
        <strong>Variant</strong>
        <select id="myls_ai_faqs_variant" class="form-select" style="width:auto;min-width:140px;">
          <option value="LONG">LONG</option>
          <option value="SHORT">SHORT</option>
        </select>
      </label>

      <label style="display:flex;align-items:center;gap:8px;margin:0;">
        <input type="checkbox" id="myls_ai_faqs_allow_links">
        <strong>Allow source links (target="_blank")</strong>
      </label>

      <label style="display:flex;align-items:center;gap:8px;margin:0;">
        <input type="checkbox" id="myls_ai_faqs_skip_existing" checked>
        <strong>Skip posts with existing MYLS FAQs</strong>
      </label>

      <label style="display:flex;align-items:center;gap:8px;margin:0;">
        <input type="checkbox" id="myls_ai_faqs_acf_replace">
        <strong>Replace existing MYLS FAQs (overwrite)</strong>
      </label>

      <button type="button" class="button button-primary" id="myls_ai_faqs_generate">Generate FAQs (Preview)</button>

      <button type="button" class="button" id="myls_ai_faqs_docx" disabled>Download .docx</button>
      <button type="button" class="button" id="myls_ai_faqs_html" disabled>Download .html</button>

      <button type="button" class="button" id="myls_ai_faqs_insert_acf" disabled>Insert into MYLS FAQs</button>
      <button type="button" class="button" id="myls_ai_faqs_delete_auto" disabled>Delete Auto-Generated FAQs (MYLS)</button>

      <button type="button" class="button" id="myls_ai_faqs_stop" disabled>Stop</button>

      <span class="myls-geo-spinner" id="myls_ai_faqs_spinner">
        <span class="dashicons dashicons-update"></span>
        <span class="small text-muted">Processing…</span>
      </span>

      <span id="myls_ai_faqs_status" class="small text-muted"></span>
    </div>

    <hr/>

    <h3 class="h5">Preview (FAQs HTML)</h3>
    <div id="myls_ai_faqs_preview" class="myls-selectbox" style="min-height:180px;max-height:420px;"></div>

    <div class="mt-3">
      <h3 class="h5">Raw Output</h3>
      <pre id="myls_ai_faqs_output" class="myls-selectbox" style="white-space:pre-wrap;background:#f8f9fa;border:1px solid #ddd;min-height:140px;max-height:260px;margin:0;"></pre>
    </div>

    <div class="mt-3">
      <h3 class="h5">Results Log</h3>
      <pre id="myls_ai_faqs_results" class="myls-selectbox" style="white-space:pre-wrap;background:#f8f9fa;border:1px solid #ddd;min-height:120px;max-height:220px;margin:0;"></pre>
    </div>

  </div>
</div>

<?php
    /**
     * Print config + load JS
     * IMPORTANT: We guard this so it only binds once.
     */
    add_action('admin_print_footer_scripts', function() use ($default_pt, $nonce) {

      static $did = false;
      if ($did) return;
      $did = true;

      // Only on our plugin screen.
      if ( empty($_GET['page']) || sanitize_key($_GET['page']) !== 'my-local-seo' ) return;

      $script_url = rtrim(MYLS_URL, '/') . '/assets/js/myls-ai-faqs.js';

      // Dev-friendly cache bust; replace with MYLS_VERSION if you have it.
      $v = (defined('MYLS_VERSION') && MYLS_VERSION) ? MYLS_VERSION : (string) time();

      ?>
      <script>
      window.MYLS_AI_FAQS = {
        ajaxurl: "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>",
        nonce: "<?php echo esc_js( $nonce ); ?>",
        defaultType: "<?php echo esc_js( $default_pt ); ?>",
        defaultVariant: "<?php echo esc_js( $variant_default ); ?>",
        action_get_posts: "myls_ai_faqs_get_posts_v1",
        action_generate: "myls_ai_faqs_generate_v1",
        action_check_existing_myls: "myls_ai_faqs_check_existing_myls_v1",
        // New (MYLS native)
        action_insert_myls: "myls_ai_faqs_insert_myls_v1",
        action_delete_auto_myls: "myls_ai_faqs_delete_auto_myls_v1",

        // Back-compat (older JS expected these keys). Map them to MYLS actions.
        action_insert_acf: "myls_ai_faqs_insert_myls_v1",
        action_delete_auto_acf: "myls_ai_faqs_delete_auto_myls_v1"
      };
      </script>
      <script src="<?php echo esc_url( $script_url . '?v=' . rawurlencode($v) ); ?>"></script>
      <?php
    }, 9999);

  }
];
