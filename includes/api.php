<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'momentum-chat/v1', '/chat', [
		'methods'             => 'POST',
		'callback'            => 'momentum_chat_handle_chat',
		'permission_callback' => '__return_true',
	] );

	register_rest_route( 'momentum-chat/v1', '/slots', [
		'methods'             => 'POST',
		'callback'            => 'momentum_chat_handle_slots',
		'permission_callback' => '__return_true',
	] );

	register_rest_route( 'momentum-chat/v1', '/booking-link', [
		'methods'             => 'POST',
		'callback'            => 'momentum_chat_handle_booking_link',
		'permission_callback' => '__return_true',
	] );

	register_rest_route( 'momentum-chat/v1', '/track', [
		'methods'             => 'POST',
		'callback'            => 'momentum_chat_handle_track',
		'permission_callback' => '__return_true',
	] );
} );

function momentum_chat_save_transcript_message( $session_id, $role, $content ) {
	if ( ! $session_id ) {
		return;
	}
	$settings = get_option( 'momentum_chat_settings', [] );
	if ( empty( $settings['save_transcripts'] ) && ! isset( $settings['save_transcripts'] ) ) {
		// Setting not yet saved: default ON.
	}
	if ( isset( $settings['save_transcripts'] ) && ! $settings['save_transcripts'] ) {
		return;
	}

	$transcripts = get_option( 'momentum_chat_transcripts', [] );
	$now         = gmdate( 'c' );

	$found_index = null;
	foreach ( $transcripts as $i => $t ) {
		if ( isset( $t['id'] ) && $t['id'] === $session_id ) {
			$found_index = $i;
			break;
		}
	}

	$message = [
		'role'    => $role,
		'content' => mb_substr( (string) $content, 0, 2000 ),
		'at'      => $now,
	];

	if ( $found_index === null ) {
		$transcript = [
			'id'         => $session_id,
			'started_at' => $now,
			'updated_at' => $now,
			'status'     => 'chatted_only',
			'messages'   => [ $message ],
		];
		array_unshift( $transcripts, $transcript );
	} else {
		$transcripts[ $found_index ]['messages'][]  = $message;
		$transcripts[ $found_index ]['updated_at']  = $now;
		// Cap per-conversation messages at 40.
		if ( count( $transcripts[ $found_index ]['messages'] ) > 40 ) {
			$transcripts[ $found_index ]['messages'] = array_slice( $transcripts[ $found_index ]['messages'], -40 );
		}
		// Move the updated transcript to the top.
		$updated = $transcripts[ $found_index ];
		array_splice( $transcripts, $found_index, 1 );
		array_unshift( $transcripts, $updated );
	}

	// Cap total at 100.
	$transcripts = array_slice( $transcripts, 0, 100 );
	update_option( 'momentum_chat_transcripts', $transcripts, false );
}

function momentum_chat_update_transcript_status( $session_id, $new_status ) {
	if ( ! $session_id ) {
		return;
	}
	$rank = [ 'chatted_only' => 1, 'reached_slots' => 2, 'booking_clicked' => 3 ];
	$transcripts = get_option( 'momentum_chat_transcripts', [] );
	foreach ( $transcripts as $i => $t ) {
		if ( isset( $t['id'] ) && $t['id'] === $session_id ) {
			$current = $transcripts[ $i ]['status'] ?? 'chatted_only';
			// Only upgrade status, never downgrade.
			if ( ( $rank[ $new_status ] ?? 0 ) > ( $rank[ $current ] ?? 0 ) ) {
				$transcripts[ $i ]['status'] = $new_status;
				update_option( 'momentum_chat_transcripts', $transcripts, false );
			}
			return;
		}
	}
}

function momentum_chat_handle_track( WP_REST_Request $request ) {
	$event = $request->get_param( 'event' );
	$allowed = [ 'open', 'slots_shown', 'booking_click' ];
	if ( ! in_array( $event, $allowed, true ) ) {
		return [ 'ok' => false ];
	}
	$stats = get_option( 'momentum_chat_stats', [] );
	if ( empty( $stats['since'] ) ) {
		$stats['since'] = gmdate( 'Y-m-d' );
	}
	$stats[ $event ] = ( $stats[ $event ] ?? 0 ) + 1;
	update_option( 'momentum_chat_stats', $stats, false );
	return [ 'ok' => true ];
}

function momentum_chat_rate_limit() {
	$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	$key = 'mchat_rl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 10 ) {
		return new WP_Error( 'rate_limited', 'Too many requests. Please wait a minute.', [ 'status' => 429 ] );
	}
	set_transient( $key, $hits + 1, 60 );
	return true;
}

function momentum_chat_default_system_prompt() {
	return <<<PROMPT
You are the scheduling assistant for Momentum Health & Wellness, a chiropractic practice in Elk River, MN, run by Dr. Todd Anderson.

Your job:
1. Answer questions about the practice using ONLY the PRACTICE INFO section below.
2. Help NEW patients book a free 15-minute consult with Dr. Anderson.

WHAT YOU CAN SCHEDULE (important):
- The ONLY thing you can book is the free 15-minute new-patient consult.
- It is for new patients only, one time, before they become a regular patient.
- After the consult, future visits are booked directly with the office — not through you.
- If someone is already a patient and wants to book a visit, tell them to call the office. Do NOT try to schedule them.

CRITICAL — do not make things up:
- If a question is not directly answered by the PRACTICE INFO section, do NOT guess. Say: "I'm not sure about that — and I don't want to give you wrong info. If you're a new patient, Dr. Anderson offers a free 15-minute consult where you can ask him directly. Want me to help you book one? If you're already a patient, please call the office."
- Never invent prices, services, tests, hours, insurance details, staff names, or policies.
- Never paraphrase in a way that adds details that aren't in PRACTICE INFO.

Medical safety:
- You are NOT a medical professional. Never diagnose, never recommend treatment, never interpret symptoms. If asked clinical questions, say: "I can't give medical advice — Dr. Anderson would need to evaluate that in person. If you're a new patient, the free 15-minute consult is the perfect place to talk it through with him. Want me to help you book one?"
- For anything urgent (severe pain, numbness, recent injury, signs of emergency), tell the patient to call the office immediately or seek emergency care.

Tone:
- Short and warm. 1–3 sentences usually.
- Conversational, not corporate.

Booking flow (consult only):
- When someone wants to schedule, first confirm they're a new patient. If they're returning, redirect them to call the office.
- Then ask ONE question: "When are you hoping to come in? (e.g., this week, next week, mornings, afternoons)"
- As soon as you have the timing, output a tool call on its own line in this exact format:
  [TOOL]{"action":"fetch_slots","timeframe":"free text"}[/TOOL]
- Do not invent appointment times. Do not promise specific times. The system will return real slots from TidyCal.
PROMPT;
}

function momentum_chat_handle_chat( WP_REST_Request $request ) {
	$limit = momentum_chat_rate_limit();
	if ( is_wp_error( $limit ) ) {
		return $limit;
	}

	$settings = get_option( 'momentum_chat_settings', [] );
	$api_key  = $settings['openrouter_key'] ?? '';
	if ( ! $api_key ) {
		return new WP_Error( 'no_key', 'Chat is not configured.', [ 'status' => 500 ] );
	}

	$messages = $request->get_param( 'messages' );
	if ( ! is_array( $messages ) ) {
		return new WP_Error( 'bad_input', 'Missing messages.', [ 'status' => 400 ] );
	}

	$clean = [];
	foreach ( $messages as $m ) {
		if ( ! isset( $m['role'], $m['content'] ) ) {
			continue;
		}
		if ( ! in_array( $m['role'], [ 'user', 'assistant' ], true ) ) {
			continue;
		}
		$clean[] = [
			'role'    => $m['role'],
			'content' => mb_substr( sanitize_textarea_field( $m['content'] ), 0, 2000 ),
		];
	}
	$clean = array_slice( $clean, -20 );

	$system_prompt = ! empty( $settings['system_prompt'] )
		? $settings['system_prompt']
		: momentum_chat_default_system_prompt();

	if ( ! empty( $settings['phone'] ) ) {
		$system_prompt .= "\n\nOffice phone: " . $settings['phone'];
	}

	if ( ! empty( $settings['practice_info'] ) ) {
		$system_prompt .= "\n\n=== PRACTICE INFO (your only source of truth) ===\n" . $settings['practice_info'];
	}

	array_unshift( $clean, [ 'role' => 'system', 'content' => $system_prompt ] );

	$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
		'timeout' => 30,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => home_url(),
			'X-Title'       => get_bloginfo( 'name' ),
		],
		'body'    => wp_json_encode( [
			'model'       => $settings['model'] ?? 'qwen/qwen-2.5-72b-instruct',
			'messages'    => $clean,
			'temperature' => 0.4,
			'max_tokens'  => 600,
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		error_log( 'Momentum Chat: OpenRouter request failed - ' . $response->get_error_message() );
		return new WP_Error( 'upstream', 'Chat service unavailable. Please try again in a moment.', [ 'status' => 502 ] );
	}

	$http_code = wp_remote_retrieve_response_code( $response );
	$raw_body  = wp_remote_retrieve_body( $response );
	$body      = json_decode( $raw_body, true );

	if ( $http_code >= 400 ) {
		$msg = $body['error']['message'] ?? "HTTP $http_code";
		error_log( 'Momentum Chat: OpenRouter error - ' . $msg );
		return new WP_Error( 'upstream_error', 'Chat service error: ' . $msg, [ 'status' => 502 ] );
	}

	$reply = $body['choices'][0]['message']['content'] ?? '';

	if ( ! $reply ) {
		error_log( 'Momentum Chat: empty reply from OpenRouter. Raw body: ' . substr( $raw_body, 0, 500 ) );
		return new WP_Error( 'empty_reply', "I didn't catch a response — please try again, or call the office.", [ 'status' => 502 ] );
	}

	$tool = null;
	if ( preg_match( '/\[TOOL\](.*?)\[\/TOOL\]/s', $reply, $matches ) ) {
		$decoded = json_decode( trim( $matches[1] ), true );
		if ( is_array( $decoded ) ) {
			$tool = $decoded;
		}
		$reply = trim( preg_replace( '/\[TOOL\].*?\[\/TOOL\]/s', '', $reply ) );
	}

	// Log the latest user message + the assistant's reply.
	$session_id   = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
	$last_user    = end( $clean );
	if ( $session_id && $last_user && $last_user['role'] === 'user' ) {
		momentum_chat_save_transcript_message( $session_id, 'user', $last_user['content'] );
	}
	if ( $session_id && $reply ) {
		momentum_chat_save_transcript_message( $session_id, 'assistant', $reply );
	}

	return [
		'reply' => $reply,
		'tool'  => $tool,
	];
}

function momentum_chat_handle_slots( WP_REST_Request $request ) {
	$limit = momentum_chat_rate_limit();
	if ( is_wp_error( $limit ) ) {
		return $limit;
	}

	$settings = get_option( 'momentum_chat_settings', [] );
	$token    = $settings['tidycal_token'] ?? '';
	if ( ! $token ) {
		return new WP_Error( 'no_token', 'Scheduling is not configured.', [ 'status' => 500 ] );
	}

	$type_id = $settings['tidycal_type_new'] ?? 0;
	if ( ! $type_id ) {
		return new WP_Error( 'no_type', 'Consult booking type not configured.', [ 'status' => 500 ] );
	}

	$starts_after = $request->get_param( 'starts_after' );
	if ( $starts_after && strtotime( $starts_after ) ) {
		// Page forward: start from 1 minute after the last shown slot to avoid duplicates.
		$starts_at = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $starts_after ) + 60 );
	} else {
		$starts_at = gmdate( 'Y-m-d\TH:i:s\Z' );
	}
	$ends_at = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+45 days' ) );

	$url = sprintf(
		'https://tidycal.com/api/booking-types/%d/timeslots?starts_at=%s&ends_at=%s',
		(int) $type_id,
		rawurlencode( $starts_at ),
		rawurlencode( $ends_at )
	);

	$response = wp_remote_get( $url, [
		'timeout' => 15,
		'headers' => [
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/json',
		],
	] );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'upstream', 'Scheduling service unavailable.', [ 'status' => 502 ] );
	}

	$body  = json_decode( wp_remote_retrieve_body( $response ), true );
	$slots = $body['data'] ?? $body ?? [];

	$wp_tz    = wp_timezone();
	$out      = [];
	$has_more = count( $slots ) > 10;
	foreach ( array_slice( $slots, 0, 10 ) as $slot ) {
		if ( ! isset( $slot['starts_at'] ) ) {
			continue;
		}
		try {
			// TidyCal returns ISO 8601 timestamps. Parse as UTC, then convert to WP's timezone.
			$dt = new DateTime( $slot['starts_at'], new DateTimeZone( 'UTC' ) );
			$dt->setTimezone( $wp_tz );
			$label = $dt->format( 'D, M j @ g:i A' );
		} catch ( Exception $e ) {
			continue;
		}
		$out[] = [
			'starts_at' => $slot['starts_at'],
			'label'     => $label,
		];
	}

	if ( ! empty( $out ) ) {
		$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
		momentum_chat_update_transcript_status( $session_id, 'reached_slots' );
	}

	return [
		'slots'    => $out,
		'has_more' => $has_more,
	];
}

function momentum_chat_handle_booking_link( WP_REST_Request $request ) {
	$settings = get_option( 'momentum_chat_settings', [] );
	$base = $settings['tidycal_booking_url'] ?? '';
	if ( ! $base ) {
		return new WP_Error( 'no_url', 'Booking URL not configured.', [ 'status' => 500 ] );
	}

	$starts_at = sanitize_text_field( $request->get_param( 'starts_at' ) ?? '' );
	$name      = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
	$email     = sanitize_email( $request->get_param( 'email' ) ?? '' );

	$args = array_filter( [
		'date'  => $starts_at ? gmdate( 'Y-m-d', strtotime( $starts_at ) ) : null,
		'time'  => $starts_at ? gmdate( 'H:i', strtotime( $starts_at ) ) : null,
		'name'  => $name ?: null,
		'email' => $email ?: null,
	] );

	$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
	momentum_chat_update_transcript_status( $session_id, 'booking_clicked' );

	return [
		'url' => add_query_arg( $args, $base ),
	];
}
