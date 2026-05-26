<?php
/**
 * Plugin Name: Momentum Chat
 * Description: AI chat assistant for Momentum Health & Wellness. Answers questions and helps patients book appointments via TidyCal.
 * Version: 0.2.0
 * Author: Dr. Todd Anderson
 * License: GPL-2.0+
 * GitHub Plugin URI: drtoddanderson/momentum-chat-plugin
 * Primary Branch: main
 * Release Asset: true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOMENTUM_CHAT_VERSION', '0.1.0' );
define( 'MOMENTUM_CHAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'MOMENTUM_CHAT_URL', plugin_dir_url( __FILE__ ) );

require_once MOMENTUM_CHAT_PATH . 'includes/admin.php';
require_once MOMENTUM_CHAT_PATH . 'includes/api.php';

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
		'disclaimer'  => 'Not medical advice. For urgent concerns, call the office.',
	] );
} );
