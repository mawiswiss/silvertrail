# Leak check

The Leak check shows what a page loads from other servers before and after consent, so a site can see that nothing slips through. It runs in the admin, and the same scenarios run in a Playwright suite.

## In the admin

Under Settings › Silvertrail › Tools, the Leak check opens each page you list in a frame, as a logged-out, first-time visitor. It looks at every page four times: before a choice, after declining, after agreeing to everything, and after loading the page again with that consent. Then it agrees to each service on its own. The report shows:

- What needs guarding: every other server contacted before a choice or after declining, with what to do about it. Turning a matching preset on opens the Services tab with its box ticked, ready to save.
- Each service, whether it is set in the Services tab or in code, whether it stays quiet until the visitor agrees, whether it then starts at once, and whether agreeing to it also starts another service. Two services that grant the same Consent Mode type do that, such as Google Tag Manager next to Google Analytics.
- Services that start only after a reload. That happens when PHP checks `silvertrail_has()` before printing a tag, or when a gated script waits for `DOMContentLoaded`, which has passed by the time the visitor agrees. A visitor who agrees then misses the service until the next page.
- Hosts contacted after agreeing that no service lists, with the service that seems to own them.

A prompt below it, ready to paste into an AI assistant, asks for the provider, purpose and privacy policy of each service it found, as the banner lists them.

Agreeing in the frame is real, so the site's trackers count one visit from your browser. The check keeps its choice in its own cookie, `silvertrail_scan`, which lasts ten minutes. It writes no consent record, and it puts your own cookies back when it ends or is cancelled. It sees what a page loads and the frames it embeds, not what happens inside those frames or after a click. An optimiser that delays JavaScript until the first mouse move or key press, such as the Delay JavaScript option of WP Rocket, keeps those scripts out of every phase, so turn it off while you scan.

The frame loads the page with `?silvertrail-scan`, which renders it without a logged-in user and without the visitor's stored choice. That only takes privileges away, so it needs no nonce. A page cache that ignores query strings would serve the cached page instead, so scan with the cache off or bypassed.

## In CI

`testing/expect-no-leaks.mjs` runs the same scenarios in a Playwright suite. Import it from the plugin folder, because it shares the analysis in `assets/scan-findings.js`, and use a fresh browser context, so no consent cookie is set:

```js
import { expectConsentWorks } from '../wp-content/plugins/silvertrail/testing/expect-no-leaks.mjs';

test('consent works on the pages that matter', async ({ page }) => {
	await expectConsentWorks(page, ['/', '/contact/'], { allow: ['cdn.example.com'] });
});
```

| Function | Does |
| --- | --- |
| `expectConsentWorks(page, urls, { allow, wait })` | Runs every scenario on each page and fails with one line per finding. |
| `checkConsent(page, urls, { wait })` | Runs the same scenarios and returns the findings instead of failing, in the shape the admin's report uses. |
| `describeFindings(result, { allow })` | Turns the findings of `checkConsent` into the lines `expectConsentWorks` fails with. |
| `expectNoLeaks(page, url, { allow, wait })` | Checks only the first visit and fails when the page contacts another host. |
| `collectLeaks(page, url, { allow, wait })` | Returns the other hosts a first visit contacts, sorted. |

`allow` lists hosts, or service IDs for `expectConsentWorks` and `describeFindings`, that may load. A host also allows its subdomains. `wait` is the time in milliseconds to wait after each load or choice for late requests, 1500 by default. The page needs Silvertrail running, or `checkConsent` throws.
