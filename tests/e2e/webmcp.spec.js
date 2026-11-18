import { test, expect } from './test.js';

const ALL_IDS = ['google-tag-manager', 'youtube', 'google-maps', 'test-analytics', 'test-gated', 'test-video', 'test-pixel'];
const TOOLS = ['allow_only_essential', 'get_cookie_consent', 'get_page_trail', 'reset_cookie_consent', 'set_cookie_consent'];

// WebMCP needs a flag or a trial token, so a stub stands in for document.modelContext.
const withWebMcp = (page) =>
	page.addInitScript(() => {
		window.silvertrailTools = {};
		Object.defineProperty(document, 'modelContext', {
			configurable: true,
			value: {
				registerTool: async (tool) => {
					window.silvertrailTools[tool.name] = tool;
				},
			},
		});
	});

const tools = (page) => page.evaluate(() => Object.keys(window.silvertrailTools || {}).sort());
const call = (page, name, input = {}) => page.evaluate(([tool, args]) => window.silvertrailTools[tool].execute(args, {}), [name, input]);
const ready = (page) => expect.poll(() => tools(page)).toEqual(TOOLS);
const ran = (page) => page.evaluate(() => window.silvertrailTest || []);
const cookie = async (context) => {
	const found = (await context.cookies()).find((c) => c.name === 'silvertrail');
	return found ? JSON.parse(decodeURIComponent(found.value)) : null;
};

test.describe('WebMCP', () => {
	test('registers the five tools with their annotations', async ({ page }) => {
		await withWebMcp(page);
		await page.goto('/');
		await ready(page);

		const annotations = await page.evaluate(() =>
			Object.fromEntries(Object.values(window.silvertrailTools).map((tool) => [tool.name, tool.annotations])),
		);
		expect(annotations.get_cookie_consent.readOnlyHint).toBe(true);
		expect(annotations.get_page_trail.readOnlyHint).toBe(true);
		expect(annotations.set_cookie_consent.consequentialHint).toBe(true);
		expect(annotations.allow_only_essential).toEqual({ readOnlyHint: false, consequentialHint: false });
		expect(annotations.reset_cookie_consent.debugging).toBe(true);
	});

	test('reads the services before a choice', async ({ page }) => {
		await withWebMcp(page);
		await page.goto('/');
		await ready(page);

		const state = await call(page, 'get_cookie_consent');
		expect(state.decided).toBe(false);
		expect(state.services.map((s) => s.id)).toEqual(ALL_IDS);
		expect(state.services.every((s) => s.allowed === false)).toBe(true);
		expect(state.services.find((s) => s.id === 'test-analytics')).toMatchObject({
			provider: 'Example Analytics',
			purpose: 'Counts visits in the e2e suite.',
		});
		expect(state.services.find((s) => s.id === 'test-gated')).not.toHaveProperty('provider');
		expect(state.essential).toContain('Silvertrail');
	});

	test('allow only essential closes the banner and runs nothing', async ({ page, context }) => {
		await withWebMcp(page);
		await page.goto('/');
		await ready(page);
		await page.evaluate(() => window.addEventListener('silvertrail:change', (e) => (window.silvertrailSource = e.detail.source)));

		expect(await call(page, 'allow_only_essential')).toEqual({ allowed: [] });

		await expect(page.getByRole('dialog')).toBeHidden();
		expect(await ran(page)).toEqual([]);
		expect((await cookie(context)).c).toEqual([]);
		expect(await page.evaluate(() => window.silvertrailSource)).toBe('agent');
	});

	test('allow only essential closes the modal and frees the page', async ({ page, context }) => {
		await context.addCookies([{ name: 'silvertrail_test_settings', value: JSON.stringify({ layout: 'modal' }), url: 'http://localhost:8892' }]);
		await withWebMcp(page);
		await page.goto('/');
		await ready(page);
		await expect(page.locator('#silvertrail-banner.silvertrail--modal')).toBeVisible();
		const home = page.getByRole('banner').getByRole('link', { name: 'silvertrail', exact: true });
		await expect(home.click({ trial: true, timeout: 1000 })).rejects.toThrow();

		await call(page, 'allow_only_essential');

		await expect(page.getByRole('dialog')).toBeHidden();
		await home.click({ trial: true, timeout: 2000 });
		expect((await cookie(context)).c).toEqual([]);
	});

	test('set cookie consent returns an error for an unknown service and runs an allowed one', async ({ page, context }) => {
		await withWebMcp(page);
		await page.goto('/');
		await ready(page);

		expect(await call(page, 'set_cookie_consent', { services: ['nope'] })).toEqual({ error: 'Unknown service nope.', services: ALL_IDS });
		expect((await call(page, 'set_cookie_consent', {})).error).toBe('Pass services as a list of service ids.');
		await expect(page.getByRole('dialog')).toBeVisible();

		expect(await call(page, 'set_cookie_consent', { services: ['google-tag-manager'] })).toEqual({ allowed: ['google-tag-manager'] });

		await expect(page.getByRole('dialog')).toBeHidden();
		await expect.poll(() => page.evaluate(() => window.silvertrailGtm)).toBe(1);
		expect((await cookie(context)).c).toEqual(['google-tag-manager']);
	});

	test('the page trail shows what was held back and what loaded', async ({ page }) => {
		await withWebMcp(page);
		await page.goto('/');
		await ready(page);

		const before = await call(page, 'get_page_trail');
		expect(before.held).toContainEqual({ id: 'google-tag-manager', name: 'Google Tag Manager' });
		expect(before.loaded.map((item) => item.host)).not.toContain('www.googletagmanager.com');

		await call(page, 'set_cookie_consent', { services: ['google-tag-manager'] });
		await expect.poll(() => page.evaluate(() => window.silvertrailGtm)).toBe(1);

		const after = await call(page, 'get_page_trail');
		expect(after.held.map((item) => item.id)).not.toContain('google-tag-manager');
		expect(after.loaded).toContainEqual({ host: 'www.googletagmanager.com', service: 'google-tag-manager', allowed: true });
	});

	test('reset brings the banner back on the next load', async ({ page, context }) => {
		await withWebMcp(page);
		await page.goto('/');
		await ready(page);
		await call(page, 'allow_only_essential');

		expect(await call(page, 'reset_cookie_consent')).toMatchObject({ decided: false });
		expect(await cookie(context)).toBeNull();

		await page.reload();
		await expect(page.getByRole('dialog', { name: 'Privacy settings' })).toBeVisible();
	});

	test('without WebMCP the tools module never loads', async ({ page }) => {
		const requests = [];
		page.on('request', (request) => request.url().includes('webmcp.js') && requests.push(request.url()));
		await page.goto('/');

		await expect(page.getByRole('dialog', { name: 'Privacy settings' })).toBeVisible();
		expect(requests).toEqual([]);
	});

	test('turned off in code, no tool registers', async ({ page, context }) => {
		await context.addCookies([{ name: 'silvertrail_test_code', value: JSON.stringify({ webmcp: false }), url: 'http://localhost:8892' }]);
		await withWebMcp(page);
		await page.goto('/');

		await expect(page.getByRole('dialog', { name: 'Privacy settings' })).toBeVisible();
		expect(await tools(page)).toEqual([]);
	});
});
