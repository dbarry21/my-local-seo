<?php
/**
 * VideoObject Schema for single Video posts
 * Path: inc/schema/video-schema.php
 *
 * Requirements for rich results (VideoObject):
 * - name
 * - description
 * - thumbnailUrl (array or string)
 * - uploadDate (ISO 8601)
 * - duration (ISO 8601, e.g. PT2M33S)  — recommended/required for some surfaces
 * - embedUrl or contentUrl
 *
 * This module:
 * - Runs only on single 'video' posts.
 * - Pulls YouTube ID from meta `_ssseo_video_id` (your convention).
 * - Tries, in order, to get a thumbnail from:
 *      1) Featured image
 *      2) YouTube (maxresdefault -> hqdefault -> mqdefault)
 * - Pulls duration from `_ssseo_duration` (seconds) or `_ssseo_duration_iso` (already ISO 8601).
 * - uploadDate from YouTube `_ssseo_published_at` (ISO) or post date.
 * - Publisher Organization pulled from your Organization options when available.
 *
 * Filters:
 * - `myls_video_schema_array` lets you alter the schema array before output.
 * - `myls_video_schema_should_render` (bool) to conditionally suppress/force.
 */

if (!defined('ABSPATH')) exit;

/**
 * Return absolute URL for featured image if present.
 */
function myls_get_featured_image_url( $post_id, $size = 'full' ) {
	$img = wp_get_attachment_image_src( get_post_thumbnail_id($post_id), $size );
	return $img && !empty($img[0]) ? esc_url( $img[0] ) : '';
}

/**
 * Basic YouTube embed/content/thumbnail helpers.
 */
function myls_youtube_embed_url( $video_id ) {
	return $video_id ? 'https://www.youtube.com/embed/' . rawurlencode($video_id) : '';
}
function myls_youtube_watch_url( $video_id ) {
	return $video_id ? 'https://www.youtube.com/watch?v=' . rawurlencode($video_id) : '';
}
/**
 * Try progressively better thumbnails from YouTube CDN.
 * Note: maxres doesn’t always exist for every video.
 */
function myls_youtube_thumbnail_candidates( $video_id ) {
	if (!$video_id) return [];
	$base = 'https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/';
	return [
		$base . 'maxresdefault.jpg',
		$base . 'hqdefault.jpg',
		$base . 'mqdefault.jpg',
	];
}

/**
 * Convert seconds to ISO 8601 duration (PT#H#M#S)
 */
function myls_seconds_to_iso8601_duration( $seconds ) {
	$seconds = (int) max(0, $seconds);
	$h = floor($seconds / 3600);
	$m = floor(($seconds % 3600) / 60);
	$s = $seconds % 60;

	$out = 'PT';
	if ($h > 0) $out .= $h . 'H';
	if ($m > 0) $out .= $m . 'M';
	if ($s > 0 || ($h === 0 && $m === 0)) $out .= $s . 'S';
	return $out;
}

/**
 * Get Organization publisher data (logo + name + URL) from your plugin options if present.
 * Adjust option keys if yours differ.
 */
function myls_get_publisher_org() {
	$org_name = trim( (string) get_option('ssseo_org_name', '') );
	$org_url  = esc_url( (string) get_option('ssseo_org_url', home_url('/') ) );
	$logo_id  = (int) get_option('ssseo_org_logo_id', 0 );
	$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

	// Fallbacks
	if ($org_name === '') $org_name = get_bloginfo('name');
	if (!$logo_url) {
		// Optionally, set a default logo from theme folder or leave blank.
		$logo_url = '';
	}

	$publisher = [
		'@type' => 'Organization',
		'name'  => $org_name,
		'url'   => $org_url,
	];
	if ($logo_url) {
		$publisher['logo'] = [
			'@type' => 'ImageObject',
			'url'   => esc_url($logo_url),
		];
	}
	return $publisher;
}

/**
 * Build the VideoObject schema array for a given post.
 */
function myls_build_video_schema_array( $post_id ) {
	$post = get_post( $post_id );
	if (!$post) return [];

	// Core fields
	$name        = wp_strip_all_tags( get_the_title( $post ), true );
	$description = trim( wp_strip_all_tags( get_the_excerpt( $post ) ?: $post->post_content, true ) );
	if ($description === '') $description = $name;

	// Meta (your conventions; adjust if needed)
	$yt_id       = get_post_meta( $post_id, '_ssseo_video_id', true );
	$iso_from_meta = get_post_meta( $post_id, '_ssseo_duration_iso', true );
	$sec_from_meta = (int) get_post_meta( $post_id, '_ssseo_duration', true );
	$published_iso = get_post_meta( $post_id, '_ssseo_published_at', true ); // e.g., "2025-01-15T12:34:56Z"

	// Dates
	$uploadDate = $published_iso ? $published_iso : get_the_date( 'c', $post );

	// Duration
	$duration = '';
	if ($iso_from_meta) {
		$duration = $iso_from_meta;
	} elseif ($sec_from_meta > 0) {
		$duration = myls_seconds_to_iso8601_duration( $sec_from_meta );
	}

	// URLs
	$permalink  = get_permalink( $post );
	$embedUrl   = $yt_id ? myls_youtube_embed_url($yt_id) : '';
	$contentUrl = $yt_id ? myls_youtube_watch_url($yt_id) : $permalink;

	// Thumbnails
	$thumbs = [];
	$featured = myls_get_featured_image_url( $post_id, 'full' );
	if ($featured) $thumbs[] = $featured;
	if ($yt_id) {
		$thumbs = array_merge( $thumbs, myls_youtube_thumbnail_candidates($yt_id) );
	}
	$thumbs = array_values( array_unique( array_filter($thumbs) ) );

	// Publisher
	$publisher = myls_get_publisher_org();

	// Main schema
	$schema = [
		'@context'        => 'https://schema.org',
		'@type'           => 'VideoObject',
		'name'            => $name,
		'description'     => $description,
		'thumbnailUrl'    => $thumbs ?: null, // Google accepts array or string
		'uploadDate'      => $uploadDate,
		'duration'        => $duration ?: null,
		'url'             => $permalink,
		'contentUrl'      => $contentUrl ?: null,
		'embedUrl'        => $embedUrl ?: null,
		'publisher'       => $publisher,
		'potentialAction' => [
			'@type'        => 'WatchAction',
			'target'       => $contentUrl ?: $permalink,
		],
	];

	// Add optional InteractionStatistic if you store views/likes
	$views = (int) get_post_meta($post_id, '_ssseo_views', true);
	if ($views > 0) {
		$schema['interactionStatistic'] = [
			'@type'                => 'InteractionCounter',
			'interactionType'      => ['@type'=>'WatchAction'],
			'userInteractionCount' => $views,
		];
	}

	// Clean out null/empty keys to keep JSON tidy
	foreach ($schema as $k => $v) {
		if ($v === null || $v === '' || (is_array($v) && empty($v))) {
			unset($schema[$k]);
		}
	}

	/**
	 * Final hook to customize before output
	 */
	return apply_filters('myls_video_schema_array', $schema, $post_id);
}

/**
 * Print JSON-LD in the head on single video posts.
 */
function myls_maybe_print_video_schema() {
	if (!is_singular('video')) return;

	$should = apply_filters('myls_video_schema_should_render', true);
	if (!$should) return;

	$post_id = get_queried_object_id();
	$schema  = myls_build_video_schema_array( $post_id );
	if (empty($schema)) return;

	echo "\n" . '<script type="application/ld+json">' . "\n";
	echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	echo "\n" . '</script>' . "\n";
}
add_action('wp_head', 'myls_maybe_print_video_schema', 30);
