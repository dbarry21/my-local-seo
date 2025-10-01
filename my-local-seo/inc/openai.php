<?php
/**
 * My Local SEO – OpenAI wiring
 * Path: inc/openai.php
 *
 * Provides:
 *  - myls_openai_get_api_key()
 *  - myls_openai_chat( $prompt, $args )
 *  - Hook: 'myls_ai_generate_text' -> myls_openai_generate_text()
 */

if ( ! defined('ABSPATH') ) exit;

/** Fetch API key from options (tries a few common keys, filterable) */
if ( ! function_exists('myls_openai_get_api_key') ) {
	function myls_openai_get_api_key() : string {
		$keys = [
			get_option('myls_openai_api_key', ''),    // My Local SEO option
			get_option('ssseo_openai_api_key', ''),   // SSSEO Tools legacy
			get_option('openai_api_key', ''),         // generic
		];
		$key = '';
		foreach ($keys as $k) { if (is_string($k) && $k !== '') { $key = $k; break; } }
		/** Allow themes/plugins to provide the key */
		return (string) apply_filters('myls_openai_api_key', $key);
	}
}

/** Low-level chat call (Chat Completions API) */
if ( ! function_exists('myls_openai_chat') ) {
	function myls_openai_chat( string $prompt, array $args = [] ) : string {
		$key = myls_openai_get_api_key();
		if ( $key === '' ) {
			error_log('[MYLS] OpenAI key missing (myls_openai_api_key).');
			return '';
		}

		$model   = $args['model']   ?? 'gpt-4o-mini'; // fast & cheap; change if you prefer
		$max_tok = $args['max_tokens'] ?? 120;
		$temp    = isset($args['temperature']) ? (float)$args['temperature'] : 0.7;

		$body = [
			'model'    => $model,
			'messages' => [
				['role' => 'system', 'content' => 'You are an SEO assistant that writes concise, high-quality metadata.'],
				['role' => 'user',   'content' => $prompt],
			],
			'temperature' => $temp,
			'max_tokens'  => $max_tok,
		];

		$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
			'timeout' => 30,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $key,
			],
			'body'    => wp_json_encode( $body ),
		]);

		if ( is_wp_error($resp) ) {
			error_log('[MYLS] OpenAI HTTP error: ' . $resp->get_error_message());
			return '';
		}
		$code = wp_remote_retrieve_response_code($resp);
		$json = json_decode( wp_remote_retrieve_body($resp), true );

		if ( $code !== 200 || !is_array($json) ) {
			error_log('[MYLS] OpenAI bad response: ' . $code . ' ' . wp_remote_retrieve_body($resp));
			return '';
		}

		$text = $json['choices'][0]['message']['content'] ?? '';
		return is_string($text) ? trim($text) : '';
	}
}

/** Hook our generator into the AI pipeline the UI already calls */
if ( ! function_exists('myls_openai_generate_text') ) {
	function myls_openai_generate_text( $out, string $prompt ) : string {
		// If something upstream already generated text, respect it.
		if ( is_string($out) && $out !== '' ) return $out;

		// Choose a shorter cap for titles; longer for descriptions
		$is_title = stripos($prompt, 'Title only') !== false;
		$args = [
			'max_tokens'  => $is_title ? 40 : 80,
			'temperature' => 0.7,
			'model'       => 'gpt-4o-mini',
		];

		return myls_openai_chat( $prompt, $args );
	}
	add_filter('myls_ai_generate_text', 'myls_openai_generate_text', 10, 2);
}
