// No import or export: the admin loads this as a classic script, and the Playwright helper imports it for its side effect.
globalThis.silvertrailFindings = (() => {
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

	const knownName = (host) => {
		const match = Object.keys(NAMES).find((known) => host === known || host.endsWith(`.${known}`));
		return match ? NAMES[match] : '';
	};

	const hostOf = (url) => {
		try {
			return new URL(url).hostname.toLowerCase();
		} catch {
			return '';
		}
	};

	function matches(url, pattern) {
		const [host, ...rest] = pattern.toLowerCase().replace(/^(https?:)?\/\//, '').replace(/^www\./, '').split('/');
		const hostname = url.hostname.toLowerCase();
		return (hostname === host || hostname.endsWith(`.${host}`)) && url.pathname.replace(/^\//, '').startsWith(rest.join('/'));
	}

	// Google Analytics and Google Ads load the same gtag URL, so the tag ID in it decides.
	function serviceFor(url, services) {
		const ids = Object.keys(services).filter((id) => (services[id].hosts || []).some((pattern) => matches(url, pattern)));
		const tagged = Object.keys(services).find((id) => services[id].tagId && services[id].tagId === url.searchParams.get('id'));
		return ids.length > 0 ? (tagged ?? ids[0]) : null;
	}

	function group(observations, page, services) {
		const own = hostOf(page);
		const found = new Map();
		for (const item of observations) {
			let url;
			try {
				url = new URL(item.url, page);
			} catch {
				continue;
			}
			const host = url.hostname.toLowerCase();
			if (!/^https?:$/.test(url.protocol) || host === own) {
				continue;
			}
			const service = serviceFor(url, services);
			const key = service ?? host;
			if (!found.has(key)) {
				found.set(key, { key, service, host, urls: [], types: new Set() });
			}
			found.get(key).urls.push(url);
			found.get(key).types.add(item.type);
		}
		return found;
	}

	function present(page, services) {
		const seen = new Set(page.held);
		for (const window of [page.first, page.declined, page.agreed, page.reloaded]) {
			group(window, page.url, services).forEach((entry) => entry.service && seen.add(entry.service));
		}
		return [...seen].filter((id) => services[id]);
	}

	// A service is isolated on the first page it is on, and again wherever agreeing starts something no run explained yet.
	function queue(page, services, essential, isolated) {
		const ids = present(page, services).filter((id) => !essential.includes(id));
		const explained = new Set(isolated.flatMap((run) => [...group(run.agreed, run.page, services).keys()]));
		const before = new Set([...group(page.first, page.url, services).keys(), ...group(page.declined, page.url, services).keys()]);
		const fresh = [...group(page.agreed, page.url, services).keys()].some((key) => !explained.has(key) && !before.has(key));
		return ids.filter((id) => fresh || !isolated.some((run) => run.service === id));
	}

	function kind(entry, scripts) {
		const script = scripts.find((node) => hostOf(node.src) === entry.host);
		if (script) {
			const handle = (String(script.id).match(/^(.+)-js$/) || [])[1] ?? '';
			return { what: handle ? 'script' : 'scriptRaw', handle };
		}
		if (entry.types.has('frame')) {
			return { what: 'frame', handle: '' };
		}
		if (entry.types.has('style')) {
			return { what: 'font', handle: '' };
		}
		return { what: entry.types.has('image') ? 'image' : 'other', handle: '' };
	}

	const union = (lists) => [...new Set(lists.flat())];

	// Trackers switch subdomains between loads, and Chrome often leaves the files a Google Fonts stylesheet loads out of Resource Timing.
	const family = (host) => knownName(host) || host.split('.').slice(-2).join('.');

	function collect(pairs) {
		const found = new Map();
		for (const [url, map] of pairs) {
			for (const [key, entry] of map) {
				if (!found.has(key)) {
					found.set(key, { entry, pages: [] });
				}
				if (!found.get(key).pages.includes(url)) {
					found.get(key).pages.push(url);
				}
			}
		}
		return found;
	}

	function analyze({ services, essential = [], ungated = [], presets = {}, pages, isolated = [] }) {
		const watched = (id) => !essential.includes(id) && !ungated.includes(id);
		const runs = isolated.map((run) => ({ service: run.service, page: run.page, agreed: group(run.agreed, run.page, services) }));
		const windows = pages.map((page) => {
			const own = isolated.filter((run) => run.page === page.url);
			const agreed = group(page.agreed, page.url, services);
			const inPlace = new Set([...agreed.keys(), ...runs.filter((run) => run.page === page.url).flatMap((run) => [...run.agreed.keys()])]);
			return {
				url: page.url,
				first: group([...page.first, ...own.flatMap((run) => run.first)], page.url, services),
				declined: group(page.declined, page.url, services),
				agreed,
				inPlace,
				reloaded: group(page.reloaded, page.url, services),
				scripts: [...page.scripts, ...own.flatMap((run) => run.scripts)],
			};
		});

		const early = collect(windows.map((window) => [window.url, window.first]));
		const declined = collect(windows.map((window) => [window.url, window.declined]));
		const reloadOnly = collect(
			windows.map((window) => {
				const earlier = new Set([...window.inPlace, ...window.first.keys(), ...window.declined.keys()]);
				const families = new Set([...earlier].filter((key) => !services[key]).map(family));
				const late = ([key, entry]) => !earlier.has(key) && (entry.service || !families.has(family(key)));
				return [window.url, new Map([...window.reloaded].filter(late))];
			}),
		);
		const agreed = collect([
			...windows.map((window) => [window.url, new Map([...window.agreed, ...window.reloaded])]),
			...runs.map((run) => [run.page, run.agreed]),
		]);
		const startedBy = new Map();
		for (const run of runs) {
			for (const key of run.agreed.keys()) {
				if (key !== run.service) {
					startedBy.set(key, union([startedBy.get(key) ?? [], [run.service]]));
				}
			}
		}
		const byAny = (key) => (startedBy.get(key) ?? []).length > 1;
		const held = union(pages.map((page) => page.held));

		const rows = Object.entries(services).map(([id, service]) => {
			if (essential.includes(id)) {
				return { id, name: service.name, before: 'essential', declined: 'essential', agreed: 'essential', also: [], pages: [] };
			}
			const cookieless = ungated.includes(id);
			const row = {
				id,
				name: service.name,
				before: early.has(id) ? (cookieless ? 'cookieless' : 'early') : 'quiet',
				declined: declined.has(id) ? (cookieless ? 'cookieless' : 'contacts') : 'quiet',
				agreed: 'absent',
				also: [],
				pages: [],
			};
			if (!cookieless && byAny(id)) {
				row.agreed = 'any';
			} else if (!cookieless && reloadOnly.has(id)) {
				row.agreed = 'reload';
			} else if (agreed.has(id) || early.has(id) || declined.has(id)) {
				row.agreed = 'starts';
			} else if (held.includes(id)) {
				row.agreed = 'unseen';
			}
			const worst = { early, contacts: declined, reload: reloadOnly, any: agreed }[row.before === 'early' ? 'early' : row.declined === 'contacts' ? 'contacts' : row.agreed];
			row.pages = worst?.get(id)?.pages ?? [];
			for (const run of runs.filter((each) => each.service === id)) {
				for (const key of run.agreed.keys()) {
					if (key !== id && services[key] && watched(key) && !early.has(key) && !declined.has(key) && !byAny(key) && !row.also.some((item) => item.id === key)) {
						const types = (service.google || []).filter((type) => (services[key].google || []).includes(type));
						row.also.push({ id: key, name: services[key].name, types });
					}
				}
			}
			return row;
		});

		const hosts = new Map();
		for (const found of [early, declined, reloadOnly, agreed]) {
			for (const [key, { entry }] of found) {
				if (!entry.service && !hosts.has(key)) {
					hosts.set(key, entry);
				}
			}
		}
		const scripts = windows.flatMap((window) => window.scripts);
		const guarded = new Set([...early.keys(), ...declined.keys()].map(knownName).filter(Boolean));
		const guard = [];
		const late = [];
		const belongs = [];
		const after = [];
		for (const [key, entry] of hosts) {
			const name = knownName(key) || key;
			if (early.has(key) || declined.has(key) || byAny(key)) {
				const when = early.has(key) ? 'before' : declined.has(key) ? 'declined' : 'any';
				const seen = { before: early, declined, any: agreed }[when].get(key).pages;
				const kin = knownName(key) && guard.find((item) => item.when === when && knownName(item.host) === knownName(key));
				if (kin) {
					kin.pages = union([kin.pages, seen]);
					kin.services = union([kin.services, startedBy.get(key) ?? []]);
					continue;
				}
				const preset = when === 'before' ? (Object.keys(presets).find((id) => presets[id].hosts.some((pattern) => entry.urls.some((url) => matches(url, pattern)))) ?? '') : '';
				guard.push({ host: key, name: preset ? presets[preset].name : name, when, preset, services: startedBy.get(key) ?? [], pages: seen, ...kind(entry, scripts) });
			} else if (reloadOnly.has(key)) {
				late.push({ host: key, name, pages: reloadOnly.get(key).pages });
			} else if (guarded.has(knownName(key))) {
				continue;
			} else if (startedBy.has(key)) {
				belongs.push({ host: key, name, service: startedBy.get(key)[0], pages: agreed.get(key).pages });
			} else {
				after.push({ host: key, name, pages: agreed.get(key).pages });
			}
		}

		const problems =
			guard.length +
			rows.filter((row) => row.before === 'early' || row.declined === 'contacts' || row.agreed === 'any').length +
			rows.reduce((count, row) => count + row.also.length, 0);
		const warnings = rows.filter((row) => row.agreed === 'reload' && row.before !== 'early' && row.declined !== 'contacts').length + late.length;

		return {
			pages: pages.map((page) => page.url),
			services: rows,
			guard,
			late,
			belongs,
			after,
			held,
			stored: union(pages.map((page) => page.stored)),
			problems,
			warnings,
		};
	}

	return { analyze, queue, knownName };
})();
