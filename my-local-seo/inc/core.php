<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Recursively include all PHP files in a directory.
 */
function myls_include_dir( string $dir ) : void {
  if ( ! is_dir($dir) ) return;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
  foreach ( $it as $file ) {
    if ( $file->isFile() && strtolower($file->getExtension()) === 'php' ) {
      require_once $file->getPathname();
    }
  }
}

/** Admin tabs registry */
$GLOBALS['myls_admin_tabs'] = [];

/**
 * Register an admin tab.
 */
function myls_register_admin_tab( string $id, string $title, callable $render_cb ) : void {
  $GLOBALS['myls_admin_tabs'][$id] = [
    'id'    => $id,
    'title' => $title,
    'cb'    => $render_cb,
  ];
}

/** Shortcode registry (for docs if you want to list later) */
$GLOBALS['myls_shortcodes'] = [];

/**
 * Register a shortcode and document it (optional $doc array).
 */
function myls_register_shortcode( string $tag, callable $cb, array $doc = [] ) : void {
  add_shortcode( $tag, $cb );
  $GLOBALS['myls_shortcodes'][ $tag ] = $doc + ['tag'=>$tag];
}

/** Options helpers (prefix-safe) */
function myls_get_option( string $k, $default = '' ) {
  return get_option($k, $default);
}
function myls_update_option( string $k, $val, bool $autoload = false ) {
  return update_option($k, $val, $autoload);
}

/** Debug log for modules (single shared place) */
function myls_debug_enabled() : bool { return (bool) get_option('myls_debug', false); }
function myls_log($msg) : void {
  if ( ! myls_debug_enabled() ) return;
  $key = 'myls_debug_log';
  $log = get_option($key, []);
  $log[] = '[' . current_time('mysql') . '] ' . ( is_string($msg) ? $msg : wp_json_encode($msg) );
  if ( count($log) > 1000 ) $log = array_slice($log, -1000);
  update_option($key, $log, false);
}
