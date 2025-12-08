<?php
/**
 * Safe appearance glue loader (optional)
 * Place in inc/sitebuilder/bootstrap-appearance.php and require_once it from your main plugin file.
 *
 * - Only runs when the Site Builder is enabled (myls_sb_is_enabled() === true)
 * - Respects MYLS_SB_APPLY_OPTION (global vs scoped application)
 */
if (!defined('ABSPATH')) exit;

if (!defined('MYLS_SB_APPLY_OPTION')) {
    define('MYLS_SB_APPLY_OPTION', 'myls_sb_apply_skin');
}

/**
 * Build a Google Fonts URL for two families (headings/body)
 * Shared helper; defined only once even if bootstrap.php also declares it.
 */
if (!function_exists('myls_sb_google_fonts_url')) {
    function myls_sb_google_fonts_url(array $skin): string {
        $fh = trim($skin['font_head'] ?? '');
        $fb = trim($skin['font_body'] ?? '');
        if (!$fh && !$fb) return '';

        $families = [];
        foreach ([$fh, $fb] as $f) {
            if (!$f) continue;
            // Simple family + weights declaration
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
 * - NOOP if builder is disabled (when helper exists)
 * - NOOP if no skin imported
 * - Global vs scoped via MYLS_SB_APPLY_OPTION
 */
add_action('wp_enqueue_scripts', function () {

    // If the helper exists and the builder is disabled, bail completely.
    if (function_exists('myls_sb_is_enabled') && !myls_sb_is_enabled()) {
        return;
    }

    $skin = get_option('myls_sb_skin', []);
    if (empty($skin) || !is_array($skin)) {
        return;
    }

    // Respect global on/off; default off for safety
    $apply_globally = (bool) get_option(MYLS_SB_APPLY_OPTION, 0);

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
  --myls-primary:{$p};
  --myls-secondary:{$s};
  --myls-accent:{$a};
  --myls-radius:{$r};
  --myls-font-headings:\"{$fh}\",system-ui,sans-serif;
  --myls-font-body:\"{$fb}\",system-ui,sans-serif;
}

/* Appearance glue (maps common elements to the imported palette/fonts) */
{$scope} body,
{$scope} .myls-imported-example {
  font-family: var(--myls-font-body);
  color: var(--myls-secondary);
}

{$scope} h1,
{$scope} h2,
{$scope} h3,
{$scope} h4,
{$scope} h5,
{$scope} h6 {
  font-family: var(--myls-font-headings);
  color: var(--myls-secondary);
  line-height: 1.2;
}

{$scope} a {
  color: var(--myls-primary);
}

{$scope} .btn,
{$scope} button,
{$scope} .button {
  border-radius: var(--myls-radius);
}

{$scope} .btn-primary,
{$scope} button[type=submit] {
  background: var(--myls-primary) !important;
  border-color: var(--myls-primary) !important;
  color:#fff !important;
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
";

    wp_add_inline_style('myls-sb-appearance', $css);
}, 20);
