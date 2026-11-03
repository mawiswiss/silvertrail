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

## Develop

```bash
composer install && npm install
npx wp-env start            # dev site on :8891, test site on :8892
npm run test:php
npx playwright test
composer lint && composer analyse
```
