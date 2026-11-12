import { test, expect } from './test.js';
import { ADMIN_STATE } from './global-setup.js';
import { checkConsent, collectLeaks, describeFindings, expectConsentWorks, expectNoLeaks } from '../../testing/expect-no-leaks.mjs';

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

// Outlasts the first window of the Leak check, so the request is still running when the visitor chooses.
const slow = async (route) => {
	await new Promise((resolve) => setTimeout(resolve, 3000));
	await route.fulfill({ body: '' }).catch(() => {});
};

test.describe('expect consent works', () => {
	test.slow();

	test('finds the same problems as the Leak check', async ({ page }) => {
		await page.route('**/slow.example.test/**', slow);
		const result = await checkConsent(
			page,
			[
				'/?pagename=silvertrail-leak',
				'/?pagename=silvertrail-careless',
				'/?pagename=silvertrail-reload',
				'/?pagename=silvertrail-cross',
				'/?pagename=silvertrail-unlisted',
				'/?pagename=silvertrail-slow',
			],
			{ wait: 500 },
		);
		const row = (id) => result.services.find((each) => each.id === id);

		expect(result.guard).toEqual(
			expect.arrayContaining([
				expect.objectContaining({ host: '127.0.0.1', when: 'before', what: 'scriptRaw' }),
				expect.objectContaining({ host: 'careless.example.test', when: 'declined' }),
				expect.objectContaining({ host: 'slow.example.test', when: 'before' }),
			]),
		);
		expect(row('test-pixel').agreed).toBe('reload');
		expect(row('test-analytics').also).toEqual([expect.objectContaining({ id: 'test-video' })]);
		expect(result.belongs).toEqual([expect.objectContaining({ host: 'unlisted.example.test', service: 'test-pixel' })]);
		expect(describeFindings(result)).toContain('Test Pixel starts only after a reload on /?pagename=silvertrail-reload');
	});

	test('passes a clean page and fails one that waits for a reload', async ({ page }) => {
		await expectConsentWorks(page, '/', { wait: 500 });
		await expect(expectConsentWorks(page, '/?pagename=silvertrail-reload', { wait: 500 })).rejects.toThrow('Test Pixel starts only after a reload');
	});
});

test.describe('visitor scan', () => {
	test.use({ storageState: ADMIN_STATE });
	test.slow();

	async function scan(page, ...urls) {
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=tools');
		const section = page.locator('#silvertrail-scan');
		await section.getByLabel('Pages', { exact: true }).fill(urls.join('\n'));
		await section.getByRole('button', { name: 'Scan', exact: true }).click();
		await expect(section.locator('[data-silvertrail-scan-result] :is(.notice, .silvertrail-verdict)')).toBeVisible({ timeout: 150000 });
		return section;
	}

	const row = (section, name) => section.getByRole('row').filter({ has: section.page().getByRole('rowheader', { name, exact: true }) });

	test('lists what loads before a choice and writes a prompt', async ({ page }) => {
		const section = await scan(page, '/?pagename=silvertrail-leak');

		await expect(section).toContainText('Something runs without consent');
		await expect(section.getByRole('link', { name: 'Support Silvertrail' })).toHaveCount(0);
		const guard = section.locator('ul').first();
		await expect(guard).toContainText('127.0.0.1');
		await expect(guard).toContainText('A script written straight into the page.');
		await expect(section).not.toContainText('gravatar');
		await expect(row(section, 'Google Tag Manager')).toContainText('Starts at once');
		const prompt = await section.getByLabel('Prompt for an AI assistant').inputValue();
		expect(prompt).toContain('Connections to other servers:\n- 127.0.0.1: script');
		expect(prompt).toContain('- Test Analytics');
		expect(prompt).not.toContain('- Google Tag Manager');
		expect(prompt).toContain('Provider: the company');
		await section.getByRole('button', { name: 'Copy prompt' }).click();
		await expect(section.getByText('Copied', { exact: true })).toBeVisible();
	});

	test('suggests the preset for an embed', async ({ page }) => {
		await page.route('**/player.vimeo.com/**', (route) => route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Vimeo</title>' }));
		const section = await scan(page, '/?pagename=silvertrail-vimeo');

		await section.getByRole('link', { name: 'Turn on Vimeo' }).click();
		const notice = page.getByText('Vimeo is ticked. Save Changes to turn it on.');
		const vimeo = page.locator('#silvertrail-preset-vimeo');
		await expect(notice).toBeVisible();
		await expect(vimeo).toBeChecked();
		await expect(vimeo).toBeFocused();
		await expect(page).not.toHaveURL(/silvertrail-turn-on/);

		await vimeo.uncheck();
		await page.locator('#silvertrail-save-embeds').click();
		await expect(page.getByText('Settings saved.')).toBeVisible();
		await expect(vimeo).not.toBeChecked();
		await expect(notice).toHaveCount(0);
		await page.reload();
		await expect(vimeo).not.toBeChecked();
	});

	test('counts a slow request under the window it started in', async ({ page }) => {
		await page.route('**/slow.example.test/**', slow);
		const section = await scan(page, '/?pagename=silvertrail-slow');

		await expect(section).toContainText('Something runs without consent');
		await expect(section.locator('ul').first()).toContainText('slow.example.test');
		await expect(section).not.toContainText('Contacted after the visitor declines.');
	});

	test('reports a host contacted after declining', async ({ page }) => {
		const section = await scan(page, '/?pagename=silvertrail-careless');

		await expect(section).toContainText('Something runs without consent');
		await expect(section.locator('ul').first()).toContainText('careless.example.test');
		await expect(section.locator('ul').first()).toContainText('Contacted after the visitor declines.');
	});

	test('warns about a service that starts only after a reload', async ({ page }) => {
		const section = await scan(page, '/?pagename=silvertrail-reload');

		await expect(section).toContainText('some services start only after the page loads again');
		await expect(row(section, 'Test Pixel')).toContainText('Only after a reload');
		await expect(section).toContainText('Test Pixel starts only after the page loads again.');
	});

	test('reports a service that starts another one', async ({ page }) => {
		const section = await scan(page, '/?pagename=silvertrail-cross');

		await expect(row(section, 'Test Analytics')).toContainText('Also starts Test Video');
		await expect(section).toContainText('Agreeing to Test Analytics also starts Test Video.');
	});

	test('ties a host that no service lists to the service that started it', async ({ page }) => {
		const section = await scan(page, '/?pagename=silvertrail-unlisted');

		await expect(section).toContainText('Seems to belong to Test Pixel. Add unlisted.example.test to its hosts');
	});

	test('passes a clean page, records nothing and leaves the cookies as they were', async ({ page, context }) => {
		await context.addCookies([
			{ name: 'silvertrail_test_settings', value: JSON.stringify({ records: true }), url: 'http://localhost:8892' },
			{ name: 'silvertrail', value: encodeURIComponent('{"n":1,"c":[],"t":1}'), url: 'http://localhost:8892' },
			{ name: 'wp_consent_marketing', value: 'deny', url: 'http://localhost:8892' },
			{ name: 'silvertrail_test_license_seen', value: '1', url: 'http://localhost:8892' },
		]);
		const recorded = [];
		page.on('request', (request) => request.url().includes('silvertrail/v1/record') && recorded.push(request.url()));
		const section = await scan(page, '/');

		await expect(section).toContainText('No leaks found');
		await expect(section.getByRole('link', { name: 'Support Silvertrail' })).toBeVisible();
		await expect(row(section, 'Google Tag Manager')).toContainText('Services tab');
		await expect(row(section, 'Test Analytics')).toContainText('Code');
		await expect(row(section, 'Test Video')).toContainText('Not on these pages');
		expect(recorded).toEqual([]);
		const cookies = Object.fromEntries((await context.cookies()).map((cookie) => [cookie.name, cookie.value]));
		expect(cookies.silvertrail_scan).toBeUndefined();
		expect(cookies.silvertrail).toBe(encodeURIComponent('{"n":1,"c":[],"t":1}'));
		expect(cookies.wp_consent_marketing).toBe('deny');
	});

	test('merges several pages into one report', async ({ page }) => {
		test.setTimeout(180000);
		const section = await scan(page, '/', '/?pagename=silvertrail-reload', '/?pagename=silvertrail-cross');

		await expect(section).toContainText('Test Pixel starts only after the page loads again.');
		await expect(section).toContainText('On /?pagename=silvertrail-reload.');
		await expect(section).toContainText('Agreeing to Test Analytics also starts Test Video.');
	});

	test('cancels a scan and restores the cookies', async ({ page, context }) => {
		await context.addCookies([{ name: 'wp_consent_marketing', value: 'deny', url: 'http://localhost:8892' }]);
		await page.goto('/wp-admin/options-general.php?page=silvertrail&tab=tools');
		const section = page.locator('#silvertrail-scan');
		await section.getByRole('button', { name: 'Scan', exact: true }).click();
		await expect(section).toContainText('Agreeing to everything', { timeout: 30000 });
		await section.getByRole('button', { name: 'Cancel' }).click();

		await expect(section).toContainText('Scan cancelled.');
		await expect(section.getByRole('button', { name: 'Scan', exact: true })).toBeEnabled();
		const cookies = Object.fromEntries((await context.cookies()).map((cookie) => [cookie.name, cookie.value]));
		expect(cookies.silvertrail_scan).toBeUndefined();
		expect(cookies.wp_consent_marketing).toBe('deny');
	});

	test('checks a switched-off site as visitors would get it once on', async ({ page, context }) => {
		await context.addCookies([{ name: 'silvertrail_test_settings', value: JSON.stringify({ enabled: false }), url: 'http://localhost:8892' }]);
		const section = await scan(page, '/?pagename=silvertrail-leak');

		await expect(section.locator('ul').first()).toContainText('127.0.0.1');
		await expect(row(section, 'Test Analytics')).toBeVisible();
	});
});
