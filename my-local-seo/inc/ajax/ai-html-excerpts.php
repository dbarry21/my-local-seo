<?php
/**
 * MYLS – AI AJAX: HTML Excerpts
 * File: inc/ajax/ai-html-excerpts.php
 *
 * Endpoints:
 *  - wp_ajax_myls_ai_html_excerpt_generate_single  (editor metabox, single post)
 *  - wp_ajax_myls_ai_html_excerpt_save_prompt       (admin AI tab, save prompt template)
 *  - wp_ajax_myls_ai_html_excerpt_generate_bulk     (admin AI tab, bulk generate)
 *
 * Saves to post_meta: html_excerpt
 * Prompt template stored in option: myls_ai_prompt_html_excerpt
 */

if ( ! defined('ABSPATH') ) exit;

/* -------------------------------------------------------------------------
 * Nonce helper (shared, only declare if missing)
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_check_nonce') ) {
    function myls_ai_check_nonce( string $action = 'myls_ai_ops' ) : void {
        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : (string) ( $_REQUEST['_ajax_nonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
    }
}

/* -------------------------------------------------------------------------
 * Factory default prompt template
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_default_html_excerpt_prompt') ) {
    function myls_ai_default_html_excerpt_prompt() : string {
        return myls_get_default_prompt('html-excerpt');
    }
}

/* -------------------------------------------------------------------------
 * Build the filled prompt for a given post
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_build_html_excerpt_prompt') ) {
    function myls_ai_build_html_excerpt_prompt( int $post_id, string $template = '' ) : string {
        if ( trim($template) === '' ) {
            $template = (string) get_option('myls_ai_prompt_html_excerpt', myls_ai_default_html_excerpt_prompt());
        }

        $post = get_post($post_id);
        if ( ! $post ) return '';

        $site_name = (string) get_bloginfo('name');

        // City/State from meta
        $city_state = '';
        if ( function_exists('get_field') ) $city_state = (string) get_field('city_state', $post_id);
        if ( $city_state === '' ) $city_state = (string) get_post_meta($post_id, 'city_state', true);

        // Primary category
        $primary_cat = '';
        $cats = get_the_category($post_id);
        if ( ! empty($cats) && ! is_wp_error($cats) ) {
            $primary_cat = (string) $cats[0]->name;
        }

        $current_excerpt = (string) $post->post_excerpt;

        $prompt = $template;
        $prompt = str_replace('{post_title}',       (string) get_the_title($post_id), $prompt);
        $prompt = str_replace('{site_name}',        $site_name, $prompt);
        $prompt = str_replace('{excerpt}',          $current_excerpt, $prompt);
        $prompt = str_replace('{primary_category}', $primary_cat, $prompt);
        $prompt = str_replace('{city_state}',       $city_state, $prompt);
        $prompt = str_replace('{permalink}',        (string) get_permalink($post_id), $prompt);

        return $prompt;
    }
}

/* -------------------------------------------------------------------------
 * Generate HTML excerpt text via OpenAI
 * ------------------------------------------------------------------------- */
if ( ! function_exists('myls_ai_generate_html_excerpt_text') ) {
    function myls_ai_generate_html_excerpt_text( string $prompt ) : string {
        if ( function_exists('myls_openai_chat') ) {
            $model = (string) get_option('myls_openai_model', 'gpt-4o');
            $max   = (int) get_option('myls_ai_html_excerpt_max_tokens', 300);
            $temp  = (float) get_option('myls_ai_html_excerpt_temperature', 0.7);

            $text = myls_openai_chat($prompt, [
                'model'       => $model,
                'max_tokens'  => $max,
                'temperature' => $temp,
                'system'      => 'You are an SEO/content assistant. Write clean, accurate HTML excerpts. Use only basic HTML tags: <p>, <strong>, <em>. No markdown, no code fences.',
            ]);

            return is_string($text) ? trim($text) : '';
        }
        return '';
    }
}

/* -------------------------------------------------------------------------
 * Single post generation (from editor metabox)
 * Accepts nonce via 'myls_html_excerpt_save' action
 * ------------------------------------------------------------------------- */
add_action('wp_ajax_myls_ai_html_excerpt_generate_single', function() : void {

    // Accept either the metabox nonce or the AI ops nonce
    $nonce = (string) ($_POST['nonce'] ?? '');
    $valid = wp_verify_nonce($nonce, 'myls_html_excerpt_save')
          || wp_verify_nonce($nonce, 'myls_ai_ops');

    if ( ! $valid ) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error(['message' => 'Permission denied'], 403);
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ( ! $post_id || ! get_post($post_id) ) {
        wp_send_json_error(['message' => 'Invalid post ID']);
    }

    $template = (string) get_option('myls_ai_prompt_html_excerpt', myls_ai_default_html_excerpt_prompt());
    $prompt   = myls_ai_build_html_excerpt_prompt($post_id, $template);

    if ( trim($prompt) === '' ) {
        wp_send_json_error(['message' => 'Prompt template is empty']);
    }

    $generated = myls_ai_generate_html_excerpt_text($prompt);

    if ( $generated === '' ) {
        wp_send_json_error(['message' => 'AI returned empty response (check API key/model)']);
    }

    // Clean up: strip code fences if present
    $generated = preg_replace('/^```html?\s*/i', '', $generated);
    $generated = preg_replace('/\s*```$/', '', $generated);
    $generated = trim($generated);

    wp_send_json_success([
        'post_id'      => $post_id,
        'html_excerpt' => $generated,
    ]);
});

/* -------------------------------------------------------------------------
 * Save prompt template (admin AI tab)
 * ------------------------------------------------------------------------- */
add_action('wp_ajax_myls_ai_html_excerpt_save_prompt', function() : void {

    myls_ai_check_nonce('myls_ai_ops');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $prompt = (string) ($_POST['prompt'] ?? '');
    $prompt = wp_kses_post($prompt);

    if ( trim($prompt) === '' ) {
        wp_send_json_error(['message' => 'Prompt cannot be empty.'], 400);
    }

    update_option('myls_ai_prompt_html_excerpt', $prompt, false);

    wp_send_json_success(['message' => 'Saved.', 'option' => 'myls_ai_prompt_html_excerpt']);
});

/* -------------------------------------------------------------------------
 * Bulk generation (admin AI tab)
 * ------------------------------------------------------------------------- */
add_action('wp_ajax_myls_ai_html_excerpt_generate_bulk', function() : void {

    myls_ai_check_nonce('myls_ai_ops');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $post_ids  = isset($_POST['post_ids']) ? (array) $_POST['post_ids'] : [];
    $overwrite = ! empty($_POST['overwrite']);
    $dryrun    = ! empty($_POST['dryrun']);

    $post_ids = array_values(array_filter(array_map('intval', $post_ids)));
    if ( empty($post_ids) ) {
        wp_send_json_error(['message' => 'No posts selected.'], 400);
    }

    $template = (string) get_option('myls_ai_prompt_html_excerpt', myls_ai_default_html_excerpt_prompt());
    if ( trim($template) === '' ) {
        wp_send_json_error(['message' => 'Missing HTML excerpt prompt template.'], 400);
    }

    $results = [];
    foreach ( $post_ids as $pid ) {

        $post = get_post($pid);
        if ( ! $post ) {
            $results[] = ['id' => $pid, 'ok' => false, 'error' => 'Post not found'];
            continue;
        }

        // Check existing
        $existing = (string) get_post_meta($pid, 'html_excerpt', true);
        if ( $existing !== '' && ! $overwrite ) {
            $results[] = ['id' => $pid, 'ok' => true, 'skipped' => true, 'reason' => 'html_excerpt exists (overwrite disabled)'];
            continue;
        }

        $prompt = myls_ai_build_html_excerpt_prompt($pid, $template);
        if ( trim($prompt) === '' ) {
            $results[] = ['id' => $pid, 'ok' => false, 'error' => 'Empty prompt'];
            continue;
        }

        $generated = myls_ai_generate_html_excerpt_text($prompt);

        if ( $generated === '' ) {
            $results[] = ['id' => $pid, 'ok' => false, 'error' => 'AI returned empty (check API key/model)'];
            continue;
        }

        // Clean code fences
        $generated = preg_replace('/^```html?\s*/i', '', $generated);
        $generated = preg_replace('/\s*```$/', '', $generated);
        $generated = trim($generated);

        $saved = false;
        if ( ! $dryrun ) {
            $saved = (bool) update_post_meta($pid, 'html_excerpt', wp_kses_post($generated));
        }

        $results[] = [
            'id'           => $pid,
            'ok'           => true,
            'saved'        => $saved,
            'dryrun'       => $dryrun,
            'html_excerpt' => $generated,
            'title'        => (string) get_the_title($pid),
        ];
    }

    wp_send_json_success([
        'count'   => count($results),
        'dryrun'  => $dryrun,
        'results' => $results,
    ]);
});
