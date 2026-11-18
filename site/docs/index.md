# Silvertrail documentation

Silvertrail holds back everything a WordPress site loads from other servers until the visitor agrees, and passes the choice on. This is the complete reference for developers and their agents: every hook, key, setting, attribute and command. The same pages are in the plugin folder under `docs/` and on https://silvertrail.marcwieland.name/docs/, where https://silvertrail.marcwieland.name/llms.txt lists them as Markdown for agents.

## How it works

- **Services and groups.** A [service](https://silvertrail.marcwieland.name/docs/services.md) is anything the site loads from another server. It belongs to a group, such as statistics or marketing. Presets cover the common trackers and embeds, and code registers the rest.
- **Nothing loads before yes.** Marked scripts wait as `type="text/plain"`, scripts that WordPress enqueues are held back by their handle, and embeds wait in a `<template>` behind a placeholder.
- **The browser decides.** The server renders every gated script and embed blocked, and Silvertrail's script decides on each page view. That is why page caches need no setup, and why PHP reads the choice only on requests a cache never serves.
- **One cookie.** The choice lives in a [first-party cookie](https://silvertrail.marcwieland.name/docs/api.md#the-cookie) with a revision number. When the set of services changes, the revision goes up and every visitor is asked again.
- **Signals pass on.** Every choice goes to [Google Consent Mode and the WP Consent API](https://silvertrail.marcwieland.name/docs/consent-signals.md). Global Privacy Control counts as "only essential" without a banner.
- **Code wins over the admin.** The admin holds only decisions, and it marks every field that code sets with "Set in code".
- **Mistakes are loud.** An unknown key, a tag ID that does not fit or an invalid setting triggers a `_doing_it_wrong` notice, and [`wp silvertrail validate`](https://silvertrail.marcwieland.name/docs/cli.md#validate) lists them all.

## Set up in code

A must-use plugin keeps the whole setup out of the admin and in version control. Save it as `wp-content/mu-plugins/silvertrail-setup.php`:

```php
<?php
/**
 * Plugin Name: Silvertrail setup
 */

add_action( 'silvertrail_register', function () {
	silvertrail_register_service( 'google-tag-manager', [ 'tag_id' => 'GTM-XXXXXXX' ] );

	silvertrail_register_service( 'hubspot', [
		'group'       => 'marketing',
		'name'        => 'HubSpot',
		'provider'    => 'HubSpot, Inc.',
		'purpose'     => 'Tracks form conversions.',
		'privacy_url' => 'https://legal.hubspot.com/privacy-policy',
		'hosts'       => [ 'js.hs-scripts.com', 'hubspot.com' ],
	] );
} );

add_filter( 'silvertrail_settings', function ( $settings ) {
	$settings['layout']          = 'bar';
	$settings['bar_position']    = 'bottom';
	$settings['floating_button'] = 'auto';
	$settings['expiry_months']   = 12;
	return $settings;
} );
```

The callbacks run only while Silvertrail is active, so the file does no harm when it is not. Then run `wp silvertrail validate` until it reports no problem, and `wp silvertrail settings` to see every value marked `code`.

## Where each concern lives

| What | Where |
| --- | --- |
| Services and groups | `silvertrail_register` action with `silvertrail_register_service()` and `silvertrail_register_group()`, see [Services and groups](https://silvertrail.marcwieland.name/docs/services.md) |
| Last word on services | `silvertrail_config` filter, see [The config filter](https://silvertrail.marcwieland.name/docs/services.md#the-config-filter) |
| Settings | `silvertrail_settings` filter, see [Settings](https://silvertrail.marcwieland.name/docs/settings.md) |
| Reading and setting the choice | `silvertrail_has()`, `window.silvertrail`, the `silvertrail:change` and `silvertrail:before-prompt` events, see [API](https://silvertrail.marcwieland.name/docs/api.md) |
| Look | the theme's `theme.json`, the `silvertrail_design` and `silvertrail_reopen_icon` filters, the theme's `silvertrail/silvertrail.css`, template overrides, see [Style the banner](https://silvertrail.marcwieland.name/docs/styling.md) |
| Texts | the theme's `silvertrail/texts-{locale}.json`, the `silvertrail_translation` and `silvertrail_texts` filters, see [Texts](https://silvertrail.marcwieland.name/docs/texts.md) |
| Records | `silvertrail_recorded` action, see [Records](https://silvertrail.marcwieland.name/docs/records.md) |
| Shell | `wp silvertrail services`, `settings`, `validate`, `ask-again`, `write-styles` and `records export`, see [WP-CLI](https://silvertrail.marcwieland.name/docs/cli.md) |
| Agents | the WebMCP tools and the abilities through the MCP Adapter, see [Agents](https://silvertrail.marcwieland.name/docs/agents.md) |
| What the plugin does not ship | regions, opt-out and other needs solved in the site's own code, see [Recipes](https://silvertrail.marcwieland.name/docs/recipes.md) |

## Pages

- [Services and groups](https://silvertrail.marcwieland.name/docs/services.md): register services, presets, groups, the config filter, gated scripts and embeds.
- [Settings](https://silvertrail.marcwieland.name/docs/settings.md): every setting, its values and where it is set.
- [API](https://silvertrail.marcwieland.name/docs/api.md): the PHP functions, the JavaScript API, the events, the cookie and every hook.
- [Reopen the preferences](https://silvertrail.marcwieland.name/docs/reopen.md): links, the shortcode, the block and the floating button.
- [Style the banner](https://silvertrail.marcwieland.name/docs/styling.md): the theme's values, the design filter, your own stylesheet, Write with AI and templates.
- [Texts](https://silvertrail.marcwieland.name/docs/texts.md): where texts come from, every text key and the translation filters.
- [Consent signals](https://silvertrail.marcwieland.name/docs/consent-signals.md): Google Consent Mode, the WP Consent API and Global Privacy Control.
- [Records](https://silvertrail.marcwieland.name/docs/records.md): what is stored, for how long, and how to keep records elsewhere.
- [Leak check](https://silvertrail.marcwieland.name/docs/leak-check.md): what a page loads before and after consent, in the admin and in CI.
- [WP-CLI](https://silvertrail.marcwieland.name/docs/cli.md): every `wp silvertrail` command.
- [Agents](https://silvertrail.marcwieland.name/docs/agents.md): setting up and checking a site with an agent, the WebMCP tools and the abilities.
- [Recipes](https://silvertrail.marcwieland.name/docs/recipes.md): use cases the plugin does not ship, solved with the site's own code, such as regions and opt-out.
