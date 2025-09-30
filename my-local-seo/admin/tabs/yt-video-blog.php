<?php
/**
 * Admin Tab: YT Video Blog
 * Path: admin/tabs/yt-video-blog.php
 *
 * Purpose:
 * - Configure templates and defaults, run generator, toggle debug and view logs.
 */

if ( ! defined('ABSPATH') ) exit;

myls_register_admin_tab([
	'id'    => 'yt-video-blog',
	'title' => 'YT Video Blog',
	'order' => 25,
	'cap'   => 'manage_options',
	'icon'  => 'dashicons-video-alt3',
	'cb'    => function () {

		/* ===== Save settings ===== */
		if (
			isset($_POST['myls_ytvb_nonce']) &&
			wp_verify_nonce( $_POST['myls_ytvb_nonce'], 'myls_ytvb_save' ) &&
			current_user_can('manage_options')
		) {
			update_option('myls_ytvb_enabled',     isset($_POST['myls_ytvb_enabled']) ? '1' : '0');

			$status = isset($_POST['myls_ytvb_status']) ? sanitize_key( wp_unslash($_POST['myls_ytvb_status']) ) : 'draft';
			if ( ! in_array( $status, ['draft','pending','publish'], true ) ) $status = 'draft';
			update_option('myls_ytvb_status', $status);

			update_option('myls_ytvb_category',  isset($_POST['myls_ytvb_category']) ? absint($_POST['myls_ytvb_category']) : 0);
			update_option('myls_ytvb_autoembed', isset($_POST['myls_ytvb_autoembed']) ? '1' : '0');

			$title_tpl   = isset($_POST['myls_ytvb_title_tpl'])   ? wp_kses_post( wp_unslash($_POST['myls_ytvb_title_tpl']) )   : '🎥 {title}';
			$content_tpl = isset($_POST['myls_ytvb_content_tpl']) ? wp_kses_post( wp_unslash($_POST['myls_ytvb_content_tpl']) ) : "<p>{description}</p>\n{embed}\n<p>Source: {channel}</p>";
			update_option('myls_ytvb_title_tpl',   $title_tpl);
			update_option('myls_ytvb_content_tpl', $content_tpl);

			$slug_prefix = isset($_POST['myls_ytvb_slug_prefix']) ? sanitize_title( wp_unslash($_POST['myls_ytvb_slug_prefix']) ) : 'video';
			update_option('myls_ytvb_slug_prefix', $slug_prefix);

			// Post type selector
			$post_type = isset($_POST['myls_ytvb_post_type']) ? sanitize_key($_POST['myls_ytvb_post_type']) : 'post';
			update_option('myls_ytvb_post_type', post_type_exists($post_type) ? $post_type : 'post');

			echo '<div class="notice notice-success is-dismissible"><p>YT Video Blog settings saved.</p></div>';
		}

		/* ===== Load settings ===== */
		$enabled     = get_option('myls_ytvb_enabled', '0');
		$status      = get_option('myls_ytvb_status', 'draft');
		$cat_id      = (int) get_option('myls_ytvb_category', 0);
		$auto_embed  = get_option('myls_ytvb_autoembed', '1');
		$title_tpl   = get_option('myls_ytvb_title_tpl', '🎥 {title}');
		$content_tpl = get_option('myls_ytvb_content_tpl', "<p>{description}</p>\n{embed}\n<p>Source: {channel}</p>");
		$slug_prefix = get_option('myls_ytvb_slug_prefix', 'video');
		$post_type   = get_option('myls_ytvb_post_type', 'post');

		// From API Integration tab options
		$yt_api_key  = get_option('myls_youtube_api_key','');
		$yt_channel  = get_option('myls_youtube_channel_id','');

		$categories = get_categories(['hide_empty'=>false,'taxonomy'=>'category']);
		$ajax_nonce = wp_create_nonce('myls_ytvb_ajax');

		$token_help = '<code>{title}</code> <code>{description}</code> <code>{channel}</code> <code>{date}</code> <code>{embed}</code> <code>{url}</code> <code>{slug}</code>';
		?>
		<div class="wrap myls-ytvb">
			<h1 class="wp-heading-inline">YT Video Blog</h1>
			<p class="description">Use your YouTube channel to create posts with a template. API key &amp; Channel ID come from <em>API Integration</em>.</p>

			<?php if ( empty($yt_api_key) || empty($yt_channel) ) : ?>
				<div class="notice notice-warning"><p><strong>Missing API settings:</strong> Set your YouTube API Key &amp; Channel ID in the <em>API Integration</em> tab.</p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field('myls_ytvb_save', 'myls_ytvb_nonce'); ?>

				<div class="card" style="padding:16px; max-width:1200px;">
					<h2 class="title">General</h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="myls_ytvb_enabled">Enable</label></th>
								<td><label><input type="checkbox" id="myls_ytvb_enabled" name="myls_ytvb_enabled" value="1" <?php checked('1', $enabled); ?>> Activate YT → Blog</label></td>
							</tr>
							<tr>
								<th scope="row"><label for="myls_ytvb_status">Default Post Status</label></th>
								<td>
									<select id="myls_ytvb_status" name="myls_ytvb_status">
										<option value="draft"   <?php selected($status,'draft'); ?>>Draft</option>
										<option value="pending" <?php selected($status,'pending'); ?>>Pending Review</option>
										<option value="publish" <?php selected($status,'publish'); ?>>Publish</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="myls_ytvb_post_type">Post Type</label></th>
								<td>
									<select id="myls_ytvb_post_type" name="myls_ytvb_post_type">
										<?php
										$pts = get_post_types(['public'=>true],'objects');
										foreach ($pts as $pt) {
											printf('<option value="%s"%s>%s</option>',
												esc_attr($pt->name),
												selected($post_type,$pt->name,false),
												esc_html($pt->labels->singular_name.' ('.$pt->name.')')
											);
										}
										?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="myls_ytvb_category">Default Category</label></th>
								<td>
									<select id="myls_ytvb_category" name="myls_ytvb_category">
										<option value="0" <?php selected($cat_id, 0); ?>>— None —</option>
										<?php foreach ( $categories as $cat ) : ?>
											<option value="<?php echo (int) $cat->term_id; ?>" <?php selected($cat_id, (int)$cat->term_id); ?>>
												<?php echo esc_html( $cat->name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="myls_ytvb_autoembed">Auto-embed Video</label></th>
								<td><label><input type="checkbox" id="myls_ytvb_autoembed" name="myls_ytvb_autoembed" value="1" <?php checked('1', $auto_embed); ?>> Use oEmbed (recommended)</label></td>
							</tr>
							<tr>
								<th scope="row"><label for="myls_ytvb_slug_prefix">Slug Prefix</label></th>
								<td>
									<input type="text" class="regular-text" id="myls_ytvb_slug_prefix" name="myls_ytvb_slug_prefix" value="<?php echo esc_attr($slug_prefix); ?>" placeholder="video">
									<p class="description">Final slug becomes <code>{prefix}-{slug}</code>.</p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<br>

				<div class="card" style="padding:16px; max-width:1200px;">
					<h2 class="title">Templates</h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="myls_ytvb_title_tpl">Title Template</label></th>
								<td>
									<input type="text" class="regular-text" id="myls_ytvb_title_tpl" name="myls_ytvb_title_tpl" value="<?php echo esc_attr($title_tpl); ?>">
									<p class="description">Tokens: <?php echo $token_help; ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="myls_ytvb_content_tpl">Content Template</label></th>
								<td>
<textarea class="large-text code" id="myls_ytvb_content_tpl" name="myls_ytvb_content_tpl" rows="10"><?php echo esc_textarea($content_tpl); ?></textarea>
									<p class="description">Tokens: <?php echo $token_help; ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<p><button type="submit" class="button button-primary">Save Settings</button></p>
			</form>

			<hr>

			<div class="card" style="padding:16px; max-width:1200px;">
				<h2 class="title">Run Now &amp; Debug</h2>
				<p class="description">API Key: <code><?php echo $yt_api_key ? '…'.esc_html(substr($yt_api_key, -6)) : 'not set'; ?></code> &nbsp; | &nbsp; Channel: <code><?php echo $yt_channel ? esc_html($yt_channel) : 'not set'; ?></code></p>

				<div class="actions" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
					<button type="button" class="button button-primary" id="myls-ytvb-run" data-nonce="<?php echo esc_attr($ajax_nonce); ?>">Generate Drafts</button>
					<label style="display:inline-flex; align-items:center; gap:.5rem; margin-left:8px;">
						<input type="checkbox" id="myls-ytvb-debug-toggle"> Enable debug log
					</label>
					<button type="button" class="button" id="myls-ytvb-log-refresh">Refresh Log</button>
					<button type="button" class="button button-secondary" id="myls-ytvb-log-clear">Clear Log</button>
					<select id="myls-ytvb-pages" class="regular-text" style="width:auto;">
						<option value="0" selected>All pages</option>
						<option value="1">1 page (50 videos)</option>
						<option value="2">2 pages (100 videos)</option>
						<option value="3">3 pages (150 videos)</option>
					</select>
				</div>

				<div id="myls-ytvb-run-result" class="notice inline" style="margin-top:10px;"></div>

				<details style="margin-top:14px;">
					<summary><strong>Debug Log</strong></summary>
					<pre id="myls-ytvb-log" style="max-height:300px; overflow:auto; background:#f8fafc; padding:10px; border:1px solid #e5e7eb; border-radius:4px;"></pre>
				</details>
			</div>

			<details>
				<summary><strong>Token Preview (example)</strong></summary>
				<?php
				$example = [
					'title'       => 'How to Optimize Google Business Profile',
					'description' => 'Step-by-step tips for local SEO using GBP.',
					'channel'     => 'Your Channel Name',
					'date'        => date_i18n( get_option('date_format') ),
					'url'         => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
					'slug'        => 'optimize-google-business-profile',
					'embed'       => '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=dQw4w9WgXcQ</div></figure>',
				];
				$render = function( $tpl ) use ( $example ) { foreach ($example as $k=>$v) { $tpl = str_replace('{'.$k.'}', $v, $tpl); } return $tpl; };
				?>
				<div class="card" style="padding:12px; max-width: 1000px;">
					<p><strong>Resolved Title:</strong> <?php echo wp_kses_post( $render( $title_tpl ) ); ?></p>
					<div><strong>Resolved Content:</strong><br><?php echo wp_kses_post( wpautop( $render( $content_tpl ) ) ); ?></div>
				</div>
			</details>
		</div>

<script>
jQuery(function($){
	const ajaxurl = window.ajaxurl || '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
	const nonce   = $('#myls-ytvb-run').data('nonce');

	function paint($el, ok, msg) {
		$el.removeClass('notice-success notice-error')
		   .addClass(ok ? 'notice-success' : 'notice-error')
		   .html('<p>' + $('<div>').text(msg || (ok ? 'OK' : 'Failed')).html() + '</p>');
	}

	// Toggle debug on load (reflect saved state)
	(function primeDebugToggle(){
		$.post(ajaxurl, { action:'myls_youtube_get_log', nonce: nonce })
		 .done(function(r){ $('#myls-ytvb-debug-toggle').prop('checked', <?php echo get_option('myls_youtube_debug', false) ? 'true' : 'false'; ?> ); });
	})();

	$('#myls-ytvb-debug-toggle').on('change', function(){
		const checked = $(this).is(':checked') ? 1 : 0;
		$.post(ajaxurl, { action:'myls_youtube_toggle_debug', enabled: checked, nonce: nonce });
	});

	$('#myls-ytvb-run').on('click', function(){
		const pages = parseInt($('#myls-ytvb-pages').val() || '0', 10);
		const $out  = $('#myls-ytvb-run-result').removeClass('notice-success notice-error').html('<em>Running…</em>');
		$.post(ajaxurl, { action:'myls_youtube_generate_drafts', pages: pages, nonce: nonce })
		 .done(function(r){
			if (!r) return paint($out,false,'No response');
			if (!r.success) return paint($out,false, (r.data && r.data.message) ? r.data.message : 'Failed');
			const d = r.data || {};
			const parts = [
				'New posts: ' + (d.new_posts || 0),
				'Existing (skipped): ' + (d.existing_posts || 0)
			];
			if (Array.isArray(d.errors) && d.errors.length) parts.push('Errors: ' + d.errors.length);
			paint($out,true, parts.join(' • '));
			$('#myls-ytvb-log-refresh').click();
		 })
		 .fail(function(){ paint($out,false,'Network error'); });
	});

	$('#myls-ytvb-log-refresh').on('click', function(){
		$.post(ajaxurl, { action:'myls_youtube_get_log', nonce: nonce })
		 .done(function(r){
			const $pre = $('#myls-ytvb-log').empty();
			if (r && r.success && r.data && Array.isArray(r.data.log)) {
				$pre.text(r.data.log.join("\n"));
			} else {
				$pre.text('(no log)');
			}
		 });
	}).click();

	$('#myls-ytvb-log-clear').on('click', function(){
		$.post(ajaxurl, { action:'myls_youtube_clear_log', nonce: nonce })
		 .done(function(){ $('#myls-ytvb-log').text('(cleared)'); });
	});
});
</script>
<?php
	},
]);
