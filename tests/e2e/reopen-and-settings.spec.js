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

test.describe('closing', () => {
	test('a required choice keeps the banner open on a click outside', async ({ page, context }) => {
		await withSettings(context, { layout: 'modal', require_choice: true });
		await page.goto('/');
		await page.mouse.click(5, 5);

		await expect(banner(page)).toBeVisible();
	});

	test('without a required choice a click outside closes the banner and stores nothing', async ({ page, context }) => {
		await withSettings(context, { layout: 'modal', require_choice: false });
		await page.goto('/');
		await expect(banner(page)).toBeVisible();
		expect(await page.evaluate(() => document.getElementById('silvertrail-banner').matches(':modal'))).toBe(true);
		await page.mouse.click(5, 5);

		await expect(banner(page)).toBeHidden();
		expect(await page.evaluate(() => window.silvertrail.decided())).toBe(false);
		await page.reload();
		await expect(banner(page)).toBeVisible();
		await page.getByRole('button', { name: 'Close' }).click();
		await expect(banner(page)).toBeHidden();
		expect(await page.evaluate(() => window.silvertrail.decided())).toBe(false);
	});

	test('without a required choice the box closes on a click outside', async ({ page, context }) => {
		await withSettings(context, { layout: 'box', require_choice: false });
		await page.goto('/');
		await expect(banner(page)).toBeVisible();
		await page.mouse.click(5, 5);

		await expect(banner(page)).toBeHidden();
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

	// The theme's own button and the page, read live so a saved style variation does not break the test.
	const colors = (page) =>
		page.evaluate(() => {
			const probe = document.body.appendChild(document.createElement('button'));
			probe.className = 'wp-element-button';
			const style = (el) => getComputedStyle(el);
			const button = style(document.querySelector('[data-silvertrail-action="accept-all"]'));
			const banner = style(document.getElementById('silvertrail-banner'));
			const lightness = (rgb) => rgb.match(/\d+/g).slice(0, 3).reduce((sum, value) => sum + Number(value), 0);
			return {
				banner: { background: banner.backgroundColor, button: button.backgroundColor, label: button.color, radius: button.borderRadius },
				theme: { background: style(document.body).backgroundColor, button: style(probe).backgroundColor, label: style(probe).color, radius: style(probe).borderRadius },
				darkBackground: lightness(banner.backgroundColor) < lightness(banner.color),
				scheme: banner.colorScheme,
			};
		});

	test('the banner takes the theme colours', async ({ page, context }) => {
		await withSettings(context, { appearance: 'theme' });
		await page.goto('/');

		const { banner, theme } = await colors(page);
		expect(banner).toEqual(theme);
	});

	test('dark appearance puts a dark background behind light text', async ({ page, context }) => {
		await withSettings(context, { appearance: 'dark' });
		await page.goto('/');

		const { banner, darkBackground, scheme } = await colors(page);
		expect(darkBackground).toBe(true);
		expect(scheme).toBe('dark');
		expect(banner.button).not.toBe(banner.background);
	});
});

test.describe('settings page', () => {
	test.use({ storageState: ADMIN_STATE });

	test('opens on the services and switches tabs', async ({ page }) => {
		await page.goto('/wp-admin/options-general.php?page=silvertrail');
		await expect(page.getByText(/The banner is on\./)).toBeVisible();
		await expect(page.getByRole('cell', { name: /Test Analytics/ })).toBeVisible();

		await page.getByRole('link', { name: 'Banner', exact: true }).click();
		await expect(page).toHaveURL(/tab=banner/);
		await expect(page.locator('.CodeMirror')).toContainText('"accept_all": "Accept all"');
	});

	test('saves and clamps the consent lifetime', async ({ page }) => {
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=consent');

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

	test('the banner tab shows position and appearance as cards', async ({ page, context }) => {
		await withSettings(context, { layout: 'box', appearance: 'dark' });
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=banner');

		await expect(page.getByRole('radio', { name: 'Corner' })).toBeChecked();
		await expect(page.getByRole('radio', { name: 'Dark' })).toBeChecked();
		await expect(page.getByRole('radio', { name: 'Like the theme' })).not.toBeChecked();
	});
});
