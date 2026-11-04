import { test as base, expect } from '@playwright/test';

export { expect };

// The GTM preset is on in the fixtures. Answer its requests locally, so no test reaches Google.
export const test = base.extend({
	page: async ({ page }, use) => {
		await page.route('**/www.googletagmanager.com/**', (route) =>
			route.fulfill({ contentType: 'text/javascript', body: 'window.silvertrailGtm = (window.silvertrailGtm || 0) + 1;' }),
		);
		await use(page);
	},
});
