jQuery(($) => {
	$('.silvertrail-color').wpColorPicker();
	document.querySelectorAll('[data-silvertrail-editor]').forEach((textarea) => {
		const editor = wp.codeEditor.initialize(textarea, JSON.parse(textarea.dataset.silvertrailEditor));
		editor.codemirror.setSize(null, 'auto');
	});
});
