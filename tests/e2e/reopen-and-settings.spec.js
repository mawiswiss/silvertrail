import { test, expect } from './test.js';
import { ADMIN_STATE } from './global-setup.js';

const withSettings = (context, settings) =>
	context.addCookies([
		{ name: 'silvertrail_test_settings', value: JSON.stringify(settings), url: 'http://localhost:8892' },
	]);

const banner = (page) => page.getByRole('dialog', { name: 'Privacy settings' });

test.describe('reopen', () => {
	test('the floating button appears after a choice and reopens the preferences', async ({ page }) => {
		await page.goto('/');
		const button = page.getByRole('button', { name: 'Privacy settings' });
		await expect(button).toBeHidden();

		await page.getByRole('button', { name: 'Only essential' }).first().click();
		await button.click();
		await expect(banner(page)).toBeVisible();
	});

	test('the shortcode link reopens the preferences and replaces the floating button', async ({ page }) => {
		await page.goto('/?pagename=silvertrail-shortcode');
		await page.getByRole('button', { name: 'Only essential' }).first().click();
		await expect(page.getByRole('button', { name: 'Privacy settings' })).toBeHidden();
		await page.getByRole('link', { name: 'Privacy settings' }).click();

		await expect(banner(page)).toBeVisible();
	});

	test('the floating button shows next to a link when code says always', async ({ page, context }) => {
		await withSettings(context, { floating_button: 'always' });
		await page.goto('/?pagename=silvertrail-shortcode');
		await page.getByRole('button', { name: 'Only essential' }).first().click();

		await expect(page.getByRole('button', { name: 'Privacy settings' })).toBeVisible();
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

test.describe('switched off', () => {
	test('leaves the site alone until a page is previewed', async ({ page, context }) => {
		await withSettings(context, { enabled: false });
		const ran = () => page.evaluate(() => window.silvertrailTest || []);

		await page.goto('/?pagename=silvertrail-shortcode');
		await expect.poll(ran).toContain('gated-after');
		await expect(banner(page)).toBeHidden();
		await expect(page.getByRole('link', { name: 'Privacy settings' })).toHaveCount(0);

		await page.goto('/?pagename=silvertrail-shortcode&silvertrail-preview');
		await expect(banner(page)).toBeVisible();
		expect(await ran()).not.toContain('gated-after');
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
		await expect(page.getByRole('button', { name: 'Privacy settings' })).toBeHidden();
	});

	test('a site with embeds only opens no banner and offers the reopen button after an embed choice', async ({ page, context }) => {
		await context.addCookies([{ name: 'silvertrail_test_in_place', value: '1', url: 'http://localhost:8892' }]);
		await page.goto('/?pagename=silvertrail-embeds');
		await expect(page.locator('#silvertrail-banner')).toBeHidden();
		const reopen = page.getByRole('button', { name: 'Privacy settings' });
		await expect(reopen).toBeHidden();

		await page.getByRole('checkbox', { name: 'Always allow Test Video' }).check();
		await page.getByRole('button', { name: 'Load content' }).click();
		await expect(page.locator('iframe[title="Test video"]')).toHaveCount(1);
		await expect(reopen).toBeVisible();

		await reopen.click();
		await expect(banner(page)).toBeVisible();
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
		await expect(page.getByRole('button', { name: 'Silvertrail is on', pressed: true })).toBeVisible();
		await expect(page.getByText(/The banner asks about \d+ services\./)).toBeVisible();
		await expect(page.getByRole('cell', { name: /Test Analytics/ })).toBeVisible();

		await page.getByRole('link', { name: 'Banner', exact: true }).click();
		await expect(page).toHaveURL(/tab=banner/);
		await expect(page.locator('.CodeMirror', { hasText: '"accept_all": "Accept all"' })).toBeVisible();
	});

	test('highlights a linked section until the next action', async ({ page }) => {
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=banner#silvertrail-links');
		const links = page.locator('#silvertrail-links');
		await expect(links).toHaveClass(/silvertrail-section--highlighted/);
		await page.keyboard.press('Shift');
		await expect(links).not.toHaveClass(/silvertrail-section--highlighted/);

		await page.evaluate(() => (location.hash = '#silvertrail-texts'));
		const texts = page.locator('#silvertrail-texts');
		await expect(texts).toHaveClass(/silvertrail-section--highlighted/);
		await page.getByRole('heading', { name: 'Silvertrail', exact: true }).click();
		await expect(texts).not.toHaveClass(/silvertrail-section--highlighted/);
	});

	test('opens the developer help at the matching tab', async ({ page }) => {
		await page.goto('/wp-admin/options-general.php?page=silvertrail');

		await page.getByRole('button', { name: 'Show the example' }).click();
		await expect(page.locator('#tab-panel-silvertrail-adjust')).toBeVisible();
		await page.getByRole('button', { name: 'Developer help' }).click();
		await expect(page.locator('#tab-panel-silvertrail-service')).toBeVisible();
		await expect(page.locator('#tab-panel-silvertrail-adjust')).toBeHidden();

		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=banner');
		await page.getByRole('button', { name: 'Developer help' }).click();
		await expect(page.locator('#tab-panel-silvertrail-settings')).toBeVisible();
	});

	test('a setting from code shows as set in code and cannot be changed', async ({ page, context }) => {
		await context.addCookies([
			{ name: 'silvertrail_test_code', value: JSON.stringify({ layout: 'box', records: true }), url: 'http://localhost:8892' },
		]);
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=banner');

		await expect(page.locator('th', { hasText: 'Position' })).toContainText('Set in code');
		await expect(page.locator('th', { hasText: 'Appearance' })).not.toContainText('Set in code');
		await expect(page.getByRole('radio', { name: 'Corner' })).toBeChecked();
		await expect(page.getByRole('radio', { name: 'Corner' })).toBeDisabled();
		await expect(page.getByRole('radio', { name: 'Centered' })).toBeDisabled();
		await expect(page.getByRole('radio', { name: 'Dark' })).toBeEnabled();
		await expect(page.getByLabel('Imprint page')).toBeEnabled();

		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=tools');
		const records = page.getByLabel('Keep a record of each decision');
		await expect(records).toBeChecked();
		await expect(records).toBeDisabled();
		await expect(page.locator('.post-state', { hasText: 'Set in code' })).toBeVisible();
	});

	test('turns records on in the tools tab', async ({ page }) => {
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=tools');

		const records = page.getByLabel('Keep a record of each decision');
		await records.check();
		await page.getByRole('button', { name: 'Save Changes' }).click();
		await expect(page.getByText('Settings saved.')).toBeVisible();
		await expect(records).toBeChecked();
		const download = page.waitForEvent('download');
		await page.getByRole('link', { name: 'Download CSV' }).click();
		expect((await download).suggestedFilename()).toMatch(/^silvertrail-records-.+\.csv$/);

		await records.uncheck();
		await page.getByRole('button', { name: 'Save Changes' }).click();
		await expect(page.getByText('Records are off. Turn them on above.')).toBeVisible();
	});

	test('asks before leaving a tab with unsaved changes', async ({ page }) => {
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=banner');
		await expect(page.getByRole('button', { name: 'Save Changes' })).toHaveCount(4);

		await page.getByRole('radio', { name: 'Dark' }).check();
		let asked = '';
		page.once('dialog', async (dialog) => {
			asked = dialog.type();
			await dialog.dismiss();
		});
		await page.getByRole('navigation', { name: 'Silvertrail settings' }).getByRole('link', { name: 'Tools' }).click();
		await expect.poll(() => asked).toBe('beforeunload');
		await expect(page).toHaveURL(/tab=banner/);
	});

	test('the banner tab shows position and appearance as cards', async ({ page, context }) => {
		await withSettings(context, { layout: 'box', appearance: 'dark' });
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=banner');

		await expect(page.getByRole('radio', { name: 'Corner' })).toBeChecked();
		await expect(page.getByRole('radio', { name: 'Dark' })).toBeChecked();
		await expect(page.getByRole('radio', { name: 'Light' })).not.toBeChecked();
	});

	test('the banner tab hands an AI assistant the styling brief', async ({ page, context }) => {
		await context.grantPermissions(['clipboard-read', 'clipboard-write']);
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=banner');

		await page.locator('summary', { hasText: 'Show the prompt' }).click();
		const prompt = page.locator('#silvertrail-styling-prompt');
		await expect(prompt).toHaveValue(/^## Brief for the AI[\s\S]*## This site[\s\S]*--silvertrail-background/);
		const preview = page.locator('.CodeMirror', { hasText: '## Brief for the AI' });
		await expect(preview).toBeVisible();
		await expect(preview.getByLabel('Prompt for an AI assistant')).toBeVisible();

		await page.getByRole('button', { name: 'Copy prompt' }).click();
		await expect(page.locator('#wpbody-content').getByText('Copied', { exact: true })).toBeVisible();
		await expect(page.locator('#a11y-speak-polite')).toHaveText('Copied');
		expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(await prompt.inputValue());
	});
});
