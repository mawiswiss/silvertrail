# Settings

Settings decide how the banner looks and behaves and what Silvertrail keeps. The admin holds the decisions a site owner makes, and code can set every one of them.

## The settings filter

The `silvertrail_settings` filter sets any setting, and code wins over the admin. The admin locks a field that code sets, marks it "Set in code" beside its name, and keeps the admin's own value underneath.

```php
add_filter( 'silvertrail_settings', function ( $settings ) {
	$settings['expiry_months'] = 12;
	$settings['records']       = true;
	return $settings;
} );
```

| Key | Admin | Values | Default |
| --- | --- | --- | --- |
| `enabled` | Top of the page | bool. Turned off, Silvertrail adds nothing to the site: no banner, no blocking, none of its tags. | `true` |
| `layout` | Design | `'modal'` (a dialog in the middle), `'box'` (the bottom right corner) or `'bar'` (across the page) | `'modal'` |
| `bar_position` | Design | `'top'` or `'bottom'`, the edge the bar sits on | `'top'` |
| `appearance` | Design | `'light'` or `'dark'`. The default `'theme'` takes whichever the theme is. | `'theme'` |
| `require_choice` | Design | bool. Turned on, the banner stays until the visitor chooses. Turned off, the close button, a click outside or Escape closes it without storing anything. `'modal'` always dims the page, and so does `'bar'` while it shows the preferences as the same centred dialog. `'box'` and the bar itself dim it only while a choice is required. `null` requires a choice for `'modal'` only. | `null` |
| `imprint_page` | Texts | page ID, `0` for none. See [Privacy and imprint pages](privacy-pages.md). | `0` |
| `floating_button` | Design | `'never'`, `'auto'` for pages without a link to `#silvertrail`, or `'always'` | `'never'` |
| `floating_position` | Design | `'left'` or `'right'`, the bottom corner of the floating button | `'left'` |
| `records` | Tools | bool. Keeps a [record](records.md) of each decision. | `false` |
| `load_css` | code | bool. Turned off, the theme's `silvertrail/silvertrail.css` is the whole stylesheet. | `true` |
| `expiry_months` | code | 1 to 13, how long the choice lasts in the browser | `6` |
| `retention_days` | code | 30 to 3650, how long records are kept | `365` |
| `gpc` | code | bool. [Global Privacy Control](consent-signals.md#global-privacy-control) counts as "only essential". | `true` |
| `trail` | code | bool. The preferences list what the page held back and loaded. | `true` |
| `webmcp` | code | bool. Offers agents the [WebMCP](agents.md#webmcp) tools in browsers that support WebMCP. | `true` |
| `webmcp_tokens` | code | list of WebMCP origin trial tokens, printed in the page head until they expire | `[]` |

An unknown key or an invalid value triggers a `_doing_it_wrong` notice. An invalid value falls back to the nearest valid one, or to the default. A setting the admin left at its default follows a new default in a later version.

`wp silvertrail settings` and the `silvertrail/get-settings` ability list every value and whether it comes from `code`, `admin` or `default`.

The presets and the texts are not in this filter. They have their own code paths: [presets](services.md#presets) through `silvertrail_register_service()` and [texts](texts.md) through the theme's JSON files.

## Preview while switched off

While `enabled` is off, add `?silvertrail-preview` to any URL to see that page as visitors would get it with Silvertrail on. Choices made in a preview are not recorded, and the Leak check scans as if Silvertrail were on.
