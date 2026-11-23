(() => {
	const config = window.silvertrailConfig;
	if (!config || typeof window.silvertrail?.has === 'function') {
		return;
	}

	const CHANGE = 'silvertrail:change';
	const SCRIPT_TIMEOUT = 10000;
	const serviceIds = Object.keys(config.services);
	const consentIds = serviceIds.filter((id) => !config.essential.includes(id));
	const gpc = config.gpc && navigator.globalPrivacyControl === true;

	// Consent store

	function readRaw() {
		const match = document.cookie.match(new RegExp('(?:^|; )' + config.cookie + '=([^;]*)'));
		if (!match) {
			return null;
		}
		try {
			const data = JSON.parse(decodeURIComponent(match[1]));
			return data && Array.isArray(data.c) && Number.isInteger(data.n) ? data : null;
		} catch {
			return null;
		}
	}

	// A stale cached page carries a lower n than the cookie, so >= keeps it from re-prompting.
	// Under Global Privacy Control, only a choice made while the signal was on counts.
	function read() {
		const data = readRaw();
		return data && data.n >= config.n && (!gpc || data.g) ? data : null;
	}

	// A grant from an embed before any banner choice leaves the other services undecided.
	function decided() {
		const data = read();
		return data !== null && !data.p;
	}

	function allowed(id) {
		if (config.essential.includes(id)) {
			return true;
		}
		const data = read();
		return !!data && data.c.includes(id);
	}

	function has(id) {
		return expand(id).every(allowed);
	}

	// A stale cached page must not lower the stored n or drop services it does not know.
	function set(ids, { silent = false, source = 'banner', partial = false } = {}) {
		const stored = readRaw();
		const newer = !!stored && stored.n > config.n;
		const consents = consentIds.filter((id) => ids.includes(id));
		if (newer) {
			consents.push(...stored.c.filter((id) => !serviceIds.includes(id)));
		}
		const data = { n: newer ? stored.n : config.n, c: consents, t: Math.floor(Date.now() / 1000) };
		if (partial) {
			data.p = 1;
		}
		if (gpc) {
			data.g = 1;
		}
		if (stored && stored.i) {
			data.i = stored.i;
		} else if (config.recordIds) {
			data.i = [...crypto.getRandomValues(new Uint8Array(16))].map((b) => b.toString(16).padStart(2, '0')).join('');
		}
		const secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = `${config.cookie}=${encodeURIComponent(JSON.stringify(data))}; Max-Age=${config.maxAge}; Path=${config.path}; SameSite=Lax${secure}`;
		window.dispatchEvent(new CustomEvent(CHANGE, { detail: { consents, silent, source } }));
		if (config.record) {
			fetch(config.record, {
				method: 'POST',
				keepalive: true,
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ i: data.i || '', n: data.n, c: consents, source }),
			}).catch(() => {});
		}
	}

	function expand(ids) {
		return [].concat(ids).flatMap((id) => {
			const members = config.services[id] ? [] : serviceIds.filter((other) => config.services[other].group === id);
			return members.length > 0 ? members : [id];
		});
	}

	function grant(ids, options = {}) {
		set([...consentIds.filter(has), ...expand(ids)], { ...options, partial: !decided() });
	}

	function revoke(ids) {
		const gone = expand(ids);
		if (consentIds.some((id) => gone.includes(id) && has(id))) {
			set(consentIds.filter((id) => !gone.includes(id) && has(id)), { partial: !decided() });
		}
	}

	function reset() {
		const secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = `${config.cookie}=; Max-Age=0; Path=${config.path}; SameSite=Lax${secure}`;
		showReopen();
	}

	// Google Consent Mode and the WP Consent API

	function syncConsentMode(consents) {
		if (typeof window.gtag !== 'function' || Object.keys(config.google).length === 0) {
			return;
		}
		const update = {};
		for (const [type, ids] of Object.entries(config.google)) {
			update[type] = ids.some((id) => config.essential.includes(id) || consents.includes(id)) ? 'granted' : 'denied';
		}
		window.gtag('consent', 'update', update);
	}

	function syncConsentApi(consents) {
		if (typeof window.wp_set_consent !== 'function') {
			return;
		}
		const everything = consentIds.every((id) => consents.includes(id));
		window.wp_set_consent('functional', 'allow');
		for (const [category, ids] of Object.entries(config.consentApi)) {
			const allowed = ids.length > 0 ? ids.every((id) => consents.includes(id)) : everything;
			window.wp_set_consent(category, allowed ? 'allow' : 'deny');
		}
	}

	// Gated scripts and embeds

	let activating = Promise.resolve();

	function activate() {
		activating = activating.then(activateScripts).catch(() => {});
		revealEmbeds();
	}

	// Inline scripts run on insertion, so each one waits for the external scripts before it.
	async function activateScripts() {
		const inert = document.querySelectorAll('script[type="text/plain"][data-silvertrail]');
		let pending = [];
		for (const node of inert) {
			if (!has(node.dataset.silvertrail) && !config.ungated.includes(node.dataset.silvertrail)) {
				continue;
			}
			const script = document.createElement('script');
			for (const { name, value } of node.attributes) {
				if (name !== 'type' && name !== 'data-silvertrail' && name !== 'data-silvertrail-type') {
					script.setAttribute(name, value);
				}
			}
			if (node.dataset.silvertrailType) {
				script.type = node.dataset.silvertrailType;
			}
			if (script.src) {
				script.async = false;
				pending.push(
					new Promise((resolve) => {
						script.addEventListener('load', resolve);
						script.addEventListener('error', resolve);
						setTimeout(resolve, SCRIPT_TIMEOUT);
					}),
				);
				node.replaceWith(script);
			} else {
				await Promise.all(pending);
				pending = [];
				script.textContent = node.textContent;
				node.replaceWith(script);
			}
		}
	}

	function reveal(placeholder) {
		const template = placeholder.querySelector('template');
		if (template) {
			placeholder.replaceWith(template.content.cloneNode(true));
		}
	}

	function revealEmbeds() {
		document.querySelectorAll('[data-silvertrail-embed]').forEach((placeholder) => {
			if (has(placeholder.dataset.silvertrailEmbed)) {
				reveal(placeholder);
			}
		});
	}

	// The trail: what this page held back and what it loaded from other hosts

	function serviceForUrl(url) {
		let parsed;
		try {
			parsed = new URL(url, window.location.href);
		} catch {
			return null;
		}
		const host = parsed.hostname.toLowerCase();
		const path = parsed.pathname.replace(/^\//, '');
		for (const [id, service] of Object.entries(config.services)) {
			for (const pattern of service.hosts) {
				const [patternHost, ...rest] = pattern.split('/');
				const patternPath = rest.join('/');
				if ((host === patternHost || host.endsWith('.' + patternHost)) && path.startsWith(patternPath)) {
					return id;
				}
			}
		}
		return null;
	}

	function trail() {
		const held = new Set();
		document.querySelectorAll('script[type="text/plain"][data-silvertrail]').forEach((node) => held.add(node.dataset.silvertrail));
		document.querySelectorAll('[data-silvertrail-embed]').forEach((node) => held.add(node.dataset.silvertrailEmbed));

		const loaded = new Map();
		for (const entry of performance.getEntriesByType('resource')) {
			const host = new URL(entry.name, window.location.href).hostname;
			if (host !== window.location.hostname && !loaded.has(host)) {
				loaded.set(host, { host, service: serviceForUrl(entry.name), startTime: entry.startTime });
			}
		}
		return { held: [...held], loaded: [...loaded.values()] };
	}

	function renderTrail() {
		const section = banner.querySelector('[data-silvertrail-trail]');
		if (!section) {
			return;
		}
		const texts = config.texts;
		const name = (id) => (config.services[id] ? config.services[id].name : id);
		const { held, loaded } = trail();
		const make = (tag, className, text = '') => {
			const node = document.createElement(tag);
			if (className) {
				node.className = className;
			}
			node.textContent = text;
			return node;
		};
		const list = (items) => {
			const ul = make('ul', 'silvertrail__trail-list');
			ul.append(...items.map((item) => make('li', '', item)));
			return ul;
		};
		const parts = document.createElement('div');
		parts.dataset.silvertrailTrailParts = '';
		parts.append(
			loaded.length === 0
				? make('p', 'silvertrail__trail-none', texts.none)
				: list(loaded.map((item) => (item.service ? `${name(item.service)} (${item.host})` : `${item.host} (${texts.unknown})`))),
		);
		if (held.length > 0) {
			parts.append(make('h3', 'silvertrail__trail-title', texts.held), list(held.map(name)));
		}
		section.querySelector('[data-silvertrail-trail-parts]')?.remove();
		section.append(parts);
		section.hidden = false;
	}

	// Banner

	const banner = document.getElementById('silvertrail-banner');
	const isPopover = !!banner && banner.hasAttribute('popover');

	function view(name) {
		banner.querySelectorAll('[data-silvertrail-view]').forEach((el) => {
			el.hidden = el.dataset.silvertrailView !== name;
		});
	}

	function syncSwitches() {
		const stored = readRaw();
		banner.querySelectorAll('[data-silvertrail-service]').forEach((input) => {
			if (!input.disabled) {
				input.checked = !!stored && stored.c.includes(input.value);
			}
		});
		banner.querySelectorAll('[data-silvertrail-group]').forEach(syncGroup);
	}

	function servicesOf(groupId) {
		return [...banner.querySelectorAll(`[data-silvertrail-service][data-group="${groupId}"]`)];
	}

	function syncGroup(groupInput) {
		if (groupInput.disabled) {
			return;
		}
		const states = servicesOf(groupInput.dataset.silvertrailGroup).map((s) => s.checked);
		groupInput.checked = states.length > 0 && states.every(Boolean);
		groupInput.indeterminate = states.some(Boolean) && !groupInput.checked;
	}

	function closable() {
		return decided() || !config.require;
	}

	// A link inside an embed placeholder goes away with the placeholder, so it does not replace the floating button.
	function showReopen() {
		const linked = [
			...document.querySelectorAll(
				'a[href$="#silvertrail"]:not([data-silvertrail-reopen]), [data-silvertrail-open]:not([data-silvertrail-reopen])',
			),
		].some((link) => !link.closest('[data-silvertrail-embed]'));
		document.querySelectorAll('[data-silvertrail-reopen]').forEach((button) => {
			button.hidden =
				read() === null ||
				(config.needsBanner && !decided()) ||
				(button.dataset.silvertrailReopen === 'auto' && linked);
		});
	}

	function show(name) {
		if (!banner) {
			return;
		}
		syncSwitches();
		view(name);
		if (name === 'preferences') {
			renderTrail();
		}
		const gpcNote = banner.querySelector('[data-silvertrail-gpc]');
		if (gpcNote) {
			gpcNote.hidden = !gpc;
		}
		const canClose = closable();
		banner.querySelector('[data-silvertrail-action="close"]').hidden = !canClose;
		const centred = config.layout === 'modal' || (config.layout === 'bar' && name === 'preferences');
		if (isPopover && canClose && !centred && !banner.open) {
			if (typeof banner.showPopover !== 'function') {
				banner.show();
			} else if (!popoverOpen()) {
				banner.popover = 'auto';
				banner.showPopover();
			}
		} else {
			banner.setAttribute('closedby', canClose ? 'any' : 'none');
			if (popoverOpen()) {
				banner.hidePopover();
			} else if (banner.open && !banner.matches(':modal')) {
				banner.close();
			}
			if (!banner.open) {
				banner.showModal();
			}
		}
	}

	function popoverOpen() {
		return typeof banner.hidePopover === 'function' && banner.matches(':popover-open');
	}

	function hide() {
		if (!banner) {
			return;
		}
		if (popoverOpen()) {
			banner.hidePopover();
		} else if (banner.open) {
			banner.close();
		}
	}

	function open() {
		show('preferences');
	}

	function choose(ids, options = {}) {
		set(ids, options);
		hide();
	}

	function bind() {
		document.addEventListener('click', (event) => {
			const opener = event.target.closest('a[href$="#silvertrail"], [data-silvertrail-open]');
			if (opener) {
				event.preventDefault();
				open();
				return;
			}
			const granter = event.target.closest('[data-silvertrail-grant]');
			if (granter) {
				grant(granter.dataset.silvertrailGrant.split(/\s+/), { source: 'embed' });
				return;
			}
			const loader = event.target.closest('[data-silvertrail-load]');
			if (loader) {
				const placeholder = loader.closest('[data-silvertrail-embed]');
				const remember = placeholder.querySelector('[data-silvertrail-remember]');
				if (remember && remember.checked) {
					grant(placeholder.dataset.silvertrailEmbed, { source: 'embed' });
				} else {
					reveal(placeholder);
				}
			}
		});

		if (!banner) {
			return;
		}

		banner.addEventListener('click', (event) => {
			const button = event.target.closest('[data-silvertrail-action]');
			if (!button) {
				return;
			}
			const action = button.dataset.silvertrailAction;
			if (action === 'accept-all') {
				choose(consentIds);
			} else if (action === 'reject-all') {
				choose([]);
			} else if (action === 'save') {
				choose([...banner.querySelectorAll('[data-silvertrail-service]:checked')].map((input) => input.value));
			} else if (action === 'preferences') {
				show('preferences');
			} else if (action === 'close') {
				hide();
			}
		});

		banner.addEventListener('change', (event) => {
			const input = event.target;
			if (input.matches('[data-silvertrail-group]')) {
				servicesOf(input.dataset.silvertrailGroup).forEach((service) => {
					if (!service.disabled) {
						service.checked = input.checked;
					}
				});
			} else if (input.matches('[data-silvertrail-service]')) {
				const group = banner.querySelector(`[data-silvertrail-group="${input.dataset.group}"]`);
				group && syncGroup(group);
			}
		});

		// Without closedby (Safari) Escape fires cancel, and a repeated Escape cannot be prevented, so a close while a choice is required reopens.
		banner.addEventListener('cancel', (event) => {
			if (!closable()) {
				event.preventDefault();
			}
		});
		banner.addEventListener('close', () => {
			if (!closable()) {
				show('entrance');
			}
		});

		// Safari has no closedby, so a press and release on the backdrop closes the modal here.
		if (!('closedBy' in HTMLDialogElement.prototype)) {
			const outside = (event) => {
				const r = banner.getBoundingClientRect();
				return event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom;
			};
			let pressedOutside = false;
			banner.addEventListener('pointerdown', (event) => {
				pressedOutside = outside(event);
			});
			banner.addEventListener('pointerup', (event) => {
				if (pressedOutside && outside(event) && banner.matches(':modal') && closable()) {
					hide();
				}
			});
		}
	}

	// Lets a site decide before the banner opens, for example by region.
	function beforePrompt() {
		let answer = Promise.resolve('prompt');
		const event = new CustomEvent('silvertrail:before-prompt', {
			detail: {
				respondWith(promise) {
					answer = Promise.resolve(promise);
				},
			},
		});
		window.dispatchEvent(event);
		const timeout = new Promise((resolve) => setTimeout(() => resolve('prompt'), 3000));
		return Promise.race([answer, timeout]).catch(() => 'prompt');
	}

	function registerTools() {
		if (config.webmcp && consentIds.length > 0 && typeof document.modelContext?.registerTool === 'function') {
			import(config.webmcp)
				.then(({ register }) => register({ config, consentIds, serviceIds, has, decided, trail, reset, choose }))
				.catch(() => {});
		}
	}

	async function start() {
		bind();
		window.wp_consent_type = 'optin';
		document.dispatchEvent(new CustomEvent('wp_consent_type_defined'));
		const stored = read();
		if (stored) {
			syncConsentApi(stored.c);
		}
		activate();
		showReopen();

		if (config.check) {
			return;
		}
		registerTools();
		if (gpc && config.needsBanner && !decided()) {
			set([], { silent: true, source: 'gpc' });
		}
		if (window.location.hash === '#silvertrail') {
			open();
			return;
		}
		// Global Privacy Control has answered already, and a site without a banner can still decide by region.
		if (decided() || consentIds.length === 0 || gpc) {
			return;
		}

		const answer = await beforePrompt();
		if (decided()) {
			return;
		}
		if (answer === 'accept-all') {
			set(consentIds, { silent: true, source: 'hook' });
		} else if (answer === 'reject-all') {
			set([], { silent: true, source: 'hook' });
		} else if (config.needsBanner) {
			show('entrance');
		}
	}

	window.addEventListener(CHANGE, (event) => {
		syncConsentMode(event.detail.consents);
		syncConsentApi(event.detail.consents);
		activate();
		showReopen();
	});
	window.addEventListener('hashchange', () => {
		if (window.location.hash === '#silvertrail') {
			open();
		}
	});

	window.silvertrail = { has, set, grant, revoke, open, decided, trail, reset };

	let started = false;
	const once = () => {
		if (!started) {
			started = true;
			start();
		}
	};
	if (document.readyState === 'complete') {
		once();
	} else {
		document.addEventListener('DOMContentLoaded', once);
		window.addEventListener('load', once);
	}
})();
