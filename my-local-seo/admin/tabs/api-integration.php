<?php
/**
 * Admin Tab: API Integration (with YouTube + GSC OAuth)
 * Path: admin/tabs/api-integration.php
 *
 * Purpose:
 * - Central place to store API credentials and manage OAuth connections.
 * - Includes:
 *     • Google Places (Business Profile) API key + Default Place ID
 *     • Google Static Maps API key
 *     • OpenAI API key
 *     • YouTube API key + Channel ID  (for public data)
 *     • YouTube OAuth (Data API)       (for user-authorized calls)
 *     • Google Search Console OAuth
 *
 * Assumptions:
 * - GSC OAuth module lives in modules/oauth/google-search-console.php (provided earlier).
 * - YouTube OAuth module lives in modules/oauth/youtube.php (similar shape to GSC module).
 *   Both expose helpers used here (see function_exists guards below).
 */

if ( ! defined('ABSPATH') ) exit;

/** --------------------------------------------------------------
 * Small display helper: mask API keys when showing current value.
 * -------------------------------------------------------------- */
if ( ! function_exists('myls_mask_key_simple') ) {
	function myls_mask_key_simple( string $k ) : string {
		$k = trim($k);
		if ( $k === '' ) return '';
		$len = strlen($k);
		if ( $len <= 8 ) return str_repeat('•', max(0, $len-2)) . substr($k, -2);
		return substr($k, 0, 4) . str_repeat('•', $len - 8) . substr($k, -4);
	}
}

/** --------------------------------------------------------------
 * Fallbacks so the UI won’t fatal if OAuth modules not loaded yet
 * (Real implementations should be in modules/oauth/*.php)
 * -------------------------------------------------------------- */
if ( ! function_exists('myls_gsc_is_connected') ) {
	function myls_gsc_is_connected() : bool { return (string) get_option('myls_gsc_refresh_token','') !== ''; }
}
if ( ! function_exists('myls_gsc_settings_url') ) {
	function myls_gsc_settings_url() : string { return admin_url('admin.php?page=my-local-seo&tab=api-integration'); }
}

if ( ! function_exists('myls_yt_is_connected') ) {
	function myls_yt_is_connected() : bool { return (string) get_option('myls_yt_refresh_token','') !== ''; }
}
if ( ! function_exists('myls_yt_settings_url') ) {
	function myls_yt_settings_url() : string { return admin_url('admin.php?page=my-local-seo&tab=api-integration'); }
}

/** --------------------------------------------------------------
 * Register the API Integration tab
 * -------------------------------------------------------------- */
myls_register_admin_tab([
	'id'    => 'api-integration',
	'title' => 'API Integration',
	'order' => 20,                      // Dashboard(1) → YT Video Blog(15) → API Integration(20) → CPT(25) → Schema(30) …
	'cap'   => 'manage_options',
	'icon'  => 'dashicons-rest-api',
	'cb'    => function () {

		// ----------------------------------------
		// SAVE
		// ----------------------------------------
		if (
			isset($_POST['myls_api_tab_nonce']) &&
			wp_verify_nonce( $_POST['myls_api_tab_nonce'], 'myls_api_tab_save' ) &&
			current_user_can('manage_options')
		) {
			// Google Places / GBP
			update_option( 'myls_google_places_api_key',      sanitize_text_field( $_POST['myls_google_places_api_key']      ?? '' ) );
			update_option( 'myls_google_places_place_id',     sanitize_text_field( $_POST['myls_google_places_place_id']     ?? '' ) );

			// Google Static Maps
			update_option( 'myls_google_static_maps_api_key', sanitize_text_field( $_POST['myls_google_static_maps_api_key'] ?? '' ) );

			// OpenAI
			update_option( 'myls_openai_api_key',             sanitize_text_field( $_POST['myls_openai_api_key']             ?? '' ) );

			// YouTube (API key + Channel)
			update_option( 'myls_youtube_api_key',            sanitize_text_field( $_POST['myls_youtube_api_key']            ?? '' ) );
			update_option( 'myls_youtube_channel_id',         sanitize_text_field( $_POST['myls_youtube_channel_id']         ?? '' ) );

			// GSC OAuth client (used by OAuth flow)
			update_option( 'myls_gsc_client_id',              sanitize_text_field( $_POST['myls_gsc_client_id']              ?? '' ) );
			update_option( 'myls_gsc_client_secret',          sanitize_text_field( $_POST['myls_gsc_client_secret']          ?? '' ) );
			update_option( 'myls_gsc_redirect_uri',           esc_url_raw(         $_POST['myls_gsc_redirect_uri']           ?? '' ) );

			// YouTube OAuth client (optional; can be same project as GSC but separate action)
			update_option( 'myls_yt_client_id',               sanitize_text_field( $_POST['myls_yt_client_id']               ?? '' ) );
			update_option( 'myls_yt_client_secret',           sanitize_text_field( $_POST['myls_yt_client_secret']           ?? '' ) );
			update_option( 'myls_yt_redirect_uri',            esc_url_raw(         $_POST['myls_yt_redirect_uri']            ?? '' ) );

			echo '<div class="notice notice-success is-dismissible"><p>API Integration settings saved.</p></div>';
		}

		// ----------------------------------------
		// LOAD
		// ----------------------------------------
		$places_api   = get_option('myls_google_places_api_key', '');
		$places_pid   = get_option('myls_google_places_place_id', '');
		$maps_api     = get_option('myls_google_static_maps_api_key', '');
		$openai_key   = get_option('myls_openai_api_key', '');

		$yt_api_key   = get_option('myls_youtube_api_key', '');
		$yt_channel   = get_option('myls_youtube_channel_id', '');

		$gsc_id       = get_option('myls_gsc_client_id', '');
		$gsc_secret   = get_option('myls_gsc_client_secret', '');
		$gsc_redirect = get_option('myls_gsc_redirect_uri', admin_url('admin-post.php?action=myls_gsc_oauth_cb'));

		$ytc_id       = get_option('myls_yt_client_id', '');
		$ytc_secret   = get_option('myls_yt_client_secret', '');
		$ytc_redirect = get_option('myls_yt_redirect_uri', admin_url('admin-post.php?action=myls_yt_oauth_cb'));

		// Optional: last test results (set by your AJAX handlers if you add them)
		$last_places_test  = get_option('myls_places_test_result',  'No test run yet.');
		$last_pid_test     = get_option('myls_places_pid_test_result', 'No test run yet.');
		$last_maps_test    = get_option('myls_maps_test_result',    'No test run yet.');
		$last_openai_test  = get_option('myls_openai_test_result',  'No test run yet.');
		$last_youtube_test = get_option('myls_youtube_test_result', 'No test run yet.');
		$last_ytoauth_test = get_option('myls_ytoauth_test_result', 'No test run yet.');
		$last_gsc_test     = get_option('myls_gsc_test_result',     'No check run yet.');

		$ajax_nonce = wp_create_nonce('myls_api_tab_ajax');

		// Connection state + URLs (exist if OAuth modules are present)
		$gsc_connected  = function_exists('myls_gsc_is_connected') ? myls_gsc_is_connected() : false;
		$gsc_connect_url    = wp_nonce_url( admin_url('admin-post.php?action=myls_gsc_oauth_start'), 'myls_gsc_oauth_start' );
		$gsc_disconnect_url = wp_nonce_url( admin_url('admin-post.php?action=myls_gsc_disconnect'),  'myls_gsc_disconnect'  );
		$gsc_flag_msg   = isset($_GET['gsc']) ? sanitize_text_field($_GET['gsc']) : '';
		$gsc_err_msg    = isset($_GET['gsc_error']) ? sanitize_text_field($_GET['gsc_error']) : '';

		$yt_connected   = function_exists('myls_yt_is_connected') ? myls_yt_is_connected() : false;
		$yt_connect_url    = wp_nonce_url( admin_url('admin-post.php?action=myls_yt_oauth_start'), 'myls_yt_oauth_start' );
		$yt_disconnect_url = wp_nonce_url( admin_url('admin-post.php?action=myls_yt_oauth_disconnect'), 'myls_yt_oauth_disconnect' );
		$yt_flag_msg    = isset($_GET['yt_oauth']) ? sanitize_text_field($_GET['yt_oauth']) : '';
		$yt_err_msg     = isset($_GET['yt_oauth_error']) ? sanitize_text_field($_GET['yt_oauth_error']) : '';

		?>
		<div class="wrap myls-api-integration">
			<h1 class="wp-heading-inline">API Integration</h1>
			<p class="description">Store and manage the API credentials used across the plugin. Use API keys for public reads; use OAuth for user-authorized operations.</p>

			<?php if ($gsc_flag_msg === 'connected'): ?>
				<div class="notice notice-success is-dismissible"><p>Google Search Console connected.</p></div>
			<?php elseif ($gsc_flag_msg === 'disconnected'): ?>
				<div class="notice notice-warning is-dismissible"><p>Google Search Console disconnected.</p></div>
			<?php endif; ?>
			<?php if ($gsc_err_msg): ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html($gsc_err_msg); ?></p></div>
			<?php endif; ?>

			<?php if ($yt_flag_msg === 'connected'): ?>
				<div class="notice notice-success is-dismissible"><p>YouTube OAuth connected.</p></div>
			<?php elseif ($yt_flag_msg === 'disconnected'): ?>
				<div class="notice notice-warning is-dismissible"><p>YouTube OAuth disconnected.</p></div>
			<?php endif; ?>
			<?php if ($yt_err_msg): ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html($yt_err_msg); ?></p></div>
			<?php endif; ?>

			<form method="post" class="mt-3" autocomplete="off">
				<?php wp_nonce_field('myls_api_tab_save', 'myls_api_tab_nonce'); ?>

				<div class="row" style="display:flex; gap:16px; flex-wrap:wrap; margin-top:8px; max-width: 1200px;">

					<!-- Google Places (Business Profile) -->
					<div class="card" style="flex:1 1 460px; min-width:460px;">
						<div class="card-body">
							<h2 class="title">Google Places (Business Profile)</h2>

							<label for="myls_google_places_api_key" class="form-label">API Key</label>
							<div class="input-group" style="display:flex; gap:8px; align-items:center;">
								<input type="text" class="regular-text" id="myls_google_places_api_key" name="myls_google_places_api_key" value="<?php echo esc_attr($places_api); ?>" placeholder="AIza...">
								<button type="button" class="button" id="myls-test-places-api" data-nonce="<?php echo esc_attr($ajax_nonce); ?>">Test</button>
							</div>
							<?php if ($places_api): ?>
								<p class="description">Current: <code><?php echo esc_html( myls_mask_key_simple($places_api) ); ?></code></p>
							<?php endif; ?>
							<p class="description">Last key test: <em><?php echo esc_html($last_places_test); ?></em></p>
							<div id="myls-places-api-test-result" class="notice inline" style="margin-top:8px;"></div>

							<hr style="margin:16px 0;">

							<label for="myls_google_places_place_id" class="form-label">Default Place ID</label>
							<div class="input-group" style="display:flex; gap:8px; align-items:center;">
								<input type="text" class="regular-text" id="myls_google_places_place_id" name="myls_google_places_place_id" value="<?php echo esc_attr($places_pid); ?>" placeholder="e.g. ChIJN1t_tDeuEmsRUsoyG83frY4">
								<button type="button" class="button" id="myls-test-places-id" data-nonce="<?php echo esc_attr($ajax_nonce); ?>">Test Place ID</button>
							</div>
							<p class="description">Used by shortcodes when a <code>place_id</code> isn’t provided explicitly.</p>
							<p class="description">Last Place ID test: <em><?php echo esc_html($last_pid_test); ?></em></p>
							<div id="myls-places-pid-test-result" class="notice inline" style="margin-top:8px;"></div>
						</div>
					</div>

					<!-- Google Static Maps -->
					<div class="card" style="flex:1 1 460px; min-width:460px;">
						<div class="card-body">
							<h2 class="title">Google Static Maps</h2>
							<label for="myls_google_static_maps_api_key" class="form-label">API Key</label>
							<div class="input-group" style="display:flex; gap:8px; align-items:center;">
								<input type="text" class="regular-text" id="myls_google_static_maps_api_key" name="myls_google_static_maps_api_key" value="<?php echo esc_attr($maps_api); ?>" placeholder="AIza...">
								<button type="button" class="button" id="myls-test-maps-api" data-nonce="<?php echo esc_attr($ajax_nonce); ?>">Test</button>
							</div>
							<p class="description">Used for generating featured static map images.</p>
							<p class="description">Last test: <em><?php echo esc_html($last_maps_test); ?></em></p>
							<div id="myls-maps-api-test-result" class="notice inline" style="margin-top:8px;"></div>
						</div>
					</div>

					<!-- OpenAI -->
					<div class="card" style="flex:1 1 460px; min-width:460px;">
						<div class="card-body">
							<h2 class="title">OpenAI</h2>
							<label for="myls_openai_api_key" class="form-label">API Key</label>
							<div class="input-group" style="display:flex; gap:8px; align-items:center;">
								<input type="text" class="regular-text" id="myls_openai_api_key" name="myls_openai_api_key" value="<?php echo esc_attr($openai_key); ?>" placeholder="sk-...">
								<button type="button" class="button" id="myls-test-openai-api" data-nonce="<?php echo esc_attr($ajax_nonce); ?>">Test</button>
							</div>
							<p class="description">Used for AI-powered features.</p>
							<p class="description">Last test: <em><?php echo esc_html($last_openai_test); ?></em></p>
							<div id="myls-openai-api-test-result" class="notice inline" style="margin-top:8px;"></div>
						</div>
					</div>

					<!-- YouTube (API Key + Channel) -->
					<div class="card" style="flex:1 1 460px; min-width:460px;">
						<div class="card-body">
							<h2 class="title">YouTube (API Key)</h2>
							<div class="row" style="display:flex; gap:8px; flex-wrap:wrap;">
								<div style="flex:1 1 60%;">
									<label class="form-label" for="myls_youtube_api_key">API Key</label>
									<input type="text" class="regular-text" id="myls_youtube_api_key" name="myls_youtube_api_key" value="<?php echo esc_attr($yt_api_key); ?>" placeholder="AIza...">
									<?php if ($yt_api_key) : ?>
										<p class="description">Current: <code><?php echo esc_html( myls_mask_key_simple($yt_api_key) ); ?></code></p>
									<?php endif; ?>
								</div>
								<div style="flex:1 1 38%;">
									<label class="form-label" for="myls_youtube_channel_id">Channel ID</label>
									<input type="text" class="regular-text" id="myls_youtube_channel_id" name="myls_youtube_channel_id" value="<?php echo esc_attr($yt_channel); ?>" placeholder="UCxxxxxxxxxxxx">
								</div>
							</div>
							<div style="margin-top:8px;">
								<button type="button" class="button" id="myls-test-youtube-api" data-nonce="<?php echo esc_attr($ajax_nonce); ?>">Test YouTube (API key)</button>
							</div>
							<p class="description" style="margin-top:8px;">Last test: <em><?php echo esc_html($last_youtube_test); ?></em></p>
							<div id="myls-youtube-api-test-result" class="notice inline" style="margin-top:8px;"></div>
						</div>
					</div>

					<!-- YouTube OAuth -->
					<div class="card" style="flex:1 1 460px; min-width:460px;">
						<div class="card-body">
							<h2 class="title">YouTube (OAuth)</h2>
							<div class="row" style="display:flex; gap:8px; flex-wrap:wrap;">
								<div style="flex:1 1 48%;">
									<label class="form-label" for="myls_yt_client_id">Client ID</label>
									<input type="text" class="regular-text" id="myls_yt_client_id" name="myls_yt_client_id" value="<?php echo esc_attr($ytc_id); ?>" placeholder="xxxx.apps.googleusercontent.com">
								</div>
								<div style="flex:1 1 48%;">
									<label class="form-label" for="myls_yt_client_secret">Client Secret</label>
									<input type="text" class="regular-text" id="myls_yt_client_secret" name="myls_yt_client_secret" value="<?php echo esc_attr($ytc_secret); ?>" placeholder="••••••••••••••">
								</div>
								<div style="flex:1 1 100%;">
									<label class="form-label" for="myls_yt_redirect_uri">OAuth Redirect URI</label>
									<input type="url" class="regular-text" id="myls_yt_redirect_uri" name="myls_yt_redirect_uri" value="<?php echo esc_attr($ytc_redirect); ?>">
									<p class="description">Add this exact URI to your Google Cloud OAuth client. Scope used: <code>youtube.readonly</code></p>
								</div>
							</div>

							<div style="margin-top:8px;">
								<p>
									<strong>Status:</strong>
									<?php if ($yt_connected): ?>
										<span class="dashicons dashicons-yes" style="color:#1a7f37;"></span> Connected
									<?php else: ?>
										<span class="dashicons dashicons-dismiss" style="color:#b91c1c;"></span> Not connected
									<?php endif; ?>
								</p>
								<p class="d-flex" style="display:flex; gap:8px;">
									<?php if ( ! $yt_connected ): ?>
										<a href="<?php echo esc_url($yt_connect_url); ?>" class="button button-primary"><?php esc_html_e('Connect Google','myls'); ?></a>
									<?php else: ?>
										<a href="<?php echo esc_url($yt_disconnect_url); ?>" class="button button-secondary"><?php esc_html_e('Disconnect','myls'); ?></a>
									<?php endif; ?>
									<button type="button" class="button" id="myls-test-youtube-oauth" data-nonce="<?php echo esc_attr($ajax_nonce); ?>">Test YouTube (OAuth)</button>
								</p>
								<p class="description" style="margin-top:8px;">Last OAuth test: <em><?php echo esc_html($last_ytoauth_test); ?></em></p>
								<div id="myls-youtube-oauth-test-result" class="notice inline" style="margin-top:8px;"></div>
							</div>
						</div>
					</div>

					<!-- Google Search Console (OAuth) -->
					<div class="card" style="flex:1 1 460px; min-width:460px;">
						<div class="card-body">
							<h2 class="title">Google Search Console (OAuth)</h2>
							<div class="row" style="display:flex; gap:8px; flex-wrap:wrap;">
								<div style="flex:1 1 48%;">
									<label class="form-label" for="myls_gsc_client_id">Client ID</label>
									<input type="text" class="regular-text" id="myls_gsc_client_id" name="myls_gsc_client_id" value="<?php echo esc_attr($gsc_id); ?>" placeholder="xxxx.apps.googleusercontent.com">
								</div>
								<div style="flex:1 1 48%;">
									<label class="form-label" for="myls_gsc_client_secret">Client Secret</label>
									<input type="text" class="regular-text" id="myls_gsc_client_secret" name="myls_gsc_client_secret" value="<?php echo esc_attr($gsc_secret); ?>" placeholder="••••••••••••••">
								</div>
								<div style="flex:1 1 100%;">
									<label class="form-label" for="myls_gsc_redirect_uri">OAuth Redirect URI</label>
									<input type="url" class="regular-text" id="myls_gsc_redirect_uri" name="myls_gsc_redirect_uri" value="<?php echo esc_attr($gsc_redirect); ?>">
									<p class="description">Add this exact URI to your Google Cloud OAuth client. Scope used: <code>webmasters.readonly</code></p>
								</div>
							</div>

							<div style="margin-top:8px;">
								<p>
									<strong>Status:</strong>
									<?php if ($gsc_connected): ?>
										<span class="dashicons dashicons-yes" style="color:#1a7f37;"></span> Connected
									<?php else: ?>
										<span class="dashicons dashicons-dismiss" style="color:#b91c1c;"></span> Not connected
									<?php endif; ?>
								</p>
								<p class="d-flex" style="display:flex; gap:8px;">
									<?php if ( ! $gsc_connected ): ?>
										<a href="<?php echo esc_url($gsc_connect_url); ?>" class="button button-primary"><?php esc_html_e('Connect Google','myls'); ?></a>
									<?php else: ?>
										<a href="<?php echo esc_url($gsc_disconnect_url); ?>" class="button button-secondary"><?php esc_html_e('Disconnect','myls'); ?></a>
									<?php endif; ?>
									<button type="button" class="button" id="myls-test-gsc-api" data-nonce="<?php echo esc_attr($ajax_nonce); ?>">Check GSC Setup</button>
								</p>
								<p class="description" style="margin-top:8px;">Last check: <em><?php echo esc_html($last_gsc_test); ?></em></p>
								<div id="myls-gsc-api-test-result" class="notice inline" style="margin-top:8px;"></div>
							</div>
						</div>
					</div>

				</div><!-- /.row -->

				<p class="submit" style="margin-top:16px;">
					<button type="submit" class="button button-primary">Save API Settings</button>
				</p>
			</form>
		</div>

		<script>
		jQuery(function($){
		  const POST_URL = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
		  if (typeof window.ajaxurl === 'undefined') window.ajaxurl = POST_URL;

		  function paint($el, ok, msg) {
			$el.removeClass('notice-success notice-error')
			   .addClass(ok ? 'notice-success' : 'notice-error')
			   .html('<p>' + $('<div>').text(msg || (ok ? 'OK' : 'Failed')).html() + '</p>');
		  }

		  const nonce = $('.button[data-nonce]').first().data('nonce') || '';

		  // --- Google Places ---
		  $('#myls-test-places-api').on('click', function(){
			const key = $('#myls_google_places_api_key').val();
			const $box = $('#myls-places-api-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
			$.post(POST_URL, { action:'myls_test_places_key', key, nonce })
			  .done(r=>paint($box, !!(r&&r.success), (r&&r.data&&r.data.message)|| (r&&r.data) || (r&&r.success?'Places key OK':'Places key test failed')))
			  .fail(()=>paint($box,false,'Network error during Places key test'));
		  });

		  $('#myls-test-places-id').on('click', function(){
			const key = $('#myls_google_places_api_key').val();
			const place_id = $('#myls_google_places_place_id').val();
			const $box = $('#myls-places-pid-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
			$.post(POST_URL, { action:'myls_test_places_pid', key, place_id, nonce })
			  .done(r=>paint($box, !!(r&&r.success), (r&&r.data&&r.data.message)|| (r&&r.data) || (r&&r.success?'Place ID OK':'Place ID test failed')))
			  .fail(()=>paint($box,false,'Network error during Place ID test'));
		  });

		  // --- Static Maps ---
		  $('#myls-test-maps-api').on('click', function(){
			const key = $('#myls_google_static_maps_api_key').val();
			const $box = $('#myls-maps-api-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
			$.post(POST_URL, { action:'myls_test_maps_key', key, nonce })
			  .done(r=>paint($box, !!(r&&r.success), (r&&r.data) || (r&&r.success?'Maps OK':'Maps test failed')))
			  .fail(()=>paint($box,false,'Network error during Maps test'));
		  });

		  // --- OpenAI ---
		  $('#myls-test-openai-api').on('click', function(){
			const key = $('#myls_openai_api_key').val();
			const $box = $('#myls-openai-api-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
			$.post(POST_URL, { action:'myls_test_openai_key', key, nonce })
			  .done(r=>paint($box, !!(r&&r.success), (r&&r.data) || (r&&r.success?'OpenAI OK':'OpenAI test failed')))
			  .fail(()=>paint($box,false,'Network error during OpenAI test'));
		  });

		  // --- YouTube (API key) ---
		  $('#myls-test-youtube-api').on('click', function(){
			const $box = $('#myls-youtube-api-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
			$.post(POST_URL, { action:'myls_test_youtube_api', nonce })
			  .done(r=>paint($box, !!(r&&r.success), (r&&r.data) || (r&&r.success?'YouTube OK':'YouTube test failed')))
			  .fail(()=>paint($box,false,'Network error during YouTube test'));
		  });

		  // --- YouTube (OAuth) ---
		  $('#myls-test-youtube-oauth').on('click', function(){
			const $box = $('#myls-youtube-oauth-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
			$.post(POST_URL, { action:'myls_test_youtube_oauth', nonce })
			  .done(r=>paint($box, !!(r&&r.success), (r&&r.data) || (r&&r.success?'YouTube OAuth OK':'YouTube OAuth test failed')))
			  .fail(()=>paint($box,false,'Network error during YouTube OAuth test'));
		  });

		  // --- GSC ---
		  $('#myls-test-gsc-api').on('click', function(){
			const $box = $('#myls-gsc-api-test-result').removeClass('notice-success notice-error').html('<em>Checking…</em>');
			$.post(POST_URL, { action:'myls_test_gsc_client', nonce })
			  .done(r=>paint($box, !!(r&&r.success), (r&&r.data) || (r&&r.success?'GSC client configured':'GSC check failed')))
			  .fail(()=>paint($box,false,'Network error during GSC check'));
		  });
		});
		</script>
		<?php
	},
]);

/** --------------------------------------------------------------
 * Tiny getters for other parts of the plugin
 * -------------------------------------------------------------- */
if ( ! function_exists('myls_get_google_places_api_key') ) {
	function myls_get_google_places_api_key() : string {
		return (string) get_option('myls_google_places_api_key', '');
	}
}
if ( ! function_exists('myls_get_openai_api_key') ) {
	function myls_get_openai_api_key() : string {
		return (string) get_option('myls_openai_api_key', '');
	}
}
if ( ! function_exists('myls_get_youtube_api_key') ) {
	function myls_get_youtube_api_key() : string {
		return (string) get_option('myls_youtube_api_key', '');
	}
}
if ( ! function_exists('myls_get_youtube_channel_id') ) {
	function myls_get_youtube_channel_id() : string {
		return (string) get_option('myls_youtube_channel_id', '');
	}
}
if ( ! function_exists('myls_get_static_maps_key') ) {
	function myls_get_static_maps_key() : string {
		return (string) get_option('myls_google_static_maps_api_key', '');
	}
}
if ( ! function_exists('myls_get_gsc_client') ) {
	function myls_get_gsc_client() : array {
		return [
			'client_id'     => (string) get_option('myls_gsc_client_id', ''),
			'client_secret' => (string) get_option('myls_gsc_client_secret', ''),
			'redirect_uri'  => (string) get_option('myls_gsc_redirect_uri', ''),
		];
	}
}
if ( ! function_exists('myls_get_youtube_oauth_client') ) {
	function myls_get_youtube_oauth_client() : array {
		return [
			'client_id'     => (string) get_option('myls_yt_client_id', ''),
			'client_secret' => (string) get_option('myls_yt_client_secret', ''),
			'redirect_uri'  => (string) get_option('myls_yt_redirect_uri', ''),
		];
	}
}
