<?php
/**
 * My Local SEO - Google Maps Metabox
 * Path: inc/metaboxes/google-maps-metabox.php
 * 
 * Adds a metabox to Service Area posts for generating Google Static Maps
 * as featured images for individual posts.
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the Google Maps metabox
 */
add_action('add_meta_boxes', function() {
    // Only add to Service Area post type
    add_meta_box(
        'myls_google_maps_metabox',
        '<span class="dashicons dashicons-location" style="margin-right:5px;"></span> Google Maps Featured Image',
        'myls_google_maps_metabox_render',
        'service_area',
        'side',
        'default'
    );
});

/**
 * Render the metabox content
 */
function myls_google_maps_metabox_render($post) {
    wp_nonce_field('myls_google_maps_metabox', 'myls_google_maps_nonce');
    
    // Get API key status
    $api_key = trim(get_option('myls_google_static_maps_api_key', ''));
    $has_api_key = !empty($api_key);
    
    // Get city and state
    $city = get_post_meta($post->ID, 'city', true);
    $state = get_post_meta($post->ID, 'state', true);
    
    // Get current featured image status
    $has_thumbnail = has_post_thumbnail($post->ID);
    
    ?>
    <div class="myls-gmaps-metabox" style="padding: 5px 0;">
        <?php if (!$has_api_key): ?>
            <div class="notice notice-warning inline" style="margin: 10px 0; padding: 8px;">
                <p style="margin: 0;">
                    <strong>API Key Required:</strong><br>
                    Configure your Google Static Maps API key in 
                    <a href="<?php echo admin_url('admin.php?page=my-local-seo&tab=api-integration'); ?>">
                        API Integration
                    </a>.
                </p>
            </div>
        <?php else: ?>
            <?php if (empty($city) || empty($state)): ?>
                <div class="notice notice-info inline" style="margin: 10px 0; padding: 8px;">
                    <p style="margin: 0;">
                        <strong>City and State Required:</strong><br>
                        Please fill in the City and State fields below to generate a map.
                    </p>
                </div>
            <?php endif; ?>
            
            <div style="margin: 10px 0;">
                <p style="margin-bottom: 8px;">
                    <strong>Location:</strong><br>
                    <?php if ($city && $state): ?>
                        <span style="color: #2271b1;"><?php echo esc_html($city . ', ' . $state); ?></span>
                    <?php else: ?>
                        <em style="color: #999;">Not set</em>
                    <?php endif; ?>
                </p>
                
                <?php if ($has_thumbnail): ?>
                    <p style="margin-bottom: 8px; color: #2c3338;">
                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                        Featured image exists
                    </p>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 12px;">
                <button 
                    type="button" 
                    id="myls_generate_map_btn" 
                    class="button button-secondary button-large"
                    style="width: 100%; height: auto; padding: 8px;"
                    <?php echo (empty($city) || empty($state)) ? 'disabled' : ''; ?>
                >
                    <span class="dashicons dashicons-location" style="margin-top: 3px;"></span>
                    <?php echo $has_thumbnail ? 'Regenerate Map' : 'Generate Map'; ?>
                </button>
                
                <p id="myls_map_status" style="margin: 8px 0 0 0; font-size: 12px; color: #666;"></p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    jQuery(function($) {
        $('#myls_generate_map_btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $status = $('#myls_map_status');
            var postId = <?php echo (int)$post->ID; ?>;
            
            // Disable button and show loading
            $btn.prop('disabled', true).text('Generating...');
            $status.html('<span class="dashicons dashicons-update spin" style="animation: rotation 2s infinite linear;"></span> Generating map...').css('color', '#2271b1');
            
            // Make AJAX request
            $.post(ajaxurl, {
                action: 'myls_generate_single_map',
                post_id: postId,
                nonce: $('#myls_google_maps_nonce').val()
            }, function(response) {
                if (response.success) {
                    $status.html('<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ' + response.data.message).css('color', '#00a32a');
                    
                    // Reload the page after 1.5 seconds to show the new featured image
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $status.html('<span class="dashicons dashicons-dismiss" style="color: #d63638;"></span> ' + (response.data.message || 'Failed to generate map')).css('color', '#d63638');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-location"></span> <?php echo $has_thumbnail ? "Regenerate Map" : "Generate Map"; ?>');
                }
            }).fail(function(xhr) {
                var errorMsg = 'Network error';
                if (xhr.status === 403) {
                    errorMsg = 'Permission denied';
                }
                $status.html('<span class="dashicons dashicons-dismiss" style="color: #d63638;"></span> ' + errorMsg).css('color', '#d63638');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-location"></span> <?php echo $has_thumbnail ? "Regenerate Map" : "Generate Map"; ?>');
            });
        });
    });
    </script>
    
    <style>
    @keyframes rotation {
        from { transform: rotate(0deg); }
        to { transform: rotate(359deg); }
    }
    .spin {
        display: inline-block;
    }
    </style>
    <?php
}

/**
 * AJAX handler for single map generation
 */
add_action('wp_ajax_myls_generate_single_map', function() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'myls_google_maps_metabox')) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission denied'], 403);
    }
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error(['message' => 'Invalid post ID']);
    }
    
    // Get API key
    $api_key = trim(get_option('myls_google_static_maps_api_key', ''));
    if (empty($api_key)) {
        wp_send_json_error([
            'message' => 'Google Static Maps API key not configured'
        ]);
    }
    
    // Get city and state
    $city = get_post_meta($post_id, 'city', true);
    $state = get_post_meta($post_id, 'state', true);
    
    if (empty($city) || empty($state)) {
        wp_send_json_error([
            'message' => 'City and State fields are required'
        ]);
    }
    
    // Generate the map (reuse function from google-maps.php)
    if (!function_exists('myls_generate_static_map')) {
        wp_send_json_error([
            'message' => 'Map generation function not available'
        ]);
    }
    
    $result = myls_generate_static_map($post_id, $city, $state, $api_key);
    
    if ($result['success']) {
        wp_send_json_success([
            'message' => 'Map generated successfully!',
            'attachment_id' => $result['attachment_id']
        ]);
    } else {
        wp_send_json_error([
            'message' => $result['message']
        ]);
    }
});
