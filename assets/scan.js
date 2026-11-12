(() => {
	const data = window.silvertrailScan;
	const findings = window.silvertrailFindings;
	const root = document.getElementById('silvertrail-scan');
	if (!data || !findings || !root) {
		return;
	}

	const WAIT = 2000;
	const WIDTH = 1280;
	const SAVED = 'silvertrail-scan-consent';
	const TYPES = { script: 'script', iframe: 'frame', frame: 'frame', css: 'style', link: 'style', img: 'image', image: 'image' };
	const TONES = { quiet: 'ok', starts: 'ok', essential: 'neutral', cookieless: 'neutral', contacts: 'bad', any: 'bad', reload: 'warn', unseen: 'muted', absent: 'muted' };

	const texts = data.texts;
	const home = new URL(data.home);
	const input = root.querySelector('#silvertrail-scan-urls');
	const run = root.querySelector('[data-silvertrail-scan-run]');
	const cancel = root.querySelector('[data-silvertrail-scan-cancel]');
	const progress = root.querySelector('[data-silvertrail-scan-progress]');
	const bar = progress.querySelector('progress');
	const step = progress.querySelector('#silvertrail-scan-step');
	const note = progress.querySelector('#silvertrail-scan-note');
	const where = progress.querySelector('[data-silvertrail-scan-where]');
	const view = progress.querySelector('[data-silvertrail-scan-view]');
	const output = root.querySelector('[data-silvertrail-scan-result]');

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

	const speak = (text) => window.wp?.a11y?.speak(text, 'polite');

	const path = (url) => {
		const parsed = new URL(url);
		return parsed.pathname + parsed.search;
	};

	// Cookies and storage

	const cookies = (doc) =>
		Object.fromEntries(
			doc.cookie
				.split('; ')
				.filter(Boolean)
				.map((cookie) => [cookie.split('=')[0], cookie.slice(cookie.indexOf('=') + 1)]),
		);

	const storageKeys = (win) => {
		try {
			return [...Object.keys(win.localStorage), ...Object.keys(win.sessionStorage)];
		} catch {
			return [];
		}
	};

	const names = (win) => [...Object.keys(cookies(win.document)), ...storageKeys(win)];

	function expire(name) {
		const host = location.hostname;
		const domains = ['', `; domain=${host}`, `; domain=.${host.split('.').slice(-2).join('.')}`];
		for (const where of new Set(['/', data.path])) {
			domains.forEach((domain) => (document.cookie = `${name}=; Max-Age=0; path=${where}${domain}`));
		}
	}

	function forget(list) {
		for (const name of list) {
			expire(name);
			try {
				localStorage.removeItem(name);
				sessionStorage.removeItem(name);
			} catch {}
		}
	}

	const consentApi = () => Object.entries(cookies(document)).filter(([name]) => name.startsWith('wp_consent'));

	// The WP Consent API cookies are the admin's own, so they come back after the scan, even an abandoned one.
	function restore() {
		let saved = null;
		try {
			saved = JSON.parse(localStorage.getItem(SAVED));
			localStorage.removeItem(SAVED);
		} catch {}
		if (!saved) {
			return;
		}
		consentApi().forEach(([name]) => expire(name));
		const secure = location.protocol === 'https:' ? '; Secure' : '';
		Object.entries(saved).forEach(([name, value]) => (document.cookie = `${name}=${value}; Max-Age=${30 * 86400}; path=/${secure}`));
	}

	function save() {
		restore();
		try {
			localStorage.setItem(SAVED, JSON.stringify(Object.fromEntries(consentApi())));
		} catch {}
	}

	let before = [];

	function reset() {
		expire(data.cookie);
		forget(names(window).filter((name) => !before.includes(name)));
		consentApi().forEach(([name]) => expire(name));
	}

	// The frame

	let session = null;
	let visits = 0;

	const loaded = (frame) =>
		new Promise((resolve, reject) => {
			frame.addEventListener('load', resolve, { once: true });
			setTimeout(() => reject(new Error('timeout')), 20000);
		});

	// A cancelled scan stops at its next step, even when a new one has started.
	function check(current) {
		if (session !== current || !current.frame.contentWindow) {
			throw new Error('cancelled');
		}
		return current.frame.contentWindow;
	}

	function ready(win) {
		const config = win.silvertrailConfig;
		if (!config || !config.check || typeof win.silvertrail?.set !== 'function') {
			throw new Error('Silvertrail is not running on this page.');
		}
		return config;
	}

	async function sweep(win) {
		const height = win.document.documentElement.scrollHeight;
		for (let y = 0, steps = 0; y < height && steps < 20; y += win.innerHeight, steps++) {
			win.scrollTo(0, y);
			await wait(100);
		}
		win.scrollTo(0, height);
	}

	const observe = (entries) => entries.map((entry) => ({ url: entry.name, type: TYPES[entry.initiatorType] ?? 'other' }));

	async function settle(current, win) {
		await sweep(win);
		await wait(WAIT);
		return check(current);
	}

	async function visit(current, url) {
		check(current);
		reset();
		const target = new URL(url);
		target.searchParams.set(data.param, String(++visits));
		const done = loaded(current.frame);
		current.frame.src = target.href;
		await done;
		const win = await settle(current, check(current));
		return {
			win,
			config: ready(win),
			first: observe(win.performance.getEntriesByType('resource')),
			scripts: [...win.document.querySelectorAll('script[src]')].map((node) => ({ src: node.src, id: node.id })),
			stored: names(win).filter((name) => !before.includes(name)),
			held: win.silvertrail.trail().held,
		};
	}

	// A request that started before the choice and finished after it belongs to the window before.
	async function choose(current, win, ids) {
		const seen = [];
		const observer = new win.PerformanceObserver((list) => seen.push(...list.getEntries()));
		observer.observe({ type: 'resource' });
		const start = win.performance.now();
		win.silvertrail.set(ids);
		await settle(current, win);
		seen.push(...observer.takeRecords());
		observer.disconnect();
		return { during: observe(seen.filter((entry) => entry.startTime >= start)), earlier: observe(seen.filter((entry) => entry.startTime < start)) };
	}

	async function reload(current, win) {
		const done = loaded(current.frame);
		win.location.reload();
		await done;
		const fresh = await settle(current, check(current));
		return observe(fresh.performance.getEntriesByType('resource'));
	}

	// Report

	const pages = (list, total) => (total > 1 && list.length > 0 ? ` ${format(texts.onPages, list.map(path).join(', '))}` : '');

	function presetLink(id) {
		const preset = data.presets[id];
		if (preset.locked) {
			return '';
		}
		const url = new URL(data.settings);
		url.searchParams.set('silvertrail-turn-on', id);
		return element('a', { href: url.href, class: 'button' }, format(texts.turnOn, preset.name));
	}

	const nameOf = (id, result) => result.services.find((row) => row.id === id)?.name ?? data.services[id]?.name ?? id;

	function guardAdvice(item, result) {
		if (item.when === 'declined') {
			return texts.guardDeclined;
		}
		if (item.when === 'any') {
			return format(texts.guardAny, item.services.map((id) => nameOf(id, result)).join(', '));
		}
		if (item.preset) {
			return format(data.presets[item.preset].tracker ? texts.presetTracker : texts.presetEmbed, data.presets[item.preset].name);
		}
		return item.what === 'script' ? format(texts.script, item.handle) : texts[item.what];
	}

	function hostList(items, advice, result, tone) {
		return element(
			'ul',
			{ class: `silvertrail-findings silvertrail-findings--${tone}` },
			...items.map((item) =>
				element(
					'li',
					{},
					element(
						'div',
						{ class: 'silvertrail-findings__text' },
						element('p', { class: 'silvertrail-findings__name' }, element('strong', {}, item.name), ...(item.name === item.host ? [] : [' ', element('code', {}, item.host)])),
						element('p', {}, advice(item), pages(item.pages, result.pages.length)),
					),
					item.preset ? presetLink(item.preset) : '',
				),
			),
		);
	}

	const rowsOf = (result) => result.services.filter((row) => data.services[row.id]?.source !== 'own');

	const state = (text, tone) => element('td', {}, element('span', { class: `silvertrail-state silvertrail-state--${tone}` }, text));

	function table(result) {
		const head = element('tr', {}, ...[texts.colService, texts.colSource, texts.colBefore, texts.colDeclined, texts.colAgreed].map((text) => element('th', { scope: 'col' }, text)));
		const body = rowsOf(result).map((row) => {
			const source = data.services[row.id]?.source === 'settings' ? texts.sourceSettings : texts.sourceCode;
			const before = row.before === 'early' ? 'contacts' : row.before;
			const agreed = [texts[row.agreed]];
			if (row.also.length > 0) {
				agreed.push(format(texts.alsoStarts, row.also.map((item) => item.name).join(', ')));
			}
			return element(
				'tr',
				{},
				element('th', { scope: 'row' }, row.name),
				element('td', { class: 'silvertrail-scan-table__source' }, source),
				state(texts[before], TONES[before]),
				state(texts[row.declined], TONES[row.declined]),
				state(agreed.join('. '), row.also.length > 0 ? 'bad' : TONES[row.agreed]),
			);
		});
		return element(
			'div',
			{ class: 'silvertrail-scan-table', role: 'region', 'aria-labelledby': 'silvertrail-scan-services', tabindex: '0' },
			element('table', { 'aria-labelledby': 'silvertrail-scan-services' }, element('thead', {}, head), element('tbody', {}, ...body)),
		);
	}

	function todo(result) {
		const lines = [];
		const total = result.pages.length;
		for (const row of rowsOf(result)) {
			if (row.before === 'early') {
				lines.push(format(texts.registered, row.name) + pages(row.pages, total));
			} else if (row.declined === 'contacts') {
				lines.push(format(texts.todoDeclined, row.name) + pages(row.pages, total));
			}
			if (row.agreed === 'any') {
				lines.push(format(texts.todoAny, row.name));
			}
			for (const item of row.also) {
				lines.push(item.types.length > 0 ? format(texts.todoAlsoShared, row.name, item.name, item.types.join(', ')) : format(texts.todoAlso, row.name, item.name));
			}
			if (row.agreed === 'reload' && row.before !== 'early' && row.declined !== 'contacts') {
				lines.push(format(texts.todoReload, row.name) + pages(row.pages, total));
			}
		}
		result.late.forEach((item) => lines.push(format(texts.todoLate, item.host) + pages(item.pages, total)));
		return lines;
	}

	function prompt(result) {
		const kinds = { script: texts.kindScript, scriptRaw: texts.kindScript, frame: texts.kindFrame, font: texts.kindFont, image: texts.kindImage, other: texts.kindOther };
		const label = (item) => (item.name === item.host ? item.host : `${item.name} (${item.host})`);
		const connections = [
			...result.guard.map((item) => `- ${label(item)}: ${kinds[item.what]}`),
			...[...result.belongs, ...result.after].map((item) => `- ${label(item)}: ${texts.kindAfter}`),
		];
		const incomplete = result.held.filter((id) => !data.services[id]?.provider || !data.services[id]?.purpose || !data.services[id]?.privacyUrl);
		const sections = [
			[texts.promptConnections, connections],
			[texts.promptHeld, incomplete.map((id) => `- ${nameOf(id, result)}`)],
			[texts.promptCookies, result.stored.map((name) => `- ${name}`)],
		].filter(([, lines]) => lines.length > 0);
		if (sections.length === 0) {
			return '';
		}
		return [
			format(texts.promptIntro, data.site, home.origin, result.pages.join(', ')),
			...sections.flatMap(([title, lines]) => ['', `${title}:`, ...lines]),
			'',
			format(texts.promptAsk, data.language),
		].join('\n');
	}

	const toneOf = (result) => (result.problems > 0 ? 'bad' : result.warnings > 0 ? 'warn' : 'ok');

	function verdict(result, loads) {
		const tone = toneOf(result);
		const [title, text, icon] = {
			bad: [texts.attentionTitle, texts.attention, 'warning'],
			warn: [texts.reloadOnlyTitle, texts.reloadOnly, 'update'],
			ok: [texts.cleanTitle, texts.clean, 'yes'],
		}[tone];
		const facts = [
			[texts.factPages, result.pages.length],
			[texts.factServices, rowsOf(result).length],
			[texts.factLoads, loads],
		];
		return element(
			'div',
			{ class: `silvertrail-verdict silvertrail-verdict--${tone}` },
			element('span', { class: `silvertrail-verdict__icon dashicons dashicons-${icon}`, 'aria-hidden': 'true' }),
			element(
				'div',
				{},
				element('h3', {}, title),
				element('p', {}, text),
				element('dl', { class: 'silvertrail-verdict__facts' }, ...facts.map(([label, value]) => element('div', {}, element('dt', {}, label), element('dd', {}, String(value))))),
			),
		);
	}

	function render(result, loads) {
		const parts = [verdict(result, loads)];
		if (result.guard.length > 0) {
			parts.push(element('h3', {}, texts.guard), hostList(result.guard, (item) => guardAdvice(item, result), result, 'bad'));
		}
		const lines = todo(result);
		if (lines.length > 0) {
			parts.push(element('h3', {}, texts.todo), element('ol', { class: 'silvertrail-steps' }, ...lines.map((line) => element('li', {}, line))));
		}
		const unlisted = [...result.belongs, ...result.after];
		if (unlisted.length > 0) {
			parts.push(
				element('h3', {}, texts.belongs),
				hostList(unlisted, (item) => (item.service ? format(texts.belongsTo, nameOf(item.service, result), item.host) : texts.afterAll), result, 'neutral'),
			);
		}
		parts.push(element('h3', { id: 'silvertrail-scan-services' }, texts.services), table(result));
		parts.push(
			element('h3', {}, texts.stored),
			result.stored.length === 0
				? element('p', {}, texts.none)
				: element('ul', { class: 'silvertrail-chips' }, ...result.stored.map((name) => element('li', {}, element('code', {}, name)))),
		);

		const handover = prompt(result);
		if (handover !== '') {
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
		}
		if (toneOf(result) === 'ok' && data.licenseSeen) {
			parts.push(element('p', { class: 'silvertrail-scan-license' }, texts.license, ' ', element('a', { href: data.license }, texts.support)));
		}
		output.replaceChildren(...parts);
		speak([...parts[0].querySelectorAll('h3, p')].map((node) => node.textContent).join(' '));
	}

	// Run

	new ResizeObserver(() => view.style.setProperty('--silvertrail-scan-scale', String(view.clientWidth / WIDTH))).observe(view);

	function cleanup() {
		session?.frame.remove();
		session = null;
		reset();
		restore();
		window.removeEventListener('pagehide', cleanup);
		run.disabled = false;
		progress.hidden = true;
	}

	// The total changes once a page's services are known, so the bar never moves back.
	function show(current, [title, explanation], at, done, total) {
		check(current);
		step.textContent = title;
		note.textContent = explanation;
		where.replaceChildren(
			...(at.of > 1 ? [element('span', {}, format(texts.stepPage, at.number, at.of))] : []),
			element('span', { class: 'silvertrail-scan-stage__address' }, at.address),
		);
		bar.value = Math.max(bar.value, done / total);
	}

	function targets() {
		const lines = input.value.split('\n').map((line) => line.trim()).filter(Boolean);
		const urls = [];
		for (const line of lines.length > 0 ? lines : [data.home]) {
			let url;
			try {
				url = new URL(line, data.home);
			} catch {
				return null;
			}
			if (url.origin !== home.origin) {
				return null;
			}
			url.hash = '';
			if (!urls.some((known) => known.href === url.href)) {
				urls.push(url);
			}
		}
		return urls;
	}

	async function scan() {
		const urls = targets();
		if (!urls) {
			output.replaceChildren(notice('error', texts.foreign));
			return;
		}
		save();
		before = names(window);
		window.addEventListener('pagehide', cleanup);
		const frame = element('iframe', { sandbox: 'allow-scripts allow-same-origin', title: texts.scanning, tabindex: '-1', width: String(WIDTH), height: '800' });
		const current = { frame };
		session = current;
		run.disabled = true;
		bar.value = 0;
		progress.hidden = false;
		view.replaceChildren(frame);
		output.replaceChildren();
		speak(texts.scanning);

		const pages = [];
		const isolated = [];
		const config = { services: {}, essential: [], ungated: [] };
		const guess = Object.values(data.services).filter((service) => !service.essential).length;
		let total = urls.length * 4 + guess;
		let count = 0;
		let result = null;
		try {
			for (const [index, url] of urls.entries()) {
				const at = { number: index + 1, of: urls.length, address: url.host + path(url) };
				show(current, [texts.stepFirst, texts.stepFirstNote], at, count++, total);
				const first = await visit(current, url);
				Object.assign(config.services, first.config.services);
				config.essential = first.config.essential;
				config.ungated = first.config.ungated;

				show(current, [texts.stepDeclined, texts.stepDeclinedNote], at, count++, total);
				const declined = await choose(current, first.win, []);
				show(current, [texts.stepAgreed, texts.stepAgreedNote], at, count++, total);
				const agreed = await choose(current, first.win, Object.keys(first.config.services));
				show(current, [texts.stepReload, texts.stepReloadNote], at, count++, total);
				const reloaded = await reload(current, first.win);

				const page = {
					url: url.href,
					first: [...first.first, ...declined.earlier],
					declined: [...declined.during, ...agreed.earlier],
					agreed: agreed.during,
					reloaded,
					held: first.held,
					stored: first.stored,
					scripts: first.scripts,
				};
				pages.push(page);

				const queue = findings.queue(page, config.services, config.essential, isolated);
				total += queue.length - (index === 0 ? guess : 0);
				for (const id of queue) {
					show(current, [format(texts.stepService, config.services[id].name), texts.stepServiceNote], at, count++, total);
					const alone = await visit(current, url);
					const agreedAlone = await choose(current, alone.win, [id]);
					isolated.push({ service: id, page: url.href, first: [...alone.first, ...agreedAlone.earlier], scripts: alone.scripts, agreed: agreedAlone.during });
				}
			}
			result = findings.analyze({ ...config, presets: data.presets, pages, isolated });
		} catch {
			result = null;
		}
		if (session !== current) {
			return;
		}
		cleanup();
		if (result) {
			render(result, pages.length * 2 + isolated.length);
		} else {
			output.replaceChildren(notice('error', texts.failed));
			speak(texts.failed);
		}
	}

	restore();
	run.addEventListener('click', scan);
	cancel.addEventListener('click', () => {
		cleanup();
		output.replaceChildren(notice('info', texts.cancelled));
		speak(texts.cancelled);
		run.focus();
	});
})();
