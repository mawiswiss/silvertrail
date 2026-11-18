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
settings?.addEventListener('input', (event) => (unsaved ||= Boolean(event.target.name)));
settings?.addEventListener('submit', () => (unsaved = false));
settings?.addEventListener('change', (event) => {
	const require = document.getElementById('silvertrail-require-choice');
	if (event.target.name === 'silvertrail[layout]' && require && !require.disabled) {
		require.checked = event.target.value === 'modal';
	}
});
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

const write = document.getElementById('silvertrail-write-styles');
write?.addEventListener('click', async () => {
	const want = document.getElementById('silvertrail-style-want');
	const answer = document.getElementById('silvertrail-style-answer');
	const output = document.getElementById('silvertrail-style-output');
	const error = document.getElementById('silvertrail-style-error');
	const spinner = write.nextElementSibling;
	error.hidden = true;
	if (!want.value.trim()) {
		want.focus();
		wp.a11y.speak(write.dataset.empty, 'assertive');
		return;
	}
	write.disabled = true;
	spinner.classList.add('is-active');
	wp.a11y.speak(write.dataset.busy);
	try {
		const result = await wp.apiFetch({
			path: '/wp-abilities/v1/abilities/silvertrail/write-styles/run',
			method: 'POST',
			data: { input: { want: want.value } },
		});
		answer.value = result.css;
		output.hidden = false;
		wp.a11y.speak(write.dataset.done);
	} catch (failure) {
		error.firstElementChild.textContent = failure.message;
		error.hidden = false;
		wp.a11y.speak(failure.message, 'assertive');
	} finally {
		write.disabled = false;
		spinner.classList.remove('is-active');
	}
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
