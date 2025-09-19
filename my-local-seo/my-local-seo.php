<?php
/**
 * Plugin Name: My Local SEO
 * Description: Modular local SEO toolkit with YouTube → Video drafts, shortcodes, and admin tabs.
 * Version: 1.1.0
 * Author: You
 * Text Domain: my-local-seo
 */

if ( ! defined('ABSPATH') ) exit;

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
define('MYLS_VERSION',   '1.1.0');
define('MYLS_PATH',       plugin_dir_path(__FILE__));
define('MYLS_URL',        plugin_dir_url(__FILE__));
define('MYLS_BASENAME',   plugin_basename(__FILE__));

// -----------------------------------------------------------------------------
// Core / Assets / Admin bootstraps
// -----------------------------------------------------------------------------
require_once MYLS_PATH . 'inc/core.php';                // defines myls_register_admin_tab(), myls_get_tabs_ordered()
require_once MYLS_PATH . 'inc/assets.php';
require_once MYLS_PATH . 'inc/admin.php';               // renderer only (no duplicate register function)
require_once MYLS_PATH . 'inc/admin-tabs-loader.php';   // <-- loads admin/tabs/* immediately
// ...the rest (ajax, cpt loader, schema, etc.)

// -----------------------------------------------------------------------------
// Admin AJAX helpers (generic; includes CPT tools like flush-rewrites, checks)
// -----------------------------------------------------------------------------
require_once MYLS_PATH . 'inc/admin-ajax.php';

// -----------------------------------------------------------------------------
// CPT Module Loader (loads one-file-per-CPT from modules/cpt/*)
//   - Each module registers itself on `init` IF enabled via options
//   - The Admin Tab (tab-cpt.php) discovers specs separately using MYLS_CPT_DISCOVERY
// -----------------------------------------------------------------------------
require_once MYLS_PATH . 'inc/load-cpt-modules.php';

require_once MYLS_PATH . 'inc/schema/helpers.php';
require_once MYLS_PATH . 'inc/schema/registry.php';

require_once MYLS_PATH . 'inc/assets.php';

// -----------------------------------------------------------------------------
// Helper: Include all modules in a directory, excluding specific subfolders.
// Avoids double-loading CPTs since those are handled by load-cpt-modules.php.
// -----------------------------------------------------------------------------
if ( ! function_exists('myls_include_dir_excluding') ) {
    /**
     * Recursively include all *.php files under $dir, skipping folders in $exclude_dirs.
     * @param string $dir
     * @param array  $exclude_dirs  Folder basenames to exclude (e.g., ['cpt'])
     * @return void
     */
    function myls_include_dir_excluding( $dir, $exclude_dirs = array() ) {
        $dir = trailingslashit( $dir );
        if ( ! is_dir($dir) || ! is_readable($dir) ) return;

        $items = scandir($dir);
        if ( ! $items ) return;

        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) continue;

            $path = $dir . $item;

            if ( is_dir($path) ) {
                if ( in_array( $item, $exclude_dirs, true ) ) {
                    continue; // skip excluded subdir entirely
                }
                myls_include_dir_excluding( $path, $exclude_dirs );
                continue;
            }

            // Include PHP files only
            if ( substr($item, -4) === '.php' ) {
                include_once $path;
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Load non-CPT modules (shortcodes, helpers, widgets, etc.)
//   - We exclude 'cpt' subfolder to prevent double-includes
// -----------------------------------------------------------------------------
myls_include_dir_excluding( MYLS_PATH . 'modules', array('cpt') );

// -----------------------------------------------------------------------------
// Activation/Deactivation: flush rewrites so newly enabled CPTs work immediately
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, function(){
    // Load CPT modules so their rules are registered on activation
    // (They respect enabled/disabled options; first install has none enabled.)
    if ( file_exists(MYLS_PATH . 'inc/load-cpt-modules.php') ) {
        include_once MYLS_PATH . 'inc/load-cpt-modules.php';
    }
    flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function(){
    flush_rewrite_rules();
});

// -----------------------------------------------------------------------------
// Optional: Plugin row links (settings quick link) — adjust slug if needed
// -----------------------------------------------------------------------------
add_filter('plugin_action_links_' . MYLS_BASENAME, function( $links ) {
    $url = admin_url('admin.php?page=my-local-seo'); // make sure this matches your admin slug
    $links[] = '<a href="' . esc_url($url) . '">'. esc_html__('Settings', 'my-local-seo') .'</a>';
    return $links;
});
