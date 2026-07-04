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

	function requiresAttendees(form) {
		return form.dataset.requiresAttendees === '1';
	}

	function formQuestions(form) {
		var tag = form.querySelector('[data-atx-questions]');
		if (!tag) {
			return [];
		}
		try {
			var parsed = JSON.parse(tag.textContent);
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function questionFieldHtml(question, fieldName) {
		var required = question.is_required ? ' required' : '';
		var label = question.label + (question.is_required ? ' *' : '');
		var options = Array.isArray(question.options) ? question.options : [];
		var html = '<div class="atx-field atx-field--attendee-q">';

		if (question.type === 'checkbox') {
			html += '<label><input type="checkbox" name="' + fieldName + '" value="1"' + required + '> ' + label + '</label>';
		} else if (question.type === 'textarea') {
			html += '<label>' + label + '</label><textarea rows="2" name="' + fieldName + '"' + required + '></textarea>';
		} else if (question.type === 'select') {
			html += '<label>' + label + '</label><select name="' + fieldName + '"' + required + '><option value="">…</option>';
			options.forEach(function (option) {
				html += '<option value="' + String(option).replace(/"/g, '&quot;') + '">' + option + '</option>';
			});
			html += '</select>';
		} else if (question.type === 'radio') {
			html += '<span class="atx-field__label">' + label + '</span>';
			options.forEach(function (option, index) {
				html += '<label class="atx-field__radio"><input type="radio" name="' + fieldName + '" value="'
					+ String(option).replace(/"/g, '&quot;') + '"' + (question.is_required && index === 0 ? ' required' : '') + '> ' + option + '</label>';
			});
		} else {
			html += '<label>' + label + '</label><input type="text" name="' + fieldName + '"' + required + '>';
		}

		return html + '</div>';
	}

	function renderAttendeeFields(form) {
		var wrap = form.querySelector('[data-attendee-fields]');
		var list = form.querySelector('.atx-attendees');
		if (!wrap || !list || !requiresAttendees(form)) {
			return;
		}

		// Preserve anything already typed before re-rendering.
		var previous = {};
		list.querySelectorAll('input, select, textarea').forEach(function (input) {
			if (input.type === 'checkbox') {
				if (input.checked) {
					previous[input.name] = '__checked__';
				}
			} else if (input.type === 'radio') {
				if (input.checked) {
					previous[input.name] = input.value;
				}
			} else {
				previous[input.name] = input.value;
			}
		});

		list.innerHTML = '';
		var total = 0;

		form.querySelectorAll('input[data-ticket-type]').forEach(function (qtyInput) {
			var qty = parseInt(qtyInput.value, 10) || 0;
			var typeId = qtyInput.dataset.ticketType;
			var typeName = qtyInput.dataset.ticketName || 'Ticket';

			for (var unit = 0; unit < qty; unit++) {
				total++;
				var nameField = 'attendee_name[' + typeId + '][' + unit + ']';
				var emailField = 'attendee_email[' + typeId + '][' + unit + ']';

				var questionsHtml = '';
				formQuestions(form).forEach(function (question) {
					var scope = Array.isArray(question.ticket_type_ids)
						? question.ticket_type_ids
						: (question.ticket_type_id ? [question.ticket_type_id] : []);

					if (scope.length && scope.map(String).indexOf(String(typeId)) === -1) {
						return;
					}
					questionsHtml += questionFieldHtml(question, 'attendee_q[' + typeId + '][' + unit + '][' + question.id + ']');
				});

				var row = document.createElement('div');
				row.className = 'atx-attendee-row';
				row.innerHTML =
					'<span class="atx-attendee-row__label">' + typeName + ' — ticket ' + (unit + 1) + '</span>' +
					'<input type="text" name="' + nameField + '" placeholder="Full name *" required autocomplete="off">' +
					'<input type="email" name="' + emailField + '" placeholder="Email (optional)" autocomplete="off">' +
					(questionsHtml ? '<div class="atx-attendee-row__questions">' + questionsHtml + '</div>' : '');
				list.appendChild(row);

				row.querySelectorAll('input, select, textarea').forEach(function (input) {
					if (previous[input.name] === undefined) {
						return;
					}
					if (input.type === 'checkbox' || input.type === 'radio') {
						input.checked = previous[input.name] === input.value || previous[input.name] === '__checked__';
					} else {
						input.value = previous[input.name];
					}
				});
			}
		});

		wrap.hidden = total === 0;
	}

	function collectAttendees(form) {
		var attendees = [];

		form.querySelectorAll('.atx-attendees input[type="text"]').forEach(function (nameInput) {
			var match = nameInput.name.match(/^attendee_name\[(\d+)\]\[(\d+)\]$/);
			if (!match) {
				return;
			}

			var emailInput = form.querySelector('[name="attendee_email[' + match[1] + '][' + match[2] + ']"]');
			var attendee = {
				ticket_type_id: parseInt(match[1], 10),
				name: nameInput.value.trim()
			};

			if (emailInput && emailInput.value.trim() !== '') {
				attendee.email = emailInput.value.trim();
			}

			var answers = {};
			var prefix = 'attendee_q[' + match[1] + '][' + match[2] + ']';
			form.querySelectorAll('[name^="attendee_q[' + match[1] + '][' + match[2] + ']"]').forEach(function (field) {
				var qMatch = field.name.slice(prefix.length).match(/^\[(\d+)\]$/);
				if (!qMatch) {
					return;
				}
				if (field.type === 'checkbox') {
					answers[qMatch[1]] = field.checked ? '1' : '0';
				} else if (field.type === 'radio') {
					if (field.checked) {
						answers[qMatch[1]] = field.value;
					}
				} else if (field.value !== '') {
					answers[qMatch[1]] = field.value;
				}
			});
			if (Object.keys(answers).length) {
				attendee.answers = answers;
			}

			attendees.push(attendee);
		});

		return attendees;
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
			attendees: collectAttendees(form),
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

		if (requiresAttendees(form) && payload.attendees.some(function (a) { return a.name === ''; })) {
			showErrors(form, ['Please enter a name for every ticket.']);
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
					var errorKeys = Object.keys(result.data.errors);

					// Stale sync fallback: if Laravel demands attendee names but
					// this page's payload predates the setting, switch the form
					// into named mode on the spot so the fields appear.
					if (errorKeys.some(function (key) { return key.indexOf('attendees') === 0; })) {
						form.dataset.requiresAttendees = '1';
						renderAttendeeFields(form);
					}

					errorKeys.forEach(function (key) {
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

	// Rebuild the per-ticket attendee fields whenever a quantity changes.
	document.addEventListener('input', function (event) {
		if (event.target && event.target.matches('input[data-ticket-type]')) {
			var form = event.target.closest('.atx-ticket-form');
			if (form) {
				renderAttendeeFields(form);
			}
		}
	});
	document.addEventListener('change', function (event) {
		if (event.target && event.target.matches('input[data-ticket-type]')) {
			var form = event.target.closest('.atx-ticket-form');
			if (form) {
				renderAttendeeFields(form);
			}
		}
	});

	function initAttendeeFields() {
		document.querySelectorAll('.atx-ticket-form').forEach(renderAttendeeFields);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAttendeeFields);
	} else {
		initAttendeeFields();
	}
})();
