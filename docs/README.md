# Silvertrail documentation

Silvertrail is the one place where a WordPress site handles everything it loads from other servers. It knows each service, holds it back until the visitor agrees and passes the choice on to Google, to other plugins and to the site's own code. The cookie banner is the part that asks visitors today, and it steps aside wherever the browser or an agent answers instead. This is the complete reference for developers and their agents: every hook, key, setting, attribute and command. The same pages are in the plugin folder under `docs/` and on https://silvertrail.marcwieland.name/docs/, where https://silvertrail.marcwieland.name/llms.txt lists them as Markdown for agents.

## How it works

- **Services and groups.** A [service](services.md) is anything the site loads from another server. It belongs to a group, such as statistics or marketing. Presets cover the common trackers and embeds, and code registers the rest.
- **Nothing loads before yes.** Marked scripts wait as `type="text/plain"`, scripts that WordPress enqueues are held back by their handle, and embeds wait in a `<template>` behind a placeholder. Gated scripts run in their original order once allowed, external and inline.
- **The browser decides.** The server renders every gated script and embed blocked, and Silvertrail's script decides on each page view. That is why page caches need no setup, and why PHP reads the choice only on requests a cache never serves. A stale cached page neither asks again nor overwrites a newer choice.
- **One cookie.** The choice lives in a [first-party cookie](api.md#the-cookie) with a revision number. When the set of services changes, the revision goes up and every visitor is asked again. Services that flip back and forth within a minute trigger a `_doing_it_wrong` notice, because they would ask on every page.
- **One choice, passed on.** Every choice goes to [Google Consent Mode and the WP Consent API](consent-signals.md). Other plugins and the site's own code read the same choice through `window.silvertrail`, the `silvertrail:change` event and `silvertrail_has()`, and the `silvertrail_recorded` action hands each decision to the site's own records. The [API](api.md) lists them all.
- **Several ways to answer.** The banner asks visitors, with "Accept all" and "Only essential" as the same button side by side. An embed asks on its own placeholder. A browser that sends [Global Privacy Control](consent-signals.md#global-privacy-control) gets "only essential" without a banner, and an agent in a browser with [WebMCP](agents.md#webmcp) answers through tools.
- **Built for agents.** This reference ships in the plugin, so an agent reads the version the site runs. `wp silvertrail settings` and `wp silvertrail validate` let it check its own work, the [abilities](agents.md#abilities-and-mcp) do the same over MCP, and the WebMCP tools let it check a site in the browser. [Agents](agents.md) covers each of them.
- **Code wins over the admin.** The admin holds only decisions, and it marks every field that code sets with "Set in code".
- **Mistakes are loud.** An unknown key, a tag ID that does not fit or an invalid setting triggers a `_doing_it_wrong` notice, and [`wp silvertrail validate`](cli.md#validate) lists them all.
- **Small and self-contained.** The front end adds one deferred script and one stylesheet, and the plugin has no external dependencies. Only a browser with WebMCP fetches a second small script for the agent tools. The plugin contacts no outside service on its own, except Write with AI when an admin clicks it, and uninstalling it removes its options, its table and its scheduled job.

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
| Services and groups | `silvertrail_register` action with `silvertrail_register_service()` and `silvertrail_register_group()`, see [Services and groups](services.md) |
| Last word on services | `silvertrail_config` filter, see [The config filter](services.md#the-config-filter) |
| Settings | `silvertrail_settings` filter, see [Settings](settings.md) |
| Reading and setting the choice | `silvertrail_has()`, `window.silvertrail`, the `silvertrail:change` and `silvertrail:before-prompt` events, see [API](api.md) |
| Look | the theme's `theme.json`, the `silvertrail_design` and `silvertrail_reopen_icon` filters, the theme's `silvertrail/silvertrail.css`, template overrides, see [Style the banner](styling.md) |
| Texts | the theme's `silvertrail/texts-{locale}.json`, the `silvertrail_translation` and `silvertrail_texts` filters, see [Texts](texts.md) |
| Records | `silvertrail_recorded` action, see [Records](records.md) |
| Shell | `wp silvertrail services`, `settings`, `validate`, `ask-again`, `write-styles` and `records export`, see [WP-CLI](cli.md) |
| Agents | the WebMCP tools and the abilities through the MCP Adapter, see [Agents](agents.md) |
| Privacy page and imprint | WordPress's privacy policy page and `imprint_page`, see [Privacy and imprint pages](privacy-pages.md) |
| What the plugin does not ship | regions, opt-out and other needs solved in the site's own code, see [Recipes](recipes.md) |

## What it does not do

Silvertrail does not implement the IAB Transparency and Consent Framework, so an ad network that requires a TCF consent string needs a different banner. It does not detect countries. A site that treats regions differently decides in the `silvertrail:before-prompt` event, as the [recipes](recipes.md) show. It does not crawl the site for cookies either. The [Leak check](leak-check.md) looks at the pages you list and shows what they actually load.

## Pages

- [Services and groups](services.md): register services, presets, groups, the config filter, gated scripts and embeds.
- [Settings](settings.md): every setting, its values and where it is set.
- [API](api.md): the PHP functions, the JavaScript API, the events, the cookie and every hook.
- [Reopen the preferences](reopen.md): links, the shortcode, the blocks, menus and the floating button.
- [Style the banner](styling.md): the theme's values, the design filter, your own stylesheet, Write with AI and templates.
- [Texts](texts.md): where texts come from, every text key and the translation filters.
- [Consent signals](consent-signals.md): Google Consent Mode, the WP Consent API and Global Privacy Control.
- [Records](records.md): what is stored, for how long, and how to keep records elsewhere.
- [Leak check](leak-check.md): what a page loads before and after consent, in the admin and in CI.
- [WP-CLI](cli.md): every `wp silvertrail` command.
- [Agents](agents.md): setting up and checking a site with an agent, the WebMCP tools and the abilities.
- [Recipes](recipes.md): use cases the plugin does not ship, solved with the site's own code, such as regions and opt-out.
- [Privacy and imprint pages](privacy-pages.md): which pages a site needs, what goes on them and how Silvertrail links them.
