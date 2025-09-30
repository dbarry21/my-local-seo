<?php
if ( ! defined('ABSPATH') ) exit;

// Admin menu entry
add_action('admin_menu', function(){
  add_menu_page(
    'My Local SEO',
    'My Local SEO',
    'manage_options',
    'my-local-seo',
    'myls_admin_page_render',
    'dashicons-location-alt',
    61
  );
});

// Page callback: use the loader's helpers (includes icons)
function myls_admin_page_render() {
  echo '<div class="wrap">';
  echo '<h1 class="wp-heading-inline">My Local SEO</h1>';
  echo '<hr class="wp-header-end">';

  // This call prints the nav tabs WITH icons and then the current tab content
  if ( function_exists('myls_render_admin_tabs_page') ) {
    myls_render_admin_tabs_page('my-local-seo');
  } else {
    // Fallback if helper not present
    if ( function_exists('myls_render_admin_tabs_nav') ) {
      myls_render_admin_tabs_nav('my-local-seo');
    }
    if ( function_exists('myls_render_current_admin_tab') ) {
      myls_render_current_admin_tab();
    }
  }

  echo '</div>';
}
