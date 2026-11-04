import { chromium } from '@playwright/test';

export const ADMIN_STATE = 'test-results/.auth/admin.json';

// One login for the whole run: parallel logins race on the user's session tokens and drop each other.
export default async function globalSetup(config) {
	const { baseURL } = config.projects[0].use;
	const browser = await chromium.launch();
	const page = await browser.newPage({ baseURL });
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'password');
	await page.click('#wp-submit');
	await page.waitForURL(/\/wp-admin\//);
	await page.context().storageState({ path: ADMIN_STATE });
	await browser.close();
}
