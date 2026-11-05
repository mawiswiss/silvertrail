# Silvertrail

A small cookie consent banner for WordPress. Nothing loads before yes, accept and reject weigh the same, and developers configure it in code.

Status: early development, not released.

## Add a service

```php
add_action( 'silvertrail_register', function () {
	silvertrail_register_service( 'hubspot', [
		'group'       => 'marketing',
		'name'        => 'HubSpot',
		'provider'    => 'HubSpot, Inc.',
		'purpose'     => 'Tracks form conversions.',
		'privacy_url' => 'https://legal.hubspot.com/privacy-policy',
		'hosts'       => [ 'js.hs-scripts.com' ],
	] );
} );
```

Groups: `essential`, `preferences`, `statistics`, `marketing`, `external-media`. Add your own with `silvertrail_register_group()`. The `silvertrail_config` filter edits or removes anything at the end.

A service marked `'consent' => 'exempt'` never needs a decision. When every active service is exempt, no banner opens.

Keep the config the same on every request. Silvertrail asks visitors again whenever the set of services changes.

## Change a service

Register an existing ID again with only the fields to change. A preset's ID (`google-tag-manager`, `google-analytics`, `google-ads`, `linkedin-insight`, `meta-pixel`, `youtube`, `vimeo`, `google-maps`) turns the preset on with its defaults, so the admin's checkbox is not needed:

```php
add_action( 'silvertrail_register', function () {
	silvertrail_register_service( 'google-tag-manager', [ 'tag_id' => 'GTM-XXXXXXX' ] );
	silvertrail_register_service( 'youtube', [ 'cookies' => 'VISITOR_INFO1_LIVE, YSC' ] );
} );
```

To remove a service, even one the admin turned on, unset it in the `silvertrail_config` filter. The settings page marks each preset that code turned on, changed or removed.

## Gate a script

```html
<script type="text/plain" data-silvertrail="hubspot" src="https://js.hs-scripts.com/123.js"></script>
```

The browser ignores the tag until the visitor consents to `hubspot`. Then Silvertrail swaps in a real script, keeping the order of inline and external scripts.

## In the browser

```js
window.silvertrail.has('hubspot');
window.silvertrail.open();

window.addEventListener('silvertrail:change', (event) => {
	console.log(event.detail.consents);
});

// Decide before the banner opens. Resolve to 'prompt', 'accept-all' or 'reject-all'.
window.addEventListener('silvertrail:before-prompt', (event) => {
	event.detail.respondWith(fetch('/my-region-check').then((r) => r.text()));
});
```

Any link to `#silvertrail` opens the preferences.

## In PHP

`silvertrail_has( 'hubspot' )` reads the cookie. Use it only on requests a page cache never serves, such as form posts. Pages render every gated thing blocked and the browser decides.

## Texts

Texts stack up in this order, and the later one wins:

1. The English source and its official translation from translate.wordpress.org.
2. A developer translation from the theme: `silvertrail/texts-{locale}.json`, such as `silvertrail/texts-de_CH.json`, else `silvertrail/texts.json` for every language. A child theme wins over its parent.
3. The admin's edits under Settings › Silvertrail › Banner, stored per language.
4. The `silvertrail_texts` filter.

```json
{
	"title": "Datenschutz",
	"accept_all": "Alle akzeptieren",
	"embed_remember": "%s immer zulassen"
}
```

The admin sees the same JSON with every key, ready to copy into the file. `embed_text` and `embed_remember` keep their `%s`, where the service name goes. `wp silvertrail validate` reports unknown keys and broken files.

To ship the translation from a plugin instead, return another path or an array in the `silvertrail_translation` filter. It receives the theme's file, or `''`, and the locale:

```php
add_filter( 'silvertrail_translation', function ( $translation, $locale ) {
	return __DIR__ . "/texts/{$locale}.json";
}, 10, 2 );
```

A missing file is skipped.

## Design

The banner, the embed placeholders and the floating button take their colours, corners and button shape from the theme's `theme.json`, including the active style variation. Under Settings › Silvertrail › Banner, Light and Dark keep the theme's colours and put the lightest or the darkest one in the background. Classic themes without `theme.json` get a neutral palette.

Set your own design with the `silvertrail_design` filter. It receives the values for the chosen appearance and the appearance itself (`theme`, `light` or `dark`). Its result is final:

```php
add_filter( 'silvertrail_design', function ( $design, $appearance ) {
	$design['accent'] = 'dark' === $appearance ? '#ff8a80' : '#c62828';
	$design['radius'] = '0.25rem';
	return $design;
}, 10, 2 );
```

Each key becomes a `--silvertrail-<key>` custom property: `background`, `text`, `muted`, `line`, `accent`, `on-accent`, `radius`, `button-padding` and `button-weight`. A hex `accent` without its own `on-accent` gets black or white text, whichever reads better. Values with quotes, semicolons, braces or angle brackets are dropped.

## Google Consent Mode and the WP Consent API

A service lists the Consent Mode types it grants, for example `'google' => [ 'analytics_storage' ]`. Silvertrail prints the default (everything denied except `security_storage`) first in `<head>` and sends an update after every decision. Google tags stay blocked until consent. Set `$config['google']['mode'] = 'advanced'` in the `silvertrail_config` filter to load them before consent, so they send cookieless pings.

Each group maps to a WP Consent API category through its `consent_api` key. A category is allowed when the visitor agreed to every service in it.

## Records

With records turned on, every decision is posted to `/wp-json/silvertrail/v1/record` and stored with a random consent ID, the choices and the time. No IP address is stored. The `silvertrail_recorded` action fires for every decision, whether records are on or not, so a site can keep them elsewhere. The data comes from the browser and is not verified.

## Check what leaks

On the Services tab, **What a visitor's browser loads** opens a page of the site in a frame as a logged-out, first-time visitor who has not chosen yet. It lists every other server the page contacts before a choice and says what to do about each: turn on a matching preset, register a service in code, or host the file on the site. A prompt below it, ready to paste into an AI assistant, asks for the provider, purpose and privacy policy of each service it found, as the banner lists them.

The frame loads the page with `?silvertrail-scan`, which renders it without a logged-in user and without the visitor's stored choice. That only takes privileges away, so it needs no nonce. A page cache that ignores query strings would serve the cached page instead, so scan with the cache off or bypassed.

For CI, `testing/expect-no-leaks.mjs` gives a Playwright suite the same check. It lives in this repository and is not part of the release zip, so copy it into your tests or check out the repository:

```js
import { expectNoLeaks } from './silvertrail/testing/expect-no-leaks.mjs';

test('home page loads nothing before consent', async ({ page }) => {
	await expectNoLeaks(page, '/', { allow: ['cdn.example.com'] });
});
```

## WP-CLI

```bash
wp silvertrail services
wp silvertrail validate
wp silvertrail ask-again
wp silvertrail records export --file=records.csv
```

## Develop

```bash
composer install && npm install
npx wp-env start            # dev site on :8891, test site on :8892
npm run test:php
npx playwright test
composer lint && composer analyse
```
