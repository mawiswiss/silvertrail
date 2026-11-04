import { test, expect } from './test.js';
import { ADMIN_STATE } from './global-setup.js';
import { collectLeaks, expectNoLeaks } from '../../testing/expect-no-leaks.mjs';

test.describe('transparency', () => {
	test('the preferences show what the page held back and loaded', async ({ page }) => {
		await page.goto('/?pagename=silvertrail-leak#silvertrail');

		const trail = page.locator('[data-silvertrail-trail]');
		await expect(trail).toBeVisible();
		await expect(trail).toContainText('Held back');
		await expect(trail).toContainText('Test Analytics');
		await expect(trail).toContainText('127.0.0.1 (not listed here)');
	});

	test('the leak check lists requests made before consent', async ({ browser }) => {
		const context = await browser.newContext({ storageState: ADMIN_STATE });
		const page = await context.newPage();
		await context.addCookies([
			{ name: 'silvertrail_test_settings', value: JSON.stringify({ leak_check: true }), url: 'http://localhost:8892' },
		]);

		await page.goto('/?pagename=silvertrail-leak&silvertrail-check=1');
		const panel = page.getByRole('complementary', { name: 'Silvertrail leak check' });
		await expect(panel).toBeVisible();
		await expect(panel).toContainText('127.0.0.1: not listed, gate it or register a service');
		await expect(page.getByRole('dialog', { name: 'Privacy settings' })).toBeHidden();

		// Logged in, the admin bar still loads the avatar from Gravatar, so the home page is not clean.
		await page.goto('/?silvertrail-check=1');
		await expect(panel).toContainText('secure.gravatar.com');
		await expect(panel).not.toContainText('127.0.0.1');
		await context.close();
	});

	test('expect-no-leaks catches an ungated script and passes a clean page', async ({ page }) => {
		expect(await collectLeaks(page, '/?pagename=silvertrail-leak', { wait: 300 })).toEqual(['127.0.0.1']);
		await expectNoLeaks(page, '/', { wait: 300 });
		await expect(expectNoLeaks(page, '/?pagename=silvertrail-leak', { wait: 300 })).rejects.toThrow('127.0.0.1');
	});

	test('with records on, a decision posts a consent ID that the cookie keeps', async ({ page, context }) => {
		await context.addCookies([
			{ name: 'silvertrail_test_settings', value: JSON.stringify({ records: true }), url: 'http://localhost:8892' },
		]);
		await page.goto('/');
		const posted = page.waitForResponse((response) => response.url().includes('silvertrail/v1/record'));
		await page.getByRole('button', { name: 'Only essential' }).first().click();

		const response = await posted;
		expect(response.status()).toBe(204);
		const body = response.request().postDataJSON();
		expect(body).toMatchObject({ c: [], source: 'banner' });
		expect(body.i).toMatch(/^[a-f0-9]{32}$/);
		const stored = (await context.cookies()).find((c) => c.name === 'silvertrail');
		expect(JSON.parse(decodeURIComponent(stored.value)).i).toBe(body.i);
	});
});
