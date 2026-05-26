<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_options_page(
		'Momentum Chat',
		'Momentum Chat',
		'manage_options',
		'momentum-chat',
		'momentum_chat_render_settings'
	);
	add_submenu_page(
		'options-general.php',
		'Momentum Chat — Conversations',
		'MC Conversations',
		'manage_options',
		'momentum-chat-conversations',
		'momentum_chat_render_conversations'
	);
} );

function momentum_chat_render_conversations() {
	// Handle actions before render.
	if ( isset( $_POST['momentum_chat_delete_all_transcripts'] ) && check_admin_referer( 'momentum_chat_delete_all_transcripts' ) ) {
		update_option( 'momentum_chat_transcripts', [], false );
		echo '<div class="notice notice-success is-dismissible"><p>All transcripts deleted.</p></div>';
	}
	if ( isset( $_POST['momentum_chat_delete_transcript'], $_POST['transcript_id'] ) && check_admin_referer( 'momentum_chat_delete_transcript' ) ) {
		$id          = sanitize_text_field( $_POST['transcript_id'] );
		$transcripts = get_option( 'momentum_chat_transcripts', [] );
		$transcripts = array_values( array_filter( $transcripts, function ( $t ) use ( $id ) {
			return ( $t['id'] ?? '' ) !== $id;
		} ) );
		update_option( 'momentum_chat_transcripts', $transcripts, false );
		echo '<div class="notice notice-success is-dismissible"><p>Transcript deleted.</p></div>';
	}

	$transcripts = get_option( 'momentum_chat_transcripts', [] );
	$filter      = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
	if ( $filter ) {
		$transcripts = array_values( array_filter( $transcripts, function ( $t ) use ( $filter ) {
			return ( $t['status'] ?? '' ) === $filter;
		} ) );
	}

	$status_labels = [
		'chatted_only'    => [ 'Chatted only',     '#aaa' ],
		'reached_slots'   => [ 'Saw times',        '#d49a3d' ],
		'booking_clicked' => [ 'Clicked to book',  '#3fa163' ],
	];

	?>
	<div class="wrap">
		<h1>Momentum Chat — Conversations</h1>

		<p style="background:#fff9e6;border-left:4px solid #f0c674;padding:10px 14px;margin:14px 0;font-size:13px;">
			<strong>Privacy note:</strong> the bot itself doesn't ask for names, emails, or phone numbers — but visitors may volunteer them. Treat these transcripts the way you'd treat clinical notes. Delete anything you're uncomfortable storing.
		</p>

		<p>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=momentum-chat-conversations' ) ); ?>" class="button <?php echo ! $filter ? 'button-primary' : ''; ?>">All</a>
			<?php foreach ( $status_labels as $key => $lbl ) : ?>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=momentum-chat-conversations&status=' . $key ) ); ?>" class="button <?php echo $filter === $key ? 'button-primary' : ''; ?>"><?php echo esc_html( $lbl[0] ); ?></a>
			<?php endforeach; ?>
			<form method="post" style="display:inline;margin-left:20px;">
				<?php wp_nonce_field( 'momentum_chat_delete_all_transcripts' ); ?>
				<button type="submit" name="momentum_chat_delete_all_transcripts" class="button" onclick="return confirm('Delete ALL transcripts? This cannot be undone.');">Delete all</button>
			</form>
		</p>

		<?php if ( empty( $transcripts ) ) : ?>
			<p>No conversations yet<?php echo $filter ? ' in this category' : ''; ?>.</p>
		<?php else : ?>
			<?php foreach ( $transcripts as $t ) :
				$status      = $t['status'] ?? 'chatted_only';
				$label       = $status_labels[ $status ] ?? [ $status, '#aaa' ];
				$msgs        = $t['messages'] ?? [];
				$started     = ! empty( $t['started_at'] ) ? wp_date( 'M j, Y g:i A', strtotime( $t['started_at'] ) ) : '';
				$detail_id   = 'mchat-t-' . esc_attr( $t['id'] ?? wp_generate_password( 6, false ) );
			?>
				<div style="background:#fff;border:1px solid #e5e5e5;border-radius:6px;margin:12px 0;">
					<div style="padding:12px 16px;display:flex;align-items:center;gap:12px;cursor:pointer;" onclick="document.getElementById('<?php echo esc_attr( $detail_id ); ?>').style.display = document.getElementById('<?php echo esc_attr( $detail_id ); ?>').style.display === 'block' ? 'none' : 'block'">
						<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;color:#fff;background:<?php echo esc_attr( $label[1] ); ?>;"><?php echo esc_html( $label[0] ); ?></span>
						<span style="color:#666;font-size:13px;flex:1;"><?php echo esc_html( $started ); ?> · <?php echo esc_html( count( $msgs ) ); ?> message<?php echo count( $msgs ) === 1 ? '' : 's'; ?></span>
						<?php
						$first_user = '';
						foreach ( $msgs as $m ) {
							if ( ( $m['role'] ?? '' ) === 'user' ) {
								$first_user = mb_substr( $m['content'] ?? '', 0, 80 );
								break;
							}
						}
						?>
						<span style="color:#999;font-style:italic;font-size:13px;flex:2;text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $first_user ); ?></span>
					</div>
					<div id="<?php echo esc_attr( $detail_id ); ?>" style="display:none;border-top:1px solid #eee;padding:16px;background:#fafafa;">
						<?php foreach ( $msgs as $m ) :
							$role = $m['role'] ?? 'user';
							$is_user = $role === 'user';
							?>
							<div style="margin-bottom:8px;display:flex;<?php echo $is_user ? 'justify-content:flex-end;' : ''; ?>">
								<div style="max-width:75%;padding:8px 12px;border-radius:12px;font-size:13px;line-height:1.5;<?php echo $is_user ? 'background:#3b5f7d;color:#fff;border-bottom-right-radius:4px;' : 'background:#fff;border:1px solid #e5e5e5;border-bottom-left-radius:4px;'; ?>"><?php echo nl2br( esc_html( $m['content'] ?? '' ) ); ?></div>
							</div>
						<?php endforeach; ?>
						<form method="post" style="margin-top:12px;text-align:right;">
							<?php wp_nonce_field( 'momentum_chat_delete_transcript' ); ?>
							<input type="hidden" name="transcript_id" value="<?php echo esc_attr( $t['id'] ?? '' ); ?>">
							<button type="submit" name="momentum_chat_delete_transcript" class="button button-small" onclick="return confirm('Delete this transcript?');">Delete this conversation</button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== 'settings_page_momentum-chat' ) {
		return;
	}
	wp_enqueue_media();
} );

add_action( 'admin_init', function () {
	register_setting( 'momentum_chat', 'momentum_chat_settings', [
		'sanitize_callback' => 'momentum_chat_sanitize_settings',
	] );
} );

function momentum_chat_sanitize_settings( $input ) {
	return [
		'enabled'             => ! empty( $input['enabled'] ) ? 1 : 0,
		'openrouter_key'      => sanitize_text_field( $input['openrouter_key'] ?? '' ),
		'model'               => sanitize_text_field( $input['model'] ?? 'qwen/qwen-2.5-72b-instruct' ),
		'tidycal_token'       => sanitize_text_field( $input['tidycal_token'] ?? '' ),
		'tidycal_booking_url' => esc_url_raw( $input['tidycal_booking_url'] ?? '' ),
		'tidycal_type_new'    => absint( $input['tidycal_type_new'] ?? 0 ),
		'tidycal_type_return' => absint( $input['tidycal_type_return'] ?? 0 ),
		'phone'               => sanitize_text_field( $input['phone'] ?? '' ),
		'greeting'            => sanitize_textarea_field( $input['greeting'] ?? '' ),
		'button_label'        => sanitize_text_field( $input['button_label'] ?? '' ),
		'panel_title'         => sanitize_text_field( $input['panel_title'] ?? '' ),
		'avatar_url'          => esc_url_raw( $input['avatar_url'] ?? '' ),
		'save_transcripts'    => ! empty( $input['save_transcripts'] ) ? 1 : 0,
		'quick_replies'       => sanitize_textarea_field( $input['quick_replies'] ?? '' ),
		'popout_text'         => sanitize_text_field( $input['popout_text'] ?? '' ),
		'popout_delay'        => max( 0, min( 120, (int) ( $input['popout_delay'] ?? 8 ) ) ),
		'practice_info'       => wp_kses_post( $input['practice_info'] ?? '' ),
		'system_prompt'       => sanitize_textarea_field( $input['system_prompt'] ?? '' ),
	];
}

function momentum_chat_default_practice_info() {
	return <<<INFO
HOURS
Mon–Fri 8a–6p, Sat 9a–12p, closed Sun

ADDRESS
123 Main St, Elk River MN 55330. Free parking out front.

SERVICES / TESTS OFFERED
- Chiropractic adjustments
- Soft tissue therapy
- Postural assessment
- (Add any tests you offer here — e.g., blood chemistry analysis, gait analysis, etc.)

INSURANCE
We accept (list your accepted plans). Cash rate for adjustments: $.

FIRST VISIT
First visits are about 45–60 minutes. We do a full health history, exam, and explanation of findings. Wear comfortable clothes.

FREE CONSULT (NEW PATIENTS ONLY, ONE TIME)
We offer a free 15-minute consult to NEW patients only, before they ever book a real appointment. It's a one-time chance to ask Dr. Anderson questions and see if we're the right fit. After the consult, the next visit is a regular paid appointment. Existing/returning patients are NOT eligible — for them, direct any clinical questions to a regular paid visit or to calling the office.

FAQ
Q: Do I need a referral?
A: No.

Q: How long is a typical adjustment visit?
A: About 15–20 minutes once you're an established patient.

Q: Do you take HSA/FSA?
A: Yes.
INFO;
}

function momentum_chat_render_settings() {
	// Handle counter reset before we render anything.
	if ( isset( $_POST['momentum_chat_reset_stats'] ) && check_admin_referer( 'momentum_chat_reset_stats' ) ) {
		update_option( 'momentum_chat_stats', [ 'since' => gmdate( 'Y-m-d' ) ], false );
		echo '<div class="notice notice-success is-dismissible"><p>Usage counters reset.</p></div>';
	}

	$s     = get_option( 'momentum_chat_settings', [] );
	$stats = get_option( 'momentum_chat_stats', [] );
	$opens     = (int) ( $stats['open'] ?? 0 );
	$shown     = (int) ( $stats['slots_shown'] ?? 0 );
	$bookings  = (int) ( $stats['booking_click'] ?? 0 );
	$since     = $stats['since'] ?? 'today';
	$conv_rate = $opens > 0 ? round( ( $bookings / $opens ) * 100, 1 ) : 0;
	?>
	<div class="wrap">
		<h1>Momentum Chat Settings</h1>

		<div style="background:#fff;border:1px solid #e5e5e5;border-radius:6px;padding:16px 20px;margin:16px 0;display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
			<div>
				<div style="font-size:11px;color:#777;text-transform:uppercase;letter-spacing:0.5px;">Chats opened</div>
				<div style="font-size:28px;font-weight:600;color:#1c2733;"><?php echo esc_html( number_format( $opens ) ); ?></div>
			</div>
			<div style="border-left:1px solid #eee;height:48px;"></div>
			<div>
				<div style="font-size:11px;color:#777;text-transform:uppercase;letter-spacing:0.5px;">Reached slot picker</div>
				<div style="font-size:28px;font-weight:600;color:#1c2733;"><?php echo esc_html( number_format( $shown ) ); ?></div>
			</div>
			<div style="border-left:1px solid #eee;height:48px;"></div>
			<div>
				<div style="font-size:11px;color:#777;text-transform:uppercase;letter-spacing:0.5px;">Booking links clicked</div>
				<div style="font-size:28px;font-weight:600;color:#1c2733;"><?php echo esc_html( number_format( $bookings ) ); ?></div>
			</div>
			<div style="border-left:1px solid #eee;height:48px;"></div>
			<div>
				<div style="font-size:11px;color:#777;text-transform:uppercase;letter-spacing:0.5px;">Conversion</div>
				<div style="font-size:28px;font-weight:600;color:#1c2733;"><?php echo esc_html( $conv_rate ); ?>%</div>
			</div>
			<div style="flex:1;text-align:right;color:#888;font-size:12px;">
				Since <?php echo esc_html( $since ); ?>
				<form method="post" style="display:inline;margin-left:12px;">
					<?php wp_nonce_field( 'momentum_chat_reset_stats' ); ?>
					<button type="submit" name="momentum_chat_reset_stats" class="button button-small" onclick="return confirm('Reset usage counters to zero?');">Reset</button>
				</form>
			</div>
		</div>
		<form method="post" action="options.php">
			<?php settings_fields( 'momentum_chat' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Enable chat widget</th>
					<td>
						<label>
							<input type="checkbox" name="momentum_chat_settings[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
							Show the chat bubble on every page
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">OpenRouter API key</th>
					<td>
						<input type="password" class="regular-text" name="momentum_chat_settings[openrouter_key]" value="<?php echo esc_attr( $s['openrouter_key'] ?? '' ); ?>">
						<p class="description">Get one at <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a>. Load ~$5 to start.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Model</th>
					<td>
						<input type="text" class="regular-text" name="momentum_chat_settings[model]" value="<?php echo esc_attr( $s['model'] ?? 'qwen/qwen-2.5-72b-instruct' ); ?>">
						<p class="description">Default: <code>qwen/qwen-2.5-72b-instruct</code>. Cheaper alternative: <code>qwen/qwen-2.5-7b-instruct</code> (often free). Quality alternative: <code>google/gemini-flash-1.5</code>.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">TidyCal API token</th>
					<td>
						<input type="password" class="regular-text" name="momentum_chat_settings[tidycal_token]" value="<?php echo esc_attr( $s['tidycal_token'] ?? '' ); ?>">
						<p class="description">Generate in TidyCal under Account &rarr; API.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">TidyCal base booking URL</th>
					<td>
						<input type="url" class="regular-text" name="momentum_chat_settings[tidycal_booking_url]" value="<?php echo esc_attr( $s['tidycal_booking_url'] ?? '' ); ?>" placeholder="https://tidycal.com/drtoddanderson">
						<p class="description">Your public TidyCal profile URL. Used to build pre-filled booking links.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Consult booking type ID</th>
					<td>
						<input type="number" name="momentum_chat_settings[tidycal_type_new]" value="<?php echo esc_attr( $s['tidycal_type_new'] ?? '' ); ?>">
						<p class="description">Numeric ID of your free consult booking type in TidyCal. Find it in the URL when editing the booking type (e.g. <code>/booking-types/<strong>12345</strong>/edit</code>).</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Save conversation transcripts</th>
					<td>
						<label>
							<input type="checkbox" name="momentum_chat_settings[save_transcripts]" value="1" <?php checked( ! isset( $s['save_transcripts'] ) || ! empty( $s['save_transcripts'] ) ); ?>>
							Store the last 100 chats so you can review them
						</label>
						<p class="description">View at <a href="<?php echo esc_url( admin_url( 'options-general.php?page=momentum-chat-conversations' ) ); ?>">MC Conversations</a>. The bot never asks for names/emails/phones, but visitors may volunteer info. Treat transcripts the way you would clinical notes. Uncheck to disable storing entirely.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Office phone</th>
					<td>
						<input type="text" class="regular-text" name="momentum_chat_settings[phone]" value="<?php echo esc_attr( $s['phone'] ?? '' ); ?>" placeholder="763-555-0100">
					</td>
				</tr>
				<tr>
					<th scope="row">Button label</th>
					<td>
						<input type="text" class="regular-text" name="momentum_chat_settings[button_label]" value="<?php echo esc_attr( $s['button_label'] ?? 'Schedule' ); ?>" placeholder="Schedule">
						<p class="description">Text shown on the pill button (e.g. "Schedule", "Book a Visit", "Chat &amp; Book").</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Panel title</th>
					<td>
						<input type="text" class="regular-text" name="momentum_chat_settings[panel_title]" value="<?php echo esc_attr( $s['panel_title'] ?? 'Scheduling Assistant' ); ?>" placeholder="Scheduling Assistant">
					</td>
				</tr>
				<tr>
					<th scope="row">Avatar image</th>
					<td>
						<input type="hidden" id="momentum_chat_avatar_url" name="momentum_chat_settings[avatar_url]" value="<?php echo esc_attr( $s['avatar_url'] ?? '' ); ?>">
						<div id="momentum_chat_avatar_preview" style="margin-bottom:8px;">
							<?php if ( ! empty( $s['avatar_url'] ) ) : ?>
								<img src="<?php echo esc_url( $s['avatar_url'] ); ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
							<?php else : ?>
								<div style="width:64px;height:64px;border-radius:50%;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;">No image</div>
							<?php endif; ?>
						</div>
						<button type="button" class="button" id="momentum_chat_upload_btn">Upload / choose image</button>
						<button type="button" class="button" id="momentum_chat_remove_btn" <?php echo empty( $s['avatar_url'] ) ? 'style="display:none;"' : ''; ?>>Remove</button>
						<p class="description">Shown in the chat panel header. A square photo works best (it will be cropped to a circle). If blank, the bot uses an "M" monogram.</p>
						<script>
						jQuery(function($){
							var frame;
							$('#momentum_chat_upload_btn').on('click', function(e){
								e.preventDefault();
								if (frame) { frame.open(); return; }
								frame = wp.media({
									title: 'Choose chat avatar',
									button: { text: 'Use this image' },
									library: { type: 'image' },
									multiple: false
								});
								frame.on('select', function(){
									var att = frame.state().get('selection').first().toJSON();
									$('#momentum_chat_avatar_url').val(att.url);
									$('#momentum_chat_avatar_preview').html('<img src="'+att.url+'" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">');
									$('#momentum_chat_remove_btn').show();
								});
								frame.open();
							});
							$('#momentum_chat_remove_btn').on('click', function(e){
								e.preventDefault();
								$('#momentum_chat_avatar_url').val('');
								$('#momentum_chat_avatar_preview').html('<div style="width:64px;height:64px;border-radius:50%;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;">No image</div>');
								$(this).hide();
							});
						});
						</script>
					</td>
				</tr>
				<tr>
					<th scope="row">Greeting message</th>
					<td>
						<textarea name="momentum_chat_settings[greeting]" rows="2" class="large-text"><?php echo esc_textarea( $s['greeting'] ?? '' ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">Quick reply buttons</th>
					<td>
						<textarea name="momentum_chat_settings[quick_replies]" rows="5" class="large-text code" placeholder="Book a free consult | I'd like to book a free consult&#10;Existing patient | I'm an existing patient&#10;What do you offer? | What services do you offer?&#10;Hours &amp; location | What are your hours and location?"><?php echo esc_textarea( $s['quick_replies'] ?? "Book a free consult | I'd like to book a free consult\nExisting patient | I'm an existing patient\nWhat do you offer? | What services do you offer?\nHours & location | What are your hours and location?" ); ?></textarea>
						<p class="description">Shown as tappable chips under the greeting so visitors don't have to type. One per line, format: <code>Button label | Message that gets sent</code>. Leave blank to hide.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Attention pop-out</th>
					<td>
						<input type="text" class="regular-text" name="momentum_chat_settings[popout_text]" value="<?php echo esc_attr( $s['popout_text'] ?? '👋 Free 15-min consult available!' ); ?>">
						<p class="description">Short message that pops up next to the bubble after a delay. Leave blank to disable.</p>
						<br>
						<label>Show after <input type="number" min="0" max="120" name="momentum_chat_settings[popout_delay]" value="<?php echo esc_attr( $s['popout_delay'] ?? 8 ); ?>" style="width:70px;"> seconds</label>
						<p class="description">Pop-out appears once per browser session, then auto-dismisses. Recommended 5–15 seconds.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Practice info (the bot's knowledge)</th>
					<td>
						<textarea name="momentum_chat_settings[practice_info]" rows="18" class="large-text code"><?php echo esc_textarea( $s['practice_info'] ?? momentum_chat_default_practice_info() ); ?></textarea>
						<p class="description">Everything the bot is allowed to state as fact. Use the section headers (HOURS, ADDRESS, SERVICES, etc.) — the bot is instructed to only answer from this content and to direct visitors to a free consult if their question isn't covered here. Keep it accurate; don't include anything you wouldn't put on your website.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">System prompt (advanced)</th>
					<td>
						<textarea name="momentum_chat_settings[system_prompt]" rows="10" class="large-text code"><?php echo esc_textarea( $s['system_prompt'] ?? '' ); ?></textarea>
						<p class="description">Leave blank to use the default. The default already includes practice info, scheduling rules, and medical disclaimers.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
