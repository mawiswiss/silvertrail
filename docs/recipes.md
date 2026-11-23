# Recipes

Silvertrail stays small, so it ships no setting for a need that only some sites have. Each recipe here is such a need, solved with a few lines of the site's own code on top of the [API](api.md). Put the PHP in a small plugin or the theme, and the JavaScript in a script that loads on every page. Replace the example IDs, hosts and URLs with the site's own.

## Answer the banner in code

Silvertrail asks every visitor before anything that needs consent loads. It knows no regions and never starts a service before a yes on its own. The first three recipes decide for some visitors in code, through the [`silvertrail:before-prompt`](api.md#events) event, and the same rules hold for all of them:

- The answer is `prompt`, `accept-all` or `reject-all`. `accept-all` and `reject-all` store the choice with the source `hook`.
- A check that fails or takes longer than three seconds opens the banner, so a mistake falls back to asking.
- Global Privacy Control is read before the event, so a browser that sends it gets only essential whatever the answer would have been.
- Pages come from the cache, so the browser decides, on the first visit. Add the listener in a script that runs before `DOMContentLoaded`, such as an inline, deferred or module script.

Do not ask an outside geolocation service from the browser. The request alone hands the visitor's IP address to that service before any choice, and that is the kind of request Silvertrail holds back.

## Decide by time zone

**Use case:** the site treats visitors in some regions differently and wants no extra request to find the region.

The device's time zone costs no request. It says how the device is set, not where the visitor is:

```js
const ZONES = []; // The time zones the site has decided on, such as 'America/Chicago'.

window.addEventListener('silvertrail:before-prompt', (event) => {
	const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
	event.detail.respondWith(ZONES.includes(zone) ? 'accept-all' : 'prompt');
});
```

## Decide by country

**Use case:** the site treats visitors from some countries differently, and its CDN knows the visitor's country.

A CDN passes the country to WordPress in a header, such as `CF-IPCountry` on Cloudflare with IP geolocation on, or `CloudFront-Viewer-Country` on CloudFront once an origin request policy forwards it. A REST route of the site's own hands it to the browser, and must never be cached:

```php
add_action( 'rest_api_init', function () {
	register_rest_route( 'mysite/v1', '/region', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$country  = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '' ) );
			$response = new WP_REST_Response( [ 'country' => $country ] );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		},
	] );
} );
```

```js
const COUNTRIES = []; // The countries the site has decided on, as ISO codes such as 'US'.

window.addEventListener('silvertrail:before-prompt', (event) => {
	event.detail.respondWith(
		fetch('/?rest_route=/mysite/v1/region')
			.then((response) => response.json())
			.then(({ country }) => (COUNTRIES.includes(country) ? 'accept-all' : 'prompt')),
	);
});
```

## Run services until the visitor objects

**Use case:** in some regions, the site runs its services until the visitor objects, instead of asking first. Whether that fits a region is the site's decision, not Silvertrail's. [Opt-in and opt-out by region](#opt-in-and-opt-out-by-region) gives a first orientation.

Answer `accept-all` for those visitors, by time zone or by country as above:

- Every service starts at once, and no banner opens. On a site whose services all ask in place, such as embeds only, the placeholders load their content without a click.
- The choice is stored like any other, with the source `hook` in `silvertrail:change` and in the records.
- The visitor still needs a way to see what runs and to object, so link to `#silvertrail` on every page or set `floating_button` to `'always'`.

To show the preferences once, as the notice that says what runs, open them after that first choice. Because the choice is made, they close on a click outside. They dim the page, except in the box, which keeps them in its corner:

```js
window.addEventListener('silvertrail:change', (event) => {
	if (event.detail.source === 'hook') {
		window.silvertrail.open();
	}
});
```

"Only essential" in there stores the objection. What already ran keeps running on this page, and the next page loads none of it.

## Opt-in and opt-out by region

Opt-in asks first and starts a service only after a yes. Silvertrail works this way for every visitor until the site's code answers the banner. Opt-out starts the services at once and stops them when the visitor objects, as the recipe above does.

Which of the two a region expects is a legal question. As a rough orientation, not legal advice:

- **EU and EEA:** opt-in. What the site does not strictly need to deliver the page waits for consent.
- **UK:** opt-in for most services. Under the Data (Use and Access) Act 2025, statistics whose only purpose is to improve the site may run until the visitor objects, as long as the site says so clearly. Advertising and tracking across sites still wait for consent.
- **Switzerland:** telling visitors what runs and that they can refuse is generally enough, under Article 45c of the Telecommunications Act. The Federal Data Protection and Information Commissioner expects consent for more intrusive tracking, such as high-risk profiling.
- **US states:** the state privacy laws use opt-out. California and a growing number of other states require honouring signals such as [Global Privacy Control](consent-signals.md#global-privacy-control), which Silvertrail reads by default.

The rules differ by service and change over time. Have a lawyer review them in depth, for each site and the regions it serves, before any visitor gets opt-out. [Privacy and imprint pages](privacy-pages.md) gives the same kind of orientation for the pages the banner links.

## Quick actions on the privacy policy

**Use case:** the privacy policy names each service, and next to it the visitor can allow or withdraw that service, or a whole group, without going through the preferences.

A link to `#silvertrail` opens the preferences in place. An address that ends in it, such as `https://example.com/privacy-policy/#silvertrail` in an email, opens them as the page loads. A button with `data-silvertrail-grant` allows services and groups without a script, as the [API](api.md#allow-services-from-html) shows. Hash links only open the preferences, because a link that allowed a service as the page loads would let any outside link agree for the visitor.

To allow and withdraw with the same button, give it the ID of a service or a group, and let a short script switch it:

```html
<p>
	<a href="#silvertrail">Change your privacy settings</a>
</p>
<p>
	<button type="button" data-consent-toggle="youtube" aria-pressed="false">YouTube videos</button>
	<button type="button" data-consent-toggle="statistics" aria-pressed="false">All statistics</button>
</p>
<script>
	window.addEventListener('DOMContentLoaded', () => {
		const consent = window.silvertrail;
		const buttons = document.querySelectorAll('[data-consent-toggle]');
		if (!consent) {
			return;
		}
		const pressed = { granted: 'true', mixed: 'mixed', denied: 'false' };
		const show = () => {
			buttons.forEach((button) => button.setAttribute('aria-pressed', pressed[consent.state(button.dataset.consentToggle).status]));
		};
		buttons.forEach((button) => {
			button.addEventListener('click', () => {
				const id = button.dataset.consentToggle;
				consent.has(id) ? consent.revoke(id) : consent.grant(id);
			});
		});
		window.addEventListener('silvertrail:change', show);
		show();
	});
</script>
```

The `status` from `state()` is `granted`, `mixed` or `denied`, and `aria-pressed` passes it on to screen readers as `true`, `mixed` or `false`. CSS can style each state through `[aria-pressed]`. A click on a partly allowed group allows the rest, and the next one withdraws the whole group. The change event keeps every button right after a choice in the banner or another button.

A service that already runs on the page stops only on the next page view after it is withdrawn. Without Silvertrail's script, such as while it is switched off, the buttons stay as they are and do nothing.

In WordPress, paste the markup and the script into a Custom HTML block. The block keeps the script only for users who may post unfiltered HTML, such as administrators on a single site. Otherwise put the script into the theme and only the markup into the page.

## Load your own script after consent

**Use case:** a script needs code around it, such as a chat widget that is configured before it loads, so a gated `<script>` tag is not enough.

Register the service first, with its hosts, so the banner lists it and the Leak check knows it. Then ask `window.silvertrail` and listen for the change:

```js
let started = false;

function startChat() {
	if (started || !window.silvertrail.has('chat')) {
		return;
	}
	started = true;
	const script = document.createElement('script');
	script.src = 'https://widget.example-chat.com/loader.js';
	document.head.append(script);
}

document.addEventListener('DOMContentLoaded', startChat);
window.addEventListener('silvertrail:change', startChat);
```

Load it as a deferred script or in the footer, so it is listening when `DOMContentLoaded` fires. The change event covers a visitor who agrees later, on the same page.

## Send each decision to another system

**Use case:** the site keeps consent records in a CRM or a data warehouse, instead of or next to Silvertrail's own table.

Hooking `silvertrail_recorded` is enough to make the browser post every decision. Hand the work to a scheduled event, so the visitor's request stays fast:

```php
add_action( 'silvertrail_recorded', function ( $record ) {
	wp_schedule_single_event( time(), 'mysite_send_consent', [ $record ] );
} );

add_action( 'mysite_send_consent', function ( $record ) {
	wp_remote_post( 'https://crm.example.com/consent', [
		'headers' => [ 'Content-Type' => 'application/json' ],
		'body'    => wp_json_encode( $record ),
		'timeout' => 10,
	] );
} );
```

[Records](records.md#keep-records-elsewhere) describes the array. The data comes from the browser and is not verified.

## Use the choice on a form post

**Use case:** a form passes its data on to a service only with consent, such as a newsletter signup that also goes to HubSpot.

A form post never comes from the page cache, so PHP can read the visitor's choice there:

```php
add_action( 'admin_post_nopriv_newsletter', 'mysite_newsletter' );
add_action( 'admin_post_newsletter', 'mysite_newsletter' );

function mysite_newsletter() {
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

	if ( silvertrail_has( 'hubspot' ) ) {
		wp_remote_post( 'https://api.example.com/subscribe', [ 'body' => [ 'email' => $email ] ] );
	}

	wp_safe_redirect( home_url( '/thanks/' ) );
	exit;
}
```

Never use `silvertrail_has()` to decide what a page prints. A cached page would hand one visitor's choice to the next, and the service would start only after a reload. Print the tag [gated](services.md#gate-a-script) instead, and let the browser decide.
