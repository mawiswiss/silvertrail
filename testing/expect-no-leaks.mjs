// Playwright helpers that fail a test when a page contacts another host before the
// visitor decides. Use a fresh browser context, so no consent cookie is set:
//
//   import { expectNoLeaks } from './path/to/silvertrail/testing/expect-no-leaks.mjs';
//   test('home page', async ({ page }) => {
//     await expectNoLeaks(page, '/', { allow: ['cdn.example.com'] });
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
