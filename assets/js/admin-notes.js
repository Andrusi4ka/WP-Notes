(function ($, window) {
	var App = window.WPNotesAdminApp = window.WPNotesAdminApp || {};

	function getImageModal() {
		var modalElement = $('.wp-notes-image-modal');

		if (modalElement.length) {
			return modalElement;
		}

		modalElement = $(
			'<div class="wp-notes-image-modal" hidden>' +
				'<div class="wp-notes-image-modal__backdrop" data-wp-notes-image-close></div>' +
				'<div class="wp-notes-image-modal__dialog" role="dialog" aria-modal="true">' +
					'<button type="button" class="wp-notes-image-modal__close" data-wp-notes-image-close aria-label="Close">' +
						'<img src="' + wpNotesAdmin.closeIconUrl + '" alt="">' +
					'</button>' +
					'<img class="wp-notes-image-modal__image" src="" alt="">' +
				'</div>' +
			'</div>'
		);

		$('body').append(modalElement);
		return modalElement;
	}

	function ensureNotesWrap() {
		var wrap = $('[data-wp-notes-wrap]').first();

		if (wrap.length) {
			return wrap;
		}

		wrap = $('<div class="wp-notes-wrap" data-wp-notes-wrap="1"></div>');
		$('.wrap').first().before(wrap);
		return wrap;
	}

	function setCardExpanded(card, expanded) {
		var toggle = card.find('.wp-notes-toggle-note').first();
		var icon = toggle.find('img').first();

		card.toggleClass('is-open', expanded);
		card.toggleClass('is-collapsed', !expanded);
		toggle.attr('aria-expanded', expanded ? 'true' : 'false');

		if (icon.length) {
			icon.attr('src', expanded ? icon.data('icon-expanded') : icon.data('icon-collapsed'));
		}
	}

	function collapseOpenCards(exceptCard) {
		$('.wp-notes-notice-card.is-open').each(function () {
			var card = $(this);

			if (exceptCard && card.is(exceptCard)) {
				return;
			}

			setCardExpanded(card, false);
		});
	}

	function deleteNote(noteId) {
		return $.post(wpNotesAdmin.ajaxUrl, {
			action: 'wp_notes_delete_note',
			nonce: wpNotesAdmin.deleteNonce,
			note_id: noteId
		});
	}

	function flashNotice(message, type) {
		var wrap = $('.wrap').first();

		if (!wrap.length) {
			window.alert(message);
			return;
		}

		wrap.find('.wp-notes-notice.is-ajax').remove();
		wrap.prepend('<div class="notice notice-' + (type || 'success') + ' is-dismissible wp-notes-notice is-ajax"><p>' + message + '</p></div>');
	}

	App.openImageModal = function (src, alt) {
		var imageModal = getImageModal();
		var image = imageModal.find('.wp-notes-image-modal__image');

		image.attr('src', src || '');
		image.attr('alt', alt || '');
		imageModal.removeAttr('hidden').addClass('is-open');
		$('body').addClass('wp-notes-modal-open');
	};

	App.closeImageModal = function () {
		var imageModal = $('.wp-notes-image-modal');

		if (!imageModal.length) {
			return;
		}

		imageModal.attr('hidden', true).removeClass('is-open');
		imageModal.find('.wp-notes-image-modal__image').attr('src', '');

		if (!App.getModal().hasClass('is-open')) {
			$('body').removeClass('wp-notes-modal-open');
		}
	};

	App.upsertCard = function (response) {
		var wrap;
		var existing;
		var duplicateSelector;
		var shouldRender = response.scope === 'global' || response.screenId === wpNotesAdmin.currentScreenId;

		if (!response.cardHtml || !shouldRender) {
			return;
		}

		wrap = ensureNotesWrap();
		existing = $('.wp-notes-notice-card[data-note-id="' + response.noteId + '"]');
		duplicateSelector = '.wp-notes-notice-card[data-note-scope="' + response.scope + '"]';

		if (response.scope !== 'global' && response.screenId) {
			duplicateSelector += '[data-note-screen-id="' + response.screenId + '"]';
		}

		if (existing.length) {
			existing.not(':first').remove();
			existing.first().replaceWith(response.cardHtml);
			App.highlightRenderedCodeBlocks(document);
			return;
		}

		$(duplicateSelector).remove();

		if (response.scope === 'global') {
			wrap.prepend(response.cardHtml);
			App.highlightRenderedCodeBlocks(document);
			return;
		}

		wrap.append(response.cardHtml);
		App.highlightRenderedCodeBlocks(document);
	};

	App.upsertRow = function (response) {
		var table = $('[data-wp-notes-table]');
		var tbody;
		var row;
		var emptyState;

		if (!table.length || !response.rowHtml) {
			return;
		}

		tbody = table.find('tbody');
		row = tbody.find('[data-note-row-id="' + response.noteId + '"]');
		emptyState = $('[data-wp-notes-empty]');

		if (row.length) {
			row.replaceWith(response.rowHtml);
		} else {
			tbody.prepend(response.rowHtml);
		}

		table.show();
		emptyState.remove();
	};

	App.removeNoteUi = function (noteId) {
		var table = $('[data-wp-notes-table]');
		var wrap = $('[data-wp-notes-wrap]').first();
		var tbody;

		$('.wp-notes-notice-card[data-note-id="' + noteId + '"]').remove();
		$('[data-note-row-id="' + noteId + '"]').remove();

		if (wrap.length && !wrap.children().length) {
			wrap.empty();
		}

		if (!table.length) {
			return;
		}

		tbody = table.find('tbody');
		if (!tbody.children().length) {
			table.hide();
			table.before('<p class="wp-notes-empty" data-wp-notes-empty="1">' + wpNotesAdmin.emptyNotes + '</p>');
		}
	};

	App.bindNoteEvents = function () {
		$(document).on('click', '.wp-notes-toggle-note', function (event) {
			var card = $(this).closest('.wp-notes-notice-card');
			var isOpen = card.hasClass('is-open');

			event.preventDefault();
			event.stopPropagation();
			collapseOpenCards(card);
			setCardExpanded(card, !isOpen);
		});

		$(document).on('click', '.wp-notes-notice-card', function (event) {
			var card = $(this);

			if ($(event.target).closest('.wp-notes-notice-card__actions').length) {
				return;
			}

			if (card.hasClass('is-collapsed')) {
				collapseOpenCards(card);
				setCardExpanded(card, true);
			}
		});

		$(document).on('click', function (event) {
			if ($(event.target).closest('.wp-notes-notice-card').length || $(event.target).closest('[data-wp-notes-modal]').length) {
				return;
			}

			collapseOpenCards();
		});

		$(document).on('click', '.wp-notes-notice-card__body img', function (event) {
			if ($(this).closest('.wp-notes-code-copy').length) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			App.openImageModal($(this).attr('src'), $(this).attr('alt'));
		});

		$(document).on('click', '[data-wp-notes-image-close]', function () {
			App.closeImageModal();
		});

		$(document).on('click', '.wp-notes-delete-note', function (event) {
			var link = $(this);
			var noteId = link.data('note-id');
			var message = link.data('confirm') || wpNotesAdmin.deleteConfirm;

			event.preventDefault();
			if (!window.confirm(message)) {
				return;
			}

			deleteNote(noteId).done(function (response) {
				if (!response || !response.success || !response.data) {
					window.alert(wpNotesAdmin.deleteError);
					return;
				}

				App.removeNoteUi(response.data.noteId);
				flashNotice(response.data.message || wpNotesAdmin.deleteError, 'success');
				App.closeModal();
			}).fail(function (xhr) {
				var errorMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : wpNotesAdmin.deleteError;
				window.alert(errorMessage);
			});
		});

		$(document).on('click', '.wp-notes-code-copy', function (event) {
			event.preventDefault();
			event.stopPropagation();
			App.copyCodeFromButton(this);
		});

		$(document).on('keydown', function (event) {
			if (event.key === 'Escape' && App.getModal().hasClass('is-open')) {
				App.closeModal();
			}

			if (event.key === 'Escape' && $('.wp-notes-image-modal').hasClass('is-open')) {
				App.closeImageModal();
			}
		});
	};
}(jQuery, window));
