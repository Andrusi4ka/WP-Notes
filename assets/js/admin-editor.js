(function ($, window) {
	var App = window.WPNotesAdminApp = window.WPNotesAdminApp || {};

	function registerQuillFormats() {
		var ColorAttributor;
		var BackgroundAttributor;
		var FontAttributor;
		var SizeAttributor;

		if (!window.Quill) {
			return;
		}

		ColorAttributor = window.Quill.import('attributors/class/color');
		if (ColorAttributor) {
			ColorAttributor.whitelist = App.COLOR_WHITELIST;
			window.Quill.register(ColorAttributor, true);
		}

		BackgroundAttributor = window.Quill.import('attributors/class/background');
		if (BackgroundAttributor) {
			BackgroundAttributor.whitelist = App.BACKGROUND_WHITELIST;
			window.Quill.register(BackgroundAttributor, true);
		}

		FontAttributor = window.Quill.import('formats/font');
		if (FontAttributor) {
			FontAttributor.whitelist = App.FONT_WHITELIST;
			window.Quill.register(FontAttributor, true);
		}

		SizeAttributor = window.Quill.import('attributors/class/size');
		if (SizeAttributor) {
			SizeAttributor.whitelist = App.SIZE_WHITELIST;
			window.Quill.register(SizeAttributor, true);
		}
	}

	function applyImageLayout(quill, action) {
		var image = $(quill.root).find('img.wp-notes-image-selected').first();

		if (!image.length) {
			return;
		}

		image.removeClass('wp-notes-image-inline wp-notes-image-left wp-notes-image-center wp-notes-image-right');

		switch (action) {
		case 'left':
			image.addClass('wp-notes-image-left');
			break;
		case 'center':
			image.addClass('wp-notes-image-center');
			break;
		case 'right':
			image.addClass('wp-notes-image-right');
			break;
		default:
			image.addClass('wp-notes-image-inline');
			break;
		}
	}

	function injectToolbarControls(root, toolbar) {
		var container = $(toolbar.container);

		if (!container.find('.ql-undo').length) {
			container.append('<span class="ql-formats"><button type="button" class="ql-undo" title="Undo">&#8630;</button><button type="button" class="ql-redo" title="Redo">&#8631;</button></span>');
		}

		if (!container.find('.wp-notes-image-tools').length) {
			container.append('<span class="ql-formats wp-notes-image-tools"><button type="button" class="wp-notes-image-tool" data-image-action="inline" title="Inline image">I</button><button type="button" class="wp-notes-image-tool" data-image-action="left" title="Float left">L</button><button type="button" class="wp-notes-image-tool" data-image-action="center" title="Center image">C</button><button type="button" class="wp-notes-image-tool" data-image-action="right" title="Float right">R</button></span>');
		}

		container.off('click.wpNotesToolbar').on('click.wpNotesToolbar', '.ql-undo, .ql-redo, .wp-notes-image-tool', function (event) {
			var quill = root.data('quillInstance');
			var action = $(this).data('image-action');

			event.preventDefault();
			if (!quill) {
				return;
			}

			if ($(this).hasClass('ql-undo')) {
				quill.history.undo();
			} else if ($(this).hasClass('ql-redo')) {
				quill.history.redo();
			} else if (action) {
				applyImageLayout(quill, action);
			}

			App.syncEditorToInput(root);
		});
	}

	function bindImageSelection(quill) {
		var editorRoot = $(quill.root);

		editorRoot.off('click.wpNotesImage').on('click.wpNotesImage', 'img', function (event) {
			editorRoot.find('img').removeClass('wp-notes-image-selected');
			$(this).addClass('wp-notes-image-selected');
			event.stopPropagation();
		});

		editorRoot.off('click.wpNotesImageClear').on('click.wpNotesImageClear', function (event) {
			if ($(event.target).is('img')) {
				return;
			}

			editorRoot.find('img').removeClass('wp-notes-image-selected');
		});
	}

	function bindImagePaste(root, quill) {
		$(quill.root).off('paste.wpNotesImage').on('paste.wpNotesImage', function (event) {
			var clipboardData = event.originalEvent && event.originalEvent.clipboardData ? event.originalEvent.clipboardData : null;
			var items;
			var index;
			var item;
			var file;

			if (!clipboardData || !clipboardData.items || !clipboardData.items.length) {
				return;
			}

			items = clipboardData.items;

			for (index = 0; index < items.length; index += 1) {
				item = items[index];
				if (!item || !item.type || item.type.indexOf('image/') !== 0) {
					continue;
				}

				file = item.getAsFile ? item.getAsFile() : null;
				if (!file) {
					continue;
				}

				event.preventDefault();
				App.uploadImageFile(root.closest('.wp-notes-form'), file);
				return;
			}
		});
	}

	App.initRichEditors = function (context) {
		$(context || document).find('[data-quill-root]').each(function () {
			var root = $(this);
			var surface;
			var input;
			var quill;
			var toolbar;
			var modules;

			if (root.data('quillReady')) {
				return;
			}

			surface = root.find('[data-quill-editor]').get(0);
			input = root.find('[data-quill-input]');

			if (!surface || !window.Quill) {
				root.addClass('is-fallback');
				input.prop('hidden', false);
				root.data('quillReady', true);
				return;
			}

			registerQuillFormats();

			if (window.ImageResize && !window.Quill.imports['modules/imageResize']) {
				window.Quill.register('modules/imageResize', window.ImageResize);
			}

			modules = {
				toolbar: [
					[{ font: App.FONT_WHITELIST }],
					[{ size: App.SIZE_WHITELIST }],
					[{ header: [1, 2, 3, false] }],
					[{ color: App.COLOR_WHITELIST }, { background: App.BACKGROUND_WHITELIST }],
					['bold', 'italic', 'underline', 'strike'],
					[{ script: 'sub' }, { script: 'super' }],
					[{ list: 'ordered' }, { list: 'bullet' }],
					[{ indent: '-1' }, { indent: '+1' }],
					[{ direction: 'rtl' }],
					['blockquote', 'code-block', 'link', 'image'],
					[{ align: ['', 'center', 'right', 'justify'] }],
					['clean']
				]
			};

			if (window.ImageResize) {
				modules.imageResize = {};
			}

			quill = new window.Quill(surface, {
				theme: 'snow',
				modules: modules
			});

			quill.on('text-change', function () {
				App.syncEditorToInput(root);
			});

			root.data('quillInstance', quill);
			root.data('quillReady', true);
			toolbar = quill.getModule('toolbar');

			if (toolbar) {
				injectToolbarControls(root, toolbar);
				toolbar.addHandler('image', function () {
					root.closest('.wp-notes-form').find('.wp-notes-upload-input').trigger('click');
				});
			}

			bindImageSelection(quill);
			bindImagePaste(root, quill);
			App.syncEditorToInput(root);
		});
	};

	App.uploadImageFile = function (form, file) {
		var formData;
		var editor = App.getEditorState(form);
		var toolbarButton = $(form).find('.ql-image');

		if (!file) {
			return;
		}

		formData = new FormData();
		formData.append('action', 'wp_notes_upload_image');
		formData.append('nonce', wpNotesAdmin.uploadNonce);
		formData.append('file', file);

		$.ajax({
			url: wpNotesAdmin.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			beforeSend: function () {
				toolbarButton.prop('disabled', true);
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data || !response.data.url) {
				window.alert(wpNotesAdmin.uploadError);
				return;
			}

			if (editor && editor.quill) {
				var range = editor.quill.getSelection(true);
				var index = range ? range.index : editor.quill.getLength();
				editor.quill.insertEmbed(index, 'image', response.data.url, 'user');
				editor.quill.setSelection(index + 1, 0, 'silent');
				App.syncEditorToInput(form);
			} else {
				App.insertIntoEditor(form, '<p><img src="' + response.data.url + '" alt=""></p>');
			}
		}).fail(function () {
			window.alert(wpNotesAdmin.uploadError);
		}).always(function () {
			toolbarButton.prop('disabled', false);
			$(form).find('.wp-notes-upload-input').val('');
		});
	};
}(jQuery, window));
