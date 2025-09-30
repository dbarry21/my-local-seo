<?php
/**
 * Google Search Console OAuth (My Local SEO)
 *
 * Provides:
 * - Connect:  /wp-admin/admin-post.php?action=myls_gsc_oauth_start
 * - Callback: /wp-admin/admin-post.php?action=myls_gsc_oauth_cb
 * - Disconnect: /wp-admin/admin-post.php?action=myls_gsc_disconnect
 * - Test AJAX: action "myls_test_gsc_client"
 *
 * Options used:
 *   myls_gsc_client_id
 *   myls_gsc_client_secret
 *   myls_gsc_redirect_uri
 *   myls_gsc_access_token
 *   myls_gsc_refresh_token
 *   myls_gsc_token_expires (unix ts)
 *   myls_gsc_scope
 *   myls_gsc_test_result   (UI convenience)
 */

if ( ! defined('ABSPATH') ) exit;

/** ===== Config / helpers ===== */
function myls_gsc_scopes() : array {
	// readonly is usually enough; use '.../auth/webmasters' for write ops (sitemaps, etc.)
	return ['https://www.googleapis.com/auth/webmasters.readonly'];
}
function myls_gsc_client() : array {
	return [
		'id'     => (string) get_option('myls_gsc_client_id', ''),
		'secret' => (string) get_option('myls_gsc_client_secret', ''),
		'redirect' => (string) get_option('myls_gsc_redirect_uri', admin_url('admin-post.php?action=myls_gsc_oauth_cb')),
	];
}
function myls_gsc_is_connected() : bool {
	return (string) get_option('myls_gsc_refresh_token', '') !== '';
}
function myls_gsc_settings_url() : string {
	// back to your tab
	return admin_url('admin.php?page=my-local-seo&tab=api-integration');
}

/** ===== Build Google OAuth URL ===== */
function myls_gsc_build_auth_url() : string {
	$c = myls_gsc_client();
	$scopes = implode(' ', myls_gsc_scopes());

	// 'state' protects CSRF + lets us carry return info
	$state_payload = [
		'nonce' => wp_create_nonce('myls_gsc_oauth_state'),
		'go'    => 'api-integration',
	];
	$state = rawurlencode( base64_encode( wp_json_encode( $state_payload ) ) );

	$args = [
		'client_id'             => $c['id'],
		'redirect_uri'          => $c['redirect'],
		'response_type'         => 'code',
		'scope'                 => $scopes,
		'access_type'           => 'offline', // get refresh token
		'include_granted_scopes'=> 'true',
		'prompt'                => 'consent', // ensure refresh token is returned
		'state'                 => $state,
	];
	return add_query_arg( $args, 'https://accounts.google.com/o/oauth2/v2/auth' );
}

/** ===== Token storage helpers ===== */
function myls_gsc_store_tokens( array $t ) : void {
	// expects: access_token, expires_in, refresh_token?, scope?
	if ( isset($t['access_token']) ) {
		update_option('myls_gsc_access_token', (string)$t['access_token'], false);
	}
	if ( isset($t['expires_in']) ) {
		update_option('myls_gsc_token_expires', time() + (int)$t['expires_in'] - 60, false); // 60s skew
	}
	if ( ! empty($t['refresh_token']) ) {
		update_option('myls_gsc_refresh_token', (string)$t['refresh_token'], false);
	}
	if ( isset($t['scope']) ) {
		update_option('myls_gsc_scope', (string)$t['scope'], false);
	}
}
function myls_gsc_clear_tokens() : void {
	delete_option('myls_gsc_access_token');
	delete_option('myls_gsc_refresh_token');
	delete_option('myls_gsc_token_expires');
	delete_option('myls_gsc_scope');
}

/** ===== Get valid access token (refresh if needed) ===== */
function myls_gsc_get_access_token() {
	$access  = (string) get_option('myls_gsc_access_token', '');
	$refresh = (string) get_option('myls_gsc_refresh_token', '');
	$exp     = (int) get_option('myls_gsc_token_expires', 0);
	$c       = myls_gsc_client();

	// if not near expiry, use current
	if ( $access !== '' && time() < $exp ) {
		return $access;
	}
	if ( $refresh === '' ) {
		return new WP_Error('no_refresh', 'Not connected (no refresh token).');
	}

	// refresh
	$resp = wp_remote_post( 'https://oauth2.googleapis.com/token', [
		'timeout' => 20,
		'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
		'body'    => [
			'client_id'     => $c['id'],
			'client_secret' => $c['secret'],
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refresh,
		],
	] );
	if ( is_wp_error($resp) ) return $resp;

	$code = wp_remote_retrieve_response_code($resp);
	$data = json_decode( wp_remote_retrieve_body($resp), true );
	if ( $code !== 200 || ! is_array($data) || empty($data['access_token']) ) {
		return new WP_Error('refresh_failed', 'Failed to refresh token: ' . ( $data['error_description'] ?? 'Unknown error' ));
	}
	myls_gsc_store_tokens($data);
	return (string) $data['access_token'];
}

/** ====== START: redirect to Google ====== */
add_action('admin_post_myls_gsc_oauth_start', function(){
	if ( ! current_user_can('manage_options') ) wp_die('Forbidden', 403);
	check_admin_referer('myls_gsc_oauth_start'); // nonce in the link

	$c = myls_gsc_client();
	if ( $c['id'] === '' || $c['secret'] === '' || $c['redirect'] === '' ) {
		wp_safe_redirect( add_query_arg('gsc_error', rawurlencode('Missing Client ID/Secret/Redirect'), myls_gsc_settings_url() ) );
		exit;
	}
	wp_safe_redirect( myls_gsc_build_auth_url() );
	exit;
});

/** ====== CALLBACK: exchange code -> tokens ====== */
add_action('admin_post_myls_gsc_oauth_cb', function(){
	// We're on the redirect URI; handle success or error
	$state_raw = isset($_GET['state']) ? wp_unslash($_GET['state']) : '';
	$state_ok = false;
	if ( $state_raw !== '' ) {
		$decoded = json_decode( base64_decode( rawurldecode($state_raw) ), true );
		if ( is_array($decoded) && ! empty($decoded['nonce']) && wp_verify_nonce( $decoded['nonce'], 'myls_gsc_oauth_state' ) ) {
			$state_ok = true;
		}
	}
	if ( ! $state_ok ) {
		wp_safe_redirect( add_query_arg('gsc_error', rawurlencode('Invalid state'), myls_gsc_settings_url() ) );
		exit;
	}

	if ( isset($_GET['error']) ) {
		// user denied or other oauth error
		$msg = sanitize_text_field( (string) $_GET['error'] );
		wp_safe_redirect( add_query_arg('gsc_error', rawurlencode($msg), myls_gsc_settings_url() ) );
		exit;
	}

	$code = isset($_GET['code']) ? sanitize_text_field( (string) $_GET['code'] ) : '';
	if ( $code === '' ) {
		wp_safe_redirect( add_query_arg('gsc_error', rawurlencode('Missing code'), myls_gsc_settings_url() ) );
		exit;
	}

	$c = myls_gsc_client();
	$resp = wp_remote_post( 'https://oauth2.googleapis.com/token', [
		'timeout' => 20,
		'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
		'body'    => [
			'code'          => $code,
			'client_id'     => $c['id'],
			'client_secret' => $c['secret'],
			'redirect_uri'  => $c['redirect'],
			'grant_type'    => 'authorization_code',
		],
	] );
	if ( is_wp_error($resp) ) {
		wp_safe_redirect( add_query_arg('gsc_error', rawurlencode($resp->get_error_message()), myls_gsc_settings_url() ) );
		exit;
	}

	$data = json_decode( wp_remote_retrieve_body($resp), true );
	$code_num = wp_remote_retrieve_response_code($resp);
	if ( $code_num !== 200 || empty($data['access_token']) ) {
		$msg = isset($data['error_description']) ? $data['error_description'] : 'Token exchange failed';
		wp_safe_redirect( add_query_arg('gsc_error', rawurlencode($msg), myls_gsc_settings_url() ) );
		exit;
	}

	myls_gsc_store_tokens($data);
	wp_safe_redirect( add_query_arg('gsc', 'connected', myls_gsc_settings_url() ) );
	exit;
});

/** ====== DISCONNECT ====== */
add_action('admin_post_myls_gsc_disconnect', function(){
	if ( ! current_user_can('manage_options') ) wp_die('Forbidden', 403);
	check_admin_referer('myls_gsc_disconnect');

	myls_gsc_clear_tokens();
	wp_safe_redirect( add_query_arg('gsc', 'disconnected', myls_gsc_settings_url() ) );
	exit;
});

/** ====== AJAX TEST: list sites ====== */
add_action('wp_ajax_myls_test_gsc_client', function(){
	if ( ! current_user_can('manage_options') ) wp_send_json_error('Insufficient permissions.');
	// Nonce is carried by your JS as 'nonce' (myls_api_tab_ajax)
	check_ajax_referer('myls_api_tab_ajax', 'nonce');

	$token = myls_gsc_get_access_token();
	if ( is_wp_error($token) ) {
		$msg = $token->get_error_message();
		update_option('myls_gsc_test_result', $msg, false);
		wp_send_json_error( $msg );
	}

	// Query GSC sites
	$resp = wp_remote_get( 'https://searchconsole.googleapis.com/webmasters/v3/sites', [
		'timeout' => 20,
		'headers' => [
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/json',
		]
	] );
	if ( is_wp_error($resp) ) {
		$msg = 'Network: ' . $resp->get_error_message();
		update_option('myls_gsc_test_result', $msg, false);
		wp_send_json_error($msg);
	}

	$code = wp_remote_retrieve_response_code($resp);
	$body = json_decode( wp_remote_retrieve_body($resp), true );

	if ( $code === 200 && is_array($body) && isset($body['siteEntry']) ) {
		$count = is_array($body['siteEntry']) ? count($body['siteEntry']) : 0;
		$msg = "OK — {$count} verified property" . ( $count === 1 ? '' : 'ies' );
		update_option('myls_gsc_test_result', $msg, false);
		wp_send_json_success($msg);
	}

	$api_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API response';
	$msg = "GSC API error (HTTP {$code}): {$api_msg}";
	update_option('myls_gsc_test_result', $msg, false);
	wp_send_json_error($msg);
});
