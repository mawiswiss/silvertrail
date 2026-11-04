import { test, expect } from './test.js';
import { ADMIN_STATE } from './global-setup.js';

const withSettings = (context, settings) =>
	context.addCookies([
		{ name: 'silvertrail_test_settings', value: JSON.stringify(settings), url: 'http://localhost:8892' },
	]);

const banner = (page) => page.getByRole('dialog', { name: 'Privacy settings' });

test.describe('reopen', () => {
	test('the floating button appears after a choice and reopens the preferences', async ({ page, context }) => {
		await withSettings(context, { floating_button: true });
		await page.goto('/');
		const button = page.getByRole('button', { name: 'Privacy settings' });
		await expect(button).toBeHidden();

		await page.getByRole('button', { name: 'Only essential' }).first().click();
		await button.click();
		await expect(banner(page)).toBeVisible();
	});

	test('the shortcode link reopens the preferences', async ({ page }) => {
		await page.goto('/?pagename=silvertrail-shortcode');
		await page.getByRole('button', { name: 'Only essential' }).first().click();
		await page.getByRole('link', { name: 'Privacy settings' }).click();

		await expect(banner(page)).toBeVisible();
	});

	test('the block link reopens the preferences', async ({ page }) => {
		await page.goto('/?pagename=silvertrail-block');
		await page.getByRole('button', { name: 'Only essential' }).first().click();
		await page.getByRole('link', { name: 'Cookie settings' }).click();

		await expect(banner(page)).toBeVisible();
	});

	test('the preferences say so when Global Privacy Control was honoured', async ({ page }) => {
		await page.addInitScript(() => Object.defineProperty(navigator, 'globalPrivacyControl', { value: true }));
		await page.goto('/#silvertrail');

		await expect(page.getByText('Your browser sends Global Privacy Control')).toBeVisible();
	});
});

test.describe('appearance settings', () => {
	test('the box layout leaves the page usable', async ({ page, context }) => {
		await withSettings(context, { layout: 'box' });
		await page.goto('/');

		const state = await page.evaluate(() => {
			const el = document.getElementById('silvertrail-banner');
			return { modal: el.matches(':modal'), open: el.matches(':popover-open') };
		});
		expect(state).toEqual({ modal: false, open: true });
		await page.getByRole('button', { name: 'Accept all' }).first().click();
		await expect(page.locator('#silvertrail-banner')).toBeHidden();
	});

	test('always allow on an embed before a banner choice still asks about the rest', async ({ page, context }) => {
		await withSettings(context, { layout: 'box' });
		await page.goto('/?pagename=silvertrail-embeds');
		await page.getByRole('checkbox', { name: 'Always allow Test Video' }).check();
		await page.getByRole('button', { name: 'Load content' }).click();
		await expect(page.locator('iframe[title="Test video"]')).toHaveCount(1);

		await page.reload();
		await expect(page.locator('iframe[title="Test video"]')).toHaveCount(1);
		await expect(page.locator('#silvertrail-banner')).toBeVisible();
		expect(await page.evaluate(() => window.silvertrail.decided())).toBe(false);
	});

	test('dark appearance and a custom accent colour', async ({ page, context }) => {
		await withSettings(context, { appearance: 'dark', colors: { accent: '#cc0000', background: '', text: '' } });
		await page.goto('/');

		const colors = await page.evaluate(() => {
			const box = getComputedStyle(document.getElementById('silvertrail-banner'));
			const button = getComputedStyle(document.querySelector('[data-silvertrail-action="accept-all"]'));
			return { background: box.backgroundColor, button: button.backgroundColor, label: button.color };
		});
		expect(colors).toEqual({ background: 'rgb(29, 34, 38)', button: 'rgb(204, 0, 0)', label: 'rgb(255, 255, 255)' });
	});
});

test.describe('settings page', () => {
	test.use({ storageState: ADMIN_STATE });

	test('saves and clamps the consent lifetime', async ({ page }) => {
		await page.goto('/wp-admin/options-general.php?page=silvertrail');

		const expiry = page.getByLabel('Keep a choice for');
		await expiry.fill('40');
		await expiry.evaluate((input) => input.removeAttribute('max'));
		await page.getByRole('button', { name: 'Save Changes' }).click();
		await expect(page.getByText('Settings saved.')).toBeVisible();
		await expect(expiry).toHaveValue('13');

		await expiry.fill('6');
		await page.getByRole('button', { name: 'Save Changes' }).click();
		await expect(expiry).toHaveValue('6');
	});
});
