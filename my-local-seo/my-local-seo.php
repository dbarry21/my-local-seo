<?php
/**
 * Plugin Name: My Local SEO
 * Description: Modular local SEO toolkit with YouTube → Video drafts, shortcodes, and admin tabs.
 * Version: 3.5
 * Author: Dave Barry
 * Text Domain: my-local-seo
 */

if ( ! defined('ABSPATH') ) exit;

/** ─────────────────────────────────────────────────────────────────────────
 * Canonical constants & helpers (single source of truth)
 * ───────────────────────────────────────────────────────────────────────── */
if ( ! defined('MYLS_VERSION') )     define('MYLS_VERSION', '2.0'); // keep in sync with header
if ( ! defined('MYLS_MAIN_FILE') )   define('MYLS_MAIN_FILE', __FILE__);
if ( ! defined('MYLS_PATH') )        define('MYLS_PATH', plugin_dir_path(MYLS_MAIN_FILE));
if ( ! defined('MYLS_URL') )         define('MYLS_URL',  plugins_url('', MYLS_MAIN_FILE));
if ( ! defined('MYLS_BASENAME') )    define('MYLS_BASENAME', plugin_basename(MYLS_MAIN_FILE));

/** (Optional) legacy aliases used elsewhere in the codebase */
if ( ! defined('MYLS_PLUGIN_FILE') )     define('MYLS_PLUGIN_FILE', MYLS_MAIN_FILE);
if ( ! defined('MYLS_PLUGIN_DIR') )      define('MYLS_PLUGIN_DIR', MYLS_PATH);
if ( ! defined('MYLS_PLUGIN_URL') )      define('MYLS_PLUGIN_URL', trailingslashit(MYLS_URL) . '');
if ( ! defined('MYLS_PLUGIN_BASENAME') ) define('MYLS_PLUGIN_BASENAME', MYLS_BASENAME);
if ( ! defined('MYLS_PLUGIN_VERSION') )  define('MYLS_PLUGIN_VERSION', MYLS_VERSION);

/** Debug toggles (as in your original) */
if ( ! defined('MYLS_SCHEMA_DEBUG') ) define('MYLS_SCHEMA_DEBUG', true);
if ( ! defined('MYLS_DEBUG_ORG') )    define('MYLS_DEBUG_ORG', true);
if ( ! defined('MYLS_DEBUG_LB') )     define('MYLS_DEBUG_LB', true);

/** Helpers */
if ( ! function_exists('myls_asset_url') ) {
	function myls_asset_url(string $rel): string { return trailingslashit(MYLS_URL) . ltrim($rel, '/'); }
}
if ( ! function_exists('myls_asset_path') ) {
	function myls_asset_path(string $rel): string { return trailingslashit(MYLS_PATH) . ltrim($rel, '/'); }
}
if ( ! function_exists('myls_is_our_admin_page') ) {
	function myls_is_our_admin_page(): bool { return is_admin() && isset($_GET['page']) && $_GET['page'] === 'my-local-seo'; }
}

/** ─────────────────────────────────────────────────────────────────────────
 * Core + loaders
 * ───────────────────────────────────────────────────────────────────────── */
require_once MYLS_PATH . 'inc/core.php';
require_once MYLS_PATH . 'inc/admin-tabs-loader.php';
require_once trailingslashit(MYLS_PATH).'inc/sitebuilder/bootstrap.php';
require_once trailingslashit(MYLS_PATH).'inc/sitebuilder/bootstrap-appearance.php';


/** Optional shim if you still have older renderers */
if ( ! function_exists('myls_get_tabs_ordered') && function_exists('myls_get_admin_tabs') ) {
	function myls_get_tabs_ordered() { return myls_get_admin_tabs(); }
}

/** Ensure discovery runs early in admin (guarded within the loader) */
if ( function_exists('myls_load_all_admin_tabs') ) {
	add_action('admin_init', 'myls_load_all_admin_tabs', 1);
}

/** Admin renderer (uses myls_get_admin_tabs() internally) */
require_once MYLS_PATH . 'inc/admin.php';

/** Assets */
require_once MYLS_PATH . 'inc/assets.php';
require_once MYLS_PATH . 'inc/blog-prefix.php';

/** CPT registration BEFORE module extras */
require_once MYLS_PATH . 'inc/cpt-registration.php';
require_once MYLS_PATH . 'inc/faq-schemas.php';
require_once MYLS_PATH . 'inc/service-area-city-state.php';

/** Admin AJAX + admin bar */
require_once MYLS_PATH . 'inc/admin-ajax.php';
require_once MYLS_PATH . 'inc/admin-bar-menu.php';

/** CPT extras AFTER registration */
require_once MYLS_PATH . 'inc/load-cpt-modules.php';
require_once MYLS_PATH . 'inc/tools/inherit-city-state.php';


/** Schema */
require_once MYLS_PATH . 'inc/schema/helpers.php';
require_once MYLS_PATH . 'inc/schema/registry.php';
require_once MYLS_PATH . 'inc/schema/providers/organization.php';
require_once MYLS_PATH . 'inc/schema/providers/localbusiness.php';
require_once MYLS_PATH . 'inc/schema/providers/build-service-schema.php';
require_once MYLS_PATH . 'inc/schema/providers/video-archive.php';
require_once MYLS_PATH . 'inc/schema/providers/video-schema.php';
require_once MYLS_PATH . 'admin/api-integration-tests.php';
require_once MYLS_PATH . 'inc/schema/providers/video-collection-head.php';
require_once MYLS_PATH . 'inc/schema/providers/faq.php';
require_once MYLS_PATH . 'inc/schema/providers/blog-posting.php';
require_once MYLS_PATH . 'inc/schema/localbusiness-sync.php';


/** AI plumbing (keep if files exist; otherwise comment these two lines) */
require_once MYLS_PATH . 'inc/ajax/ai.php';
require_once MYLS_PATH . 'inc/ajax/ai-about.php';
require_once MYLS_PATH . 'inc/openai.php';
require_once MYLS_PATH . 'inc/ajax/ai-geo.php';

/** Updater */
require_once MYLS_PATH . 'update-plugin.php';

/** Include non-CPT modules (skip modules/cpt) */
if ( ! function_exists('myls_include_dir_excluding') ) {
	function myls_include_dir_excluding( $dir, $exclude_dirs = array() ) {
		$dir = trailingslashit( $dir );
		if ( ! is_dir($dir) || ! is_readable($dir) ) return;
		foreach ( scandir($dir) ?: [] as $item ) {
			if ( $item === '.' || $item === '..' ) continue;
			$path = $dir . $item;
			if ( is_dir($path) ) {
				if ( in_array( $item, $exclude_dirs, true ) ) continue;
				myls_include_dir_excluding( $path, $exclude_dirs );
				continue;
			}
			if ( substr($item, -4) === '.php' ) include_once $path;
		}
	}
}
myls_include_dir_excluding( MYLS_PATH . 'modules', array('cpt') );

/** Activation/Deactivation */
register_activation_hook( __FILE__, 'myls_activate_register_cpts_and_flush' );
register_deactivation_hook( __FILE__, function(){ flush_rewrite_rules(); });

/** Plugin row “Settings” link */
add_filter('plugin_action_links_' . MYLS_BASENAME, function( $links ) {
	$url = admin_url('admin.php?page=my-local-seo');
	$links[] = '<a href="' . esc_url($url) . '">'. esc_html__('Settings', 'my-local-seo') .'</a>';
	return $links;
});

/** ─────────────────────────────────────────────────────────────────────────
 * Admin CSS — scoped to our page only (prevents global 404s)
 * ───────────────────────────────────────────────────────────────────────── */
add_action('admin_enqueue_scripts', function(){
	if ( ! myls_is_our_admin_page() ) return;

	$vars  = 'assets/css/variables.css';
	$utils = 'assets/css/utilities.css';
	$admin = 'assets/css/admin.css';

	$missing = [];
	foreach ([$vars, $utils, $admin] as $rel) {
		if ( ! file_exists( myls_asset_path($rel) ) ) $missing[] = $rel;
	}

	if ( $missing ) {
		add_action('admin_notices', function() use ($missing){
			echo '<div class="notice notice-error"><p><strong>My Local SEO:</strong> Missing admin CSS asset(s):</p><ul>';
			foreach ($missing as $rel) {
				printf('<li><code>%s</code></li>', esc_html($rel));
			}
			echo '</ul><p>Please build or upload the files to <code>assets/css/</code>.</p></div>';
		});
		return; // don’t enqueue broken URLs (prevents 404 spam)
	}

	wp_enqueue_style('myls-vars',      myls_asset_url($vars),  [], MYLS_VERSION);
	wp_enqueue_style('myls-utils',     myls_asset_url($utils), ['myls-vars'], MYLS_VERSION);
	wp_enqueue_style('myls-admin-css', myls_asset_url($admin), ['myls-utils'], MYLS_VERSION);
});

/** Tabs CSS (also scoped to our page) */
add_action('admin_enqueue_scripts', function(){
	if ( ! myls_is_our_admin_page() ) return;
	wp_enqueue_style('myls-tabs-css', myls_asset_url('assets/css/tabs.css'), [], MYLS_VERSION);
});

/** Duplicate class hook (kept from your original) */
add_filter('myls_admin_tabs_nav_classes', function( $classes, $tabs = [], $current_id = '' ){
  return trim($classes . ' myls-tabs');
}, 10, 3);

/** Ensure dashicons + attach inline CSS only on our page. */
add_action('admin_enqueue_scripts', function(){
	if ( ! myls_is_our_admin_page() ) return;

	wp_enqueue_style('dashicons');

	$css = <<<CSS
.myls-tabs { background:#fff; padding:8px 0; border-bottom:1px solid #e5e7eb; }
.myls-tabs .nav-tab { display:inline-flex; align-items:center; gap:6px; padding:10px 14px; font-weight:600; border-radius:10px 10px 0 0; border:1px solid transparent; color:#334155; }
.myls-tabs .nav-tab .dashicons { font-size:18px; width:18px; height:18px; line-height:18px; }
.myls-tabs .nav-tab:hover { color:#0ea5e9; text-decoration:none; }
.myls-tabs .nav-tab.nav-tab-active { color:#0f172a; background:#fff; border-color:#e5e7eb #e5e7eb #fff; box-shadow:0 2px 0 0 #fff, 0 -1px 0 0 #e5e7eb, 0 -6px 14px rgba(15,23,42,.04); }
@media (max-width:782px){ .myls-tabs .nav-tab{ padding:8px 10px; gap:4px; } }
CSS;

	wp_add_inline_style('myls-admin-css', $css);
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('myls-accordion', MYLS_URL . '/assets/css/myls-accordion.css', [], MYLS_VERSION);
});

/** Meta history */
require_once MYLS_PATH . 'inc/myls-meta-history-logger.php';
require_once MYLS_PATH . 'inc/myls-meta-history-endpoints.php';
require_once MYLS_PATH . 'inc/llms-txt.php';

if ( is_admin() ) {
	require_once MYLS_PATH . 'admin/api-integration-tests.php';
	require_once MYLS_PATH . 'modules/meta/meta-history.php';
}
