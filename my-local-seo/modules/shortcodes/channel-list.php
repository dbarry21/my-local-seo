<?php
/**
 * [youtube_channel_list pagesize="12" max="0" channel="" schema="auto"]
 * - If API key + channel are set, shows latest uploads from YouTube
 * - Fallback: lists recent local 'video' posts (published)
 * - NEW: Emits ItemList + VideoObject JSON-LD (footer by default)
 */

if ( ! defined('ABSPATH') ) exit;

/** =========================================================
 * One-time schema queue + flusher (footer)
 * ========================================================= */
if ( ! function_exists('myls_ycl_queue_schema') ) {
	$GLOBALS['myls_ycl_schema_queue'] = [];

	function myls_ycl_queue_schema(array $schema){
		if (empty($schema)) return;
		$GLOBALS['myls_ycl_schema_queue'][] = $schema;
	}

	function myls_ycl_flush_schema_footer(){
		$queue = isset($GLOBALS['myls_ycl_schema_queue']) ? $GLOBALS['myls_ycl_schema_queue'] : [];
		if (empty($queue)) return;

		// De-dupe by @id if present
		$seen = [];
		foreach ($queue as $schema){
			$id = is_array($schema) && isset($schema['@id']) ? (string)$schema['@id'] : md5( wp_json_encode($schema) );
			if (isset($seen[$id])) continue;
			$seen[$id] = true;

			echo "\n<!-- MYLS YouTube Channel ItemList JSON-LD -->\n";
			echo '<script type="application/ld+json">' . "\n";
			echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . "\n";
			echo "</script>\n";
		}
		$GLOBALS['myls_ycl_schema_queue'] = [];
	}
	add_action('wp_footer', 'myls_ycl_flush_schema_footer', 20);
}

/** =========================================================
 * Build ItemList JSON-LD from items
 * $items[] shape (YouTube API path): [
 *   'videoId' => 'abc123',
 *   'title'   => 'Video title',
 *   'thumb'   => 'https://i.ytimg.com/vi/abc123/mqdefault.jpg',
 *   'date'    => '2025-01-01T12:00:00Z' (ISO)  // optional if you have it
 * ]
 * Fallback items (local posts) may omit videoId/thumb/date.
 * ========================================================= */
if ( ! function_exists('myls_ycl_build_itemlist_schema') ) {
	function myls_ycl_build_itemlist_schema(array $items, array $ctx = []) : array {
		// Page context
		$page_url  = isset($ctx['page_url']) ? esc_url_raw($ctx['page_url']) : home_url(add_query_arg([]));
		$list_id   = trailingslashit($page_url) . '#videoList';
		$site_name = get_bloginfo('name');
		$title     = isset($ctx['title']) && $ctx['title'] !== '' ? sanitize_text_field($ctx['title']) : ($site_name . ' – Video Gallery');

		// Publisher (Organization) from MYLS options if present
		$org_name = get_option('myls_org_name', $site_name);
		$org_url  = get_option('myls_org_url', home_url('/'));
		$logo_id  = (int) get_option('myls_org_logo_id', 0);
		$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

		$schema = [
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'@id'             => esc_url_raw($list_id),
			'name'            => $title,
			'description'     => 'Browse recent videos on ' . $site_name,
			'publisher'       => array_filter([
				'@type' => 'Organization',
				'name'  => sanitize_text_field($org_name),
				'url'   => esc_url_raw($org_url),
				'logo'  => $logo_url ? [
					'@type' => 'ImageObject',
					'url'   => esc_url_raw($logo_url),
				] : null,
			]),
			'numberOfItems'   => count($items),
			'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',
			'dateModified'    => current_time('c'),
			'itemListElement' => [],
		];

		foreach ($items as $i => $it) {
			$vid     = isset($it['videoId']) ? sanitize_text_field($it['videoId']) : '';
			$title   = isset($it['title']) ? sanitize_text_field($it['title']) : '';
			$thumb   = isset($it['thumb']) ? esc_url_raw($it['thumb']) : '';
			$dateISO = isset($it['date'])  ? sanitize_text_field($it['date'])  : ''; // optional

			// Destination URL: prefer local post if supplied, else watch URL if we have a videoId
			$local  = isset($it['permalink']) ? esc_url_raw($it['permalink']) : '';
			$watch  = $vid ? ('https://www.youtube.com/watch?v=' . rawurlencode($vid)) : '';
			$to     = $local ?: $watch ?: $page_url;

			$embed  = $vid ? ('https://www.youtube.com/embed/' . rawurlencode($vid)) : '';

			$videoObj = array_filter([
				'@type'            => 'VideoObject',
				'mainEntityOfPage' => esc_url_raw($to),
				'url'              => esc_url_raw($to),
				'name'             => $title ?: 'Video',
				'description'      => $title ?: 'Video',
				'thumbnailUrl'     => $thumb ? [ $thumb ] : null,
				'uploadDate'       => $dateISO ?: null,
				'embedUrl'         => $embed ?: null,
				'isFamilyFriendly' => 'true',
			]);

			$schema['itemListElement'][] = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'item'     => $videoObj,
			];
		}

		/**
		 * Allow last-minute customization:
		 * add_filter('myls_ycl_itemlist_schema', function($schema, $items, $ctx){ ... });
		 */
		return apply_filters('myls_ycl_itemlist_schema', $schema, $items, $ctx);
	}
}

/** =========================================================
 * Shortcode
 * ========================================================= */
myls_register_shortcode('youtube_channel_list', function($atts){
	$a = shortcode_atts([
		'pagesize' => '12',        // [1..50]
		'max'      => '0',         // [0..50], 0=no hard cap
		'channel'  => '',          // override default channel ID
		'schema'   => 'auto',      // NEW: auto|inline|off
	], $atts, 'youtube_channel_list');

	$ppp     = max(1, min(50, (int) $a['pagesize']));
	$max_cap = max(0, min(50, (int) $a['max']));
	$schema_mode = in_array($a['schema'], ['auto','inline','off'], true) ? $a['schema'] : 'auto';

	$api_key = function_exists('myls_yt_get_api_key') ? myls_yt_get_api_key() : myls_get_youtube_api_key();
	$channel = $a['channel'] !== '' ? sanitize_text_field($a['channel']) :
	           (function_exists('myls_yt_get_channel_id') ? myls_yt_get_channel_id() : myls_get_youtube_channel_id());

	$schema_items = [];

	// --- API path
	if ( $api_key !== '' && $channel !== '' && function_exists('myls_yt_get_uploads_playlist_id') && function_exists('myls_yt_fetch_uploads_batch') ) {
		$uploads = myls_yt_get_uploads_playlist_id($channel, $api_key);
		if ( $uploads !== '' ) {
			$items = myls_yt_fetch_uploads_batch($uploads, $api_key);
			if ( $items ) {
				if ( $max_cap > 0 ) $items = array_slice($items, 0, $max_cap);
				$items = array_slice($items, 0, $ppp);

				// Prepare schema items (also try to map to local posts if any)
				foreach ($items as &$it) {
					$vid   = $it['videoId'];
					$title = $it['title'];
					$thumb = $it['thumb'];
					$perma = function_exists('myls_yt_find_video_post_url') ? myls_yt_find_video_post_url($vid, $title) : '';
					$it['permalink'] = $perma ?: ''; // for schema builder
				}
				unset($it);

				$schema_items = $items;

				// Render the grid
				ob_start(); ?>
				<div class="container myls-youtube-grid">
					<div class="row row-cols-1 row-cols-md-3 g-4">
						<?php foreach ( $items as $it ):
							$vid   = $it['videoId'];
							$title = $it['title'];
							$thumb = $it['thumb'];
							$perma = $it['permalink'];
							$yturl = 'https://www.youtube.com/watch?v=' . rawurlencode($vid);
						?>
						<div class="col">
							<div class="card h-100 shadow-sm">
								<?php if ( $thumb ) : ?>
									<a class="ratio ratio-16x9 d-block" href="<?php echo esc_url($yturl); ?>" target="_blank" rel="noopener">
										<img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" class="card-img-top" loading="lazy">
									</a>
								<?php else: ?>
									<div class="ratio ratio-16x9 bg-light d-flex align-items-center justify-content-center">
										<span class="text-muted">No thumbnail</span>
									</div>
								<?php endif; ?>
								<div class="card-body d-flex flex-column">
									<h5 class="card-title" style="font-size:1rem;"><?php echo esc_html($title); ?></h5>
									<div class="mt-auto d-flex gap-2">
										<a class="btn btn-sm btn-primary" href="<?php echo esc_url($yturl); ?>" target="_blank" rel="noopener">Watch on YouTube</a>
										<?php if ( $perma ) : ?>
											<a class="btn btn-sm btn-outline-secondary" href="<?php echo esc_url($perma); ?>">Visit Post</a>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
				$html = ob_get_clean();

				// Build & emit schema
				$schema = myls_ycl_build_itemlist_schema(
					$schema_items,
					['page_url' => get_permalink(), 'title' => get_the_title() . ' – Video Gallery']
				);

				if ( $schema_mode === 'inline' ) {
					$html .= "\n<script type=\"application/ld+json\">\n" .
					         wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) .
					         "\n</script>\n";
				} elseif ( $schema_mode !== 'off' ) {
					myls_ycl_queue_schema($schema); // footer
				}

				return $html;
			}
		}
	}

	// --- Fallback: local 'video' posts
	$rows = get_posts([
		'post_type'        => 'video',
		'post_status'      => 'publish',
		'posts_per_page'   => $ppp,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => true,
		'no_found_rows'    => true,
	]);
	if ( ! $rows ) return '<p><em>No videos yet.</em></p>';

	// Map to schema-friendly items
	foreach ( $rows as $p ) {
		$vid_meta = get_post_meta($p->ID, '_myls_video_id', true);
		if (!$vid_meta) $vid_meta = get_post_meta($p->ID, '_ssseo_video_id', true); // legacy
		$schema_items[] = [
			'videoId'   => $vid_meta ?: '',
			'title'     => get_the_title($p) ?: ($vid_meta ?: 'Video'),
			'thumb'     => $vid_meta ? ('https://i.ytimg.com/vi/' . rawurlencode($vid_meta) . '/mqdefault.jpg') : '',
			'permalink' => get_permalink($p),
			'date'      => get_the_date('c', $p),
		];
	}

	// Render simple list (your existing fallback layout)
	ob_start();
	echo '<div class="row row-cols-1 row-cols-md-3 g-3 myls-video-list">';
	foreach ( $rows as $p ) {
		$title = get_the_title($p) ?: get_post_meta($p->ID, '_myls_video_id', true) ?: get_post_meta($p->ID, '_ssseo_video_id', true);
		$link  = get_permalink($p) ?: '#';
		echo '<div class="col"><div class="card h-100 shadow-sm">';
		echo   '<div class="card-body">';
		echo     '<h5 class="card-title">'.esc_html($title).'</h5>';
		echo     '<a class="btn btn-primary" href="'.esc_url($link).'">View</a>';
		echo   '</div>';
		echo '</div></div></div>';
	}
	echo '</div>';
	$html = ob_get_clean();

	// Build & emit schema for fallback too
	$schema = myls_ycl_build_itemlist_schema(
		$schema_items,
		['page_url' => get_permalink(), 'title' => get_the_title() . ' – Video Gallery']
	);

	if ( $schema_mode === 'inline' ) {
		$html .= "\n<script type=\"application/ld+json\">\n" .
		         wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) .
		         "\n</script>\n";
	} elseif ( $schema_mode !== 'off' ) {
		myls_ycl_queue_schema($schema); // footer
	}

	return $html;
}, [
	'tag'         => 'youtube_channel_list',
	'description' => 'Grid of recent channel uploads (falls back to local video posts). Emits ItemList JSON-LD.',
	'params'      => [
		'pagesize' => 'Items to show (1–50). Default 12.',
		'max'      => 'Hard cap from API (0–50). Default 0 (no cap).',
		'channel'  => 'Channel ID override; default uses saved setting.',
		'schema'   => 'Schema output mode: auto (footer), inline (next to grid), off.',
	],
]);
