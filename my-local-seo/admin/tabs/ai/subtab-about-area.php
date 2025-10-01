<?php
/**
 * AI Subtab: About the Area
 * Path: admin/tabs/ai/subtab-about-area.php
 *
 * - Persistent prompt template (options)
 * - “Skip if filled” toggle
 * - PostType + multi-select
 * - Progress log + count
 * - Enqueues assets/js/myls-ai-about.js
 */

if ( ! defined('ABSPATH') ) exit;

return [
  'id'    => 'about-area',
  'label' => 'About the Area',
  'order' => 30,
  'render'=> function () {

    // Save template/params
    if ( isset($_POST['myls_ai_about_save']) && check_admin_referer('myls_ai_about_save_nonce', 'myls_ai_about_save_nonce') ) {
      update_option('myls_ai_about_prompt_template', wp_kses_post(stripslashes($_POST['myls_ai_about_prompt_template'] ?? '')));
      update_option('myls_ai_about_tokens', max(1, (int)($_POST['myls_ai_about_tokens'] ?? 600)));
      update_option('myls_ai_about_temperature', (float)($_POST['myls_ai_about_temperature'] ?? 0.7));
      echo '<div class="updated notice"><p>Saved “About the Area” template & params.</p></div>';
    }

    // Defaults
    $default_prompt = <<<EOT
You are an expert local SEO copywriter. Write a concise, skimmable “About the Area” section (400–500 words) as clean HTML for the service area: {{CITY_STATE}}.

Requirements:
- Tone: warm, helpful, trustworthy (no fluff).
- Structure: brief intro, 2–3 subsections with <h3> headings (e.g., Neighborhoods, Things to Do, Getting Around), and a short closing.
- Include locally relevant landmarks, roads, or districts when appropriate.
- Do not mention or sell any business; this is area context only.
- Use <p>, <h3>, <ul>/<li>; no inline styles.
EOT;

    $about_prompt = get_option('myls_ai_about_prompt_template', $default_prompt);
    $about_tokens = (int) get_option('myls_ai_about_tokens', 600);
    $about_temp   = (float) get_option('myls_ai_about_temperature', 0.7);

    // Post types for selector
    $pts = get_post_types(['public'=>true], 'objects');
    $default_pt = isset($pts['service_area']) ? 'service_area' : ( isset($pts['page']) ? 'page' : array_key_first($pts) );

    // Nonce shared with tab-ai.php localization
    $nonce = wp_create_nonce('myls_ai_ops');
    ?>

    <div class="myls-two-col" style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;">
      <!-- Left: Template -->
      <div style="border:1px solid #000;padding:16px;border-radius:12px;">
        <h2 class="h4" style="margin-top:0;">About the Area — Prompt Template</h2>
        <form method="post">
          <?php wp_nonce_field('myls_ai_about_save_nonce','myls_ai_about_save_nonce'); ?>
          <div class="mb-3">
            <label class="form-label"><strong>Prompt Template</strong></label>
            <textarea name="myls_ai_about_prompt_template" class="widefat" rows="12"><?php echo esc_textarea($about_prompt); ?></textarea>
            <p class="description">Use <code>{{CITY_STATE}}</code> which is taken from the post’s <em>city_state</em> field.</p>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Max Tokens</label>
              <input type="number" min="1" name="myls_ai_about_tokens" class="regular-text form-control" value="<?php echo esc_attr($about_tokens); ?>" />
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Temperature</label>
              <input type="number" step="0.1" min="0" max="2" name="myls_ai_about_temperature" class="regular-text form-control" value="<?php echo esc_attr($about_temp); ?>" />
            </div>
          </div>

          <p><button type="submit" name="myls_ai_about_save" class="button button-primary">Save Template & Params</button></p>
          <p class="description">These settings persist in options (mirrors your Meta tab behavior).</p>
        </form>
      </div>

      <!-- Right: Generator -->
      <div style="border:1px solid #000;padding:16px;border-radius:12px;">
        <h2 class="h4" style="margin-top:0;">Generate “About the Area”</h2>

        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Post Type</label>
            <select id="myls_ai_about_pt" class="form-select">
              <?php foreach ($pts as $pt_key => $obj): if ($pt_key === 'attachment') continue; ?>
                <option value="<?php echo esc_attr($pt_key); ?>" <?php selected($pt_key, $default_pt); ?>>
                  <?php echo esc_html($obj->labels->singular_name . " ({$pt_key})"); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-8">
            <label class="form-label">Select Posts</label>
            <select id="myls_ai_about_posts" class="form-select" multiple size="8" style="width:100%;"></select>
          </div>
        </div>

        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="myls_ai_about_skip_filled" checked>
          <label class="form-check-label" for="myls_ai_about_skip_filled">
            Skip posts where <code>about_the_area</code> is already filled
          </label>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button id="myls_ai_about_generate" class="button button-primary"><i class="bi bi-magic"></i> Generate for Selected</button>
          <button id="myls_ai_about_stop" class="button">Stop</button>
          <span id="myls_ai_about_status" style="margin-left:10px;"></span>
        </div>

        <hr/>
        <div>
          <h3 class="h5">Results</h3>
          <div id="myls_ai_about_results" style="background:#f8f9fa;border:1px solid #ccc;border-radius:8px;padding:12px;min-height:140px;white-space:pre-wrap;overflow:auto;"></div>
          <div class="mt-2"><strong>Processed:</strong> <span id="myls_ai_about_count">0</span></div>
        </div>
      </div>
    </div>

    <?php
    // Enqueue this subtab's JS and define config before it runs
add_action('admin_print_footer_scripts', function() {
	if ( empty($_GET['page']) || $_GET['page'] !== 'my-local-seo' ) return;

	// 1) Define config + log it so we know this block ran
	$nonce       = wp_create_nonce('myls_ai_ops');
	$pts         = get_post_types([], 'objects');
	$default_pt  = isset($pts['service_area']) ? 'service_area' : ( isset($pts['page']) ? 'page' : array_key_first($pts) );
	?>
	<script>
	window.MYLS_AI_ABOUT = {
	  ajaxurl: "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>",
	  nonce:   "<?php echo esc_js( $nonce ); ?>",
	  defaultType: "<?php echo esc_js( $default_pt ); ?>"
	};
	console.log('[MYLS AI ABOUT] inline config set:', window.MYLS_AI_ABOUT);
	</script>
	<?php

	// 2) Build a bulletproof URL to /assets/js/myls-ai-about.js and verify file_exists
	$script_rel   = 'assets/js/myls-ai-about.js';

	// Prefer constant if your main plugin defines it
	if ( defined('MYLS_URL') ) {
		$base_url = rtrim(MYLS_URL, '/');
	} elseif ( function_exists('myls_plugin_base_url') ) {
		$base_url = rtrim( myls_plugin_base_url(), '/' );
	} else {
		// Fallback: guess from plugin root file name
		$maybe_root = dirname(__DIR__, 2) . '/my-local-seo.php';
		$base_url   = rtrim( plugins_url('', $maybe_root), '/' );
	}

	$script_url  = $base_url . '/' . $script_rel;

	// Compute disk path to test file_exists()
	$plugin_root = dirname(__DIR__, 2); // plugin/
	$script_path = $plugin_root . '/' . $script_rel;
	$file_ok     = file_exists($script_path) ? 'YES' : 'NO';

	// 3) Log the final URL + filesystem check
	?>
	<script>
	console.log('[MYLS AI ABOUT] expecting JS at:', "<?php echo esc_js( $script_url ); ?>", 'file_exists=<?php echo esc_js( $file_ok ); ?>');
	</script>
	<?php

	// 4) TEMP: force-insert a raw <script> tag so we bypass any enqueue/path issues
	//    (Keep this while debugging; we can revert to wp_enqueue_script after it works.)
	echo '<script src="'. esc_url( $script_url . '?v=' . (defined('MYLS_VERSION') ? MYLS_VERSION : time()) ) .'"></script>';

	// 5) Tiny inline ping so you see "file loaded" IF the external JS loads
	echo "<script>console.log('[MYLS AI ABOUT] external file tag inserted');</script>";
});


  }
];
