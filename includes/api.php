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
} );

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
			'max_tokens'  => 400,
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'upstream', 'Chat service unavailable.', [ 'status' => 502 ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$reply = $body['choices'][0]['message']['content'] ?? '';

	$tool = null;
	if ( preg_match( '/\[TOOL\](.*?)\[\/TOOL\]/s', $reply, $matches ) ) {
		$decoded = json_decode( trim( $matches[1] ), true );
		if ( is_array( $decoded ) ) {
			$tool = $decoded;
		}
		$reply = trim( preg_replace( '/\[TOOL\].*?\[\/TOOL\]/s', '', $reply ) );
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

	$starts_at = gmdate( 'Y-m-d\TH:i:s\Z' );
	$ends_at   = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+14 days' ) );

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

	$out = [];
	foreach ( array_slice( $slots, 0, 6 ) as $slot ) {
		if ( isset( $slot['starts_at'] ) ) {
			$out[] = [
				'starts_at' => $slot['starts_at'],
				'label'     => wp_date( 'D, M j @ g:i A', strtotime( $slot['starts_at'] ) ),
			];
		}
	}

	return [
		'slots' => $out,
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

	return [
		'url' => add_query_arg( $args, $base ),
	];
}
