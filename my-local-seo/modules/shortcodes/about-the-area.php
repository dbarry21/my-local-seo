<?php
add_shortcode('about_the_area', 'ssseo_shortcode_about_the_area');
function ssseo_shortcode_about_the_area($atts) {
    global $post;
    if (! $post || $post->post_type !== 'service_area') return '';

    $content = '<div class="container about-the-area">';
    $content .= get_post_meta($post->ID, '_about_the_area', true);
    $content .= '</div>';
    return wpautop(do_shortcode($content));
}