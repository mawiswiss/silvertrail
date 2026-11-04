(() => {
	const api = window.silvertrail;
	const config = window.silvertrailConfig;
	const texts = window.silvertrailLeakTexts;
	if (!api || !config || !texts) {
		return;
	}

	const element = (tag, text, attributes = {}) => {
		const node = document.createElement(tag);
		if (text !== undefined) {
			node.textContent = text;
		}
		Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
		return node;
	};

	const storageKeys = (storage) => {
		try {
			return Object.keys(storage);
		} catch {
			return [];
		}
	};

	function render() {
		const essential = new Set(config.essential);
		const requests = api.trail().loaded.map((item) => {
			const name = item.service ? config.services[item.service].name : '';
			if (item.service && essential.has(item.service)) {
				return { text: `${item.host}: ${name}, ${texts.ok}`, leak: false };
			}
			return { text: `${item.host}: ${item.service ? `${name}, ${texts.early}` : texts.unknown}`, leak: true };
		});
		const cookies = document.cookie
			.split('; ')
			.filter(Boolean)
			.map((cookie) => cookie.split('=')[0]);
		const storage = [...storageKeys(window.localStorage), ...storageKeys(window.sessionStorage)];

		const panel = element('aside', undefined, { class: 'silvertrail-leaks', 'aria-label': texts.title });
		panel.style.cssText =
			'position:fixed;left:1rem;bottom:1rem;z-index:100000;max-width:28rem;max-height:70vh;overflow:auto;padding:1rem 1.25rem;background:#fff;color:#1d2327;border:2px solid #1d2327;border-radius:.5rem;font:14px/1.45 system-ui,sans-serif;box-shadow:0 1rem 3rem rgb(0 0 0/.25)';

		const close = element('button', '×', { type: 'button', 'aria-label': texts.close });
		close.style.cssText = 'float:right;border:0;background:none;font-size:1.25rem;cursor:pointer';
		close.addEventListener('click', () => panel.remove());

		panel.append(close, element('strong', texts.title), element('p', texts.intro));
		panel.append(element('h3', texts.requests));
		if (requests.length === 0) {
			panel.append(element('p', texts.clean, { 'data-silvertrail-leaks': '0' }));
		} else {
			const list = element('ul', undefined, { 'data-silvertrail-leaks': String(requests.filter((r) => r.leak).length) });
			requests.forEach((request) => {
				const item = element('li', request.text);
				item.style.color = request.leak ? '#b32d2e' : '#00733a';
				list.append(item);
			});
			panel.append(list);
		}
		panel.append(element('h3', texts.cookies), element('p', cookies.join(', ') || texts.none));
		panel.append(element('h3', texts.storage), element('p', storage.join(', ') || texts.none));
		panel.append(element('p', texts.limits));
		document.body.append(panel);
	}

	const later = () => setTimeout(render, 1500);
	if (document.readyState === 'complete') {
		later();
	} else {
		window.addEventListener('load', later);
	}
})();
