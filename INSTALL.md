# Momentum Chat — Install & Setup

## 1. Upload the plugin

**Option A — WordPress admin (easiest):**
1. Zip the entire `momentum-chat-plugin` folder.
2. WordPress admin → Plugins → Add New → Upload Plugin.
3. Choose the zip, install, activate.

**Option B — FTP/SFTP:**
1. Upload the `momentum-chat-plugin` folder to `wp-content/plugins/` on your server.
2. WordPress admin → Plugins → activate Momentum Chat.

## 2. Get an OpenRouter API key

1. Sign up at https://openrouter.ai
2. Add ~$5 of credit (Qwen 2.5 72B is ~$0.40 per million tokens — that's months of chatting).
3. Generate a key at https://openrouter.ai/keys

## 3. Get a TidyCal API token

1. Log into TidyCal → Account → API.
2. Generate a personal access token.
3. Note the **numeric IDs** of your booking types. You can find these in the URL when editing a booking type, or by calling `https://tidycal.com/api/booking-types` with your token.

## 4. Configure the plugin

WordPress admin → Settings → Momentum Chat. Fill in:
- OpenRouter API key
- Model (default `qwen/qwen-2.5-72b-instruct` is fine — switch to `qwen/qwen-2.5-7b-instruct` for free)
- TidyCal API token
- Base booking URL (e.g. `https://tidycal.com/drtoddanderson`)
- Booking type IDs for new and returning patients
- Office phone
- Check **Enable chat widget**

Save. The blue chat bubble should appear bottom-right on your site.

## 5. Test it

Open your site in an incognito window and test these flows:

- [ ] Bubble appears bottom-right
- [ ] Clicking opens the chat panel with the greeting
- [ ] Ask a basic question ("what are your hours?") — the bot should answer
- [ ] Ask a clinical question ("my back hurts, what should I do?") — the bot should refuse to diagnose and offer to book
- [ ] Say "I want to schedule an appointment" — bot should ask: new vs returning, reason, timing
- [ ] After answering all three, slot buttons should appear
- [ ] Clicking a slot should produce a TidyCal link with date/time pre-filled
- [ ] Following the link should drop you on TidyCal with the slot selected

## Things to know

**API endpoint verification.** The TidyCal endpoints in `includes/api.php` were written from public documentation. If TidyCal has updated their API, you may need to adjust the URLs in `momentum_chat_handle_slots()`. The plugin will return a "scheduling service unavailable" error if so — check your server's PHP error log for details.

**Rate limiting.** Each visitor IP is limited to 10 requests per minute. Adjust in `momentum_chat_rate_limit()` if needed.

**Cost control.** Set a monthly spend cap on OpenRouter (Account → Settings → Limits). $10/month is plenty for most small practice traffic.

**Privacy/HIPAA.** This bot is for scheduling and FAQ only. The system prompt explicitly forbids clinical advice. Chat history is **not stored server-side** — it lives only in the visitor's browser session. Don't change that without talking to a compliance advisor.

**Disabling quickly.** Uncheck "Enable chat widget" in settings — the bubble disappears immediately.
