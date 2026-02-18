<?php
/**
 * AI Subtab: Page Builder
 * Path: admin/tabs/ai/subtab-page-builder.php
 *
 * Create any page or post type with AI-generated content.
 */
if ( ! defined('ABSPATH') ) exit;

return [
    'id'    => 'page-builder',
    'label' => 'Page Builder',
    'icon'  => 'bi-file-earmark-plus',
    'order' => 70,
    'render'=> function () {

        // All public + custom post types
        $pts = get_post_types( ['public' => true], 'objects' );
        unset($pts['attachment']);

        // Business vars from Site Builder settings (or fallback)
        $sb = get_option('myls_sb_settings', []);
        $biz_name = $sb['business_name'] ?? get_bloginfo('name');
        $biz_city = $sb['city'] ?? '';
        $biz_phone = $sb['phone'] ?? '';
        $biz_email = $sb['email'] ?? get_bloginfo('admin_email');

        // Default prompt
        $saved_prompt = get_option('myls_pb_prompt_template', '');

        $nonce = wp_create_nonce('myls_pb_create');
        ?>

        <div style="display:grid; grid-template-columns:1fr 2fr; gap:20px;">

            <!-- ═══════════ LEFT: Page Setup ═══════════ -->
            <div style="border:1px solid #000; padding:16px; border-radius:1em;">
                <h4 class="mb-3"><i class="bi bi-file-earmark-plus"></i> Page Setup</h4>

                <label class="form-label fw-bold">Post Type</label>
                <select id="myls_pb_post_type" class="form-select mb-3">
                    <?php foreach ($pts as $pt => $obj): ?>
                        <option value="<?php echo esc_attr($pt); ?>" <?php selected($pt, 'page'); ?>>
                            <?php echo esc_html($obj->labels->singular_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label fw-bold">Page Title <span class="text-danger">*</span></label>
                <input type="text" id="myls_pb_title" class="form-control mb-3"
                       placeholder="e.g., Intelligize Chat – AI-Powered Chat Plugin">

                <label class="form-label fw-bold">Description / Instructions</label>
                <textarea id="myls_pb_description" class="form-control mb-1" rows="8"
                          placeholder="Describe what this page is about. The more detail you give, the better the AI output.&#10;&#10;Example:&#10;Intelligize Chat is a WordPress plugin that adds an AI-powered chat widget. Highlight features like knowledge base training, customizable appearance, lead capture, and easy setup. Target audience: small business owners. Include a CTA to download."></textarea>
                <div class="form-text mb-3">Key features, target audience, tone, page structure — whatever helps the AI.</div>

                <hr class="my-3">

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">Status</label>
                        <select id="myls_pb_status" class="form-select">
                            <option value="draft" selected>Draft</option>
                            <option value="publish">Publish</option>
                        </select>
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="myls_pb_menu" checked>
                            <label class="form-check-label" for="myls_pb_menu">Add to Main Menu</label>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <h5 class="mb-2">Business Variables</h5>
                <p class="form-text mt-0 mb-2">Auto-filled from Site Builder settings. Edit here for this session only.</p>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Business Name</label>
                        <input type="text" id="myls_pb_biz_name" class="form-control form-control-sm"
                               value="<?php echo esc_attr($biz_name); ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">City, State</label>
                        <input type="text" id="myls_pb_biz_city" class="form-control form-control-sm"
                               value="<?php echo esc_attr($biz_city); ?>">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Phone</label>
                        <input type="text" id="myls_pb_biz_phone" class="form-control form-control-sm"
                               value="<?php echo esc_attr($biz_phone); ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Email</label>
                        <input type="text" id="myls_pb_biz_email" class="form-control form-control-sm"
                               value="<?php echo esc_attr($biz_email); ?>">
                    </div>
                </div>

                <input type="hidden" id="myls_pb_nonce" value="<?php echo esc_attr($nonce); ?>">
            </div>

            <!-- ═══════════ RIGHT: Prompt + Results ═══════════ -->
            <div style="border:1px solid #000; padding:16px; border-radius:1em;">
                <h4 class="mb-2"><i class="bi bi-robot"></i> AI Content Generation</h4>
                <p class="mb-3" style="color:#555;">
                    Tokens: <code>{{PAGE_TITLE}}</code> <code>{{DESCRIPTION}}</code>
                    <code>{{BUSINESS_NAME}}</code> <code>{{CITY}}</code>
                    <code>{{PHONE}}</code> <code>{{EMAIL}}</code>
                    <code>{{SITE_NAME}}</code> <code>{{SITE_URL}}</code> <code>{{POST_TYPE}}</code>
                </p>

                <div class="card mb-3" style="border:1px solid #ddd;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>AI Prompt Template</strong>
                            <div class="d-flex gap-1">
                                <button type="button" class="button button-secondary" id="myls_pb_reset_prompt">Reset to Default</button>
                                <button type="button" class="button button-primary" id="myls_pb_save_prompt">Save Template</button>
                            </div>
                        </div>
                        <textarea id="myls_pb_prompt" class="form-control font-monospace" rows="12"
                                  style="font-size:12px;"><?php echo esc_textarea($saved_prompt); ?></textarea>
                        <small style="color:#666;">Saved to: <code>myls_pb_prompt_template</code> · Leave blank to use the built-in default.</small>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="button button-primary button-hero" id="myls_pb_create_btn">
                        <i class="bi bi-lightning-charge"></i> Create Page with AI
                    </button>
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0 fw-bold">Results</label>
                    <span id="myls_pb_edit_link" style="display:none;">
                        <a id="myls_pb_edit_url" href="#" target="_blank" class="button button-secondary">
                            <i class="bi bi-pencil-square"></i> Edit Page
                        </a>
                    </span>
                </div>
                <pre id="myls_pb_log"
                     style="min-height:140px; max-height:360px; overflow:auto; background:#f9f9f9;
                            border:1px solid #ddd; border-radius:8px; padding:12px;
                            white-space:pre-wrap; font-size:12px;"></pre>
            </div>

        </div>

        <script>
        (function(){
            const $ = (id) => document.getElementById(id);

            const defaultPrompt = `Create a professional, SEO-optimized WordPress page for "{{PAGE_TITLE}}".

Business: {{BUSINESS_NAME}} in {{CITY}}
Phone: {{PHONE}} | Email: {{EMAIL}}

Page Description & Instructions:
{{DESCRIPTION}}

Requirements:
- Write clean, semantic HTML using Bootstrap 5 classes
- Include an engaging hero section with a clear headline and subheading
- Add 3-5 content sections covering key features or benefits
- Include a strong call-to-action section at the bottom
- Use <section>, <h2>, <h3>, <p>, <ul>, <li> tags — NO markdown
- Make it locally relevant and SEO-friendly
- Output raw HTML only, no code fences or explanation`;

            // Init prompt textarea
            const promptEl = $('myls_pb_prompt');
            if (promptEl && !promptEl.value.trim()) {
                promptEl.value = defaultPrompt;
            }

            // Reset to default
            $('myls_pb_reset_prompt')?.addEventListener('click', () => {
                promptEl.value = defaultPrompt;
            });

            // Save prompt template
            $('myls_pb_save_prompt')?.addEventListener('click', async () => {
                const fd = new FormData();
                fd.append('action', 'myls_pb_save_prompt');
                fd.append('prompt_template', promptEl.value);
                fd.append('_wpnonce', $('myls_pb_nonce').value);
                try {
                    const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const data = await res.json();
                    alert(data?.success ? 'Prompt template saved.' : (data?.data?.message || 'Error saving.'));
                } catch(e) { alert('Error: ' + e.message); }
            });

            // ── Create Page ─────────────────────────────────────────────
            $('myls_pb_create_btn')?.addEventListener('click', async () => {
                const title = $('myls_pb_title').value.trim();
                if (!title) { alert('Please enter a Page Title.'); $('myls_pb_title').focus(); return; }

                const logEl = $('myls_pb_log');
                const btn   = $('myls_pb_create_btn');
                const editLink = $('myls_pb_edit_link');

                logEl.textContent = '⏳ Generating content with AI… this may take 15-30 seconds.';
                editLink.style.display = 'none';
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating…';

                const fd = new FormData();
                fd.append('action',           'myls_pb_create_page');
                fd.append('_wpnonce',         $('myls_pb_nonce').value);
                fd.append('page_title',       title);
                fd.append('post_type',        $('myls_pb_post_type').value);
                fd.append('page_status',      $('myls_pb_status').value);
                fd.append('page_description', $('myls_pb_description').value);
                fd.append('prompt_template',  promptEl.value);
                fd.append('add_to_menu',      $('myls_pb_menu').checked ? '1' : '0');

                try {
                    const res  = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data?.success) {
                        logEl.textContent = data.data.log || data.data.message || 'Done.';
                        if (data.data.edit_url) {
                            $('myls_pb_edit_url').href = data.data.edit_url;
                            editLink.style.display = '';
                        }
                    } else {
                        logEl.textContent = '❌ ' + (data?.data?.message || 'Unknown error.');
                    }
                } catch(e) {
                    logEl.textContent = '❌ Network error: ' + e.message;
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-lightning-charge"></i> Create Page with AI';
                }
            });
        })();
        </script>
        <?php
    }
];
