<?php
/**
 * AJAX handler: AI Page Builder
 * Path: inc/ajax/ai-page-builder.php
 *
 * Standalone page/post creation via AI — no dependency on Site Builder toggle.
 * Actions:
 *   myls_pb_create_page  – Generate and create a page/post with AI content
 */
if ( ! defined('ABSPATH') ) exit;

add_action('wp_ajax_myls_pb_create_page', function () {
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    if ( ! wp_verify_nonce($_POST['_wpnonce'] ?? '', 'myls_pb_create') ) {
        wp_send_json_error(['message' => 'Bad nonce'], 400);
    }

    // ── Collect & sanitize inputs ───────────────────────────────────────
    $page_title      = sanitize_text_field($_POST['page_title'] ?? '');
    $post_type       = sanitize_key($_POST['post_type'] ?? 'page');
    $page_status     = in_array(($_POST['page_status'] ?? ''), ['draft','publish'], true)
                        ? $_POST['page_status'] : 'draft';
    $description     = wp_kses_post($_POST['page_description'] ?? '');
    $prompt_template = wp_kses_post($_POST['prompt_template'] ?? '');
    $add_to_menu     = ! empty($_POST['add_to_menu']);

    if ( empty($page_title) ) {
        wp_send_json_error(['message' => 'Page title is required.'], 400);
    }

    // Validate post type exists
    if ( ! post_type_exists($post_type) ) {
        wp_send_json_error(['message' => 'Invalid post type: ' . $post_type], 400);
    }

    // ── Load business variables from Site Builder settings ──────────────
    $sb_settings = get_option('myls_sb_settings', []);
    $vars = [
        'business_name' => $sb_settings['business_name'] ?? get_bloginfo('name'),
        'city'          => $sb_settings['city']          ?? '',
        'phone'         => $sb_settings['phone']         ?? '',
        'email'         => $sb_settings['email']         ?? get_bloginfo('admin_email'),
        'site_name'     => get_bloginfo('name'),
        'site_url'      => home_url(),
    ];

    // ── Build the AI prompt ────────────────────────────────────────────
    if ( empty(trim($prompt_template)) ) {
        $prompt_template = myls_pb_default_prompt();
    }

    // Token replacement
    $token_map = array_merge($vars, [
        'page_title'  => $page_title,
        'description' => $description ?: 'A page about ' . $page_title,
        'post_type'   => $post_type,
    ]);
    $prompt = myls_pb_replace_tokens($prompt_template, $token_map);

    // ── Generate content via AI ────────────────────────────────────────
    $html = '';
    $ai_used = false;

    if ( function_exists('myls_openai_chat') ) {
        $model = (string) get_option('myls_openai_model', 'gpt-4o');
        $html = myls_openai_chat($prompt, [
            'model'       => $model,
            'max_tokens'  => 3000,
            'temperature' => 0.7,
            'system'      => 'You are an expert web content writer. Write clean, structured HTML for WordPress pages. Use HTML tags like <section>, <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>. Use Bootstrap 5 utility classes for layout. NEVER use markdown syntax. Output raw HTML only, no code fences, no explanation text.',
        ]);
        if ( ! empty(trim($html)) ) {
            $ai_used = true;
        }
    }

    // Fallback if AI unavailable
    if ( empty(trim($html)) ) {
        $t_esc = esc_html($page_title);
        $d_esc = esc_html($description ?: 'Learn more about ' . $page_title);
        $html  = "<section class='container py-5'>\n";
        $html .= "  <div class='text-center mb-4'>\n";
        $html .= "    <h1 class='display-5 mb-3'>{$t_esc}</h1>\n";
        $html .= "    <p class='lead'>{$d_esc}</p>\n";
        $html .= "  </div>\n";
        $html .= "</section>\n";
        $html .= "<section class='container py-4'>\n";
        $html .= "  <div class='row'><div class='col-lg-8 mx-auto'>\n";
        $html .= "    <h2 class='h3 mb-3'>About {$t_esc}</h2>\n";
        $html .= "    <p>{$d_esc}</p>\n";
        $html .= "    <a class='btn btn-primary btn-lg mt-3' href='/contact/'>Get Started Today</a>\n";
        $html .= "  </div></div>\n";
        $html .= "</section>";
    }

    // ── Upsert the page/post ──────────────────────────────────────────
    $meta_key = '_myls_pb_generated_key';
    $gen_key  = 'pb:' . sanitize_title($page_title);

    // Check for existing
    $existing = get_posts([
        'post_type'      => $post_type,
        'post_status'    => ['draft','publish','pending','future','private'],
        'meta_key'       => $meta_key,
        'meta_value'     => $gen_key,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    if ( $existing ) {
        $post_id = (int) $existing[0];
        wp_update_post([
            'ID'           => $post_id,
            'post_title'   => $page_title,
            'post_content' => $html,
            'post_status'  => $page_status,
        ]);
        $action_label = 'updated';
    } else {
        $post_id = (int) wp_insert_post([
            'post_type'    => $post_type,
            'post_status'  => $page_status,
            'post_title'   => $page_title,
            'post_content' => $html,
            'meta_input'   => [
                '_myls_pb_generated' => 1,
                $meta_key            => $gen_key,
            ],
        ]);
        $action_label = 'created';
    }

    if ( ! $post_id || is_wp_error($post_id) ) {
        wp_send_json_error(['message' => 'Failed to create post.'], 500);
    }

    // Set Yoast meta if available
    $desc_for_yoast = wp_strip_all_tags($html);
    $desc_for_yoast = trim(preg_replace('/\s+/', ' ', $desc_for_yoast));
    $desc_for_yoast = mb_substr($desc_for_yoast, 0, 155);
    update_post_meta($post_id, '_yoast_wpseo_title', $page_title . ' %%sitename%%');
    update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc_for_yoast);

    // ── Add to menu if requested ──────────────────────────────────────
    $menu_msg = '';
    if ( $add_to_menu ) {
        $menu_msg = myls_pb_add_to_menu($post_id, $post_type);
    }

    // ── Build response log ────────────────────────────────────────────
    $type_obj = get_post_type_object($post_type);
    $type_label = $type_obj ? $type_obj->labels->singular_name : $post_type;
    $edit_url = admin_url('post.php?post=' . $post_id . '&action=edit');
    $view_url = get_permalink($post_id);

    $log_lines = [];
    $log_lines[] = "✅ {$type_label} {$action_label}: \"{$page_title}\"";
    $log_lines[] = "   Post ID: {$post_id} | Status: {$page_status}";
    $log_lines[] = "   AI: " . ($ai_used ? 'Content generated by AI' : 'Using fallback template (check OpenAI API key)');
    if ( $menu_msg ) {
        $log_lines[] = "   Menu: {$menu_msg}";
    }
    $log_lines[] = "   Edit: {$edit_url}";

    wp_send_json_success([
        'message'  => "{$type_label} {$action_label} successfully.",
        'log'      => implode("\n", $log_lines),
        'post_id'  => $post_id,
        'edit_url' => $edit_url,
        'view_url' => $view_url,
        'ai_used'  => $ai_used,
    ]);
});

/**
 * Default prompt template for AI Page Builder
 */
function myls_pb_default_prompt(): string {
    return <<<'EOT'
Create a professional, SEO-optimized WordPress page for "{{PAGE_TITLE}}".

Business: {{BUSINESS_NAME}} in {{CITY}}
Phone: {{PHONE}} | Email: {{EMAIL}}

Page Description & Instructions:
{{DESCRIPTION}}

Requirements:
- Write clean, semantic HTML using Bootstrap 5 classes
- Include an engaging hero section with a clear headline and subheading
- Add 3-5 content sections covering key features or benefits
- Include a strong call-to-action section at the bottom
- Use <section>, <h2>, <h3>, <p>, <ul>, <li> tags — NO markdown
- Make it locally relevant and SEO-friendly
- Output raw HTML only, no code fences or explanation
EOT;
}

/**
 * Replace {{TOKEN}} placeholders (case-insensitive keys → UPPER tokens)
 */
function myls_pb_replace_tokens(string $text, array $vars): string {
    foreach ($vars as $k => $v) {
        if ( is_string($v) || is_numeric($v) ) {
            $text = str_replace('{{' . strtoupper($k) . '}}', (string) $v, $text);
        }
    }
    return $text;
}

/**
 * Add a post to the Main Menu (create if needed, no duplicates)
 */
function myls_pb_add_to_menu(int $post_id, string $post_type = 'page'): string {
    $menu_name = 'Main Menu';
    $menu = wp_get_nav_menu_object($menu_name);
    if ( ! $menu ) {
        $menu_id = wp_create_nav_menu($menu_name);
        if ( is_wp_error($menu_id) ) {
            return 'Could not create menu.';
        }
    } else {
        $menu_id = (int) $menu->term_id;
    }

    // Check for duplicate
    $items = wp_get_nav_menu_items($menu_id);
    if ( $items ) {
        foreach ( $items as $item ) {
            if ( (int) $item->object_id === $post_id ) {
                return "Already in '{$menu_name}' menu.";
            }
        }
    }

    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-object-id' => $post_id,
        'menu-item-object'    => $post_type,
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
    ]);

    // Assign to primary location if not already
    if ( class_exists('\\MYLS\\SiteBuilder\\Utils') ) {
        $location = \MYLS\SiteBuilder\Utils::first_available_menu_location();
    } else {
        $locs = get_registered_nav_menus();
        $location = isset($locs['primary']) ? 'primary' : ( $locs ? array_key_first($locs) : '' );
    }

    if ( $location ) {
        $locations = get_nav_menu_locations();
        if ( empty($locations[$location]) || (int) $locations[$location] !== $menu_id ) {
            $locations[$location] = $menu_id;
            set_theme_mod('nav_menu_locations', $locations);
        }
    }

    return "Added to '{$menu_name}' menu.";
}

/**
 * AJAX: Save the Page Builder prompt template
 */
add_action('wp_ajax_myls_pb_save_prompt', function () {
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    if ( ! wp_verify_nonce($_POST['_wpnonce'] ?? '', 'myls_pb_create') ) {
        wp_send_json_error(['message' => 'Bad nonce'], 400);
    }
    $template = wp_kses_post($_POST['prompt_template'] ?? '');
    update_option('myls_pb_prompt_template', $template);
    wp_send_json_success(['message' => 'Prompt template saved.']);
});
