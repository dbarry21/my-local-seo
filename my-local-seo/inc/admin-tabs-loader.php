<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Include all admin tabs so they can call myls_register_admin_tab().
 * Uses the core recursive includer for simplicity.
 */
if ( ! function_exists('myls_load_all_admin_tabs') ) {
    function myls_load_all_admin_tabs() {
        if ( ! function_exists('myls_include_dir') ) return;
        $dir = trailingslashit( MYLS_PATH ) . 'admin/tabs';
        if ( is_dir($dir) ) {
            myls_include_dir( $dir ); // recursive: fine (schema subtabs will just return; no harm)
        }
    }
}

// Load immediately (this request) AND early in admin.
myls_load_all_admin_tabs();
add_action('admin_init', 'myls_load_all_admin_tabs', 1);
