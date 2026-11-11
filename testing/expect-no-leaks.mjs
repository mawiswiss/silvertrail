// Playwright helpers for the Leak check. Import them from the plugin folder, because they read
// ../assets/scan-findings.js, and use a fresh browser context, so no consent cookie is set:
//
//   import { expectConsentWorks, expectNoLeaks } from './path/to/silvertrail/testing/expect-no-leaks.mjs';
//   test('consent works', async ({ page }) => {
//     await expectConsentWorks(page, ['/', '/contact/'], { allow: ['cdn.example.com'] });
//   });

export async function collectLeaks(page, url, { allow = [], wait = 1500 } = {}) {
	const hosts = new Set();
	const listen = (request) => {
		const { hostname } = new URL(request.url());
		if (hostname) {
			hosts.add(hostname);
		}
	};
	page.on('request', listen);
	try {
		await page.goto(url, { waitUntil: 'load' });
		await page.waitForTimeout(wait);
	} finally {
		page.off('request', listen);
	}
	const own = new URL(page.url()).hostname;
	const allowed = (host) => allow.some((entry) => host === entry || host.endsWith(`.${entry}`));
	return [...hosts].filter((host) => host !== own && !allowed(host)).sort();
}

export async function expectNoLeaks(page, url, options) {
	const leaks = await collectLeaks(page, url, options);
	if (leaks.length > 0) {
		throw new Error(`${url} contacted other hosts before consent: ${leaks.join(', ')}`);
	}
}

const TYPES = { script: 'script', document: 'frame', stylesheet: 'style', font: 'style', image: 'image' };

async function analysis() {
	await import(new URL('../assets/scan-findings.js', import.meta.url).href);
	return globalThis.silvertrailFindings;
}

// Counts what the admin's Leak check counts: the page's own requests and the documents of its frames.
function recorder(page) {
	let seen = null;
	const listen = (request) => {
		if (!seen) {
			return;
		}
		let frame;
		try {
			frame = request.frame();
		} catch {
			return;
		}
		const main = page.mainFrame();
		if (frame === main && !request.isNavigationRequest()) {
			seen.push({ url: request.url(), type: TYPES[request.resourceType()] ?? 'other' });
		} else if (request.isNavigationRequest() && frame.parentFrame() === main) {
			seen.push({ url: request.url(), type: 'frame' });
		}
	};
	page.on('request', listen);
	return {
		start: () => (seen = []),
		stop: () => {
			const found = seen;
			seen = null;
			return found;
		},
		off: () => page.off('request', listen),
	};
}

const sweep = (page) =>
	page.evaluate(async () => {
		const height = document.documentElement.scrollHeight;
		for (let y = 0, steps = 0; y < height && steps < 20; y += innerHeight, steps++) {
			scrollTo(0, y);
			await new Promise((resolve) => setTimeout(resolve, 100));
		}
		scrollTo(0, height);
	});

async function watch(page, recorder, wait, action) {
	recorder.start();
	await action();
	await sweep(page);
	await page.waitForTimeout(wait);
	return recorder.stop();
}

/**
 * Runs the Leak check's scenarios on each page: a first visit, declining, agreeing to everything,
 * a reload, and agreeing to each service on its own. Returns the same findings as the admin.
 */
export async function checkConsent(page, urls, { wait = 1500 } = {}) {
	const findings = await analysis();
	const context = page.context();
	const cookies = await context.cookies();
	const known = new Set(cookies.map((cookie) => cookie.name));
	const recording = recorder(page);
	const config = { services: {}, essential: [], ungated: [] };
	const pages = [];
	const isolated = [];

	const visit = async (url) => {
		await context.clearCookies();
		await context.addCookies(cookies);
		if (page.url().startsWith('http') && new URL(page.url()).origin === new URL(url, page.url()).origin) {
			await page.evaluate(() => {
				localStorage.clear();
				sessionStorage.clear();
			});
		}
		const first = await watch(page, recording, wait, () => page.goto(url, { waitUntil: 'load' }));
		const state = await page.evaluate(() => ({
			config: window.silvertrailConfig && typeof window.silvertrail?.set === 'function' ? window.silvertrailConfig : null,
			held: window.silvertrail?.trail().held ?? [],
			scripts: [...document.querySelectorAll('script[src]')].map((node) => ({ src: node.src, id: node.id })),
			storage: [...Object.keys(localStorage), ...Object.keys(sessionStorage)],
		}));
		if (!state.config) {
			throw new Error(`Silvertrail does not run on ${url}.`);
		}
		const set = (await context.cookies(page.url())).map((cookie) => cookie.name).filter((name) => !known.has(name));
		return { ...state, first, stored: [...set, ...state.storage] };
	};
	const choose = (ids) => watch(page, recording, wait, () => page.evaluate((list) => window.silvertrail.set(list), ids));

	try {
		for (const url of [urls].flat()) {
			const first = await visit(url);
			const target = page.url();
			Object.assign(config.services, first.config.services);
			config.essential = first.config.essential;
			config.ungated = first.config.ungated;
			const declined = await choose([]);
			const agreed = await choose(Object.keys(first.config.services));
			const reloaded = await watch(page, recording, wait, () => page.reload({ waitUntil: 'load' }));
			const entry = { url: target, first: first.first, declined, agreed, reloaded, held: first.held, stored: first.stored, scripts: first.scripts };
			pages.push(entry);

			for (const id of findings.queue(entry, config.services, config.essential, isolated)) {
				const alone = await visit(target);
				isolated.push({ service: id, page: target, first: alone.first, scripts: alone.scripts, agreed: await choose([id]) });
			}
		}
	} finally {
		recording.off();
		await context.clearCookies();
		await context.addCookies(cookies);
	}
	return findings.analyze({ ...config, pages, isolated });
}

export function describeFindings(result, { allow = [] } = {}) {
	const allowed = (key) => allow.some((entry) => key === entry || key.endsWith(`.${entry}`));
	const where = (pages) => (pages.length > 0 ? ` on ${pages.map((url) => new URL(url).pathname + new URL(url).search).join(', ')}` : '');
	const name = (id) => result.services.find((row) => row.id === id)?.name ?? id;
	const lines = [];
	for (const item of result.guard.filter((each) => !allowed(each.host))) {
		const when = { before: 'before a choice', declined: 'after the visitor declines', any: `whenever the visitor agrees to any of ${item.services.map(name).join(', ')}` }[item.when];
		lines.push(`${item.host} is contacted ${when}${where(item.pages)}`);
	}
	for (const row of result.services.filter((each) => !allowed(each.id))) {
		if (row.before === 'early') {
			lines.push(`${row.name} loads before a choice${where(row.pages)}`);
		} else if (row.declined === 'contacts') {
			lines.push(`${row.name} is contacted after the visitor declines${where(row.pages)}`);
		}
		if (row.agreed === 'any') {
			lines.push(`${row.name} starts when the visitor agrees to any service`);
		}
		for (const item of row.also) {
			lines.push(`Agreeing to ${row.name} also starts ${item.name}${item.types.length > 0 ? `, because both grant ${item.types.join(', ')}` : ''}`);
		}
		if (row.agreed === 'reload' && row.before !== 'early' && row.declined !== 'contacts') {
			lines.push(`${row.name} starts only after a reload${where(row.pages)}`);
		}
	}
	for (const item of result.late.filter((each) => !allowed(each.host))) {
		lines.push(`${item.host} is contacted only after a reload${where(item.pages)}`);
	}
	return lines;
}

export async function expectConsentWorks(page, urls, { allow = [], wait } = {}) {
	const lines = describeFindings(await checkConsent(page, urls, { wait }), { allow });
	if (lines.length > 0) {
		throw new Error(`Consent does not work as it should:\n- ${lines.join('\n- ')}`);
	}
}
