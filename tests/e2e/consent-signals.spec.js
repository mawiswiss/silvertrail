import { test, expect } from './test.js';

const consentCalls = (page) =>
	page.evaluate(() =>
		(window.dataLayer || [])
			.map((entry) => Array.from(entry))
			.filter((args) => args[0] === 'consent')
			.map(([, action, values]) => ({ action, values })),
	);

test.describe('consent signals', () => {
	test('Consent Mode starts denied and updates on a choice', async ({ page }) => {
		await page.goto('/');

		const [first] = await consentCalls(page);
		expect(first.action).toBe('default');
		expect(first.values).toMatchObject({
			ad_storage: 'denied',
			analytics_storage: 'denied',
			ad_user_data: 'denied',
			ad_personalization: 'denied',
			security_storage: 'granted',
		});

		await page.getByRole('button', { name: 'Choose' }).click();
		await page.getByRole('checkbox', { name: 'Statistics' }).check();
		await page.getByRole('button', { name: 'Save choices' }).click();

		const calls = await consentCalls(page);
		expect(calls.at(-1)).toEqual({
			action: 'update',
			values: { analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' },
		});
	});

	test('a returning visitor sends the update before anything else runs', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Accept all' }).first().click();
		await page.reload();

		const calls = await consentCalls(page);
		expect(calls.slice(0, 2).map((call) => call.action)).toEqual(['default', 'update']);
		expect(calls[1].values.ad_storage).toBe('granted');
	});

	test('Global Privacy Control keeps Google denied over an earlier accept all', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Accept all' }).first().click();
		await page.addInitScript(() => Object.defineProperty(navigator, 'globalPrivacyControl', { value: true }));
		await page.reload();

		await expect.poll(async () => (await consentCalls(page)).filter((call) => call.action === 'update').length).toBe(1);
		const granted = (await consentCalls(page)).filter((call) => Object.values(call.values).includes('granted'));
		expect(granted.map((call) => call.action)).toEqual(['default']);
		expect(await page.evaluate(() => window.wp_has_consent('marketing'))).toBe(false);
	});

	test('the WP Consent API reads the choice', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('button', { name: 'Choose' }).click();
		await page.getByRole('checkbox', { name: 'Statistics' }).check();
		await page.getByRole('button', { name: 'Save choices' }).click();

		const state = await page.evaluate(() => ({
			type: window.wp_consent_type,
			statistics: window.wp_has_consent('statistics'),
			marketing: window.wp_has_consent('marketing'),
			functional: window.wp_has_consent('functional'),
		}));
		expect(state).toEqual({ type: 'optin', statistics: true, marketing: false, functional: true });
	});

	test('the GTM preset loads only after consent and after the Consent Mode signals', async ({ page }) => {
		const requests = [];
		page.on('request', (request) => request.url().includes('googletagmanager.com') && requests.push(request.url()));
		await page.goto('/');

		await expect(page.locator('script[type="text/plain"][data-silvertrail="google-tag-manager"]')).toHaveCount(1);
		expect(requests).toEqual([]);

		await page.getByRole('button', { name: 'Accept all' }).first().click();
		await expect.poll(() => page.evaluate(() => window.silvertrailGtm)).toBe(1);
		expect(requests[0]).toContain('gtm.js?id=GTM-TEST123');

		const order = await page.evaluate(() =>
			window.dataLayer.map((entry) => (entry[0] === 'consent' ? `consent:${entry[1]}` : entry.event || '')),
		);
		expect(order.indexOf('consent:default')).toBeLessThan(order.indexOf('consent:update'));
		expect(order.indexOf('consent:update')).toBeLessThan(order.indexOf('gtm.js'));
	});
});
