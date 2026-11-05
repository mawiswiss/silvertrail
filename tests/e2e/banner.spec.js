import { test, expect } from './test.js';

const ALL_IDS = ['google-tag-manager', 'test-analytics', 'test-gated', 'test-video', 'test-pixel'];
// Footer scripts print before the fixture's inline tags, and the after code waits for its file.
const ALL_RAN = ['gated-src', 'gated-after', 'analytics', 'pixel'];

const ran = (page) => page.evaluate(() => window.silvertrailTest || []);
const cookie = async (context) => {
	const found = (await context.cookies()).find((c) => c.name === 'silvertrail');
	return found ? JSON.parse(decodeURIComponent(found.value)) : null;
};

test.describe('banner', () => {
	test('opens on the first view and runs nothing before a choice', async ({ page }) => {
		await page.goto('/');

		const banner = page.getByRole('dialog', { name: 'Privacy settings' });
		await expect(banner).toBeVisible();
		expect(await ran(page)).toEqual([]);
	});

	test('accept all runs the gated scripts without a reload', async ({ page, context }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Accept all' }).first().click();

		await expect(page.getByRole('dialog')).toBeHidden();
		await expect.poll(() => ran(page)).toEqual(ALL_RAN);
		expect((await cookie(context)).c).toEqual(ALL_IDS);
	});

	test('only essential runs nothing and stores no service', async ({ page, context }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Only essential' }).first().click();

		await expect(page.getByRole('dialog')).toBeHidden();
		expect(await ran(page)).toEqual([]);
		expect((await cookie(context)).c).toEqual([]);
	});

	test('a choice in the preferences runs only that service', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Choose' }).click();
		await page.getByRole('checkbox', { name: 'Statistics' }).check();
		await page.getByRole('button', { name: 'Save choices' }).click();

		await expect.poll(() => ran(page)).toEqual(['gated-src', 'gated-after', 'analytics']);
	});

	test('the buttons stay in view while the preferences scroll', async ({ page }) => {
		await page.setViewportSize({ width: 375, height: 500 });
		await page.goto('/');
		await page.getByRole('button', { name: 'Choose' }).click();

		await expect(page.getByRole('button', { name: 'Save choices' })).not.toBeInViewport();
		await expect(page.getByRole('button', { name: 'Accept all' })).toBeInViewport();
		await page.getByRole('button', { name: 'Save choices' }).scrollIntoViewIfNeeded();
		await expect(page.getByRole('button', { name: 'Only essential' })).toBeInViewport();
	});

	test('escape does not dismiss the banner without a choice', async ({ page }) => {
		await page.goto('/');
		await page.keyboard.press('Escape');
		await page.keyboard.press('Escape');

		await expect(page.getByRole('dialog', { name: 'Privacy settings' })).toBeVisible();
	});

	test('a returning visitor sees no banner and the stored choice runs', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Accept all' }).first().click();
		await page.reload();

		await expect(page.getByRole('dialog')).toBeHidden();
		await expect.poll(() => ran(page)).toEqual(ALL_RAN);
	});

	test('the #silvertrail link reopens the preferences with the stored choice', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Choose' }).click();
		await page.getByRole('checkbox', { name: 'Statistics' }).check();
		await page.getByRole('button', { name: 'Save choices' }).click();

		await page.goto('/#silvertrail');
		const banner = page.getByRole('dialog', { name: 'Privacy settings' });
		await expect(banner).toBeVisible();
		await expect(page.getByRole('checkbox', { name: 'Statistics' })).toBeChecked();
		await expect(page.getByRole('checkbox', { name: 'Marketing' })).not.toBeChecked();
		await page.getByRole('button', { name: 'Close' }).click();
		await expect(banner).toBeHidden();
	});

	test('a cookie from a newer revision on a stale page shows no banner', async ({ page, context }) => {
		await page.goto('/');
		const { n } = await page.evaluate(() => window.silvertrailConfig);
		const newer = encodeURIComponent(JSON.stringify({ n: n + 5, c: ['test-pixel'], t: 0 }));
		await context.addCookies([{ name: 'silvertrail', value: newer, url: page.url() }]);
		await page.reload();

		await expect(page.getByRole('dialog')).toBeHidden();
		await expect.poll(() => ran(page)).toEqual(['pixel']);
		expect((await cookie(context)).n).toBe(n + 5);
	});

	test('global privacy control means only essential and no banner', async ({ page, context }) => {
		await page.addInitScript(() => Object.defineProperty(navigator, 'globalPrivacyControl', { value: true }));
		await page.goto('/');

		await expect(page.getByRole('dialog')).toBeHidden();
		expect((await cookie(context)).c).toEqual([]);
	});

	test('the before-prompt hook can accept for the visitor', async ({ page, context }) => {
		await page.addInitScript(() =>
			window.addEventListener('silvertrail:before-prompt', (event) => event.detail.respondWith('accept-all')),
		);
		await page.goto('/');

		await expect(page.getByRole('dialog')).toBeHidden();
		await expect.poll(() => ran(page)).toEqual(ALL_RAN);
		expect((await cookie(context)).c).toEqual(ALL_IDS);
	});

	test('an embed waits behind a placeholder and loads once on request', async ({ page }) => {
		await page.goto('/?pagename=silvertrail-embeds');
		await page.getByRole('button', { name: 'Only essential' }).first().click();

		const frame = page.locator('iframe[title="Test video"]');
		await expect(frame).toHaveCount(0);
		await page.getByRole('button', { name: 'Load content' }).click();
		await expect(frame).toHaveCount(1);

		await page.reload();
		await expect(frame).toHaveCount(0);
	});

	test('always allow on an embed stores consent for the service', async ({ page, context }) => {
		await page.goto('/?pagename=silvertrail-embeds');
		await page.getByRole('button', { name: 'Only essential' }).first().click();
		await page.getByRole('checkbox', { name: 'Always allow Test Video' }).check();
		await page.getByRole('button', { name: 'Load content' }).click();

		await expect(page.locator('iframe[title="Test video"]')).toHaveCount(1);
		expect((await cookie(context)).c).toEqual(['test-video']);
		await page.reload();
		await expect(page.locator('iframe[title="Test video"]')).toHaveCount(1);
	});

	test('saving on a stale page keeps the newer revision and unknown services', async ({ page, context }) => {
		await page.goto('/');
		const { n } = await page.evaluate(() => window.silvertrailConfig);
		const newer = encodeURIComponent(JSON.stringify({ n: n + 5, c: ['future-service'], t: 0 }));
		await context.addCookies([{ name: 'silvertrail', value: newer, url: page.url() }]);
		await page.goto('/?stale=1#silvertrail');
		await page.getByRole('checkbox', { name: 'Statistics' }).check();
		await page.getByRole('button', { name: 'Save choices' }).click();

		const stored = await cookie(context);
		expect(stored.n).toBe(n + 5);
		expect(stored.c).toEqual(['test-analytics', 'test-gated', 'future-service']);
	});
});
