<?php
// File: inc/assets.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Returns true when we’re on the My Local SEO admin page.
 */
if ( ! function_exists('myls_is_admin_page') ) {
    function myls_is_admin_page(): bool {
        // Adjust 'my-local-seo' if your slug differs
        return is_admin() && isset($_GET['page']) && $_GET['page'] === 'my-local-seo';
    }
}

/**
 * Admin styles/scripts
 */
add_action('admin_enqueue_scripts', function( $hook ) {
    if ( ! myls_is_admin_page() ) return;

    // --- Bootstrap 5.3 (no jQuery dependency)
    $ver = '5.3.3';
    wp_enqueue_style(
        'myls-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        $ver
    );
    // Optional: Bootstrap Icons (uncomment if you use them)
    // wp_enqueue_style('myls-bootstrap-icons','https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',[], '1.11.3');

    wp_enqueue_script(
        'myls-popper',
        'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js',
        [],
        '2.11.8',
        true
    );
    wp_enqueue_script(
        'myls-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js',
        ['myls-popper'],
        $ver,
        true
    );

    // --- Minimal admin shim so Bootstrap + WP admin styles play nicely
    $shim = "
    /* Space the content a bit and keep Bootstrap cards readable in wp-admin */
    .myls-admin-wrap .card { border-radius: .5rem; }
    .myls-admin-wrap .card .card-header { font-weight: 600; }
    .myls-admin-wrap .form-control, .myls-admin-wrap .form-select { max-width: 100%; }
    .myls-admin-wrap .container-fluid { padding-left: 0; padding-right: 0; }
    .myls-admin-wrap details summary { cursor: pointer; }
    /* Keep WP nav tabs unchanged; our tab bodies can still use Bootstrap */
    .myls-admin-content .row { margin-left: 0; margin-right: 0; }
    ";
    wp_register_style('myls-admin-inline', false);
    wp_enqueue_style('myls-admin-inline');
    wp_add_inline_style('myls-admin-inline', $shim);
}, 20);
