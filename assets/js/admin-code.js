(function ($, window, document) {
	var App = window.WPNotesAdminApp = window.WPNotesAdminApp || {};

	function normalizeCodeLanguage(language) {
		switch ((language || '').toLowerCase()) {
		case 'xml':
		case 'html':
			return 'HTML';
		case 'css':
			return 'CSS';
		case 'javascript':
		case 'js':
			return 'JavaScript';
		case 'typescript':
		case 'ts':
			return 'TypeScript';
		case 'php':
			return 'PHP';
		case 'json':
			return 'JSON';
		default:
			return 'Code';
		}
	}

	function ensureCopyButton(block, text) {
		var button = block.querySelector('.wp-notes-code-copy');
		var icon;

		if (!button) {
			button = document.createElement('button');
			button.type = 'button';
			button.className = 'wp-notes-code-copy';
			button.setAttribute('aria-label', wpNotesAdmin.copyCode);
			button.setAttribute('title', wpNotesAdmin.copyCode);
			icon = document.createElement('img');
			icon.src = wpNotesAdmin.copyIconUrl;
			icon.alt = '';
			button.appendChild(icon);
			block.appendChild(button);
		}

		button.setAttribute('data-copy-text', text);
	}

	function showCopyToast() {
		var toast = $('.wp-notes-copy-toast');

		if (!toast.length) {
			toast = $('<div class="wp-notes-copy-toast" role="status" aria-live="polite"></div>');
			$('body').append(toast);
		}

		toast.text(wpNotesAdmin.copiedText).addClass('is-visible');
		window.clearTimeout(toast.data('hideTimer'));
		toast.data('hideTimer', window.setTimeout(function () {
			toast.removeClass('is-visible');
		}, 1800));
	}

	function showCopiedState(button) {
		var originalTitle = button.getAttribute('title') || wpNotesAdmin.copyCode;

		button.classList.add('is-copied');
		button.setAttribute('title', wpNotesAdmin.copiedText);
		button.setAttribute('aria-label', wpNotesAdmin.copiedText);
		showCopyToast();

		window.setTimeout(function () {
			button.classList.remove('is-copied');
			button.setAttribute('title', originalTitle);
			button.setAttribute('aria-label', wpNotesAdmin.copyCode);
		}, 1200);
	}

	App.highlightRenderedCodeBlocks = function (context) {
		if (!window.hljs) {
			return;
		}

		$(context || document).find('.wp-notes-notice-card__body pre').each(function () {
			var block = this;
			var text = block.textContent || '';
			var result;
			var language;

			if (!text.trim()) {
				return;
			}

			result = window.hljs.highlightAuto(text);
			language = result.language || '';
			block.innerHTML = result.value;
			block.classList.add('hljs');
			block.setAttribute('data-code-language', normalizeCodeLanguage(language));
			ensureCopyButton(block, text);
		});
	};

	App.copyCodeFromButton = function (button) {
		var text = button.getAttribute('data-copy-text') || '';

		if (!text) {
			return;
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				showCopiedState(button);
			});
			return;
		}

		showCopiedState(button);
	};
}(jQuery, window, document));
