<?
function service_area_shortcode_all() {
    // Get the current post ID
    if (is_singular('service_area')) {
        $current_post_id = get_the_ID();
    } else {
        $current_post_id = 0;
    }

   // Query for 'service_area' posts with post_parent of '0' and alphabetize the post listing
$args = array(
    'post_type'      => 'service_area',
    'post_parent'    => 0,
    'posts_per_page' => -1,
    'orderby'        => 'title',  // Order by the title
    'order'          => 'ASC',    // Order in ascending order
);

    // Exclude the current post if on a service_area post page
    if ($current_post_id) {
        $args['post__not_in'] = array($current_post_id);
    }

    $service_areas = new WP_Query($args);

    // Check if there are any posts
    if ($service_areas->have_posts()) {
        // Initialize output variable
        $output = '<div class="container service-areas"><div class="row">';
        $output .= '<div class="col-lg-12">';
        $output .= '<ul class="list-unstyled service-area-list">';

        // Loop through posts and build the list items
        while ($service_areas->have_posts()) {
            $service_areas->the_post();
            $output .= '
                <li>
                    <i class="fa fa-map-marker ssseo-icon"></i>
                    <a href="' . get_permalink() . '" class="service-area-link">' . get_the_title() . '</a>
                </li>';
        }

        $output .= '</ul></div></div></div>';

        // Reset post data
        wp_reset_postdata();

        return $output;
    } else {
        return '<p>No service areas found.</p>';
    }
}

// Register the shortcode
add_shortcode('service_area_list_all', 'service_area_shortcode_all');

function service_area_list_shortcode() {
    // Get the current post ID
    if (is_singular('service_area')) {
        $current_post_id = get_the_ID();
    } else {
        $current_post_id = 0;
    }

   // Query for 'service_area' posts with post_parent of '0' and alphabetize the post listing
$args = array(
    'post_type'      => 'service_area',
    'post_parent'    => 0,
    'posts_per_page' => -1,
    'orderby'        => 'title',  // Order by the title
    'order'          => 'ASC',    // Order in ascending order
);

    // Exclude the current post if on a service_area post page
    if ($current_post_id) {
        $args['post__not_in'] = array($current_post_id);
    }

    $service_areas = new WP_Query($args);

    // Check if there are any posts
    if ($service_areas->have_posts()) {
        // Initialize output variable
        $output = '<div class="container service-areas"><div class="row">';
        $output .= '<div class="col-lg-12">';
        $output .= '<ul class="list-unstyled service-area-list">';

        // Loop through posts and build the list items
        while ($service_areas->have_posts()) {
            $service_areas->the_post();
            $output .= '
                <li>
                    <i class="fa fa-map-marker ssseo-icon"></i>
                    <a href="' . get_permalink() . '" class="service-area-link">' . get_the_title() . '</a>
                </li>';
        }

        $output .= '</ul></div></div></div>';

        // Reset post data
        wp_reset_postdata();

        return $output;
    } else {
        return '<p>No service areas found.</p>';
    }
}

// Register the shortcode
add_shortcode('service_area_list', 'service_area_list_shortcode');