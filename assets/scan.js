(() => {
	const data = window.silvertrailScan;
	const root = document.getElementById('silvertrail-scan');
	if (!data || !root) {
		return;
	}

	const texts = data.texts;
	const home = new URL(data.home);
	const input = root.querySelector('#silvertrail-scan-url');
	const run = root.querySelector('[data-silvertrail-scan-run]');
	const output = root.querySelector('[data-silvertrail-scan-result]');

	const NAMES = {
		'fonts.googleapis.com': 'Google Fonts',
		'fonts.gstatic.com': 'Google Fonts',
		'use.typekit.net': 'Adobe Fonts',
		'p.typekit.net': 'Adobe Fonts',
		'use.fontawesome.com': 'Font Awesome',
		'kit.fontawesome.com': 'Font Awesome',
		'gravatar.com': 'Gravatar',
		's.w.org': 'WordPress.org',
		'cdn.jsdelivr.net': 'jsDelivr',
		'cdnjs.cloudflare.com': 'cdnjs',
		'unpkg.com': 'unpkg',
	};

	const format = (text, ...args) => {
		let next = 0;
		return text.replace(/%(?:(\d)\$)?s/g, (_, position) => String(args[position ? position - 1 : next++] ?? ''));
	};

	const element = (tag, attributes = {}, ...children) => {
		const node = document.createElement(tag);
		Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
		node.append(...children);
		return node;
	};

	const notice = (type, text) => element('div', { class: `notice notice-${type} inline` }, element('p', {}, text));

	const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

	const loaded = (frame) =>
		new Promise((resolve, reject) => {
			frame.addEventListener('load', resolve, { once: true });
			setTimeout(() => reject(new Error('timeout')), 20000);
		});

	const cookieNames = (doc) =>
		doc.cookie
			.split('; ')
			.filter(Boolean)
			.map((cookie) => cookie.split('=')[0]);

	const storageKeys = (win) => {
		try {
			return [...Object.keys(win.localStorage), ...Object.keys(win.sessionStorage)];
		} catch {
			return [];
		}
	};

	const matches = (url, pattern) => {
		const [host, ...rest] = pattern.toLowerCase().replace(/^(https?:)?\/\//, '').replace(/^www\./, '').split('/');
		const hostMatches = url.hostname === host || url.hostname.endsWith(`.${host}`);
		return hostMatches && url.pathname.replace(/^\//, '').startsWith(rest.join('/'));
	};

	const knownName = (host) => {
		const match = Object.keys(NAMES).find((known) => host === known || host.endsWith(`.${known}`));
		return match ? NAMES[match] : '';
	};

	const serviceName = (id, config) => data.services[id]?.name ?? config.services[id]?.name ?? id;

	function kind(item) {
		if (item.script) {
			return item.script.handle ? 'script' : 'scriptRaw';
		}
		if (item.types.has('iframe')) {
			return 'frame';
		}
		if (item.types.has('css') || item.types.has('link')) {
			return 'font';
		}
		return item.types.has('img') ? 'image' : 'other';
	}

	function describe(item, config) {
		const what = kind(item);
		const base = { host: item.host, what };
		if (item.service) {
			const name = serviceName(item.service, config);
			if (config.essential.includes(item.service)) {
				return { ...base, name, early: false, advice: format(texts.essential, name) };
			}
			if (config.ungated.includes(item.service)) {
				return { ...base, name, early: false, advice: format(texts.cookieless, name) };
			}
			return { ...base, name, early: true, advice: format(texts.registered, name) };
		}
		const preset = Object.keys(data.presets).find((id) => data.presets[id].hosts.some((pattern) => item.urls.some((url) => matches(url, pattern))));
		if (preset) {
			const { name, tracker } = data.presets[preset];
			return { ...base, name, early: true, preset, advice: format(tracker ? texts.presetTracker : texts.presetEmbed, name) };
		}
		const advice = what === 'script' ? format(texts.script, item.script.handle) : texts[what];
		return { ...base, name: knownName(item.host) || item.host, early: true, advice };
	}

	function collect(win, before, url) {
		const config = win.silvertrailConfig;
		if (!config || !config.check || typeof win.silvertrail?.trail !== 'function') {
			throw new Error('Silvertrail is not running on this page.');
		}
		const trail = win.silvertrail.trail();
		const services = new Map(trail.loaded.map((item) => [item.host, item.service]));
		const scripts = [...win.document.querySelectorAll('script[src]')];
		const hosts = new Map();
		for (const entry of win.performance.getEntriesByType('resource')) {
			const target = new URL(entry.name, win.location.href);
			if (target.hostname === win.location.hostname) {
				continue;
			}
			if (!hosts.has(target.hostname)) {
				const script = scripts.find((node) => new URL(node.src, win.location.href).hostname === target.hostname);
				hosts.set(target.hostname, {
					host: target.hostname,
					service: services.get(target.hostname) ?? null,
					script: script ? { handle: (script.id.match(/^(.+)-js$/) || [])[1] ?? '' } : null,
					types: new Set(),
					urls: [],
				});
			}
			hosts.get(target.hostname).types.add(entry.initiatorType);
			hosts.get(target.hostname).urls.push(target);
		}
		return {
			url: url.href,
			items: [...hosts.values()].map((item) => describe(item, config)),
			held: trail.held,
			stored: [...cookieNames(win.document), ...storageKeys(win)].filter((name) => !before.includes(name)),
		};
	}

	// Leaves the admin's browser as it was, so the next scan sees the same cookies a new visitor gets.
	function forget(win, names) {
		const domains = ['', `; domain=${win.location.hostname}`, `; domain=.${win.location.hostname.split('.').slice(-2).join('.')}`];
		for (const name of names) {
			domains.forEach((domain) => (win.document.cookie = `${name}=; Max-Age=0; path=/${domain}`));
			try {
				win.localStorage.removeItem(name);
				win.sessionStorage.removeItem(name);
			} catch {}
		}
	}

	function presetLink(id) {
		const preset = data.presets[id];
		if (preset.locked) {
			return '';
		}
		const url = new URL(data.settings);
		url.searchParams.set('silvertrail-turn-on', id);
		return element('a', { href: url.href, class: 'button button-small' }, format(texts.turnOn, preset.name));
	}

	function list(items) {
		return element(
			'ul',
			{ class: 'silvertrail-scan-list' },
			...items.map((item) =>
				element(
					'li',
					{},
					element('strong', {}, item.name),
					...(item.name === item.host ? [] : [' ', element('code', {}, item.host)]),
					element('br'),
					item.advice,
					' ',
					item.preset ? presetLink(item.preset) : '',
				),
			),
		);
	}

	const heldName = (id) => data.services[id]?.name ?? id;

	const bullets = (items) =>
		items.length === 0 ? element('p', {}, texts.none) : element('ul', { class: 'silvertrail-scan-list' }, ...items.map((children) => element('li', {}, ...children)));

	function prompt(scan) {
		const kinds = { script: texts.kindScript, scriptRaw: texts.kindScript, frame: texts.kindFrame, font: texts.kindFont, image: texts.kindImage, other: texts.kindOther };
		const connections = scan.items.map((item) => `- ${item.name === item.host ? item.host : `${item.name} (${item.host})`}: ${kinds[item.what]}`);
		const incomplete = scan.held.filter((id) => !data.services[id]?.provider || !data.services[id]?.purpose || !data.services[id]?.privacyUrl);
		const sections = [
			[texts.promptConnections, connections],
			[texts.promptHeld, incomplete.map((id) => `- ${heldName(id)}`)],
			[texts.promptCookies, scan.stored.map((name) => `- ${name}`)],
		].filter(([, lines]) => lines.length > 0);
		if (sections.length === 0) {
			return '';
		}
		return [
			format(texts.promptIntro, data.site, home.origin, scan.url),
			...sections.flatMap(([title, lines]) => ['', `${title}:`, ...lines]),
			'',
			format(texts.promptAsk, data.language),
		].join('\n');
	}

	function render(scan) {
		const early = scan.items.filter((item) => item.early);
		const fine = scan.items.filter((item) => !item.early);
		const parts = [early.length > 0 ? notice('warning', texts.attention) : notice('success', texts.clean)];
		if (early.length === 0) {
			parts[0].append(element('p', {}, texts.license, ' ', element('a', { href: data.license }, data.license)));
		}
		if (early.length > 0) {
			parts.push(element('h3', {}, texts.early), list(early));
		}
		parts.push(element('h3', {}, texts.held), bullets(scan.held.map((id) => [element('strong', {}, heldName(id)), ' ', element('span', { class: 'description' }, data.services[id] ? `(${data.services[id].group})` : '')])));
		if (fine.length > 0) {
			parts.push(element('h3', {}, texts.fine), list(fine));
		}
		parts.push(element('h3', {}, texts.stored), bullets(scan.stored.map((name) => [element('code', {}, name)])));

		const handover = prompt(scan);
		if (handover === '') {
			output.replaceChildren(...parts);
			return;
		}
		const text = element('textarea', { class: 'large-text code', rows: '12', readonly: '', id: 'silvertrail-scan-report' });
		text.value = handover;
		parts.push(
			element('h3', {}, element('label', { for: 'silvertrail-scan-report' }, texts.report)),
			element('p', { class: 'description' }, texts.reportHelp),
			text,
			element(
				'p',
				{ class: 'copy-to-clipboard-container' },
				element('button', { type: 'button', class: 'button', 'data-silvertrail-copy': 'silvertrail-scan-report' }, texts.copy),
				element('span', { class: 'success hidden', 'aria-hidden': 'true' }, texts.copied),
			),
		);
		output.replaceChildren(...parts);
	}

	async function scan() {
		let url;
		try {
			url = new URL(input.value.trim() || data.home, data.home);
		} catch {
			url = null;
		}
		if (!url || url.origin !== home.origin) {
			output.replaceChildren(notice('error', texts.foreign));
			return;
		}
		const target = new URL(url);
		target.searchParams.set(data.param, '1');
		const before = [...cookieNames(document), ...storageKeys(window)];
		const frame = element('iframe', { src: target.href, sandbox: 'allow-scripts allow-same-origin', title: texts.scanning, tabindex: '-1' });
		frame.style.cssText = 'width:1280px;height:800px;border:0;transform:scale(.25);transform-origin:0 0';
		const preview = element('div', {}, frame);
		preview.style.cssText = 'width:320px;height:200px;overflow:hidden;border:1px solid #c3c4c7;margin:8px 0';
		run.disabled = true;
		output.replaceChildren(element('p', {}, texts.scanning), preview);

		let result = null;
		try {
			await loaded(frame);
			const win = frame.contentWindow;
			win.scrollTo(0, win.document.documentElement.scrollHeight);
			await wait(2500);
			result = collect(win, before, url);
			forget(win, result.stored);
		} catch {
			result = null;
		}
		frame.remove();
		run.disabled = false;
		if (result) {
			render(result);
		} else {
			output.replaceChildren(notice('error', texts.failed));
		}
	}

	run.addEventListener('click', scan);
	input.addEventListener('keydown', (event) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			scan();
		}
	});
})();
