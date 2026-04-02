(function (window) {
	var App = window.WPNotesAdminApp = window.WPNotesAdminApp || {};

	App.init = function () {
		App.bindModalEvents();
		App.bindNoteEvents();
		App.initRichEditors(document);
		App.highlightRenderedCodeBlocks(document);
	};

	App.init();
}(window));
