(function ($, window) {
	var App = window.WPNotesAdminApp = window.WPNotesAdminApp || {};
	var modal = null;
	var modalContent = null;
	var modalTitle = null;

	App.FONT_WHITELIST = ['sans', 'serif', 'mono', 'code'];
	App.SIZE_WHITELIST = ['small', false, 'large', 'huge'];
	App.COLOR_WHITELIST = ['white', 'black', 'gray', 'red', 'orange', 'yellow', 'green', 'teal', 'blue', 'indigo', 'purple', 'pink'];
	App.BACKGROUND_WHITELIST = ['black', 'gray', 'red', 'orange', 'yellow', 'green', 'teal', 'blue', 'indigo', 'purple', 'pink'];

	App.getModal = function () {
		if (!modal) {
			modal = $('[data-wp-notes-modal]');
			modalContent = modal.find('[data-wp-notes-modal-content]');
			modalTitle = modal.find('.wp-notes-modal__title');
		}

		return modal;
	};

	App.getModalContent = function () {
		App.getModal();
		return modalContent;
	};

	App.getModalTitle = function () {
		App.getModal();
		return modalTitle;
	};

	App.getEditorState = function (context) {
		var root = $(context).find('[data-quill-root]').first();
		var input = root.find('[data-quill-input]');
		var surface = root.find('[data-quill-editor]');

		if (!root.length || !input.length || !surface.length) {
			return null;
		}

		return {
			root: root,
			input: input,
			surface: surface,
			quill: root.data('quillInstance') || null
		};
	};

	App.syncEditorToInput = function (context) {
		var editor = App.getEditorState(context);
		var html;

		if (!editor) {
			return;
		}

		if (editor.quill) {
			html = editor.quill.root.innerHTML;
			if (html === '<p><br></p>') {
				html = '';
			}
			editor.input.val(html);
			return;
		}

		editor.input.val(editor.surface.html());
	};

	App.insertIntoEditor = function (context, content) {
		var editor = App.getEditorState(context);
		var range;

		if (!editor) {
			return;
		}

		if (editor.quill) {
			range = editor.quill.getSelection(true);
			editor.quill.clipboard.dangerouslyPasteHTML(range ? range.index : editor.quill.getLength(), content);
			App.syncEditorToInput(editor.root);
			return;
		}

		editor.input.val((editor.input.val() || '') + content);
	};
}(jQuery, window));
