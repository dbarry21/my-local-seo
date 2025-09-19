<?php
// File: inc/admin.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Admin Menu
 */
add_action('admin_menu', function () {
    add_menu_page(
        __('My Local SEO', 'my-local-seo'),
        __('My Local SEO', 'my-local-seo'),
        'manage_options',
        'my-local-seo',
        'myls_render_admin_page',
        'dashicons-chart-area',
        65
    );
});

/**
 * Order tabs (by title; keep it simple)
 */
if ( ! function_exists('myls_get_admin_tabs_ordered') ) {
    function myls_get_admin_tabs_ordered(): array {
        $tabs = isset($GLOBALS['myls_admin_tabs']) && is_array($GLOBALS['myls_admin_tabs'])
            ? $GLOBALS['myls_admin_tabs']
            : [];

        uasort($tabs, function($a, $b){
            $la = $a['title'] ?? '';
            $lb = $b['title'] ?? '';
            return strcasecmp($la, $lb);
        });

        return $tabs;
    }
}

/**
 * Page Renderer (defaults to 'dashboard')
 */
if ( ! function_exists('myls_render_admin_page') ) {
    function myls_render_admin_page() {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'my-local-seo') );
        }

        // Read tabs from the SAME registry core uses
        $tabs = myls_get_admin_tabs_ordered();

        // Default to 'dashboard' if none specified / invalid
        $requested = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
        $active    = $requested !== '' ? $requested : 'dashboard';
        if ( ! isset($tabs[$active]) ) {
            $active = isset($tabs['dashboard']) ? 'dashboard' : (array_key_first($tabs) ?: '');
        }

        echo '<div class="wrap myls-admin-wrap">';
        echo '<h1 class="wp-heading-inline">'. esc_html__('My Local SEO', 'my-local-seo') .'</h1>';

        // Nav
        if ( ! empty($tabs) ) {
            echo '<h2 class="nav-tab-wrapper" style="margin-top:15px;">';
            foreach ( $tabs as $id => $tab ) {
                $url   = add_query_arg(['page' => 'my-local-seo', 'tab' => $id], admin_url('admin.php'));
                $class = 'nav-tab' . ( $id === $active ? ' nav-tab-active' : '' );
                $label = $tab['title'] ?? $id;
                echo '<a class="'. esc_attr($class) .'" href="'. esc_url($url) .'">'. esc_html($label) .'</a>';
            }
            echo '</h2>';
        } else {
            echo '<div class="notice notice-warning"><p>'.
                 esc_html__('No admin tabs registered. Ensure your tab files are loaded (e.g., admin/tabs/dashboard.php).', 'my-local-seo') .
                 '</p></div>';
        }

        // Content
        echo '<div class="myls-admin-content" style="margin-top:20px;">';
        if ( $active && isset($tabs[$active]) && is_callable($tabs[$active]['cb'] ?? null) ) {
            call_user_func( $tabs[$active]['cb'] );
        }
        echo '</div>';

        echo '</div>';
    }
}
