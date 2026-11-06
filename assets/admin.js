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

const highlighted = 'silvertrail-section--highlighted';
let highlight;

function clearHighlight() {
	highlight?.abort();
	document.querySelector(`.${highlighted}`)?.classList.remove(highlighted);
}

function highlightSection() {
	clearHighlight();
	const section = location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
	if (!section?.classList.contains('silvertrail-section')) {
		return;
	}
	section.classList.add(highlighted);
	highlight = new AbortController();
	for (const type of ['pointerdown', 'keydown', 'input']) {
		document.addEventListener(type, clearHighlight, { capture: true, signal: highlight.signal });
	}
}

highlightSection();
window.addEventListener('hashchange', highlightSection);
window.addEventListener('pageshow', (event) => event.persisted && clearHighlight());
