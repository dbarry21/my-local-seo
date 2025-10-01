<?php
/**
 * Plugin Name: My Local SEO
 * Description: Modular local SEO toolkit with YouTube → Video drafts, shortcodes, and admin tabs.
 * Version: 1.5.1
 * Author: You
 * Text Domain: my-local-seo
 */
if ( ! defined('ABSPATH') ) exit;

/** Constants */
define('MYLS_VERSION',  '1.1.0');
define('MYLS_PATH',      plugin_dir_path(__FILE__));
define('MYLS_URL',       plugin_dir_url(__FILE__));
define('MYLS_BASENAME',  plugin_basename(__FILE__));
define('MYLS_SCHEMA_DEBUG', true);
// --- Schema Debug Toggles ---
define('MYLS_DEBUG_ORG', true);
define('MYLS_DEBUG_LB',  true);

// Core plugin constants
if ( ! defined( 'MYLS_PLUGIN_FILE' ) ) {
	define( 'MYLS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MYLS_PLUGIN_DIR' ) ) {
	define( 'MYLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MYLS_PLUGIN_URL' ) ) {
	define( 'MYLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'MYLS_PLUGIN_BASENAME' ) ) {
	define( 'MYLS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'MYLS_PLUGIN_VERSION' ) ) {
	// You can also read from plugin header if you want it dynamic
	define( 'MYLS_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'MYLS_PATH' ) ) {
	define( 'MYLS_PATH', trailingslashit( MYLS_PLUGIN_DIR ) );
}


/**
 * IMPORTANT: Registry + ordered getters live ONLY in core.php.
 * No other file should (re)define myls_register_admin_tab(),
 * myls_get_admin_tabs(), or reset $GLOBALS['myls_admin_tabs'].
 */
require_once MYLS_PATH . 'inc/core.php';

/**
 * Loader only: includes admin/tabs/*.php (each calls myls_register_admin_tab()).
 * The loader MUST NOT reset the registry or redefine functions.
 */
require_once MYLS_PATH . 'inc/admin-tabs-loader.php';

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

/** CPT registration BEFORE module extras */
require_once MYLS_PATH . 'inc/cpt-registration.php';

/** Admin AJAX */
require_once MYLS_PATH . 'inc/admin-ajax.php';
require_once MYLS_PATH . 'inc/admin-bar-menu.php';

/** CPT extras AFTER registration */
require_once MYLS_PATH . 'inc/load-cpt-modules.php';

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

/** Admin CSS */
add_action('admin_enqueue_scripts', function() {
	$base = MYLS_URL . 'assets/css/';
	wp_enqueue_style('myls-vars',       $base . 'variables.css', [], MYLS_VERSION);
	wp_enqueue_style('myls-utils',      $base . 'utilities.css', ['myls-vars'], MYLS_VERSION);
	wp_enqueue_style('myls-admin-css',  $base . 'admin.css',     ['myls-utils'], MYLS_VERSION);
});

/** Front-end CSS (registered only) */
add_action('wp_enqueue_scripts', function() {
	$base = MYLS_URL . 'assets/css/';
	wp_register_style('myls-vars',           $base.'variables.css', [], MYLS_VERSION);
	wp_register_style('myls-utils',          $base.'utilities.css', ['myls-vars'], MYLS_VERSION);
	wp_register_style('myls-frontend',       $base.'frontend.css',  ['myls-vars','myls-utils'], MYLS_VERSION);
	wp_register_style('myls-default-styles', $base.'default-styles.css', ['myls-frontend'], MYLS_VERSION);
});
if ( ! function_exists('myls_enqueue_frontend_styles_once') ) {
	function myls_enqueue_frontend_styles_once( $with_defaults = true ) {
		static $done = false; if ( $done ) return; $done = true;
		if ( apply_filters('myls_disable_frontend_styles', false) ) return;
		wp_enqueue_style('myls-vars');
		wp_enqueue_style('myls-utils');
		wp_enqueue_style('myls-frontend');
		if ( $with_defaults ) wp_enqueue_style('myls-default-styles');
		do_action('myls_after_enqueue_frontend');
	}
}

add_filter('myls_admin_tabs_nav_classes', function( $classes, $tabs, $current_id ){
  // Append our class without blowing away the default
  return trim($classes . ' myls-tabs');
}, 10, 3);

// Enqueue styles for our admin screen only
add_action('admin_enqueue_scripts', function( $hook ){
  if ( isset($_GET['page']) && $_GET['page'] === 'my-local-seo' ) {
    wp_enqueue_style(
      'myls-tabs-css',
      MYLS_URL . 'assets/css/tabs.css',
      [],
      MYLS_VERSION
    );
  }
});


add_filter('myls_admin_tabs_nav_classes', function( $classes ) {
  return trim($classes . ' myls-tabs');
}, 10, 1);

// Ensure dashicons + attach inline CSS only on our page.
add_action('admin_enqueue_scripts', function( $hook ){
  if ( isset($_GET['page']) && $_GET['page'] === 'my-local-seo' ) {
    // Dashicons (in case something dequeued it)
    wp_enqueue_style('dashicons');

    // Attach inline CSS to your existing admin stylesheet (or change the handle if needed).
    $css = <<<CSS
.myls-tabs { background:#fff; padding:8px 0; border-bottom:1px solid #e5e7eb; }
.myls-tabs .nav-tab { display:inline-flex; align-items:center; gap:6px; padding:10px 14px; font-weight:600; border-radius:10px 10px 0 0; border:1px solid transparent; color:#334155; }
.myls-tabs .nav-tab .dashicons { font-size:18px; width:18px; height:18px; line-height:18px; }
.myls-tabs .nav-tab:hover { color:#0ea5e9; text-decoration:none; }
.myls-tabs .nav-tab.nav-tab-active { color:#0f172a; background:#fff; border-color:#e5e7eb #e5e7eb #fff; box-shadow:0 2px 0 0 #fff, 0 -1px 0 0 #e5e7eb, 0 -6px 14px rgba(15,23,42,.04); }
@media (max-width:782px){ .myls-tabs .nav-tab{ padding:8px 10px; gap:4px; } }
CSS;
    // Use your own handle; here I reuse 'myls-admin-css' which you already enqueue.
    wp_add_inline_style('myls-admin-css', $css);
  }
});

require_once MYLS_PATH . 'inc/myls-meta-history-logger.php';
require_once MYLS_PATH . 'inc/myls-meta-history-endpoints.php';

// In my-local-seo.php (or a bootstrap that runs in admin)
if (is_admin()) {
  require_once MYLS_PATH . 'admin/api-integration-tests.php';
  require_once MYLS_PATH . 'modules/meta/meta-history.php';
}


