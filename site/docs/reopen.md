# Reopen the preferences

Visitors need a way back to their choice on every page. A link, the shortcode, the block or the floating button opens the preferences, and the [API](https://silvertrail.marcwieland.name/docs/api.md#open-the-preferences) does the same from a script.

## Links

Any link to `#silvertrail` opens the preferences, and so does any element with `data-silvertrail-open`:

```html
<a href="#silvertrail">Privacy settings</a>
<button type="button" data-silvertrail-open>Privacy settings</button>
```

The `[silvertrail_link]` shortcode prints such a link, with its own text as `[silvertrail_link]Cookie settings[/silvertrail_link]`. The Privacy settings link block (`silvertrail/reopen-link`) does the same in the editor. Put one in the footer, so it is on every page.

## Floating button

The floating button is off by default. Turned on under Settings › Silvertrail › Design › Look, or with `floating_button` set to `'auto'`, it sits in a bottom corner of every page without such a link. `'always'` shows it next to a link too, and `floating_position` picks the left or the right corner. The `silvertrail_reopen_icon` filter swaps its icon for inline SVG or an `<img>`, and the button keeps its label for screen readers:

```php
add_filter( 'silvertrail_reopen_icon', function () {
	return '<img src="' . esc_url( get_theme_file_uri( 'images/privacy.svg' ) ) . '" alt="">';
} );
```

The theme's `silvertrail/silvertrail.css` styles it through `.silvertrail-reopen`, `.silvertrail-reopen--left` and `.silvertrail-reopen--right`.
