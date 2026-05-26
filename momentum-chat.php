<?php
/**
 * Plugin Name: Momentum Chat
 * Description: AI chat assistant for Momentum Health & Wellness. Answers questions and helps patients book appointments via TidyCal.
 * Version: 0.2.10
 * Author: Dr. Todd Anderson
 * License: GPL-2.0+
 * GitHub Plugin URI: tdander26/momentum-chat-plugin
 * Primary Branch: main
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOMENTUM_CHAT_VERSION', '0.1.0' );
define( 'MOMENTUM_CHAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'MOMENTUM_CHAT_URL', plugin_dir_url( __FILE__ ) );

require_once MOMENTUM_CHAT_PATH . 'includes/admin.php';
require_once MOMENTUM_CHAT_PATH . 'includes/api.php';

function momentum_chat_parse_quick_replies( $raw ) {
	$out = [];
	if ( ! $raw ) {
		return $out;
	}
	$lines = preg_split( '/\r?\n/', $raw );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( ! $line || strpos( $line, '|' ) === false ) {
			continue;
		}
		list( $label, $message ) = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( $label && $message ) {
			$out[] = [ 'label' => $label, 'message' => $message ];
		}
	}
	return array_slice( $out, 0, 6 );
}

add_action( 'wp_enqueue_scripts', function () {
	$settings = get_option( 'momentum_chat_settings', [] );
	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	wp_enqueue_style(
		'momentum-chat',
		MOMENTUM_CHAT_URL . 'assets/chat.css',
		[],
		MOMENTUM_CHAT_VERSION
	);

	wp_enqueue_script(
		'momentum-chat',
		MOMENTUM_CHAT_URL . 'assets/chat.js',
		[],
		MOMENTUM_CHAT_VERSION,
		true
	);

	wp_localize_script( 'momentum-chat', 'MomentumChat', [
		'restUrl'     => esc_url_raw( rest_url( 'momentum-chat/v1/' ) ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'phone'       => $settings['phone'] ?? '',
		'greeting'    => $settings['greeting'] ?? "Hi! I can answer questions about the practice or help you book an appointment. What can I help you with?",
		'buttonLabel' => $settings['button_label'] ?? 'Schedule',
		'panelTitle'  => $settings['panel_title'] ?? 'Scheduling Assistant',
		'avatarUrl'   => $settings['avatar_url'] ?? '',
		'quickReplies' => momentum_chat_parse_quick_replies( $settings['quick_replies'] ?? '' ),
		'popoutText'   => $settings['popout_text'] ?? '',
		'popoutDelay'  => isset( $settings['popout_delay'] ) ? (int) $settings['popout_delay'] : 8,
		'disclaimer'  => 'Not medical advice. For urgent concerns, call the office.',
	] );
} );
