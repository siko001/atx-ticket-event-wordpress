/**
 * Ticket form: collects the selection, posts it to the WordPress checkout
 * proxy, and redirects the visitor to the returned Stripe Checkout URL.
 * Vanilla JS — no jQuery dependency.
 */
(function () {
	'use strict';

	function showErrors(form, messages) {
		var box = form.querySelector('.atx-ticket-form__errors');
		if (!box) {
			return;
		}
		box.innerHTML = '';
		messages.forEach(function (message) {
			var p = document.createElement('p');
			p.textContent = message;
			box.appendChild(p);
		});
		box.hidden = messages.length === 0;
		if (messages.length) {
			box.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	function collectPayload(form) {
		var items = [];
		form.querySelectorAll('input[data-ticket-type]').forEach(function (input) {
			var qty = parseInt(input.value, 10) || 0;
			if (qty > 0) {
				items.push({
					ticket_type_id: parseInt(input.dataset.ticketType, 10),
					quantity: qty
				});
			}
		});

		var answers = {};
		form.querySelectorAll('[name^="answer["]').forEach(function (field) {
			var match = field.name.match(/^answer\[(\d+)\]$/);
			if (!match) {
				return;
			}
			var id = match[1];
			if (field.type === 'checkbox') {
				answers[id] = field.checked ? '1' : '0';
			} else if (field.type === 'radio') {
				if (field.checked) {
					answers[id] = field.value;
				}
			} else if (field.value !== '') {
				answers[id] = field.value;
			}
		});

		var occurrenceField = form.querySelector('[name="occurrence_id"]');

		return {
			event_id: parseInt(form.dataset.eventId, 10),
			occurrence_id: occurrenceField ? parseInt(occurrenceField.value, 10) : 0,
			items: items,
			purchaser: {
				name: (form.querySelector('[name="purchaser_name"]') || {}).value || '',
				email: (form.querySelector('[name="purchaser_email"]') || {}).value || ''
			},
			answers: answers,
			discount_code: (form.querySelector('[name="discount_code"]') || {}).value || ''
		};
	}

	function handleSubmit(event) {
		event.preventDefault();

		var form = event.target;
		var payload = collectPayload(form);
		var button = form.querySelector('.atx-button--buy');
		var busy = form.querySelector('.atx-ticket-form__busy');

		if (!payload.items.length) {
			showErrors(form, [form.dataset.msgNoTickets || 'Please choose at least one ticket.']);
			return;
		}

		showErrors(form, []);
		if (button) {
			button.disabled = true;
		}
		if (busy) {
			busy.hidden = false;
		}

		window.fetch(window.atxTicketing.checkoutEndpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.atxTicketing.nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify(payload)
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { status: response.status, data: data };
				});
			})
			.then(function (result) {
				if (result.status === 201 && result.data.checkout_url) {
					window.location.assign(result.data.checkout_url);
					return;
				}

				var messages = [];
				if (result.data && result.data.errors) {
					Object.keys(result.data.errors).forEach(function (key) {
						result.data.errors[key].forEach(function (message) {
							messages.push(message);
						});
					});
				}
				if (!messages.length) {
					messages.push((result.data && result.data.message) || 'Something went wrong. Please try again.');
				}
				showErrors(form, messages);
			})
			.catch(function () {
				showErrors(form, ['Network error — please check your connection and try again.']);
			})
			.finally(function () {
				if (button) {
					button.disabled = false;
				}
				if (busy) {
					busy.hidden = true;
				}
			});
	}

	document.addEventListener('submit', function (event) {
		if (event.target && event.target.classList.contains('atx-ticket-form')) {
			handleSubmit(event);
		}
	});
})();
