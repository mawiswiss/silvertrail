# Texts

Every word the banner, the placeholders and the floating button show is a text with a key. A site changes them in the admin, in a JSON file in the theme or in code.

## Where texts come from

Texts stack up in this order, and the later one wins:

1. The English source and its official translation from translate.wordpress.org.
2. A developer translation from the theme: `silvertrail/texts-{locale}.json`, such as `silvertrail/texts-de_CH.json`, else `silvertrail/texts.json` for every language. A child theme wins over its parent.
3. The admin's edits under Settings › Silvertrail › Texts, stored per language.
4. The `silvertrail_texts` filter.

```json
{
	"title": "Datenschutz",
	"accept_all": "Alle akzeptieren",
	"embed_remember": "%s immer zulassen"
}
```

The Texts tab shows the same JSON with every key, ready to copy into the file. An empty text keeps the one below it. `embed_text` and `embed_remember` keep exactly one `%s`, where the service name goes. `wp silvertrail validate` reports unknown keys, texts that are not strings, a missing `%s` and broken files.

## Text keys

| Key | English source |
| --- | --- |
| `title` | Privacy settings |
| `intro` | We use cookies and similar technologies. Some are needed for this site to work. Everything else only runs if you agree. |
| `accept_all` | Accept all |
| `reject_all` | Only essential |
| `preferences` | Choose |
| `preferences_intro` | Choose what may run on this site. You can change it at any time. |
| `save` | Save choices |
| `close` | Close |
| `provider` | Provider |
| `purpose` | Purpose |
| `cookies` | Cookies |
| `expiry` | Lifetime |
| `privacy_url` | Privacy policy |
| `privacy_link` | Privacy policy |
| `imprint_link` | Imprint |
| `embed_text` | This content comes from %s. Loading it tells them about your visit. |
| `embed_load` | Load content |
| `embed_remember` | Always allow %s |
| `reopen` | Privacy settings |
| `gpc` | Your browser sends Global Privacy Control, so only essential services run unless you allow more here. |
| `trail_title` | On this page |
| `trail_held` | Held back |
| `trail_loaded` | Loaded from |
| `trail_unknown` | not listed here |
| `trail_none` | Nothing from other sites. |

`privacy_url` labels the link in a service's details, and `privacy_link` the site's own privacy policy under the banner. `reopen` is the floating button's label for screen readers.

## Ship texts from a plugin

To ship the translation from a plugin instead of the theme, return another path or an array in the `silvertrail_translation` filter. It receives the theme's file, or `''`, and the locale:

```php
add_filter( 'silvertrail_translation', function ( $translation, $locale ) {
	return __DIR__ . "/texts/{$locale}.json";
}, 10, 2 );
```

A missing file is skipped.

## The texts filter

The `silvertrail_texts` filter receives every text in the visitor's language, after the admin's edits, and has the last word:

```php
add_filter( 'silvertrail_texts', function ( $texts ) {
	$texts['reject_all'] = 'Necessary only';
	return $texts;
} );
```

The services' and groups' own names and descriptions are not texts. They come from the [config](https://silvertrail.marcwieland.name/docs/services.md).
