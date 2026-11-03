(() => {
	const config = window.silvertrailConfig;
	if (!config || typeof window.silvertrail?.has === 'function') {
		return;
	}

	const CHANGE = 'silvertrail:change';
	const SCRIPT_TIMEOUT = 10000;
	const serviceIds = Object.keys(config.services);
	const consentIds = serviceIds.filter((id) => !config.essential.includes(id));

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
	function read() {
		const data = readRaw();
		return data && data.n >= config.n ? data : null;
	}

	// A grant from an embed before any banner choice leaves the other services undecided.
	function decided() {
		const data = read();
		return data !== null && !data.p;
	}

	function has(id) {
		if (config.essential.includes(id)) {
			return true;
		}
		const data = read();
		return !!data && data.c.includes(id);
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
		if (stored && stored.i) {
			data.i = stored.i;
		}
		const secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = `${config.cookie}=${encodeURIComponent(JSON.stringify(data))}; Max-Age=${config.maxAge}; Path=${config.path}; SameSite=Lax${secure}`;
		window.dispatchEvent(new CustomEvent(CHANGE, { detail: { consents, silent, source } }));
	}

	function grant(id) {
		set([...consentIds.filter(has), id], { partial: !decided() });
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
			if (!has(node.dataset.silvertrail)) {
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

	// Banner

	const banner = document.getElementById('silvertrail-banner');
	const isBox = !!banner && banner.hasAttribute('popover');

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

	function show(name) {
		if (!banner) {
			return;
		}
		syncSwitches();
		view(name);
		const closable = decided();
		banner.querySelector('[data-silvertrail-action="close"]').hidden = !closable;
		if (isBox) {
			if (typeof banner.showPopover === 'function') {
				banner.matches(':popover-open') || banner.showPopover();
			} else if (!banner.open) {
				banner.show();
			}
		} else {
			banner.setAttribute('closedby', closable ? 'closerequest' : 'none');
			if (!banner.open) {
				banner.showModal();
			}
		}
	}

	function hide() {
		if (!banner) {
			return;
		}
		if (isBox && typeof banner.hidePopover === 'function') {
			banner.matches(':popover-open') && banner.hidePopover();
		} else if (banner.open) {
			banner.close();
		}
	}

	function open() {
		show('preferences');
	}

	function choose(ids) {
		set(ids);
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
			const loader = event.target.closest('[data-silvertrail-load]');
			if (loader) {
				const placeholder = loader.closest('[data-silvertrail-embed]');
				const remember = placeholder.querySelector('[data-silvertrail-remember]');
				if (remember && remember.checked) {
					grant(placeholder.dataset.silvertrailEmbed);
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
				view('preferences');
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

		// Without closedby (Safari) Escape fires cancel, and a repeated Escape cannot be prevented, so a close without a decision reopens.
		banner.addEventListener('cancel', (event) => {
			if (!decided()) {
				event.preventDefault();
			}
		});
		banner.addEventListener('close', () => {
			if (!decided()) {
				show('entrance');
			}
		});
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

	async function start() {
		bind();
		activate();

		if (window.location.hash === '#silvertrail') {
			open();
			return;
		}
		if (decided() || !config.needsBanner) {
			return;
		}
		if (config.gpc && navigator.globalPrivacyControl === true) {
			set([], { silent: true, source: 'gpc' });
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
		} else {
			show('entrance');
		}
	}

	window.addEventListener(CHANGE, activate);
	window.addEventListener('hashchange', () => {
		if (window.location.hash === '#silvertrail') {
			open();
		}
	});

	window.silvertrail = { has, set, grant, open, decided };

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
