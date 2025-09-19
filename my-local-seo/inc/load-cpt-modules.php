<?php
// File: inc/load-cpt-modules.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Load CPT modules immediately (so callbacks are defined),
 * then fire a custom hook on 'init' priority 0 that modules use
 * to register enabled CPTs in all request types (including admin-ajax).
 */

// 1) Include all CPT module files now (so they can attach to our custom hook)
(function(){
    $dir = trailingslashit( plugin_dir_path( __FILE__ ) ) . '../modules/cpt';
    if ( ! is_dir($dir) || ! is_readable($dir) ) return;

    $files = glob( $dir . '/*.php' );
    if ( empty($files) ) return;

    natsort($files);
    $skip = ['register.php','_bootstrap.php','_loader.php'];
    foreach ( $files as $file ) {
        $base = basename($file);
        if ( in_array($base, $skip, true) ) continue;
        include_once $file;
    }
})();

// 2) Fire our custom registration hook as early as possible on init
add_action('init', function(){
    /**
     * Modules hook their registrar to this action.
     * Each module should check its own "enable" option and call register_post_type().
     */
    do_action('myls_register_enabled_cpts');
}, 0);
