<?php
// File: inc/admin-ajax.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Simple nonce check for this tab's AJAX
 */
function myls_cpt_ajax_check() {
    if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'myls_cpt_ajax') ) {
        wp_send_json_error(['message' => 'Invalid nonce.'], 403);
    }
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Unauthorized.'], 403);
    }
}

/**
 * Flush rewrites
 */
add_action('wp_ajax_myls_flush_rewrites', function(){
    myls_cpt_ajax_check();
    flush_rewrite_rules();
    wp_send_json_success(['message' => 'Rewrite rules flushed.']);
});

/**
 * Check if a CPT is registered
 */
add_action('wp_ajax_myls_check_cpt', function(){
    myls_cpt_ajax_check();
    $cpt = isset($_POST['cpt']) ? sanitize_key($_POST['cpt']) : '';
    $exists = $cpt ? post_type_exists($cpt) : false;
    wp_send_json_success(['registered' => (bool)$exists]);
});
