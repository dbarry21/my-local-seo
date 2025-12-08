<?php
/**
 * Site Builder bootstrap (with example page support on skin import)
 */
if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------------
// Core options / flags
// -----------------------------------------------------------------------------
if (!defined('MYLS_SB_OPTION')) {
    define('MYLS_SB_OPTION', 'myls_sitebuilder_enabled');
}

/**
 * Option flag: apply global appearance mapping?
 * Toggle true/false via update_option('myls_sb_apply_skin', 1 or 0)
 */
if (!defined('MYLS_SB_APPLY_OPTION')) {
    define('MYLS_SB_APPLY_OPTION', 'myls_sb_apply_skin');
}

// -----------------------------------------------------------------------------
// Enable / disable / reset helpers
// -----------------------------------------------------------------------------

/**
 * Is the Site Builder enabled?
 */
if (!function_exists('myls_sb_is_enabled')) {
    function myls_sb_is_enabled(): bool {
        // Hard kill switch if defined in wp-config.php or elsewhere.
        if (defined('MYLS_SITEBUILDER_FORCE_OFF') && MYLS_SITEBUILDER_FORCE_OFF) {
            return false;
        }
        return (bool) get_option(MYLS_SB_OPTION, 0);
    }
}

/**
 * Central toggle for builder enable/disable.
 * Also keeps the appearance "apply skin" flag in sync.
 */
if (!function_exists('myls_sb_set_enabled')) {
    function myls_sb_set_enabled($on): void {
        $on = $on ? 1 : 0;
        update_option(MYLS_SB_OPTION, $on);
        update_option(MYLS_SB_APPLY_OPTION, $on); // keep appearance flag aligned
    }
}

/**
 * Reset imported look & feel:
 * - Stops applying global skin
 * - Clears imported skin + stored example page id
 *
 * Call this from the "Reset Style" button handler in the admin tab.
 */
if (!function_exists('myls_sb_reset_style')) {
    function myls_sb_reset_style(): void {
        // Stop applying imported skin
        update_option(MYLS_SB_APPLY_OPTION, 0);

        // Remove imported skin data
        delete_option('myls_sb_skin');

        // Remove stored example page reference
        delete_option('myls_sb_skin_example_page_id');

        // If you also want to trash the example page itself, you can
        // fetch and trash it here. Left out intentionally for safety.
    }
}

// -----------------------------------------------------------------------------
// Includes
// -----------------------------------------------------------------------------
$__myls_sb_utils = trailingslashit(MYLS_PATH) . 'inc/sitebuilder/Utils.php';
$__myls_sb_gen   = trailingslashit(MYLS_PATH) . 'inc/sitebuilder/Generator.php';
$__myls_sb_lf    = trailingslashit(MYLS_PATH) . 'inc/sitebuilder/LookAndFeel.php';

if (file_exists($__myls_sb_utils)) require_once $__myls_sb_utils;
if (file_exists($__myls_sb_gen))   require_once $__myls_sb_gen;
if (file_exists($__myls_sb_lf))    require_once $__myls_sb_lf;

// -----------------------------------------------------------------------------
// Look & Feel output – gated by builder enabled
// -----------------------------------------------------------------------------
add_action('wp_head', function () {
    if (!myls_sb_is_enabled()) {
        return;
    }
    if (class_exists('\MYLS\SiteBuilder\LookAndFeel')) {
        \MYLS\SiteBuilder\LookAndFeel::output_skin_css();
    }
}, 40);

// -----------------------------------------------------------------------------
// AI hook – generate HTML via shared MYLS AI helper
// -----------------------------------------------------------------------------
add_filter('myls_sb_ai_generate', function ($default_html, $payload) {
    if (function_exists('myls_ai_generate_html')) {
        try {
            $purpose = $payload['purpose'] ?? 'generic';
            $vars    = $payload['vars'] ?? [];
            $html    = myls_ai_generate_html($purpose, $vars);
            if (is_string($html) && strlen(trim($html)) > 0) {
                return $html;
            }
        } catch (\Throwable $e) {
            // fail silent, fall back below
        }
    }
    return '';
}, 10, 2);

// -----------------------------------------------------------------------------
// AJAX: save settings
// -----------------------------------------------------------------------------
add_action('wp_ajax_myls_sb_save_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    check_admin_referer('myls_sb_settings', '_wpnonce');

    $settings = [
        'business_name' => sanitize_text_field($_POST['business_name'] ?? ''),
        'city'          => sanitize_text_field($_POST['city'] ?? ''),
        'phone'         => sanitize_text_field($_POST['phone'] ?? ''),
        'email'         => sanitize_email($_POST['email'] ?? ''),
        'services'      => wp_kses_post($_POST['services'] ?? ''),
        'areas'         => wp_kses_post($_POST['areas'] ?? ''),
    ];

    update_option('myls_sb_settings', $settings);

    wp_send_json_success([
        'message'  => 'Saved',
        'settings' => $settings,
    ]);
});

// -----------------------------------------------------------------------------
// AJAX: generate core/bulk
// -----------------------------------------------------------------------------
add_action('wp_ajax_myls_sb_generate', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'myls_sb_generate')) {
        wp_send_json_error(['message' => 'Bad nonce'], 400);
    }
    if (!myls_sb_is_enabled()) {
        wp_send_json_error(['message' => 'Builder is disabled'], 400);
    }
    if (!class_exists('\MYLS\SiteBuilder\Generator')) {
        wp_send_json_error(['message' => 'Generator missing'], 500);
    }

    $gen    = new \MYLS\SiteBuilder\Generator();
    $result = $gen->run([
        'per_service'   => !empty($_POST['per_service']),
        'service_areas' => !empty($_POST['service_areas']),
        'blog_starters' => !empty($_POST['blog_starters']),
    ]);

    wp_send_json_success([
        'log'   => $result['log']   ?? 'Done',
        'posts' => $result['posts'] ?? [],
    ]);
});

// -----------------------------------------------------------------------------
// AJAX: generate single add-on page
// -----------------------------------------------------------------------------
add_action('wp_ajax_myls_sb_generate_single', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'myls_sb_generate_single')) {
        wp_send_json_error(['message' => 'Bad nonce'], 400);
    }
    if (!myls_sb_is_enabled()) {
        wp_send_json_error(['message' => 'Builder is disabled'], 400);
    }
    if (!class_exists('\MYLS\SiteBuilder\Generator')) {
        wp_send_json_error(['message' => 'Generator missing'], 500);
    }

    $gen    = new \MYLS\SiteBuilder\Generator();
    $result = $gen->generate_single(sanitize_text_field($_POST['type'] ?? ''), [
        'service' => sanitize_text_field($_POST['service'] ?? ''),
        'area'    => sanitize_text_field($_POST['area'] ?? ''),
        'topic'   => sanitize_text_field($_POST['topic'] ?? ''),
    ]);

    wp_send_json_success([
        'log'   => $result['log']   ?? 'Done',
        'posts' => $result['posts'] ?? [],
    ]);
});

// -----------------------------------------------------------------------------
// AJAX: generate batch
// -----------------------------------------------------------------------------
add_action('wp_ajax_myls_sb_generate_batch', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'myls_sb_generate_batch')) {
        wp_send_json_error(['message' => 'Bad nonce'], 400);
    }
    if (!myls_sb_is_enabled()) {
        wp_send_json_error(['message' => 'Builder is disabled'], 400);
    }
    if (!class_exists('\MYLS\SiteBuilder\Generator')) {
        wp_send_json_error(['message' => 'Generator missing'], 500);
    }

    $gen    = new \MYLS\SiteBuilder\Generator();
    $result = $gen->generate_batch([
        'make_services' => !empty($_POST['make_services']),
        'make_areas'    => !empty($_POST['make_areas']),
        'services_txt'  => wp_kses_post($_POST['services'] ?? ''),
        'areas_txt'     => wp_kses_post($_POST['areas'] ?? ''),
        'topics_txt'    => wp_kses_post($_POST['topics'] ?? ''),
    ]);

    wp_send_json_success([
        'log'   => $result['log']   ?? 'Done',
        'posts' => $result['posts'] ?? [],
    ]);
});

// -----------------------------------------------------------------------------
// AJAX: import look & feel (skin)
// -----------------------------------------------------------------------------
add_action('wp_ajax_myls_sb_import_skin', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'myls_sb_import_skin')) {
        wp_send_json_error(['message' => 'Bad nonce'], 400);
    }

    $url = esc_url_raw($_POST['url'] ?? '');
    if (!$url) {
        wp_send_json_error(['message' => 'Invalid URL'], 400);
    }
    if (!class_exists('\MYLS\SiteBuilder\LookAndFeel')) {
        wp_send_json_error(['message' => 'Engine missing'], 500);
    }

    $r = \MYLS\SiteBuilder\LookAndFeel::import_from_url($url);
    if (empty($r['ok'])) {
        wp_send_json_error(['message' => ($r['message'] ?? 'Import failed')], 500);
    }

    $page_id = (int) ($r['page_id'] ?? 0);
    $edit_url = $page_id ? admin_url('post.php?post=' . $page_id . '&action=edit') : '';

    wp_send_json_success([
        'message'  => $r['message'] ?? 'Imported.',
        'skin'     => $r['skin']    ?? [],
        'page_id'  => $page_id,
        'edit_url' => $edit_url,
    ]);
});

// -----------------------------------------------------------------------------
// Appearance glue (fonts + CSS vars) – gated by builder enabled
// -----------------------------------------------------------------------------

/**
 * Build a Google Fonts URL for two families (headings/body)
 */
if (!function_exists('myls_sb_google_fonts_url')) {
    function myls_sb_google_fonts_url(array $skin): string {
        $fh = trim($skin['font_head'] ?? '');
        $fb = trim($skin['font_body'] ?? '');
        if (!$fh && !$fb) return '';

        $families = [];
        foreach ([$fh, $fb] as $f) {
            if (!$f) continue;
            $families[] = str_replace(' ', '+', $f) . ':wght@400;600;700';
        }
        $family = implode('&family=', array_unique($families));

        return $family
            ? 'https://fonts.googleapis.com/css2?family=' . $family . '&display=swap'
            : '';
    }
}

/**
 * Enqueue fonts + appearance glue stylesheet.
 * - NOOP if builder is disabled
 * - Respects MYLS_SB_APPLY_OPTION for global vs scoped application
 */
add_action('wp_enqueue_scripts', function () {
    // If builder is disabled, nothing runs.
    if (!myls_sb_is_enabled()) {
        return;
    }

    $skin = get_option('myls_sb_skin', []);
    if (empty($skin) || !is_array($skin)) {
        return;
    }

    // Respect global on/off
    $apply_globally = (bool) get_option(MYLS_SB_APPLY_OPTION, 1);

    // Enqueue Google Fonts if present
    $fonts_url = myls_sb_google_fonts_url($skin);
    if ($fonts_url) {
        wp_enqueue_style('myls-sb-fonts', $fonts_url, [], null);
    }

    // Base handle (we’ll attach inline CSS to it)
    wp_register_style('myls-sb-appearance', false, [], null);
    wp_enqueue_style('myls-sb-appearance');

    // Scope: ':root' = global; otherwise use a wrapper (e.g., '.myls-imported-example').
    $scope = $apply_globally ? ':root' : '.myls-imported-example';

    $p  = esc_html($skin['primary']   ?? '#136B92');
    $s  = esc_html($skin['secondary'] ?? '#333333');
    $a  = esc_html($skin['accent']    ?? '#FFC107');
    $r  = esc_html($skin['radius']    ?? '12px');
    $fh = esc_html($skin['font_head'] ?? 'Inter');
    $fb = esc_html($skin['font_body'] ?? $fh);

    $css = "
{$scope}{
  --myls-primary: {$p};
  --myls-secondary: {$s};
  --myls-accent: {$a};
  --myls-radius: {$r};
  --myls-font-headings: \"{$fh}\", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  --myls-font-body: \"{$fb}\", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
}

/* Appearance glue (maps common elements to the imported palette/fonts) */
{$scope} body, {$scope} .myls-imported-example {
  font-family: var(--myls-font-body);
  color: var(--myls-secondary);
}

{$scope} h1, {$scope} h2, {$scope} h3, {$scope} h4, {$scope} h5, {$scope} h6 {
  font-family: var(--myls-font-headings);
  color: var(--myls-secondary);
  line-height: 1.2;
}

{$scope} a {
  color: var(--myls-primary);
  text-decoration-color: color-mix(in oklab, var(--myls-primary) 50%, white);
}
{$scope} a:hover {
  text-decoration-color: var(--myls-primary);
}

{$scope} .btn, {$scope} button, {$scope} .button {
  border-radius: var(--myls-radius);
}

{$scope} .btn-primary,
{$scope} button[type=submit],
{$scope} .wp-element-button.is-primary {
  background: var(--myls-primary) !important;
  border-color: var(--myls-primary) !important;
  color: #fff !important;
}

{$scope} .btn-outline-primary {
  color: var(--myls-primary) !important;
  border-color: var(--myls-primary) !important;
  background: transparent !important;
}

{$scope} .card,
{$scope} .wp-block-group,
{$scope} .wp-block-columns {
  border-radius: var(--myls-radius);
}

{$scope} .badge,
{$scope} .label,
{$scope} .pill {
  border-radius: calc(var(--myls-radius) / 2);
  background: var(--myls-accent);
  color: #111;
  padding: .25em .5em;
}

/* Optional: accent helpers */
{$scope} .border-accent { border-color: var(--myls-accent) !important; }
{$scope} .text-accent   { color: var(--myls-accent) !important; }
";

    wp_add_inline_style('myls-sb-appearance', $css);
}, 20);
