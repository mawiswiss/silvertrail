# Reopen the preferences

Visitors need a way back to their choice on every page. A link, the shortcode, the block, a menu item or the floating button opens the preferences, and the [API](api.md#open-the-preferences) does the same from a script.

## Links

Any link to `#silvertrail` opens the preferences, and so does any element with `data-silvertrail-open`:

```html
<a href="#silvertrail">Privacy settings</a>
<button type="button" data-silvertrail-open>Privacy settings</button>
```

The `[silvertrail_link]` shortcode prints such a link, with its own text as `[silvertrail_link]Cookie settings[/silvertrail_link]`. The Silvertrail Link block (`silvertrail/reopen-link`) under Widgets does the same in the editor. Put one in the footer, so it is on every page.

## Menus

In a classic theme, Appearance › Menus has a Silvertrail box. Its Privacy settings item adds a custom link to `#silvertrail`, which moves like any other menu item. It takes its label from the site's language, and the label stays editable in the menu. On the shell, `wp menu item add-custom <menu> "Privacy settings" "#silvertrail"` adds the same link. While Silvertrail is switched off, menus leave such links out, as the shortcode and the block print nothing.

The Navigation block of a block theme and the Customizer have no Silvertrail box. Add a custom link to `#silvertrail` there.

## Floating button

The floating button is off by default. Turned on under Settings › Silvertrail › Design › Look, or with `floating_button` set to `'auto'`, it sits in a bottom corner of every page without such a link. `'always'` shows it next to a link too, and `floating_position` picks the left or the right corner. The `silvertrail_reopen_icon` filter swaps its icon for inline SVG or an `<img>`, and the button keeps its label for screen readers:

```php
add_filter( 'silvertrail_reopen_icon', function () {
	return '<img src="' . esc_url( get_theme_file_uri( 'images/privacy.svg' ) ) . '" alt="">';
} );
```

The theme's `silvertrail/silvertrail.css` styles it through `.silvertrail-reopen`, `.silvertrail-reopen--left` and `.silvertrail-reopen--right`.
