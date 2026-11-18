# Consent signals

Silvertrail passes every choice on to the tools that need it, and it listens to the one signal browsers send by themselves. This page covers Google Consent Mode, the WP Consent API and Global Privacy Control.

## Google Consent Mode

A service lists the Consent Mode types it grants, for example `'google' => [ 'analytics_storage' ]`. The types are `ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`, `functionality_storage`, `personalization_storage` and `security_storage`.

As soon as one service lists a type, Silvertrail prints the default first in `<head>`: everything denied except `security_storage`, with `wait_for_update` at 500 milliseconds and ad data redaction on. A returning visitor's stored choice follows in the same script, before any tag loads. After every new decision, Silvertrail sends an update. A type is granted when the visitor agreed to any service that lists it.

Google tags stay blocked until consent. Set `$config['google']['mode'] = 'advanced'` in the [`silvertrail_config` filter](https://silvertrail.marcwieland.name/docs/services.md#the-config-filter) to load them before consent, so they send cookieless pings. `url_passthrough` in the same place passes ad click IDs in links while ad cookies are denied.

## WP Consent API

Every choice goes to the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/), so plugins that read the API follow it. Silvertrail registers as an opt-in consent plugin. `functional` is always allowed.

Each group maps to a WP Consent API category through its `consent_api` key. A category is allowed when the visitor agreed to every service in it. A category without services is allowed only when the visitor accepted everything. `statistics-anonymous` follows the `statistics` services.

## Global Privacy Control

[Global Privacy Control](https://globalprivacycontrol.org/) is a signal that a browser or an extension sends to every site, asking it not to sell or share the visitor's data. Silvertrail reads it as "only essential". A browser that sends it gets no banner and nothing that needs consent loads. The `silvertrail:change` event and the records name the source `gpc`.

The signal names no services, so the visitor can still allow more. The preferences say that the browser sends the signal, and what the visitor allows there, or with "Always allow" on an embed, is kept for later visits. A choice made before the signal was turned on no longer counts while it is on, so a visitor who once accepted everything gets only essential again. Silvertrail tells the two apart because the cookie marks every choice made under the signal with `"g": 1`. When the visitor turns the signal off, their last choice stays.

The same rule holds wherever Silvertrail reads the cookie: in the browser, in the Consent Mode update in `<head>`, and in `silvertrail_has()`, which also reads the `Sec-GPC` header.

Silvertrail answers `/.well-known/gpc.json` with `{"gpc": true, "lastUpdate": "…"}`, where the date is the day it first served the file. Tools that look for the file learn from it that the site honours the signal. The file must sit at the root of the domain, so a WordPress in a subdirectory and the subsites of a subdirectory multisite cannot serve it. The request also has to reach WordPress, which pretty permalinks arrange on Apache and the usual `try_files` rule arranges on nginx. A real file at that path wins.

The privacy policy guide under Settings › Privacy suggests a paragraph on how the site treats the signal, and one on records when they are on. Until the guide has shown Silvertrail's current text, the overview links to it, and `wp silvertrail validate` warns and prints the text. Set `gpc` to `false` in the `silvertrail_settings` filter to ask these visitors like everyone else, which also stops the file.

The [implementation guide](https://globalprivacycontrol.org/implementation) and the [W3C specification](https://w3c.github.io/gpc/) describe the signal in full.
