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
} );

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
	$s = get_option( 'momentum_chat_settings', [] );
	?>
	<div class="wrap">
		<h1>Momentum Chat Settings</h1>
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
					<th scope="row">New patient booking type ID</th>
					<td>
						<input type="number" name="momentum_chat_settings[tidycal_type_new]" value="<?php echo esc_attr( $s['tidycal_type_new'] ?? '' ); ?>">
						<p class="description">Numeric ID of your "New Patient Consult" booking type in TidyCal.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Returning patient booking type ID</th>
					<td>
						<input type="number" name="momentum_chat_settings[tidycal_type_return]" value="<?php echo esc_attr( $s['tidycal_type_return'] ?? '' ); ?>">
						<p class="description">Numeric ID of your "Adjustment" or returning-patient booking type.</p>
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
