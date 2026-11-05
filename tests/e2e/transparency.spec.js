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

test.describe('visitor scan', () => {
	test.use({ storageState: ADMIN_STATE });

	test('lists what a logged-out visitor loads before choosing', async ({ page }) => {
		await page.route('**/player.vimeo.com/**', (route) => route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Vimeo</title>' }));
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=check');
		const scan = page.locator('#silvertrail-scan');
		const url = scan.getByLabel('Page', { exact: true });
		const run = scan.getByRole('button', { name: 'Scan', exact: true });

		await url.fill('/?pagename=silvertrail-leak');
		await run.click();
		const early = scan.locator('ul').first();
		await expect(early).toContainText('127.0.0.1', { timeout: 15000 });
		await expect(early).toContainText('A script written straight into the page.');
		await expect(scan).not.toContainText('gravatar');
		await expect(scan).toContainText('Test Analytics');
		const prompt = await scan.getByLabel('Prompt for an AI assistant').inputValue();
		expect(prompt).toContain('Connections to other servers:\n- 127.0.0.1: script');
		expect(prompt).toContain('- Test Analytics');
		expect(prompt).not.toContain('- Google Tag Manager');
		expect(prompt).toContain('Provider: the company');
		await scan.getByRole('button', { name: 'Copy prompt' }).click();
		await expect(scan.getByText('Copied', { exact: true })).toBeVisible();

		await url.fill('/?pagename=silvertrail-vimeo');
		await run.click();
		await scan.getByRole('link', { name: 'Turn on Vimeo' }).click({ timeout: 15000 });
		await expect(page).toHaveURL(/silvertrail-turn-on=vimeo/);
		await expect(page.getByText('Vimeo is ticked. Save Changes to turn it on.')).toBeVisible();
		await expect(page.locator('#silvertrail-preset-vimeo')).toBeChecked();
		await expect(page.locator('#silvertrail-preset-vimeo')).toBeFocused();
		await page.goBack();

		await url.fill('/');
		await run.click();
		await expect(scan).toContainText('Clean.', { timeout: 15000 });
	});
});
