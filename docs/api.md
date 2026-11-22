# API

The PHP functions, the JavaScript API and the events read the visitor's choice, change it and open the preferences. Other plugins and the site's own code use them to base their own decisions on the same choice that Google and the WP Consent API get. Every hook Silvertrail offers is listed at the end, with the page that explains it.

## PHP functions

| Function | Returns or does |
| --- | --- |
| `silvertrail_register_service( string $id, array $args = [] )` | Registers a service, or turns on and changes a preset. Call it on the `silvertrail_register` action. [Services](services.md#register-a-service) lists the keys. |
| `silvertrail_register_group( string $id, array $args )` | Registers a group, or changes one. Call it on the same action. [Groups](services.md#groups) lists the keys. |
| `silvertrail_has( string $service ): bool` | Whether the visitor agreed to the service. |
| `silvertrail_config(): array` | The final config, in the shape the [config filter](services.md#the-config-filter) receives. Call it on `init` or later. |

```php
if ( silvertrail_has( 'hubspot' ) ) {
	wp_remote_post( 'https://api.example.com/subscribe', [ 'body' => [ 'email' => $email ] ] );
}
```

`silvertrail_has()` reads the cookie and the `Sec-GPC` header. Essential services always return `true`, and an unknown ID returns `false`. Use it only on requests a page cache never serves, such as form posts and REST calls. Pages render every gated thing blocked, and the browser decides, so never use it to decide what a page prints.

## JavaScript

`window.silvertrail` reads and changes the choice in the browser:

```js
if (window.silvertrail.has('hubspot')) {
	startHubSpotForms();
}

window.silvertrail.open();
window.silvertrail.set(['google-analytics', 'youtube']);
```

| Call | Returns or does |
| --- | --- |
| `has(id)` | Whether the visitor agreed to the service. Essential services are always `true`. |
| `set(ids)` | Stores exactly these services as the choice. Unknown and essential IDs are ignored. |
| `grant(id)` | Adds one service to the choice, as "Always allow" on an embed does. |
| `open()` | Opens the preferences. |
| `decided()` | Whether the visitor made a full choice. A grant from an embed alone does not count. |
| `trail()` | `{ held, loaded }`: the services the page held back and the other hosts it loaded from. |
| `reset()` | Forgets the choice in this browser, so the next page view is a first visit. Meant for testing. |

Silvertrail's script loads with `defer`, so `window.silvertrail` exists from `DOMContentLoaded` on. An inline script runs earlier, so it waits for that event before it calls the API.

### Open the preferences

`open()` opens the preferences from a script, such as a button of your own. HTML needs no script for it: any link to `#silvertrail` and any element with `data-silvertrail-open` open them as well.

```html
<a href="#silvertrail">Privacy settings</a>
<button type="button" data-silvertrail-open>Privacy settings</button>
```

[Reopen the preferences](reopen.md) covers the shortcode, the blocks, menus and the floating button.

## Events

`silvertrail:change` fires on `window` after every choice:

```js
window.addEventListener('silvertrail:change', (event) => {
	const { consents, silent, source } = event.detail;
});
```

| Field | What it holds |
| --- | --- |
| `consents` | The IDs of the services the visitor agreed to, without the essential ones. |
| `silent` | `true` when the choice came from Global Privacy Control or a `before-prompt` answer, without the visitor. |
| `source` | `banner`, `embed`, `gpc`, `hook` or `agent`. |

`silvertrail:before-prompt` fires on `window` once per page view for a visitor who has not chosen yet, before the banner opens. It also fires on a site that needs no banner because its services all ask in place, and never under Global Privacy Control. Add the listener in a script that runs before `DOMContentLoaded`, such as an inline, deferred or module script, because an `async` script may come too late. It answers with `prompt`, `accept-all` or `reject-all`, or with a promise of one:

```js
window.addEventListener('silvertrail:before-prompt', (event) => {
	event.detail.respondWith(fetch('/my-region-check').then((r) => r.text()));
});
```

An answer that fails or takes longer than three seconds opens the banner. `accept-all` and `reject-all` store the choice with the source `hook`. The [recipes](recipes.md#answer-the-banner-in-code) build on this event to decide by region.

## The cookie

Silvertrail keeps the choice in one first-party cookie, `silvertrail`. On a multisite network, every site but the main one adds its ID, as in `silvertrail_3`. The Leak check uses `silvertrail_scan` for ten minutes and leaves the visitor's own cookie alone. The cookie's path is the site's home path, it lasts `expiry_months`, and it is `SameSite=Lax`, plus `Secure` on HTTPS.

Its value is URL-encoded JSON:

```json
{ "n": 3, "c": ["google-analytics", "youtube"], "t": 1763452800, "g": 1, "i": "9f86d081884c7d659a2feaa0c55ad015" }
```

| Field | What it holds |
| --- | --- |
| `n` | The revision of the services when the visitor chose. A cookie with a lower `n` than the site's is outdated, and the visitor is asked again. |
| `c` | The services the visitor agreed to, without the essential ones. |
| `t` | The time of the choice, in seconds since 1970. |
| `p` | `1` while only an embed's "Always allow" decided anything, so the banner still asks. |
| `g` | `1` when the choice was made while the browser sent Global Privacy Control. |
| `i` | A random consent ID, set only while records are on. |

Read the choice through `window.silvertrail` or `silvertrail_has()`, not from the cookie directly, because both also apply the revision and Global Privacy Control.

## Hooks

| Hook | Kind | Receives | Explained in |
| --- | --- | --- | --- |
| `silvertrail_register` | action | nothing | [Services and groups](services.md#register-a-service) |
| `silvertrail_config` | filter | `$config`: the groups, the services and `google`. It runs last. | [The config filter](services.md#the-config-filter) |
| `silvertrail_settings` | filter | `$settings`: every setting but the presets and the texts | [Settings](settings.md#the-settings-filter) |
| `silvertrail_design` | filter | `$design`, the design values, and `$appearance`, `light` or `dark` | [The design filter](styling.md#the-design-filter) |
| `silvertrail_reopen_icon` | filter | `$icon`: the floating button's icon, as inline SVG or an `<img>` | [Floating button](reopen.md#floating-button) |
| `silvertrail_translation` | filter | `$translation`, the theme's JSON file or `''`, and `$locale` | [Ship texts from a plugin](texts.md#ship-texts-from-a-plugin) |
| `silvertrail_texts` | filter | `$texts`: every text in the visitor's language | [The texts filter](texts.md#the-texts-filter) |
| `silvertrail_recorded` | action | `$record`: `consent_id`, `revision`, `choices`, `source` and `created_at` | [Keep records elsewhere](records.md#keep-records-elsewhere) |

The browser's hooks are the two [events](#events) above.
