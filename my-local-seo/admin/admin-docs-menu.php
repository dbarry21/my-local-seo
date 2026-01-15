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
