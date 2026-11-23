import { test, expect } from './test.js';

const ALL_IDS = ['google-tag-manager', 'youtube', 'google-maps', 'test-analytics', 'test-gated', 'test-video', 'test-pixel'];
// Footer scripts print before the fixture's inline tags, and the after code waits for its file.
const ALL_RAN = ['gated-src', 'gated-after', 'analytics', 'pixel'];

const ran = (page) => page.evaluate(() => window.silvertrailTest || []);
const sendGpc = (page) => page.addInitScript(() => Object.defineProperty(navigator, 'globalPrivacyControl', { value: true }));
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
		await sendGpc(page);
		await page.goto('/');

		await expect(page.getByRole('dialog')).toBeHidden();
		expect(await cookie(context)).toMatchObject({ c: [], g: 1 });
	});

	test('global privacy control wins over a choice made without it', async ({ page, context }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Accept all' }).first().click();
		await expect.poll(() => ran(page)).toEqual(ALL_RAN);

		await sendGpc(page);
		await page.reload();

		await expect.poll(async () => (await cookie(context)).g).toBe(1);
		await expect(page.getByRole('dialog')).toBeHidden();
		expect((await cookie(context)).c).toEqual([]);
		expect(await page.evaluate(() => window.silvertrail.has('test-analytics'))).toBe(false);
		expect(await ran(page)).toEqual([]);
	});

	test('a choice made under global privacy control stays', async ({ page, context }) => {
		await sendGpc(page);
		await page.goto('/#silvertrail');
		await page.getByRole('checkbox', { name: 'Statistics' }).check();
		await page.getByRole('button', { name: 'Save choices' }).click();
		await page.goto('/');

		await expect(page.getByRole('dialog')).toBeHidden();
		await expect.poll(() => ran(page)).toEqual(['gated-src', 'gated-after', 'analytics']);
		expect(await cookie(context)).toMatchObject({ c: ['test-analytics', 'test-gated'], g: 1 });
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

	test('on a site that asks in place, the before-prompt hook can accept and embeds load without a click', async ({ page, context }) => {
		await context.addCookies([{ name: 'silvertrail_test_in_place', value: '1', url: 'http://localhost:8892' }]);
		await page.addInitScript(() =>
			window.addEventListener('silvertrail:before-prompt', (event) => event.detail.respondWith('accept-all')),
		);
		await page.goto('/?pagename=silvertrail-embeds');

		await expect(page.locator('iframe[title="Test video"]')).toHaveCount(1);
		await expect(page.getByRole('dialog')).toBeHidden();
		expect((await cookie(context)).c).toEqual(ALL_IDS);
	});

	test('on a site that asks in place, global privacy control keeps the before-prompt hook from being asked', async ({ page, context }) => {
		await context.addCookies([{ name: 'silvertrail_test_in_place', value: '1', url: 'http://localhost:8892' }]);
		await sendGpc(page);
		await page.addInitScript(() =>
			window.addEventListener('silvertrail:before-prompt', (event) => {
				window.silvertrailAsked = true;
				event.detail.respondWith('accept-all');
			}),
		);
		await page.goto('/?pagename=silvertrail-embeds');

		await expect(page.getByRole('button', { name: 'Load content' })).toBeVisible();
		expect(await page.evaluate(() => window.silvertrailAsked === true)).toBe(false);
		await expect(page.locator('iframe[title="Test video"]')).toHaveCount(0);
		expect(await cookie(context)).toBeNull();
	});

	for (const layout of ['modal', 'bar']) {
		test(`the opt-out recipe runs everything and shows the preferences once, ${layout}`, async ({ page, context }) => {
			await context.addCookies([
				{ name: 'silvertrail_test_settings', value: JSON.stringify({ layout }), url: 'http://localhost:8892' },
			]);
			await page.addInitScript(() => {
				window.addEventListener('silvertrail:before-prompt', (event) => event.detail.respondWith('accept-all'));
				window.addEventListener('silvertrail:change', (event) => {
					if (event.detail.source === 'hook') {
						window.silvertrail.open();
					}
				});
			});
			await page.goto('/');

			await expect(page.getByRole('dialog')).toBeVisible();
			await expect.poll(() => ran(page)).toEqual(ALL_RAN);
			expect(await page.evaluate(() => document.getElementById('silvertrail-banner').matches(':modal'))).toBe(true);
			await page.getByRole('button', { name: 'Only essential' }).first().click();
			expect((await cookie(context)).c).toEqual([]);

			await page.reload();
			await expect(page.getByRole('dialog')).toBeHidden();
			expect(await ran(page)).toEqual([]);
		});
	}

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

	test('a YouTube embed waits behind a placeholder without any setup', async ({ page }) => {
		await page.route('**/www.youtube-nocookie.com/**', (route) => route.fulfill({ contentType: 'text/html', body: '' }));
		await page.goto('/?pagename=silvertrail-youtube');
		await page.getByRole('button', { name: 'Only essential' }).first().click();

		await expect(page.locator('iframe[title="YouTube video"]')).toHaveCount(0);
		await expect(page.getByText('YouTube', { exact: false }).first()).toBeVisible();
		await page.getByRole('button', { name: 'Load content' }).click();
		await expect(page.locator('iframe[title="YouTube video"]')).toHaveCount(1);
	});

	for (const width of [1280, 360]) {
		test(`an embed placeholder has the height of its embed at ${width}px`, async ({ page }) => {
			await page.setViewportSize({ width, height: 900 });
			await page.goto('/?pagename=silvertrail-sizes');
			await page.getByRole('button', { name: 'Only essential' }).first().click();

			for (const title of ['Pixels', 'Full width', 'Block']) {
				const placeholder = page.locator('[data-silvertrail-embed]').first();
				const before = await placeholder.boundingBox();
				await placeholder.getByRole('button', { name: 'Load content' }).click();
				const after = await page.locator(`iframe[title="${title}"]`).boundingBox();

				expect(Math.abs(after.height - before.height), title).toBeLessThanOrEqual(1);
				// On a phone the 560px iframe overflows the column, while the placeholder fits it.
				if (width > 600) {
					expect(Math.abs(after.width - before.width), title).toBeLessThanOrEqual(1);
				}
			}
		});
	}

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

	test('revoke removes one service and keeps the rest of the choice', async ({ page, context }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Accept all' }).first().click();
		await page.evaluate(() => window.silvertrail.revoke('test-analytics'));

		expect((await cookie(context)).c).toEqual(ALL_IDS.filter((id) => id !== 'test-analytics'));
		await page.reload();
		await expect(page.getByRole('dialog')).toBeHidden();
		await expect.poll(() => ran(page)).toEqual(['gated-src', 'gated-after', 'pixel']);
	});

	test('revoke leaves an undecided visitor undecided', async ({ page, context }) => {
		await page.goto('/');
		await page.evaluate(() => {
			window.silvertrail.revoke('silvertrail');
			window.silvertrail.revoke('test-analytics');
		});
		expect(await cookie(context)).toBeNull();

		await page.evaluate(() => {
			window.silvertrail.grant('test-video');
			window.silvertrail.revoke('test-video');
		});
		expect(await cookie(context)).toMatchObject({ c: [], p: 1 });
		expect(await page.evaluate(() => window.silvertrail.decided())).toBe(false);
	});

	test('grant and revoke take whole groups in one change', async ({ page, context }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Only essential' }).first().click();
		await page.evaluate(() => window.silvertrail.grant(['external-media', 'test-pixel']));
		expect((await cookie(context)).c).toEqual(['youtube', 'google-maps', 'test-video', 'test-pixel']);

		const changes = await page.evaluate(() => {
			let count = 0;
			window.addEventListener('silvertrail:change', () => count++);
			window.silvertrail.revoke(['marketing', 'youtube']);
			return count;
		});
		expect(changes).toBe(1);
		expect((await cookie(context)).c).toEqual(['google-maps', 'test-video']);
	});

	test('always allow on an embed under global privacy control stays', async ({ page, context }) => {
		await sendGpc(page);
		await page.goto('/?pagename=silvertrail-embeds');
		await expect(page.getByRole('dialog')).toBeHidden();
		await page.getByRole('checkbox', { name: 'Always allow Test Video' }).check();
		await page.getByRole('button', { name: 'Load content' }).click();

		const frame = page.locator('iframe[title="Test video"]');
		await expect(frame).toHaveCount(1);
		expect(await cookie(context)).toMatchObject({ c: ['test-video'], g: 1 });
		await page.reload();
		await expect(frame).toHaveCount(1);
	});

	test('loading an embed once under global privacy control stores nothing', async ({ page, context }) => {
		await sendGpc(page);
		await page.goto('/?pagename=silvertrail-embeds');
		await page.getByRole('button', { name: 'Load content' }).click();

		const frame = page.locator('iframe[title="Test video"]');
		await expect(frame).toHaveCount(1);
		expect((await cookie(context)).c).toEqual([]);
		await page.reload();
		await expect(frame).toHaveCount(0);
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
