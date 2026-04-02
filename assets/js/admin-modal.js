(function ($, window) {
	var App = window.WPNotesAdminApp = window.WPNotesAdminApp || {};

	function parseLink(url) {
		var parsed;
		var params;
		var data = {};

		try {
			parsed = new URL(url, window.location.origin);
			params = parsed.searchParams;
			data.note_id = params.get('note_id') || '';
			data.scope = params.get('scope') || '';
			data.screen_id = params.get('screen_id') || '';
			data.return_url = params.get('return_url') || window.location.href;
			data.page_url = params.get('page_url') || '';
			data.page_title = params.get('page_title') || document.title;
		} catch (error) {
			data.return_url = window.location.href;
			data.page_title = document.title;
		}

		return data;
	}

	function loadForm(url) {
		var requestData = parseLink(url);

		requestData.action = 'wp_notes_get_note_form';
		requestData.nonce = wpNotesAdmin.formNonce;

		App.showModal(wpNotesAdmin.loadingText, '<div class="wp-notes-modal__loading">' + wpNotesAdmin.loadingText + '</div>');

		$.get(wpNotesAdmin.ajaxUrl, requestData).done(function (response) {
			if (!response || !response.success || !response.data || !response.data.html) {
				window.alert(wpNotesAdmin.genericError);
				App.closeModal();
				return;
			}

			App.showModal(response.data.modalTitle || wpNotesAdmin.modalTitleAdd, response.data.html);
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : wpNotesAdmin.genericError;
			window.alert(message);
			App.closeModal();
		});
	}

	function showFormMessage(form, message, type) {
		var container = $(form).find('[data-wp-notes-form-messages]');
		container.html('<div class="notice notice-' + (type || 'error') + ' inline"><p>' + message + '</p></div>');
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

	function serializeForm(form) {
		App.syncEditorToInput(form);

		var data = $(form).serializeArray();
		data.push({ name: 'action', value: 'wp_notes_save_note' });
		data.push({ name: 'nonce', value: wpNotesAdmin.saveNonce });

		return $.param(data);
	}

	function isNoteModalLink(link) {
		var href = $(link).attr('href') || '';
		return href.indexOf('page=wp-notes-add') !== -1 || href.indexOf('page=wp-notes-edit') !== -1 || $(link).hasClass('wp-notes-open-modal');
	}

	App.showModal = function (title, html) {
		var modal = App.getModal();
		var modalContent = App.getModalContent();
		var modalTitle = App.getModalTitle();

		modalTitle.text(title || '');
		modalContent.html(html);
		modal.removeAttr('hidden').addClass('is-open');
		$('body').addClass('wp-notes-modal-open');
		App.initRichEditors(modalContent);
	};

	App.closeModal = function () {
		var modal = App.getModal();
		var modalContent = App.getModalContent();

		modal.attr('hidden', true).removeClass('is-open');
		modalContent.empty();
		$('body').removeClass('wp-notes-modal-open');
	};

	App.bindModalEvents = function () {
		$(document).on('click', 'a', function (event) {
			if (!isNoteModalLink(this)) {
				return;
			}

			if ($(this).closest('.wp-notes-admin-bar__item.is-disabled').length) {
				return;
			}

			event.preventDefault();
			loadForm($(this).attr('href'));
		});

		$(document).on('click', '[data-wp-notes-close]', function () {
			App.closeModal();
		});

		$(document).on('submit', '.wp-notes-form.is-modal', function (event) {
			var form = this;
			var submitButton = $(form).find('[data-wp-notes-submit]');
			var originalLabel = submitButton.data('label') || submitButton.text();

			event.preventDefault();
			submitButton.prop('disabled', true).text(wpNotesAdmin.savingText);

			$.post(wpNotesAdmin.ajaxUrl, serializeForm(form)).done(function (response) {
				if (!response || !response.success || !response.data) {
					showFormMessage(form, wpNotesAdmin.genericError, 'error');
					return;
				}

				App.upsertCard(response.data);
				App.upsertRow(response.data);
				flashNotice(response.data.message || wpNotesAdmin.genericError, 'success');
				App.closeModal();
			}).fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : wpNotesAdmin.genericError;
				showFormMessage(form, message, 'error');
			}).always(function () {
				submitButton.prop('disabled', false).text(originalLabel);
			});
		});

		$(document).on('submit', '.wp-notes-form:not(.is-modal)', function () {
			App.syncEditorToInput(this);
		});

		$(document).on('change', '.wp-notes-upload-input', function () {
			var input = this;
			var file = input.files && input.files[0];
			var form = $(input).closest('.wp-notes-form');

			if (!file) {
				return;
			}

			App.uploadImageFile(form, file);
		});
	};
}(jQuery, window));
