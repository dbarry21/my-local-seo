<?php if ( ! defined('ABSPATH') ) exit;

return [
  'id'    => 'excerpt',
  'label' => 'Excerpts',
  'icon'  => 'bi-file-text',
  'order' => 20,
  'render'=> function () {

    $pts = get_post_types( ['public' => true], 'objects' );
    unset($pts['attachment']);
    $default_pt = isset($pts['page']) ? 'page' : ( $pts ? array_key_first($pts) : 'page' );

    // ---------- FACTORY DEFAULT TEMPLATES ----------

    $default_excerpt_prompt = <<<EOT
You are an SEO assistant. Write a concise page excerpt for the page below.
Requirements:
- 1–2 short sentences (aim 20–40 words)
- Describe what the page is about and who it helps
- If a location is obvious, include it once
- Avoid fluff, quotes, and ALL CAPS
Inputs:
- Page Title: {post_title}
- Site Name: {site_name}
- Current Excerpt: {excerpt}
- Primary Category: {primary_category}
- URL: {permalink}
Output: Excerpt only.
EOT;

    $default_html_excerpt_prompt = function_exists('myls_ai_default_html_excerpt_prompt')
        ? myls_ai_default_html_excerpt_prompt()
        : <<<EOT
You are an SEO copywriter. Write a concise HTML excerpt for the page below.
Requirements:
- 2–4 short sentences (aim 40–80 words)
- Use basic HTML: <p>, <strong>, <em> tags only
- Describe what the page is about and who it helps
- If a location is obvious, include it naturally
- Avoid fluff, quotes, and ALL CAPS
- Output HTML only, no markdown, no code fences
Inputs:
- Page Title: {post_title}
- Site Name: {site_name}
- Current WP Excerpt: {excerpt}
- Primary Category: {primary_category}
- City/State: {city_state}
- URL: {permalink}
Output: HTML excerpt only.
EOT;

    // ---------- LOAD SAVED (PERSISTENT) VALUES ----------
    $saved_excerpt_prompt      = get_option('myls_ai_prompt_excerpt', $default_excerpt_prompt);
    $saved_html_excerpt_prompt = get_option('myls_ai_prompt_html_excerpt', $default_html_excerpt_prompt);

    // Preload initial posts
    $initial_posts = get_posts([
      'post_type'       => $default_pt,
      'post_status'     => ['publish','draft','pending','future','private'],
      'posts_per_page'  => 300,
      'orderby'         => 'title',
      'order'           => 'ASC',
      'fields'          => 'ids',
      'suppress_filters'=> true,
    ]);

    $nonce = wp_create_nonce('myls_ai_ops');
    ?>
    <div class="myls-three-col" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">

      <!-- =============== Column 1: Select Posts =============== -->
      <div class="myls-left" style="border:1px solid #000;padding:16px;border-radius:1em;">
        <h4 class="mb-3">Select Posts</h4>

        <label class="form-label">Post Type</label>
        <select id="myls_ai_ex_pt" class="form-select">
          <?php foreach ($pts as $pt => $o): ?>
            <option value="<?php echo esc_attr($pt); ?>" <?php selected($pt, $default_pt); ?>>
              <?php echo esc_html($o->labels->singular_name); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="form-label mt-3">Search</label>
        <input type="text" id="myls_ai_ex_filter" class="form-control" placeholder="Filter posts...">

        <label class="form-label mt-3">Posts (multi-select)</label>
        <select id="myls_ai_ex_posts" class="form-select" multiple size="12" aria-label="Select multiple posts">
          <?php
            foreach ($initial_posts as $pid) {
              $title = get_the_title($pid) ?: '(no title)';
              printf('<option value="%d">%s</option>', (int)$pid, esc_html($title));
            }
          ?>
        </select>

        <div class="d-flex gap-2 mt-2">
          <button type="button" class="button" id="myls_ai_ex_select_all">Select All</button>
          <button type="button" class="button" id="myls_ai_ex_clear">Clear</button>
        </div>

        <hr class="my-3">

        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="myls_ai_ex_overwrite" checked>
          <label class="form-check-label" for="myls_ai_ex_overwrite">Overwrite existing</label>
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="myls_ai_ex_dryrun">
          <label class="form-check-label" for="myls_ai_ex_dryrun">Dry-run (preview only)</label>
        </div>

        <input type="hidden" id="myls_ai_ex_nonce" value="<?php echo esc_attr($nonce); ?>">
      </div>

      <!-- =============== Column 2: AI Actions (WP Excerpt) =============== -->
      <div class="myls-center" style="border:1px solid #000;padding:16px;border-radius:1em;">
        <h4 class="mb-2">AI Actions</h4>
        <p class="mb-3" style="color:#555;font-size:12px;">
          Generates the standard WP <code>post_excerpt</code>.<br>
          Placeholders: <code>{post_title}</code>, <code>{site_name}</code>, <code>{excerpt}</code>, <code>{primary_category}</code>, <code>{permalink}</code>.
        </p>

        <div class="card mb-3" style="border:1px solid #ddd;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2" style="gap:8px;">
              <strong>Excerpt Prompt Template</strong>
              <div>
                <button type="button" class="button button-secondary" id="myls_ai_ex_reset_prompt" data-default="<?php echo esc_attr($default_excerpt_prompt); ?>">Reset</button>
                <button type="button" class="button button-primary" id="myls_ai_ex_save_prompt">Save</button>
              </div>
            </div>
            <textarea id="myls_ai_ex_prompt" class="form-control" rows="8"><?php echo esc_textarea($saved_excerpt_prompt); ?></textarea>
            <small style="color:#666;">Option: <code>myls_ai_prompt_excerpt</code></small>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-2">
          <button class="button button-primary" id="myls_ai_ex_gen">Generate Excerpts</button>
        </div>

        <hr>
        <label class="form-label mt-2">Results</label>
        <pre id="myls_ai_ex_results" style="min-height:100px;max-height:280px;overflow:auto;background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:10px;white-space:pre-wrap;font-size:11px;"></pre>
      </div>

      <!-- =============== Column 3: HTML Excerpt Actions =============== -->
      <div class="myls-right" style="border:1px solid #000;padding:16px;border-radius:1em;">
        <h4 class="mb-2">HTML Excerpt Actions</h4>
        <p class="mb-3" style="color:#555;font-size:12px;">
          Generates the <code>html_excerpt</code> meta field used by <code>[service_area_grid]</code>.<br>
          Extra placeholder: <code>{city_state}</code>.
        </p>

        <div class="card mb-3" style="border:1px solid #ddd;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2" style="gap:8px;">
              <strong>HTML Excerpt Prompt</strong>
              <div>
                <button type="button" class="button button-secondary" id="myls_ai_hex_reset_prompt" data-default="<?php echo esc_attr($default_html_excerpt_prompt); ?>">Reset</button>
                <button type="button" class="button button-primary" id="myls_ai_hex_save_prompt">Save</button>
              </div>
            </div>
            <textarea id="myls_ai_hex_prompt" class="form-control" rows="8"><?php echo esc_textarea($saved_html_excerpt_prompt); ?></textarea>
            <small style="color:#666;">Option: <code>myls_ai_prompt_html_excerpt</code></small>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-2">
          <button class="button button-primary" id="myls_ai_hex_gen">Generate HTML Excerpts</button>
        </div>

        <hr>
        <label class="form-label mt-2">Results</label>
        <pre id="myls_ai_hex_results" style="min-height:100px;max-height:280px;overflow:auto;background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:10px;white-space:pre-wrap;font-size:11px;"></pre>
      </div>

    </div>

    <!-- JSON bootstrap for initial posts list -->
    <script type="application/json" id="myls_ai_ex_bootstrap_posts">
      <?php
        echo wp_json_encode([
          'post_type' => $default_pt,
          'posts'     => array_map(function($pid){
            return ['id' => (int)$pid, 'title' => get_the_title($pid) ?: '(no title)'];
          }, $initial_posts),
        ]);
      ?>
    </script>
    <?php
  }
];
