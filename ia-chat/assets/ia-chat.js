/* Gestiona la apertura del widget y el intercambio de mensajes con WordPress. */
(function () {
	'use strict';

	document.querySelectorAll('.iac-chat').forEach(function (widget) {
		const panel = widget.querySelector('.iac-chat-panel');
		const toggle = widget.querySelector('.iac-chat-toggle');
		const close = widget.querySelector('.iac-chat-close');
		const form = widget.querySelector('.iac-chat-form');
		const input = widget.querySelector('textarea');
		const messages = widget.querySelector('.iac-chat-messages');
		const history = [];
		const config = {
			ajaxUrl: widget.dataset.ajaxUrl,
			nonce: widget.dataset.nonce,
			strings: {
				open: 'Abrir chat con Claude',
				close: 'Cerrar chat',
				waiting: 'Claude está escribiendo…',
				error: 'No se pudo enviar el mensaje. Inténtalo de nuevo.'
			}
		};

		function setOpen(open) {
			panel.hidden = !open;
			toggle.setAttribute('aria-expanded', String(open));
			toggle.setAttribute('aria-label', open ? config.strings.close : config.strings.open);
			if (open) {
				input.focus();
			}
		}

		function addMessage(content, role, extraClass) {
			const node = document.createElement('div');
			node.className = 'iac-chat-message iac-chat-message--' + role + (extraClass ? ' ' + extraClass : '');
			node.textContent = content;
			messages.appendChild(node);
			messages.scrollTop = messages.scrollHeight;
			return node;
		}

		toggle.addEventListener('click', function () {
			setOpen(panel.hidden);
		});
		close.addEventListener('click', function () {
			setOpen(false);
			toggle.focus();
		});

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			const message = input.value.trim();
			if (!message || form.dataset.busy === 'true') {
				return;
			}

			input.value = '';
			addMessage(message, 'user');
			form.dataset.busy = 'true';
			const waiting = addMessage(config.strings.waiting, 'assistant', 'iac-chat-message--waiting');

			const body = new URLSearchParams({
				action: 'iac_chat_message',
				nonce: config.nonce,
				message: message,
				history: JSON.stringify(history)
			});

			fetch(config.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			})
				.then(function (response) {
					return response.json().then(function (data) {
						if (!response.ok || !data.success) {
							throw new Error(data.data && data.data.message ? data.data.message : config.strings.error);
						}
						return data.data.message;
					});
				})
				.then(function (answer) {
					waiting.textContent = answer;
					waiting.classList.remove('iac-chat-message--waiting');
					history.push({ role: 'user', content: message }, { role: 'assistant', content: answer });
					if (history.length > 20) {
						history.splice(0, history.length - 20);
					}
				})
				.catch(function (error) {
					waiting.textContent = error.message || config.strings.error;
					waiting.classList.add('iac-chat-message--error');
				})
				.finally(function () {
					form.dataset.busy = 'false';
					input.focus();
				});
		});
	});
})();
