<?php
/**
 * Shortcode: [youtube_channel_list pagesize="12" max="0" channel="" schema="auto" page=""]
 * - Bootstrap 5 grid with server-side pagination (via ycl_page URL param OR page="" attr)
 * - JSON-LD ItemList reflects ONLY the items shown on the current page (per-page schema)
 * - "Visit Post" button appears when a local post exists whose meta `_myls_youtube_video_id` matches the YouTube video ID.
 * - NEW: Titles shown in the grid (and in schema) are cleaned to stop at the first "#" and remove emojis/symbols.
 */

if ( ! defined('ABSPATH') ) exit;

/** --------------------------------------------------------------
 * Title cleaner (grid + schema)
 * - Hard cut at first '#'
 * - Strip emojis/symbols
 * - Collapse whitespace and trim punctuation
 * If your admin tab provides myls_ytvb_clean_title(), we’ll use that.
 * -------------------------------------------------------------- */
if ( ! function_exists('myls_ycl_clean_title') ) {
	function myls_ycl_clean_title( $raw ) {
		$raw = (string) $raw;

		// Prefer the central cleaner if available
		if ( function_exists('myls_ytvb_clean_title') ) {
			return myls_ytvb_clean_title($raw);
		}

		// Fallback lightweight cleaner (hard stop at first '#')
		$s = html_entity_decode( wp_strip_all_tags( $raw ), ENT_QUOTES, 'UTF-8' );

		// Remove URLs
		$s = preg_replace('~https?://\S+~i', '', $s);

		// Keep everything before the first '#' (discard the rest)
		if ( preg_match('/^(.*?)(?:\s*#|$)/u', $s, $m) ) {
			$s = isset($m[1]) ? trim($m[1]) : $s;
		}

		// Strip emojis / pictographs / symbols (broad ranges)
		$s = preg_replace('/[\x{1F100}-\x{1F1FF}\x{1F300}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $s);

		// Normalize separators and excessive punctuation
		$s = str_replace(array('|','/','\\','–','—','·','•','►','»','«'), ' ', $s);
		$s = preg_replace('/[[:punct:]]{2,}/u', ' ', $s);

		// Collapse whitespace and trim edges/punct
		$s = preg_replace('/\s+/u', ' ', trim($s));
		$s = trim($s, " \t\n\r\0\x0B-_.:,;!?#*()[]{}\"'");

		return $s !== '' ? $s : ( $raw !== '' ? $raw : 'Video' );
	}
}

/** --------------------------------------------------------------
 * Helper: find local "video" post permalink by YouTube ID
 * Looks for post with meta_key `_myls_youtube_video_id` === $video_id
 * Returns '' if not found.
 * -------------------------------------------------------------- */
if ( ! function_exists('myls_ycl_find_post_permalink_by_youtube_id') ) {
	function myls_ycl_find_post_permalink_by_youtube_id( $video_id ) {
		$video_id = trim((string)$video_id);
		if ($video_id === '') return '';

		$posts = get_posts(array(
			'post_type'        => 'video', // adjust if you store videos under a different CPT
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => '_myls_youtube_video_id',
			'meta_value'       => $video_id,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		));

		if ( ! empty($posts) ) {
			$pid  = (int) $posts[0];
			$link = get_permalink($pid);
			return $link ? $link : '';
		}
		return '';
	}
}

/** --------------------------------------------------------------
 * Footer flusher for queued JSON-LD
 * -------------------------------------------------------------- */
if ( ! function_exists('myls_ycl_queue_schema') ) {
	$GLOBALS['myls_ycl_schema_queue'] = array();

	function myls_ycl_queue_schema( $schema ){
		if ( empty($schema) ) return;
		$GLOBALS['myls_ycl_schema_queue'][] = $schema;
	}

	function myls_ycl_flush_schema_footer(){
		$queue = isset($GLOBALS['myls_ycl_schema_queue']) ? $GLOBALS['myls_ycl_schema_queue'] : array();
		if ( empty($queue) ) return;

		$seen = array();
		foreach ( $queue as $schema ){
			$id = is_array($schema) && isset($schema['@id']) ? (string)$schema['@id'] : md5( wp_json_encode($schema) );
			if ( isset($seen[$id]) ) continue;
			$seen[$id] = true;

			echo "\n<!-- MYLS YouTube Channel ItemList JSON-LD (per-page) -->\n";
			echo '<script type="application/ld+json">' . "\n";
			echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . "\n";
			echo "</script>\n";
		}
		$GLOBALS['myls_ycl_schema_queue'] = array();
	}
	add_action('wp_footer', 'myls_ycl_flush_schema_footer', 20);
}

/** --------------------------------------------------------------
 * Build ItemList JSON-LD from items (PER-PAGE)
 * $items contains only the currently displayed page slice.
 * Each $it should include: videoId, title, thumb, date (ISO), permalink (optional)
 * (We clean the title here too.)
 * -------------------------------------------------------------- */
if ( ! function_exists('myls_ycl_build_itemlist_schema') ) {
	function myls_ycl_build_itemlist_schema( $items, $ctx = array() ) {
		$page_url  = ( isset($ctx['page_url']) && $ctx['page_url'] !== '' )
			? esc_url_raw($ctx['page_url'])
			: home_url(add_query_arg(array()));

		$list_id   = trailingslashit($page_url) . '#videoList';
		$site_name = get_bloginfo('name');
		$title     = ( isset($ctx['title']) && $ctx['title'] !== '' ) ? sanitize_text_field($ctx['title']) : ($site_name . ' – Video Gallery');

		$org_name = get_option('myls_org_name', $site_name);
		$org_url  = get_option('myls_org_url', home_url('/'));
		$logo_id  = (int) get_option('myls_org_logo_id', 0);
		$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'@id'             => esc_url_raw($list_id),
			'name'            => $title,
			'description'     => 'Browse videos on ' . $site_name,
			'publisher'       => array_filter(array(
				'@type' => 'Organization',
				'name'  => sanitize_text_field($org_name),
				'url'   => esc_url_raw($org_url),
				'logo'  => $logo_url ? array(
					'@type' => 'ImageObject',
					'url'   => esc_url_raw($logo_url),
				) : null,
			)),
			'numberOfItems'   => is_array($items) ? count($items) : 0,
			'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',
			'dateModified'    => current_time('c'),
			'itemListElement' => array(),
		);

		if ( is_array($items) ) {
			foreach ($items as $i => $it) {
				$vid     = isset($it['videoId']) ? sanitize_text_field($it['videoId']) : '';
				$rawName = isset($it['title'])   ? (string) $it['title'] : 'Video';
				$name    = myls_ycl_clean_title( $rawName ); // CLEAN HERE
				$thumb   = isset($it['thumb'])   ? esc_url_raw($it['thumb']) : '';
				$dateISO = isset($it['date'])    ? sanitize_text_field($it['date'])  : '';

				$local  = isset($it['permalink']) ? esc_url_raw($it['permalink']) : '';
				$watch  = $vid ? ('https://www.youtube.com/watch?v=' . rawurlencode($vid)) : '';
				$to     = $local ? $local : ( $watch ? $watch : $page_url );
				$embed  = $vid ? ('https://www.youtube.com/embed/' . rawurlencode($vid)) : '';

				$videoObj = array_filter(array(
					'@type'            => 'VideoObject',
					'mainEntityOfPage' => esc_url_raw($to),
					'url'              => esc_url_raw($to),
					'name'             => $name,
					'description'      => $name,
					'thumbnailUrl'     => $thumb ? array( $thumb ) : null,
					'uploadDate'       => $dateISO ? $dateISO : null,
					'embedUrl'         => $embed ? $embed : null,
					'isFamilyFriendly' => 'true',
				));

				$schema['itemListElement'][] = array(
					'@type'    => 'ListItem',
					'position' => (int) $i + 1,
					'item'     => $videoObj,
				);
			}
		}

		return apply_filters('myls_ycl_itemlist_schema', $schema, $items, $ctx);
	}
}

/** --------------------------------------------------------------
 * Pagination builder (Bootstrap 5)
 * -------------------------------------------------------------- */
if ( ! function_exists('myls_ycl_build_pagination') ) {
	function myls_ycl_build_pagination( $total_pages, $current_page, $args = array() ) {
		$total_pages  = (int) $total_pages;
		$current_page = (int) $current_page;
		if ( $total_pages <= 1 ) return '';
		$param   = isset($args['param']) ? $args['param'] : 'ycl_page';
		$wrap    = isset($args['wrap_class']) ? $args['wrap_class'] : 'd-flex justify-content-center mt-4';
		$ul_cls  = isset($args['ul_class']) ? $args['ul_class'] : 'pagination';

		$qargs = $_GET; // preserve other query vars
		if ( isset($qargs[$param]) ) unset($qargs[$param]);

		$html  = '<nav class="'.esc_attr($wrap).'" aria-label="Video pagination">';
		$html .= '<ul class="'.esc_attr($ul_cls).'">';

		$prev_disabled = $current_page <= 1 ? ' disabled' : '';
		$qprev = $qargs; $qprev[$param] = max(1, $current_page - 1);
		$html .= '<li class="page-item'.$prev_disabled.'"><a class="page-link" href="'.esc_url(add_query_arg($qprev)).'" aria-label="Previous">&laquo;</a></li>';

		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$active = $i === $current_page ? ' active' : '';
			$qi = $qargs; $qi[$param] = $i;
			$html .= '<li class="page-item'.$active.'"><a class="page-link" href="'.esc_url(add_query_arg($qi)).'">'.$i.'</a></li>';
		}

		$next_disabled = $current_page >= $total_pages ? ' disabled' : '';
		$qnext = $qargs; $qnext[$param] = min($total_pages, $current_page + 1);
		$html .= '<li class="page-item'.$next_disabled.'"><a class="page-link" href="'.esc_url(add_query_arg($qnext)).'" aria-label="Next">&raquo;</a></li>';

		$html .= '</ul></nav>';
		return $html;
	}
}

/** --------------------------------------------------------------
 * Shortcode (server-side paging + PER-PAGE schema)
 * - Remote path (YouTube API): add "Visit Post" when `_myls_youtube_video_id` matches.
 * - Local fallback path: primary meta is `_myls_youtube_video_id` (with legacy fallbacks).
 * - Titles displayed in the grid are CLEANED via myls_ycl_clean_title().
 * -------------------------------------------------------------- */
myls_register_shortcode('youtube_channel_list', function($atts){
	$a = shortcode_atts(array(
		'pagesize' => '12',        // items per page [1..50]
		'max'      => '0',         // hard cap for API [0..50], 0=no cap
		'channel'  => '',          // override channel ID
		'schema'   => 'auto',      // auto|inline|off
		'page'     => '',          // optional explicit page (overrides URL if set)
	), $atts, 'youtube_channel_list');

	$ppp         = max(1, min(50, (int) $a['pagesize']));
	$max_cap     = max(0, min(50, (int) $a['max']));
	$schema_mode = in_array($a['schema'], array('auto','inline','off'), true) ? $a['schema'] : 'auto';

	// Current page: shortcode attr > URL param > 1
	$url_page     = isset($_GET['ycl_page']) ? (int) $_GET['ycl_page'] : 0;
	$current_page = max(1, (int) ($a['page'] !== '' ? $a['page'] : $url_page));

	// Resolve current full URL (with ycl_page if present) for per-page schema @id
	$current_url_for_schema = home_url(add_query_arg(array()));

	// Your project’s helper(s) for API keys/channels (guarded)
	$api_key = function_exists('myls_yt_get_api_key') ? myls_yt_get_api_key() : ( function_exists('myls_get_youtube_api_key') ? myls_get_youtube_api_key() : '' );
	$channel = $a['channel'] !== '' ? sanitize_text_field($a['channel'])
	          : ( function_exists('myls_yt_get_channel_id') ? myls_yt_get_channel_id()
	          : ( function_exists('myls_get_youtube_channel_id') ? myls_get_youtube_channel_id() : '' ) );

	$html_grid    = '';
	$total_items  = 0;
	$total_pages  = 1;

	/** =========================================================
	 * Preferred path: YouTube API (uploads playlist)
	 * ========================================================= */
	if ( $api_key && $channel && function_exists('myls_yt_get_uploads_playlist_id') && function_exists('myls_yt_fetch_uploads_batch') ) {
		$uploads = myls_yt_get_uploads_playlist_id($channel, $api_key);
		if ( $uploads !== '' ) {
			$all = myls_yt_fetch_uploads_batch($uploads, $api_key); // up to 50 (per your helper)
			if ( $all && is_array($all) ) {
				if ( $max_cap > 0 ) $all = array_slice($all, 0, $max_cap);

				// Map local permalinks for buttons (via _myls_youtube_video_id)
				foreach ($all as &$it) {
					$vid   = isset($it['videoId']) ? (string) $it['videoId'] : '';
					$it['permalink'] = $vid ? myls_ycl_find_post_permalink_by_youtube_id($vid) : '';
					// CLEAN TITLE for display and schema
					$it['title'] = myls_ycl_clean_title( isset($it['title']) ? $it['title'] : 'Video' );
				}
				unset($it);

				$total_items  = count($all);
				$total_pages  = max(1, (int) ceil($total_items / $ppp));
				$current_page = min($current_page, $total_pages);

				// Slice for current page (used for BOTH grid and schema)
				$offset = ($current_page - 1) * $ppp;
				$items  = array_slice($all, $offset, $ppp);

				ob_start(); ?>
				<div class="container myls-youtube-grid" id="videoList">
					<div class="row row-cols-1 row-cols-md-3 g-4">
						<?php foreach ( $items as $it ):
							$vid   = isset($it['videoId']) ? $it['videoId'] : '';
							$title = isset($it['title'])   ? $it['title']   : 'Video'; // already cleaned above
							$thumb = isset($it['thumb'])   ? $it['thumb']   : '';
							$perma = isset($it['permalink']) ? $it['permalink'] : ''; // set only if local post exists
							$yturl = $vid ? ('https://www.youtube.com/watch?v=' . rawurlencode($vid)) : '#';
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
										<?php if ( $yturl && $yturl !== '#' ): ?>
											<a class="btn btn-sm btn-primary" href="<?php echo esc_url($yturl); ?>" target="_blank" rel="noopener">Watch on YouTube</a>
										<?php endif; ?>
										<?php if ( $perma ) : ?>
											<a class="btn btn-sm btn-outline-secondary" href="<?php echo esc_url($perma); ?>">Visit Post</a>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<?php echo myls_ycl_build_pagination($total_pages, $current_page, array('param' => 'ycl_page')); ?>
				</div>
				<?php
				$html_grid = ob_get_clean();

				// PER-PAGE schema: only the visible $items (already have cleaned titles)
				$schema = myls_ycl_build_itemlist_schema(
					$items,
					array(
						'page_url' => $current_url_for_schema,
						'title'    => get_the_title() . ' – Video Gallery (Page ' . $current_page . ')'
					)
				);

				if ( $schema_mode === 'inline' ) {
					$html_grid .= "\n<script type=\"application/ld+json\">\n" .
					             wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) .
					             "\n</script>\n";
				} elseif ( $schema_mode !== 'off' ) {
					myls_ycl_queue_schema($schema);
				}

				return $html_grid;
			}
		}
	}

	/** =========================================================
	 * Fallback: local "video" posts (paged) — PER-PAGE schema
	 * Primary video-id meta is `_myls_youtube_video_id`; legacy fallbacks preserved.
	 * Titles for display + schema are cleaned here too.
	 * ========================================================= */
	$paged = $current_page;

	$q = new WP_Query(array(
		'post_type'      => 'video',
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'posts_per_page' => $ppp,
		'paged'          => $paged,
		'no_found_rows'  => false, // we need total counts
	));

	if ( ! $q->have_posts() ) return '<p><em>No videos yet.</em></p>';

	$total_pages = max(1, (int) $q->max_num_pages);

	// Build current page items for schema (PER-PAGE)
	$schema_items = array();
	$page_posts   = $q->posts; // current page slice
	foreach ( $page_posts as $p ) {
		$pid      = $p->ID;
		$raw_name = get_the_title($pid);
		$clean    = myls_ycl_clean_title( $raw_name ?: 'Video' );

		// Prefer new key, then legacy keys for compatibility
		$vid_meta = get_post_meta($pid, '_myls_youtube_video_id', true);
		if ( ! $vid_meta ) $vid_meta = get_post_meta($pid, '_myls_video_id', true);
		if ( ! $vid_meta ) $vid_meta = get_post_meta($pid, '_ssseo_video_id', true);

		$schema_items[] = array(
			'videoId'   => $vid_meta ? $vid_meta : '',
			'title'     => $clean, // CLEANED
			'thumb'     => $vid_meta ? ('https://i.ytimg.com/vi/' . rawurlencode($vid_meta) . '/mqdefault.jpg') : '',
			'permalink' => get_permalink($pid),
			'date'      => get_the_date('c', $pid),
		);
	}

	ob_start();
	echo '<div class="container myls-video-list" id="videoList">';
		echo '<div class="row row-cols-1 row-cols-md-3 g-4">';
		while ( $q->have_posts() ) { $q->the_post();
			$pid        = get_the_ID();
			$raw_title  = get_the_title() ?: 'Video';
			$clean_title= myls_ycl_clean_title( $raw_title ); // CLEAN FOR DISPLAY
			$link       = get_permalink() ?: '#';

			// Prefer new key, then legacy keys for compatibility
			$vid_meta = get_post_meta($pid, '_myls_youtube_video_id', true);
			if ( ! $vid_meta ) $vid_meta = get_post_meta($pid, '_myls_video_id', true);
			if ( ! $vid_meta ) $vid_meta = get_post_meta($pid, '_ssseo_video_id', true);

			$thumb = $vid_meta ? ('https://i.ytimg.com/vi/' . rawurlencode($vid_meta) . '/mqdefault.jpg') : '';

			echo '<div class="col"><div class="card h-100 shadow-sm">';
				if ( $thumb ) {
					echo '<a class="ratio ratio-16x9 d-block" href="'.esc_url($link).'">';
					echo   '<img class="card-img-top" src="'.esc_url($thumb).'" alt="'.esc_attr($clean_title).'" loading="lazy">';
					echo '</a>';
				} else {
					echo '<div class="ratio ratio-16x9 bg-light d-flex align-items-center justify-content-center"><span class="text-muted">No thumbnail</span></div>';
				}
				echo '<div class="card-body d-flex flex-column">';
					echo '<h5 class="card-title" style="font-size:1rem;">'.esc_html($clean_title).'</h5>';
					echo '<div class="mt-auto"><a class="btn btn-sm btn-primary" href="'.esc_url($link).'">View</a></div>';
				echo '</div>';
			echo '</div></div>';
		}
		echo '</div>'; // row

		echo myls_ycl_build_pagination($total_pages, $current_page, array('param' => 'ycl_page'));
	echo '</div>';
	wp_reset_postdata();

	$html = ob_get_clean();

	// Emit PER-PAGE schema (only current page items) — already cleaned titles
	$schema = myls_ycl_build_itemlist_schema(
		$schema_items,
		array(
			'page_url' => $current_url_for_schema,
			'title'    => get_the_title() . ' – Video Gallery (Page ' . $current_page . ')'
		)
	);

	if ( $schema_mode === 'inline' ) {
		$html .= "\n<script type=\"application/ld+json\">\n" .
		         wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) .
		         "\n</script>\n";
	} elseif ( $schema_mode !== 'off' ) {
		myls_ycl_queue_schema($schema);
	}

	return $html;
}, array(
	'tag'         => 'youtube_channel_list',
	'description' => 'Grid of channel uploads with Bootstrap pagination. Emits per-page ItemList JSON-LD. Adds "Visit Post" when a local post with meta `_myls_youtube_video_id` matches the video ID. Cleans titles before display & schema.',
	'params'      => array(
		'pagesize' => 'Items per page (1–50). Default 12.',
		'max'      => 'Hard cap from API (0–50). Default 0 (no cap).',
		'channel'  => 'Channel ID override; default uses saved setting.',
		'schema'   => 'Schema output: auto (footer), inline (next to grid), off.',
		'page'     => 'Force current page (overrides URL ycl_page param).',
	),
));
