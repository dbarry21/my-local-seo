<?php

add_action('admin_menu', 'mlseo_add_single_docs_submenu');
function mlseo_add_single_docs_submenu() {
    add_submenu_page(
        'my-local-seo', // ✅ Correct parent slug
        'Documentation',
        'Documentation',
        'manage_options',
        'mlseo-docs',
        'mlseo_render_docs_combined'
    );
}

function mlseo_render_docs_combined() {
    include plugin_dir_path(__FILE__) . 'docs/documentation.php';
}

/* -------------------------------------------------------------------------
 * Phase 3: Export Shortcode Reference (Markdown / HTML)
 *
 * These exports are generated at request-time by scanning /modules/shortcodes/.
 * We send the file as a download (no writes inside the plugin directory).
 * ---------------------------------------------------------------------- */

add_action('admin_post_mlseo_docs_export_shortcodes_md', 'mlseo_docs_export_shortcodes_md');
add_action('admin_post_mlseo_docs_export_shortcodes_html', 'mlseo_docs_export_shortcodes_html');
add_action('admin_post_mlseo_docs_export_pdf', 'mlseo_docs_export_pdf');

// Quick Start Progress AJAX handlers
add_action('wp_ajax_mlseo_update_quick_start_progress', 'mlseo_update_quick_start_progress');
add_action('wp_ajax_mlseo_reset_quick_start_progress', 'mlseo_reset_quick_start_progress');

function mlseo_update_quick_start_progress() {
    check_ajax_referer('mlseo_quick_start_progress');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $step = isset($_POST['step']) ? sanitize_text_field($_POST['step']) : '';
    $completed = isset($_POST['completed']) ? intval($_POST['completed']) : 0;
    
    if (empty($step)) {
        wp_send_json_error(['message' => 'Invalid step']);
    }
    
    $user_id = get_current_user_id();
    $progress = get_user_meta($user_id, 'mlseo_quick_start_progress', true);
    
    if (!is_array($progress)) {
        $progress = [];
    }
    
    if ($completed) {
        if (!in_array($step, $progress)) {
            $progress[] = $step;
        }
    } else {
        $progress = array_diff($progress, [$step]);
    }
    
    update_user_meta($user_id, 'mlseo_quick_start_progress', $progress);
    
    wp_send_json_success([
        'completed_count' => count($progress),
        'steps' => $progress
    ]);
}

function mlseo_reset_quick_start_progress() {
    check_ajax_referer('mlseo_quick_start_progress');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $user_id = get_current_user_id();
    delete_user_meta($user_id, 'mlseo_quick_start_progress');
    
    wp_send_json_success(['message' => 'Progress reset']);
}

function mlseo_docs_export_shortcodes_md() {
    if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
    check_admin_referer('mlseo_docs_export_shortcodes');

    require_once plugin_dir_path(__FILE__) . 'docs/lib/shortcodes.php';
    $items = mlseo_docs_build_shortcodes_index(true);
    $md = mlseo_docs_render_shortcodes_markdown($items);

    $filename = 'my-local-seo-shortcodes-' . gmdate('Ymd-His') . '.md';
    nocache_headers();
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $md;
    exit;
}

function mlseo_docs_export_shortcodes_html() {
    if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
    check_admin_referer('mlseo_docs_export_shortcodes');

    require_once plugin_dir_path(__FILE__) . 'docs/lib/shortcodes.php';
    $items = mlseo_docs_build_shortcodes_index(true);
    $html = mlseo_docs_render_shortcodes_html($items);

    $filename = 'my-local-seo-shortcodes-' . gmdate('Ymd-His') . '.html';
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $html;
    exit;
}

function mlseo_docs_export_pdf() {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('mlseo_docs_export_pdf');

    // Load shortcode data
    require_once plugin_dir_path(__FILE__) . 'docs/shortcode-data.php';
    $shortcodes = mlseo_compile_shortcode_documentation();

    // Generate HTML for PDF
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>My Local SEO - Shortcodes Reference</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            h1 { color: #2271b1; border-bottom: 3px solid #2271b1; padding-bottom: 10px; }
            h2 { color: #333; margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            .shortcode { margin-bottom: 40px; page-break-inside: avoid; }
            .shortcode-name { font-family: 'Courier New', monospace; font-size: 18px; font-weight: bold; color: #2271b1; }
            .category { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; margin-bottom: 10px; }
            .cat-location { background: #4CAF50; }
            .cat-services { background: #2196F3; }
            .cat-content { background: #FF9800; }
            .cat-schema { background: #9C27B0; }
            .cat-social { background: #00BCD4; }
            .cat-utility { background: #607D8B; }
            .description { margin: 10px 0; }
            .usage { background: #f5f5f5; padding: 10px; font-family: 'Courier New', monospace; margin: 10px 0; border-left: 3px solid #2271b1; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background: #f0f0f1; text-align: left; padding: 8px; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; vertical-align: top; }
            .attr-name { font-family: 'Courier New', monospace; font-weight: bold; color: #d63638; }
            .attr-default { font-family: 'Courier New', monospace; background: #f0f0f1; padding: 2px 6px; }
            .example { background: #f9f9f9; padding: 10px; margin: 5px 0; font-family: 'Courier New', monospace; font-size: 12px; }
            .tips { background: #e7f3ff; border-left: 3px solid #2196F3; padding: 10px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <h1>My Local SEO - Shortcodes Reference</h1>
        <p><strong>Generated:</strong> <?php echo date('F j, Y g:i a'); ?></p>
        <p><strong>Total Shortcodes:</strong> <?php echo count($shortcodes); ?></p>
        
        <?php
        $categories = [
            'location' => 'Location & Geography',
            'services' => 'Services & Service Areas',
            'content' => 'Content Display',
            'schema' => 'Schema & SEO',
            'social' => 'Social & Sharing',
            'utility' => 'Utility & Tools'
        ];
        
        foreach ($categories as $cat_key => $cat_label):
            $cat_shortcodes = array_filter($shortcodes, fn($sc) => $sc['category'] === $cat_key);
            if (empty($cat_shortcodes)) continue;
        ?>
        
        <h2><?php echo esc_html($cat_label); ?></h2>
        
        <?php foreach ($cat_shortcodes as $sc): ?>
            <div class="shortcode">
                <div class="shortcode-name">[<?php echo esc_html($sc['name']); ?>]</div>
                <div class="category cat-<?php echo esc_attr($sc['category']); ?>">
                    <?php echo esc_html($cat_label); ?>
                </div>
                
                <div class="description">
                    <?php echo esc_html($sc['description']); ?>
                </div>
                
                <div class="usage">
                    <strong>Basic Usage:</strong> <?php echo esc_html($sc['basic_usage']); ?>
                </div>
                
                <?php if (!empty($sc['attributes'])): ?>
                    <h3>Attributes</h3>
                    <table>
                        <thead>
                            <tr>
                                <th width="25%">Attribute</th>
                                <th width="20%">Default</th>
                                <th width="55%">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sc['attributes'] as $attr => $info): ?>
                                <tr>
                                    <td class="attr-name"><?php echo esc_html($attr); ?></td>
                                    <td><span class="attr-default"><?php echo esc_html($info['default'] ?? '—'); ?></span></td>
                                    <td><?php echo esc_html($info['description'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if (!empty($sc['examples'])): ?>
                    <h3>Examples</h3>
                    <?php foreach ($sc['examples'] as $ex): ?>
                        <div class="example">
                            <strong><?php echo esc_html($ex['label']); ?>:</strong><br>
                            <?php echo esc_html($ex['code']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($sc['tips'])): ?>
                    <div class="tips">
                        <strong>💡 Tips:</strong>
                        <ul>
                            <?php foreach ($sc['tips'] as $tip): ?>
                                <li><?php echo esc_html($tip); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // For now, output as HTML (browsers can print to PDF)
    // To generate actual PDF, you'd need a library like TCPDF or Dompdf
    $filename = 'my-local-seo-shortcodes-' . gmdate('Ymd-His') . '.html';
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $html;
    exit;
}
