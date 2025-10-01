<?php
/**
 * My Local SEO – AI AJAX
 * Path: inc/ajax/ai.php
 *
 * Endpoints:
 *  - wp_ajax_myls_ai_posts_by_type
 *  - wp_ajax_myls_ai_generate_meta
 */

if ( ! defined('ABSPATH') ) exit;

/** Utility: JSON response */
if ( ! function_exists('myls_ai_json') ) {
	function myls_ai_json( array $data, int $code = 200 ) : void {
		status_header( $code );
		wp_send_json( $data );
	}
}

/** Security/nonce checker */
if ( ! function_exists('myls_ai_check_nonce') ) {
	function myls_ai_check_nonce( string $action = 'myls_ai_ops' ) : void {
		// Accept both 'nonce' and '_ajax_nonce'
		$nonce = isset($_POST['nonce']) ? $_POST['nonce'] : ( $_REQUEST['_ajax_nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			myls_ai_json( ['ok'=>false, 'error'=>'Invalid or missing nonce'], 403 );
		}
	}
}

/** GET POSTS BY TYPE */
add_action('wp_ajax_myls_ai_posts_by_type', function(){
	myls_ai_check_nonce();

	$pt = isset($_POST['pt']) ? sanitize_key($_POST['pt']) : 'page';
	if ( ! post_type_exists($pt) ) {
		myls_ai_json( ['ok'=>false, 'error'=>'Invalid post type'] , 400 );
	}

	// Cap: require edit_posts on this PT
	$ptype_obj = get_post_type_object($pt);
	$cap = $ptype_obj && isset($ptype_obj->cap->edit_posts) ? $ptype_obj->cap->edit_posts : 'edit_posts';
	if ( ! current_user_can($cap) ) {
		myls_ai_json( ['ok'=>false, 'error'=>'Insufficient permissions'], 403 );
	}

	$ids = get_posts([
		'post_type'      => $pt,
		'post_status'    => ['publish','draft','pending','future','private'],
		'posts_per_page' => 500,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
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

/** Small helper: collect context tokens for a post */
if ( ! function_exists('myls_ai_context_for_post') ) {
	function myls_ai_context_for_post( int $post_id ) : array {
		$post = get_post( $post_id );
		if ( ! $post ) return [];

		$site_name = get_bloginfo('name');

		$excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '…' );

		$primary_category = '';
		$cats = get_the_category( $post_id );
		if ( is_array($cats) && !empty($cats) ) {
			$primary_category = $cats[0]->name;
		}

		return [
			'post_title'       => get_the_title($post_id),
			'site_name'        => $site_name,
			'excerpt'          => $excerpt,
			'primary_category' => $primary_category,
			'permalink'        => get_permalink($post_id),
		];
	}
}

/** Replace tokens {like_this} in the prompt with values */
if ( ! function_exists('myls_ai_apply_tokens') ) {
	function myls_ai_apply_tokens( string $tpl, array $ctx ) : string {
		return preg_replace_callback('/\{([a-z0-9_]+)\}/i', function($m) use ($ctx){
			$key = $m[1];
			return isset($ctx[$key]) ? (string)$ctx[$key] : $m[0];
		}, $tpl);
	}
}

/** Call OpenAI (or your existing generator) */
if ( ! function_exists('myls_ai_generate_text') ) {
	function myls_ai_generate_text( string $prompt ) : string {
		/**
		 * Hook here to your existing integration.
		 * If you already have a helper (e.g., myls_openai_generate_text($prompt)), use it.
		 * Fallback: filter allows external providers to fill in.
		 */
		$resp = apply_filters('myls_ai_generate_text', '', $prompt);
		if ( is_string($resp) && $resp !== '' ) return $resp;

		// As a safe fallback, just return empty so UI shows SKIPPED/preview.
		return '';
	}
}

/** GENERATE META (title or description) */
add_action('wp_ajax_myls_ai_generate_meta', function(){
	myls_ai_check_nonce();

	$kind     = isset($_POST['kind']) ? sanitize_key($_POST['kind']) : 'title'; // 'title' | 'desc'
	$pt       = isset($_POST['pt']) ? sanitize_key($_POST['pt']) : 'page';
	$ids      = isset($_POST['ids']) ? (array) $_POST['ids'] : [];
	$prompt   = isset($_POST['prompt']) ? wp_unslash($_POST['prompt']) : '';
	$overwrite= ! empty($_POST['overwrite']);
	$dryrun   = ! empty($_POST['dryrun']);

	if ( $kind !== 'title' && $kind !== 'desc' ) {
		myls_ai_json( ['ok'=>false,'error'=>'Invalid kind'], 400 );
	}
	if ( ! post_type_exists($pt) ) {
		myls_ai_json( ['ok'=>false,'error'=>'Invalid post type'], 400 );
	}
	if ( empty($ids) ) {
		myls_ai_json( ['ok'=>false,'error'=>'No post IDs provided'], 400 );
	}

	// Capability — require edit_post for each
	$items = [];
	$saved_count = 0;

	foreach ($ids as $maybe_id) {
		$id = (int) $maybe_id;
		$row = ['id'=>$id, 'post_title'=> get_the_title($id)];
		if ( ! $id || ! current_user_can('edit_post', $id) ) {
			$row['error'] = 'Insufficient permissions';
			$items[] = $row;
			continue;
		}

		// Determine Yoast keys
		$meta_key = ($kind === 'title') ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc';
		$old = get_post_meta($id, $meta_key, true);

		// Skip if not overwriting and already set
		if ( !$overwrite && is_string($old) && $old !== '' ) {
			$row['old'] = $old;
			$row['new'] = '';
			$row['saved'] = false;
			$row['msg'] = 'Existing value present; skipping (overwrite off).';
			$items[] = $row;
			continue;
		}

		// Build context and apply tokens
		$ctx = myls_ai_context_for_post($id);
		$final_prompt = myls_ai_apply_tokens($prompt, $ctx);

		// Generate
		$new = trim( myls_ai_generate_text( $final_prompt ) );

		// If provider returned empty, mark skipped
		if ($new === '') {
			$row['old'] = $old;
			$row['new'] = '';
			$row['saved'] = false;
			$row['msg'] = 'No output from AI provider.';
			$items[] = $row;
			continue;
		}

		$row['old'] = $old;
		$row['new'] = $new;
		$row['dryrun'] = !!$dryrun;

		if ( $dryrun ) {
			$row['saved'] = false;
			$row['msg'] = 'Dry-run only.';
		} else {
			update_post_meta( $id, $meta_key, $new );
			$row['saved'] = true;
			$row['msg'] = 'Saved.';
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
