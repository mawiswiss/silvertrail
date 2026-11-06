document.querySelectorAll('[data-silvertrail-editor]').forEach((textarea) => {
	const editor = wp.codeEditor.initialize(textarea, JSON.parse(textarea.dataset.silvertrailEditor));
	const label = textarea.labels[0];
	if (label) {
		editor.codemirror.getInputField().setAttribute('aria-label', label.textContent.trim());
	}
	if (!textarea.readOnly) {
		editor.codemirror.setSize(null, 'auto');
		editor.codemirror.on('change', () => textarea.dispatchEvent(new Event('input', { bubbles: true })));
		return;
	}
	editor.codemirror.setSize(null, '28em');
	textarea.closest('details')?.addEventListener('toggle', () => editor.codemirror.refresh());
});

const settings = document.querySelector('form[action="options.php"]');
let unsaved = false;
settings?.addEventListener('input', () => (unsaved = true));
settings?.addEventListener('submit', () => (unsaved = false));
window.addEventListener('beforeunload', (event) => {
	if (unsaved) {
		event.preventDefault();
	}
});

// ClipboardJS delegates from document.body, so it also handles the buttons the scan adds later.
new ClipboardJS('[data-silvertrail-copy]', {
	text: (button) => document.getElementById(button.dataset.silvertrailCopy).value,
}).on('success', (event) => {
	const success = event.trigger.nextElementSibling;
	event.clearSelection();
	event.trigger.focus();
	success.classList.remove('hidden');
	wp.a11y.speak(success.textContent);
	clearTimeout(success.timer);
	success.timer = setTimeout(() => success.classList.add('hidden'), 3000);
});

document.addEventListener('click', (event) => {
	const trigger = event.target.closest('[data-silvertrail-help]');
	const toggle = document.getElementById('contextual-help-link');
	if (!trigger || !toggle) {
		return;
	}
	if (toggle.getAttribute('aria-expanded') !== 'true') {
		toggle.click();
	}
	const tab = document.querySelector(`#tab-link-${trigger.dataset.silvertrailHelp} a`);
	tab?.click();
	tab?.focus({ preventScroll: true });
	window.scrollTo({ top: 0, behavior: 'smooth' });
});
