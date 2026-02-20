<?php
/**
 * My Local SEO – OpenAI wiring (refactor)
 * Path: inc/openai.php
 *
 * Provides:
 *  - myls_openai_get_api_key()
 *  - myls_openai_chat( $prompt, $args )
 *  - Hook: 'myls_ai_complete'       -> myls_openai_complete()  [NEW - respects model/max_tokens/context]
 *  - Hook: 'myls_ai_generate_text'  -> myls_openai_generate_text() [LEGACY - meta titles/descriptions]
 */

if ( ! defined('ABSPATH') ) exit;

/** Fetch API key (filterable) */
if ( ! function_exists('myls_openai_get_api_key') ) {
	function myls_openai_get_api_key() : string {
		$keys = [
			get_option('myls_openai_api_key', ''),    // My Local SEO option
			get_option('ssseo_openai_api_key', ''),   // SSSEO Tools legacy
			get_option('openai_api_key', ''),         // generic
		];
		$key = '';
		foreach ($keys as $k) { if (is_string($k) && $k !== '') { $key = $k; break; } }
		return (string) apply_filters('myls_openai_api_key', $key);
	}
}

/**
 * Low-level chat call (Chat Completions API)
 * Args supported:
 *  - model        (string)
 *  - max_tokens   (int)
 *  - temperature  (float)
 *  - system       (string) custom system prompt
 */
if ( ! function_exists('myls_openai_chat') ) {
	function myls_openai_chat( string $prompt, array $args = [] ) : string {
		$key = myls_openai_get_api_key();
		if ( $key === '' ) {
			error_log('[MYLS] OpenAI key missing (myls_openai_api_key).');
			return '';
		}

		$model   = $args['model']       ?? 'gpt-4o';
		$max_tok = isset($args['max_tokens']) ? (int)$args['max_tokens'] : 300;
		$temp    = isset($args['temperature']) ? (float)$args['temperature'] : 0.7;
		$system  = isset($args['system']) && is_string($args['system'])
			? $args['system']
			: 'You are a helpful SEO/content assistant. Write clean, accurate text.';

		$body = [
			'model'    => $model,
			'messages' => [
				['role' => 'system', 'content' => $system],
				['role' => 'user',   'content' => $prompt],
			],
			'temperature' => $temp,
			'max_tokens'  => $max_tok,
		];

		// Penalties reduce pattern-locking across batch generations.
		// presence_penalty (0–2): penalizes tokens that already appeared, encouraging new topics.
		// frequency_penalty (0–2): penalizes tokens proportional to frequency, reducing repetition.
		if ( isset($args['presence_penalty']) && is_numeric($args['presence_penalty']) ) {
			$body['presence_penalty'] = (float) $args['presence_penalty'];
		}
		if ( isset($args['frequency_penalty']) && is_numeric($args['frequency_penalty']) ) {
			$body['frequency_penalty'] = (float) $args['frequency_penalty'];
		}

		$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
			'timeout' => 45,
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

/**
 * NEW: First-class completion hook that About-the-Area uses.
 * It RESPECTS the args passed from callers (model, max_tokens, temperature),
 * and applies sensible defaults per context.
 */
if ( ! function_exists('myls_openai_complete') ) {
	function myls_openai_complete( $out, array $args ) : string {
		// If a previous filter produced output, respect it.
		if ( is_string($out) && $out !== '' ) return $out;

		$prompt      = isset($args['prompt']) ? (string)$args['prompt'] : '';
		$context     = isset($args['context']) ? (string)$args['context'] : '';
		$model       = isset($args['model']) ? (string)$args['model'] : null;
		$max_tokens  = isset($args['max_tokens']) ? (int)$args['max_tokens'] : null;
		$temperature = isset($args['temperature']) ? (float)$args['temperature'] : 0.7;

		// Context-sensitive defaults
		if ( $context === 'about_the_area' ) {
			$model      = $model ?: 'gpt-4o';      // bigger model
			$max_tokens = $max_tokens ?: 1600;     // enough room for sectioned HTML
			$system     = 'You write clean, structured, sectioned HTML for local area guides. Use HTML tags like <strong>, <em>, <h3>, <p>, <ul>, <li>. NEVER use markdown syntax such as ** or __ for bold, * for italic, or ### for headings. Output raw HTML only, no code fences.';
			// Higher temperature + penalties reduce pattern-locking in batch runs
			$temperature = max( $temperature, 0.85 );
		} elseif ( $context === 'faqs_generate' ) {
			$model      = $model ?: 'gpt-4o';      // bigger model for FAQs
			$max_tokens = $max_tokens ?: 10000;    // enough for 10-15 LONG FAQs with detailed multi-block answers, lists, and sources
			$system     = 'You are an expert local SEO copywriter. Generate clean, structured HTML for FAQ content. No markdown, no code fences.';
			$temperature = max( $temperature, 0.85 );
		} else {
			$model      = $model ?: 'gpt-4o-mini';
			$max_tokens = $max_tokens ?: 300;
			$system     = 'You are a helpful SEO assistant. Keep responses concise and accurate.';
		}

		// Apply penalties for batch-heavy contexts to encourage diversity.
		// These are passed through to myls_openai_chat() which injects them into the API body.
		$presence_penalty  = isset($args['presence_penalty'])  ? (float)$args['presence_penalty']  : null;
		$frequency_penalty = isset($args['frequency_penalty']) ? (float)$args['frequency_penalty'] : null;

		// Default penalties for content-heavy contexts (About Area, FAQs)
		if ( in_array( $context, ['about_the_area', 'faqs_generate'], true ) ) {
			$presence_penalty  = $presence_penalty  ?? 0.9;
			$frequency_penalty = $frequency_penalty ?? 0.5;
		}

		$chat_args = [
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
			'system'      => $system,
		];
		if ( $presence_penalty !== null )  $chat_args['presence_penalty']  = $presence_penalty;
		if ( $frequency_penalty !== null ) $chat_args['frequency_penalty'] = $frequency_penalty;

		return myls_openai_chat( $prompt, $chat_args );
	}
	add_filter('myls_ai_complete', 'myls_openai_complete', 10, 2);
}

/**
 * LEGACY: Simple text generator used by the Meta tab.
 * KEEP this for backward compatibility. It deliberately enforces tiny caps.
 */
if ( ! function_exists('myls_openai_generate_text') ) {
	function myls_openai_generate_text( $out, string $prompt ) : string {
		if ( is_string($out) && $out !== '' ) return $out;

		$is_title = stripos($prompt, 'Title only') !== false;
		$args = [
			'max_tokens'  => $is_title ? 40 : 80,
			'temperature' => 0.7,
			'model'       => 'gpt-4o-mini',
			'system'      => 'You are an SEO assistant that writes concise, high-quality metadata.',
		];

		return myls_openai_chat( $prompt, $args );
	}
	add_filter('myls_ai_generate_text', 'myls_openai_generate_text', 10, 2);
}
