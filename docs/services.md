# Services and groups

A service is anything the site loads from another server: a tracker, a chat widget, an embed. Silvertrail holds each one back until the visitor agrees to it. Every service belongs to a group, and the banner asks per group and per service.

## Register a service

Register services on the `silvertrail_register` action, in a small plugin or the theme:

```php
add_action( 'silvertrail_register', function () {
	silvertrail_register_service( 'hubspot', [
		'group'       => 'marketing',
		'name'        => 'HubSpot',
		'provider'    => 'HubSpot, Inc.',
		'purpose'     => 'Tracks form conversions.',
		'privacy_url' => 'https://legal.hubspot.com/privacy-policy',
		'hosts'       => [ 'js.hs-scripts.com' ],
		'handles'     => [ 'hubspot-tracking' ],
	] );
} );
```

| Key | Type | Default | What it does |
| --- | --- | --- | --- |
| `group` | string | none, required | The group the service belongs to. A service in a group that does not exist is skipped. |
| `name` | string | the ID | The name the banner shows. |
| `provider` | string | `''` | The company behind the service, with its address. |
| `purpose` | string | `''` | What the service does, in one sentence. |
| `privacy_url` | string | `''` | The provider's privacy policy. |
| `cookies` | string | `''` | The cookies it sets, as text. |
| `expiry` | string | `''` | How long they last, as text. |
| `hosts` | list | `[]` | Hosts, or host and path, such as `google.com/maps`. Embeds from them wait behind a placeholder, and the trail and the Leak check name requests to them. |
| `handles` | list | `[]` | Script handles held back until consent, with their before and after code. |
| `google` | list | `[]` | The Consent Mode types the service grants, such as `analytics_storage`. |
| `consent` | `'required'` or `'exempt'` | `'required'` | An exempt service never needs a decision. |
| `tag_id` | string | `''` | The ID of a tracker preset, such as `GTM-XXXXXXX`. |

A service ID starts with a lowercase letter or a digit and goes on with lowercase letters, digits, `-` and `_`. Registering the ID of a preset, or of a service the admin turned on, changes only the given fields. Registering the same ID twice keeps only the second call.

A host matches itself and every subdomain, so `facebook.com` also covers `www.facebook.com`. A pattern with a path, such as `google.com/maps`, matches only URLs whose path starts with it. Silvertrail strips `https://`, `//` and `www.` from each pattern.

The config always holds a service with the ID `silvertrail` in the `essential` group. It lists Silvertrail's own cookie in the banner, and code cannot remove it.

Keep the config the same on every request, because Silvertrail asks every visitor again whenever the set of services changes. A banner opens only when at least one service outside the essential and in-place groups needs consent.

## Presets

Silvertrail ships eight presets. The tracker presets print the provider's official tag once their ID is set. The embed presets start switched on.

| ID | Group | ID format | Grants in Consent Mode |
| --- | --- | --- | --- |
| `google-tag-manager` | `marketing` | Container ID, `GTM-XXXXXXX` | `ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage` |
| `google-analytics` | `statistics` | Measurement ID, `G-XXXXXXXXXX` | `analytics_storage` |
| `google-ads` | `marketing` | Conversion ID, `AW-123456789` | `ad_storage`, `ad_user_data`, `ad_personalization` |
| `linkedin-insight` | `marketing` | Partner ID, `123456` | none |
| `meta-pixel` | `marketing` | Pixel ID, `1234567890` | none |
| `youtube` | `external-media` | none | none |
| `vimeo` | `external-media` | none | none |
| `google-maps` | `external-media` | none | none |

Registering a preset's ID turns it on with its defaults, and only the given fields change:

```php
add_action( 'silvertrail_register', function () {
	silvertrail_register_service( 'google-tag-manager', [ 'tag_id' => 'GTM-XXXXXXX' ] );
	silvertrail_register_service( 'youtube', [ 'cookies' => 'VISITOR_INFO1_LIVE, YSC' ] );
} );
```

To remove a service, even one the admin turned on, unset it in the `silvertrail_config` filter:

```php
add_filter( 'silvertrail_config', function ( $config ) {
	unset( $config['services']['google-maps'] );
	return $config;
} );
```

The Services tab marks each preset that code turned on, changed or removed. `wp silvertrail services` and the `silvertrail/list-services` ability show the same.

## Groups

The groups are `essential`, `preferences`, `statistics`, `marketing` and `external-media`. Add your own, or change one, with `silvertrail_register_group()` on the same action:

```php
add_action( 'silvertrail_register', function () {
	silvertrail_register_group( 'support', [
		'name'        => 'Support',
		'description' => 'Lets you chat with us on this page.',
		'consent_api' => 'preferences',
	] );
} );
```

| Key | Type | Default | What it does |
| --- | --- | --- | --- |
| `name` | string | the ID | The name the banner shows. |
| `description` | string | `''` | One sentence under the name. |
| `locked` | bool | `false` | A locked group is always on. `essential` is always locked. |
| `consent_api` | string | `'marketing'` | The WP Consent API category: `functional`, `preferences`, `statistics`, `statistics-anonymous` or `marketing`. |
| `in_place` | bool | `false` | Its services ask on their placeholder and never open the banner. `external-media` asks in place. |

| Group | Locked | WP Consent API | Asks in place |
| --- | --- | --- | --- |
| `essential` | yes | `functional` | no |
| `preferences` | no | `preferences` | no |
| `statistics` | no | `statistics` | no |
| `marketing` | no | `marketing` | no |
| `external-media` | no | `marketing` | yes |

A group that asks in place suits embeds only. A service in it that lists `handles` or a `tag_id` gets a warning, because the banner never prompts for it.

## The config filter

The `silvertrail_config` filter runs last, after every registration and the admin's presets. It receives the whole config and returns it:

```php
[
	'groups'   => [ 'essential' => [ 'name' => …, 'description' => …, 'locked' => true, 'consent_api' => 'functional', 'in_place' => false ], … ],
	'services' => [ 'hubspot' => [ 'group' => 'marketing', 'name' => 'HubSpot', … every key from the table above ], … ],
	'google'   => [ 'mode' => 'basic', 'url_passthrough' => false ],
]
```

Under `google`, `mode` is `basic` or `advanced`, and `url_passthrough` passes ad click IDs in links while ad cookies are denied:

```php
add_filter( 'silvertrail_config', function ( $config ) {
	$config['google']['mode']            = 'advanced';
	$config['google']['url_passthrough'] = true;
	return $config;
} );
```

Unknown keys, unknown Consent Mode types, a service in a group that does not exist and a tracker ID from code that does not fit its preset each trigger a `_doing_it_wrong` notice. `wp silvertrail validate` lists them.

## Gate a script

Mark a script in the HTML, and the browser ignores it until the visitor agrees to its service:

```html
<script type="text/plain" data-silvertrail="hubspot" src="https://js.hs-scripts.com/123.js"></script>
```

Then Silvertrail swaps in a real script and keeps the order of inline and external scripts. A module keeps its type in `data-silvertrail-type`:

```html
<script type="text/plain" data-silvertrail="hubspot" data-silvertrail-type="module" src="/widget.js"></script>
```

A script that WordPress enqueues needs no change. List its handle under the service's `handles`, and Silvertrail gates it together with the code WordPress adds before and after it. Data from `wp_localize_script()` stays as it is, because it loads nothing.

To find the handle of a script another plugin adds, open the page's source. WordPress prints the script as `<script src="…" id="example-tracker-js">`, and the handle is the ID without `-js`:

```php
add_action( 'silvertrail_register', function () {
	silvertrail_register_service( 'example-tracker', [
		'group'   => 'statistics',
		'name'    => 'Example Tracker',
		'purpose' => 'Counts visits and page views.',
		'handles' => [ 'example-tracker' ],
		'hosts'   => [ 'cdn.example-tracker.com', 'collect.example-tracker.com' ],
	] );
} );
```

A script that a plugin prints as plain HTML has no handle. Ask its author for a filter, or turn the plugin's output off and print the tag yourself, gated as above.

A gated script that waits for `DOMContentLoaded` misses it, because the event has passed by the time the visitor agrees. Such a service starts only on the next page. The Leak check reports it.

## Embeds

Every `<iframe>` whose address matches a service's `hosts` waits in an empty `<template>` behind a placeholder, unless the service is essential. Silvertrail finds them in oEmbeds, in post content, in Custom HTML blocks anywhere, including templates, template parts and patterns, and in text and HTML widgets. The placeholder asks for this once or for good, and opens no banner.

An iframe that the theme prints in PHP passes none of these filters. Render it as a Custom HTML block, so it gets its placeholder too:

```php
echo do_blocks( '<!-- wp:html --><iframe src="https://www.youtube-nocookie.com/embed/VIDEO" title="Video"></iframe><!-- /wp:html -->' );
```

### More buttons on the placeholder

The `silvertrail_embed_actions` filter adds buttons under the placeholder's own, with no template of your own. Each button allows services for good or opens the preferences. The filter also receives the placeholder's service ID, so a button can appear on some placeholders only.

```php
add_filter( 'silvertrail_embed_actions', function ( array $actions, string $service ): array {
	$actions[] = [ 'label' => __( 'Always allow all external media', 'my-theme' ), 'grant' => 'external-media' ];
	$actions[] = [ 'label' => __( 'Privacy settings', 'my-theme' ), 'open' => true ];
	return $actions;
}, 10, 2 );
```

| Key | Type | What it does |
| --- | --- | --- |
| `label` | string | The button's text. Silvertrail's texts do not include it, so translate it in your own text domain. |
| `grant` | string or list | Service or group IDs. A group stands for all its services. The button adds them to the visitor's choice, and every placeholder of these services on the page loads. |
| `open` | bool | Opens the preferences, where the visitor chooses per service. Used when `grant` is not set. |

A button without a label, or whose `grant` names no service that needs consent, is left out. A grant counts like "Always allow": it is recorded with the source `embed`, and the banner still asks about the rest. A theme's own `embed.php` shows the buttons only if it renders `$args['actions']`, as the plugin's template does. The [API](api.md#allow-services-from-html) shows the attribute behind the `grant` button, for a button elsewhere on the page.

[Styling](styling.md#templates) shows how to change the placeholder's markup.

To read the visitor's choice in PHP or in the browser, see the [API](api.md).
