(function () {
	'use strict';

	var state = {
		open: false,
		busy: false,
		messages: []
	};

	var root, log, input, sendBtn, bubble, typingEl;

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'class') node.className = attrs[k];
				else if (k === 'text') node.textContent = attrs[k];
				else if (k === 'html') node.innerHTML = attrs[k];
				else node.setAttribute(k, attrs[k]);
			});
		}
		(children || []).forEach(function (c) { node.appendChild(c); });
		return node;
	}

	function render() {
		bubble = el('button', { class: 'mchat-bubble', 'aria-label': 'Open scheduling assistant' });
		var iconOpen = '<svg class="mchat-bubble-icon-open" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
		var iconClose = '<svg class="mchat-bubble-icon-close" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
		bubble.innerHTML = iconOpen + '<span class="mchat-bubble-label">' + (MomentumChat.buttonLabel || 'Schedule') + '</span>' + iconClose;
		bubble.addEventListener('click', toggle);

		root = el('div', { class: 'mchat-panel', 'aria-hidden': 'true', role: 'dialog', 'aria-label': 'Chat with Momentum Health' });

		// Header with avatar
		var header = el('div', { class: 'mchat-header' });
		var avatar = el('div', { class: 'mchat-avatar' });
		if (MomentumChat.avatarUrl) {
			var img = el('img', { src: MomentumChat.avatarUrl, alt: '', class: 'mchat-avatar-img' });
			avatar.appendChild(img);
		} else {
			avatar.textContent = 'M';
		}
		var headerText = el('div', { class: 'mchat-header-text' });
		headerText.appendChild(el('div', { class: 'mchat-title', text: MomentumChat.panelTitle || 'Scheduling Assistant' }));
		headerText.appendChild(el('div', { class: 'mchat-subtitle', text: 'Usually replies instantly' }));
		var close = el('button', { class: 'mchat-close', 'aria-label': 'Close chat', text: '×' });
		close.addEventListener('click', toggle);
		header.appendChild(avatar);
		header.appendChild(headerText);
		header.appendChild(close);

		var disclaimer = el('div', { class: 'mchat-disclaimer', text: MomentumChat.disclaimer });
		log = el('div', { class: 'mchat-log' });

		var form = el('form', { class: 'mchat-form' });
		input = el('input', { type: 'text', class: 'mchat-input', placeholder: 'Type a message…', autocomplete: 'off', 'aria-label': 'Message' });
		sendBtn = el('button', { type: 'submit', class: 'mchat-send', 'aria-label': 'Send message' });
		sendBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
		form.appendChild(input);
		form.appendChild(sendBtn);
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			send();
		});

		root.appendChild(header);
		root.appendChild(disclaimer);
		root.appendChild(log);
		root.appendChild(form);

		document.body.appendChild(bubble);
		document.body.appendChild(root);

		addMessage('assistant', MomentumChat.greeting);
	}

	function toggle() {
		state.open = !state.open;
		root.setAttribute('aria-hidden', state.open ? 'false' : 'true');
		root.classList.toggle('mchat-open', state.open);
		bubble.classList.toggle('mchat-bubble-open', state.open);
		if (state.open) setTimeout(function () { input.focus(); }, 220);
	}

	function escapeHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function renderText(text) {
		var out = escapeHtml(text);
		// Markdown links: [label](url)
		out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (_, label, url) {
			return '<a href="' + url + '" target="_blank" rel="noopener">' + label + '</a>';
		});
		// Bare http/https URLs (skip ones already inside an href)
		out = out.replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, function (_, pre, url) {
			return pre + '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>';
		});
		return out;
	}

	function addMessage(role, text) {
		state.messages.push({ role: role, content: text });
		var msg = el('div', { class: 'mchat-msg mchat-msg-' + role });
		msg.innerHTML = renderText(text);
		log.appendChild(msg);
		scrollLogToBottom();
	}

	function showTyping() {
		if (typingEl) return;
		typingEl = el('div', { class: 'mchat-typing', 'aria-label': 'Assistant is typing' });
		typingEl.appendChild(el('span'));
		typingEl.appendChild(el('span'));
		typingEl.appendChild(el('span'));
		log.appendChild(typingEl);
		scrollLogToBottom();
	}

	function hideTyping() {
		if (typingEl && typingEl.parentNode) {
			typingEl.parentNode.removeChild(typingEl);
		}
		typingEl = null;
	}

	function scrollLogToBottom() {
		requestAnimationFrame(function () {
			log.scrollTop = log.scrollHeight;
		});
	}

	function addSlotButtons(slots) {
		if (!slots.length) {
			addMessage('assistant', "I don't see any open slots in the next two weeks. The easiest thing is to call the office at " + (MomentumChat.phone || 'the number on our site') + ".");
			return;
		}
		var wrap = el('div', { class: 'mchat-slots' });
		slots.forEach(function (slot) {
			var btn = el('button', { type: 'button', class: 'mchat-slot', text: slot.label });
			btn.addEventListener('click', function () { chooseSlot(slot); });
			wrap.appendChild(btn);
		});
		log.appendChild(wrap);
		scrollLogToBottom();
	}

	function chooseSlot(slot) {
		fetch(MomentumChat.restUrl + 'booking-link', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': MomentumChat.nonce },
			body: JSON.stringify({ starts_at: slot.starts_at })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.url) {
					addMessage('assistant', "Perfect — " + slot.label + ". One last step:");
					var link = el('a', { href: data.url, target: '_blank', rel: 'noopener', class: 'mchat-cta' });
					link.innerHTML = 'Confirm appointment <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
					log.appendChild(link);
					scrollLogToBottom();
				}
			});
	}

	function setBusy(b) {
		state.busy = b;
		sendBtn.disabled = b;
		input.disabled = b;
	}

	function send() {
		var text = input.value.trim();
		if (!text || state.busy) return;
		input.value = '';
		addMessage('user', text);
		setBusy(true);
		showTyping();

		fetch(MomentumChat.restUrl + 'chat', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': MomentumChat.nonce },
			body: JSON.stringify({ messages: state.messages })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				hideTyping();
				if (data.code && data.message) {
					addMessage('assistant', "Sorry — I'm not set up properly yet. (" + data.message + ") Please call the office at " + (MomentumChat.phone || 'the number on our site') + ".");
					setBusy(false);
					return;
				}
				if (data.reply) addMessage('assistant', data.reply);
				if (data.tool && data.tool.action === 'fetch_slots') {
					showTyping();
					fetchSlots();
				} else {
					setBusy(false);
				}
			})
			.catch(function () {
				hideTyping();
				addMessage('assistant', "Sorry — I'm having trouble connecting. Please call the office at " + (MomentumChat.phone || 'the number on our site') + ".");
				setBusy(false);
			});
	}

	function fetchSlots() {
		fetch(MomentumChat.restUrl + 'slots', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': MomentumChat.nonce },
			body: JSON.stringify({})
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				hideTyping();
				if (data.code && data.message) {
					addMessage('assistant', "I can't pull up the calendar right now (" + data.message + "). Please call the office at " + (MomentumChat.phone || 'the number on our site') + " to book.");
				} else if (data.slots) {
					addSlotButtons(data.slots);
				} else {
					addMessage('assistant', "Something went wrong getting the calendar. Please call the office at " + (MomentumChat.phone || 'the number on our site') + ".");
				}
				setBusy(false);
			})
			.catch(function () {
				hideTyping();
				addMessage('assistant', "I'm having trouble reaching the calendar. Please call the office at " + (MomentumChat.phone || 'the number on our site') + ".");
				setBusy(false);
			});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', render);
	} else {
		render();
	}
})();
