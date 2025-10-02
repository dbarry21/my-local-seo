<?php
/**
 * My Local SEO – AI AJAX
 * Path: inc/ajax/ai.php
 *
 * Endpoints:
 *  - wp_ajax_myls_ai_posts_by_type
 *  - wp_ajax_myls_ai_generate_meta
 *  - wp_ajax_myls_ai_about_get_posts
 *  - wp_ajax_myls_ai_about_generate
 */
if ( ! defined('ABSPATH') ) exit;

/* ------------------------- JSON helper ------------------------- */
if ( ! function_exists('myls_ai_json') ) {
	function myls_ai_json( array $data, int $code = 200 ) : void {
		status_header( $code );
		wp_send_json( $data );
	}
}

/* ------------------------- Nonce helper ------------------------ */
if ( ! function_exists('myls_ai_check_nonce') ) {
	function myls_ai_check_nonce( string $action = 'myls_ai_ops' ) : void {
		$nonce = isset($_POST['nonce']) ? $_POST['nonce'] : ( $_REQUEST['_ajax_nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			myls_ai_json( ['ok'=>false, 'error'=>'Invalid or missing nonce'], 403 );
		}
	}
}

/* ---------------------- Context helper (meta) ------------------ */
if ( ! function_exists('myls_ai_context_for_post') ) {
	function myls_ai_context_for_post( int $post_id ) : array {
		$post = get_post( $post_id );
		if ( ! $post ) return [];
		$site_name = get_bloginfo('name');
		$excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '…' );
		$primary_category = '';
		$cats = get_the_category( $post_id );
		if ( is_array($cats) && !empty($cats) ) $primary_category = $cats[0]->name;

		return [
			'post_title'       => get_the_title($post_id),
			'site_name'        => $site_name,
			'excerpt'          => $excerpt,
			'primary_category' => $primary_category,
			'permalink'        => get_permalink($post_id),
		];
	}
}

/* ---------------------- Token replace (meta) ------------------- */
if ( ! function_exists('myls_ai_apply_tokens') ) {
	function myls_ai_apply_tokens( string $tpl, array $ctx ) : string {
		return preg_replace_callback('/\{([a-z0-9_]+)\}/i', function($m) use ($ctx){
			$key = $m[1];
			return isset($ctx[$key]) ? (string)$ctx[$key] : $m[0];
		}, $tpl);
	}
}

/* ---------------------- AI provider wrapper -------------------- */
if ( ! function_exists('myls_ai_generate_text') ) {
	function myls_ai_generate_text( string $prompt, array $opts = [] ) : string {
		// Preferred provider
		$out = apply_filters('myls_ai_complete', '', array_merge([
			'prompt'      => $prompt,
			'max_tokens'  => $opts['max_tokens']  ?? null,
			'temperature' => $opts['temperature'] ?? null,
			'context'     => $opts['context']     ?? null,
			'post_id'     => $opts['post_id']     ?? null,
		], $opts));
		if ( is_string($out) && $out !== '' ) return $out;

		// Legacy fallback
		$resp = apply_filters('myls_ai_generate_text', '', $prompt);
		if ( is_string($resp) && $resp !== '' ) return $resp;

		return '';
	}
}

/* ========================= EXISTING: Meta ====================== */

/** GET POSTS BY TYPE (used by Meta tab) */
add_action('wp_ajax_myls_ai_posts_by_type', function(){
	myls_ai_check_nonce();

	$pt = isset($_POST['pt']) ? sanitize_key($_POST['pt']) : 'page';
	if ( ! post_type_exists($pt) ) {
		myls_ai_json( ['ok'=>false, 'error'=>'Invalid post type'] , 400 );
	}

	$ptype_obj = get_post_type_object($pt);
	$cap = $ptype_obj && isset($ptype_obj->cap->edit_posts) ? $ptype_obj->cap->edit_posts : 'edit_posts';
	if ( ! current_user_can($cap) ) {
		myls_ai_json( ['ok'=>false, 'error'=>'Insufficient permissions'], 403 );
	}

	$ids = get_posts([
		'post_type'       => $pt,
		'post_status'     => ['publish','draft','pending','future','private'],
		'posts_per_page'  => 500,
		'orderby'         => 'title',
		'order'           => 'ASC',
		'fields'          => 'ids',
		'suppress_filters'=> true,
	]);

	$posts = [];
	foreach ($ids as $pid) {
		$title = get_the_title($pid);
		if ($title === '') $title = '(no title)';
		$posts[] = ['id' => (int)$pid, 'title' => $title];
	}

	myls_ai_json( ['ok'=>true, 'posts'=>$posts] );
});

/** GENERATE META (Yoast title/description) */
add_action('wp_ajax_myls_ai_generate_meta', function(){
	myls_ai_check_nonce();

	$kind      = isset($_POST['kind']) ? sanitize_key($_POST['kind']) : 'title'; // 'title'|'desc'
	$pt        = isset($_POST['pt']) ? sanitize_key($_POST['pt']) : 'page';
	$ids       = isset($_POST['ids']) ? (array) $_POST['ids'] : [];
	$prompt    = isset($_POST['prompt']) ? wp_unslash($_POST['prompt']) : '';
	$overwrite = ! empty($_POST['overwrite']);
	$dryrun    = ! empty($_POST['dryrun']);

	if ( $kind !== 'title' && $kind !== 'desc' ) {
		myls_ai_json( ['ok'=>false,'error'=>'Invalid kind'], 400 );
	}
	if ( ! post_type_exists($pt) ) {
		myls_ai_json( ['ok'=>false,'error'=>'Invalid post type'], 400 );
	}
	if ( empty($ids) ) {
		myls_ai_json( ['ok'=>false,'error'=>'No post IDs provided'], 400 );
	}

	$items = [];
	$saved_count = 0;

	foreach ($ids as $maybe_id) {
		$id  = (int) $maybe_id;
		$row = ['id'=>$id, 'post_title'=> get_the_title($id)];
		if ( ! $id || ! current_user_can('edit_post', $id) ) {
			$row['error'] = 'Insufficient permissions';
			$items[] = $row;
			continue;
		}

		$meta_key = ($kind === 'title') ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc';
		$old = get_post_meta($id, $meta_key, true);

		if ( !$overwrite && is_string($old) && $old !== '' ) {
			$row['old']   = $old;
			$row['new']   = '';
			$row['saved'] = false;
			$row['msg']   = 'Existing value present; skipping (overwrite off).';
			$items[] = $row;
			continue;
		}

		$ctx = myls_ai_context_for_post($id);
		$final_prompt = myls_ai_apply_tokens($prompt, $ctx);
		$new = trim( myls_ai_generate_text( $final_prompt ) );

		if ($new === '') {
			$row['old']   = $old;
			$row['new']   = '';
			$row['saved'] = false;
			$row['msg']   = 'No output from AI provider.';
			$items[] = $row;
			continue;
		}

		$row['old']    = $old;
		$row['new']    = $new;
		$row['dryrun'] = !!$dryrun;

		if ( $dryrun ) {
			$row['saved'] = false;
			$row['msg']   = 'Dry-run only.';
		} else {
			update_post_meta( $id, $meta_key, $new );
			$row['saved'] = true;
			$row['msg']   = 'Saved.';
			$saved_count++;
		}

		$items[] = $row;
	}

	$summary = sprintf(
		'%d item(s) processed. %d saved. %d preview/skip.',
		count($items),
		$saved_count,
		count($items) - $saved_count
	);

	myls_ai_json( ['ok'=>true, 'items'=>$items, 'summary'=>$summary] );
});

/* ===================== NEW: About the Area ===================== */

/** List posts for multiselect in About tab */
add_action('wp_ajax_myls_ai_about_get_posts', function(){
	myls_ai_check_nonce();

	$pt = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'service_area';
	if ( ! post_type_exists($pt) ) {
		wp_send_json_error(['status'=>'error','message'=>'invalid_post_type'], 400);
	}

	$ptype_obj = get_post_type_object($pt);
	$cap = $ptype_obj && isset($ptype_obj->cap->edit_posts) ? $ptype_obj->cap->edit_posts : 'edit_posts';
	if ( ! current_user_can($cap) ) {
		wp_send_json_error(['status'=>'error','message'=>'cap_denied'], 403);
	}

	$ids = get_posts([
		'post_type'       => $pt,
		'post_status'     => ['publish','draft','pending','future','private'],
		'posts_per_page'  => -1,
		'orderby'         => 'title',
		'order'           => 'ASC',
		'fields'          => 'ids',
		'suppress_filters'=> true,
	]);

	$out = [];
	foreach ($ids as $pid) {
		$out[] = [
			'id'    => (int) $pid,
			'title' => get_the_title($pid),
		];
	}

	wp_send_json_success(['status'=>'ok','posts'=>$out,'marker'=>'about_get_posts_v1']);
});

/** Generate + save About-the-Area for one post */
add_action('wp_ajax_myls_ai_about_generate', function(){
	myls_ai_check_nonce();

	$post_id     = (int) ($_POST['post_id'] ?? 0);
	$skip_filled = !empty($_POST['skip_filled']);
	$template    = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
	$tokens      = max(1, (int) ($_POST['tokens'] ?? 600));
	$temperature = (float) ($_POST['temperature'] ?? 0.7);

	if ( $post_id <= 0 || get_post_status($post_id) === false ) {
		wp_send_json_error(['marker'=>'about_generate_v1','status'=>'error','message'=>'bad_post'], 400);
	}
	if ( ! current_user_can('edit_post', $post_id) ) {
		wp_send_json_error(['marker'=>'about_generate_v1','status'=>'error','message'=>'cap_denied'], 403);
	}

	// Skip if already filled
	$existing = get_post_meta($post_id, 'about_the_area', true);
	if ( $skip_filled && is_string($existing) && trim($existing) !== '' ) {
		wp_send_json_success([
			'marker'  => 'about_generate_v1',
			'status'  => 'skipped',
			'post_id' => $post_id,
			'reason'  => 'already_filled',
		]);
	}

	// CITY/STATE from ACF or meta or title
	$city_state = '';
	if ( function_exists('get_field') ) $city_state = (string) get_field('city_state', $post_id);
	if ( $city_state === '' ) $city_state = (string) get_post_meta($post_id, 'city_state', true);
	if ( $city_state === '' ) $city_state = get_the_title($post_id);

	// Template fallback
	if ( $template === '' ) $template = get_option('myls_ai_about_prompt_template', '');
	if ( $template === '' ) $template = "Write a 400–500 word clean-HTML 'About the Area' for {{CITY_STATE}}.";
	$prompt = str_replace('{{CITY_STATE}}', $city_state, $template);

	// Generate
	$html = myls_ai_generate_text($prompt, [
		'max_tokens'  => $tokens,
		'temperature' => $temperature,
		'context'     => 'about_the_area',
		'post_id'     => $post_id,
	]);

	if ( $html === '' || is_wp_error($html) ) {
		$err = is_wp_error($html) ? $html->get_error_message() : 'empty_response';
		wp_send_json_error([
			'marker'  => 'about_generate_v1',
			'status'  => 'error',
			'post_id' => $post_id,
			'message' => 'ai_failed',
			'error'   => $err,
		], 500);
	}

	// Sanitize
	$allowed = wp_kses_allowed_html('post');
	$clean   = wp_kses( wp_unslash($html), $allowed );

	/* ---------- Robust ACF save (name → key → raw-meta+link) ---------- */

	// Field key discovery (handles Group/Repeater/Flex)
	if ( ! function_exists('myls_acf_find_field_key_by_name') ) {
		function myls_acf_find_field_key_by_name( $post_id, $target_name ) {
			if ( ! function_exists('get_field_objects') ) return '';
			$objects = get_field_objects( $post_id, false );
			if ( ! is_array($objects) ) return '';
			$stack = array_values($objects);
			while ( $stack ) {
				$fo = array_pop($stack);
				if ( ! is_array($fo) ) continue;
				if ( ! empty($fo['name']) && $fo['name'] === $target_name && ! empty($fo['key']) ) return $fo['key'];
				if ( ! empty($fo['sub_fields']) && is_array($fo['sub_fields']) ) foreach ($fo['sub_fields'] as $sub) $stack[] = $sub;
				if ( ! empty($fo['layouts']) && is_array($fo['layouts']) ) {
					foreach ($fo['layouts'] as $lay) {
						if ( ! empty($lay['sub_fields']) && is_array($lay['sub_fields']) ) foreach ($lay['sub_fields'] as $sub) $stack[] = $sub;
					}
				}
			}
			return '';
		}
	}

	$saved        = false;
	$saved_method = '';
	$acf_key_used = '';

	// (1) ACF by NAME
	if ( function_exists('update_field') && update_field('about_the_area', $clean, $post_id) ) {
		$saved = true; $saved_method = 'acf_update_field_by_name';
	}

	// (2) ACF by KEY
	if ( ! $saved && function_exists('update_field') ) {
		$key = '';
		if ( function_exists('get_field_object') ) {
			$fo = get_field_object('about_the_area', $post_id);
			if ( $fo && !empty($fo['key']) ) $key = $fo['key'];
		}
		if ( $key === '' ) $key = myls_acf_find_field_key_by_name( $post_id, 'about_the_area' );
		if ( $key !== '' && update_field( $key, $clean, $post_id ) ) {
			$saved = true; $saved_method = 'acf_update_field_by_key'; $acf_key_used = $key;
		}
	}

	// (3) Raw meta + link companion key
	if ( ! $saved ) {
		$saved_raw = (bool) update_post_meta( $post_id, 'about_the_area', $clean );
		if ( $saved_raw ) {
			$saved = true; $saved_method = 'raw_update_post_meta';
			if ( $acf_key_used === '' ) $acf_key_used = myls_acf_find_field_key_by_name( $post_id, 'about_the_area' );
			if ( $acf_key_used !== '' ) {
				update_post_meta($post_id, '_about_the_area', $acf_key_used);
				$saved_method .= '+acf_key_link';
			}
		}
	}

	// (4) Verify
	$verify = '';
	if ( function_exists('get_field') ) $verify = (string) get_field('about_the_area', $post_id);
	if ( $verify === '' ) $verify = (string) get_post_meta($post_id, 'about_the_area', true);

	if ( ! $saved || trim( wp_strip_all_tags($verify) ) === '' ) {
		wp_send_json_error([
			'marker'  => 'about_generate_v1',
			'status'  => 'error',
			'post_id' => $post_id,
			'message' => 'save_failed_or_empty_after_write',
			'debug'   => [
				'saved'        => $saved ? 'true' : 'false',
				'saved_method' => $saved_method,
				'acf_key_used' => $acf_key_used,
				'verify_len'   => strlen($verify),
			],
		], 500);
	}

	wp_send_json_success([
		'marker'     => 'about_generate_v1',
		'status'     => 'saved',
		'post_id'    => $post_id,
		'length'     => strlen($clean),
		'city_state' => $city_state,
		'preview'    => mb_substr( wp_strip_all_tags( $verify ), 0, 80 ) . ( strlen( wp_strip_all_tags($verify) ) > 80 ? '…' : '' ),
		'debug'      => [
			'saved_method' => $saved_method,
			'acf_key_used' => $acf_key_used,
		],
	]);
});
