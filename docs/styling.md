# Style the banner

The banner takes its colours, corners and buttons from the theme's `theme.json`. For anything beyond that, change single values in PHP, or let an AI assistant write one CSS file for the theme. The last section of this file is the brief for that assistant.

The layers stack up in this order, and the later one wins: the theme's `theme.json`, the `silvertrail_design` filter, the theme's `silvertrail/silvertrail.css`.

## From the theme

The banner, the embed placeholders and the floating button take their colours, corners and button shape from the theme's `theme.json`, including the active style variation. Under Settings › Silvertrail › Design, the appearance that matches the theme keeps its colours as they are. The other one puts the theme's lightest or darkest colour in the background. Classic themes without `theme.json` get a neutral light or dark palette.

## The design filter

Set your own values with the `silvertrail_design` filter. It receives the values for the chosen appearance and the appearance itself, `light` or `dark`. Its result is final. When it returns the same colours for both, the admin drops the choice:

```php
add_filter( 'silvertrail_design', function ( $design, $appearance ) {
	$design['accent'] = 'dark' === $appearance ? '#ff8a80' : '#c62828';
	$design['radius'] = '0.25rem';
	return $design;
}, 10, 2 );
```

Each key becomes a `--silvertrail-<key>` custom property: `background`, `text`, `muted`, `line`, `accent`, `on-accent`, `radius`, `button-padding` and `button-weight`. A hex `accent` without its own `on-accent` gets black or white text, whichever reads better. Values with quotes, semicolons, braces or angle brackets are dropped.

## Your own stylesheet

To change more than these values, add `silvertrail/silvertrail.css` to the theme or the child theme. Silvertrail loads it after its own styles and the filter's values, so a rule with the same selector wins. With `load_css` turned off in the `silvertrail_settings` filter, it is the whole stylesheet, and `assets/silvertrail.css` in the plugin folder is the place to start from.

An AI assistant writes the file best:

1. Under Settings › Silvertrail › Design › Custom styles, copy the prompt. Paste it into an AI assistant and describe the look you want, or add a screenshot of the site. A coding agent working in the theme reads this file instead, or calls the `silvertrail/get-styling-brief` ability.
2. The assistant answers with one CSS block. Save it in the theme, or the child theme, as `silvertrail/silvertrail.css`. Silvertrail loads it after its own styles, and the Design tab confirms that it found the file.
3. Check the centred banner, the box and the bar at the top and the bottom, with and without a required choice, the preferences view behind Choose, every appearance, an embed placeholder, the reopen button, a 320px wide screen and the keyboard. The Leak check under Tools should list no new connection.

## Write with AI

From WordPress 7.0, with an AI provider set up under Settings › Connectors, Write with AI under Design › Custom styles sends the prompt and a description of the look to the provider and shows the stylesheet it writes. Nothing is saved, so check it and save it in the theme. This is the one thing in Silvertrail that sends something to another server, and only when an admin clicks it.

On the shell, `wp silvertrail write-styles "A dark bar with rounded buttons"` does the same and prints the file. The `silvertrail/write-styles` ability serves both and stays out of MCP.

## Templates

To change the markup, copy a template from the plugin's `templates/` folder into the theme's `silvertrail/` folder. A child theme wins over its parent. Keep every `data-silvertrail-*` attribute, because the script finds the parts through them. `embed.php` also keeps its empty `<template>`, which Silvertrail fills with the embed.

Each template receives `$args`:

| Template | `$args` |
| --- | --- |
| `banner.php` | `layout` (`modal`, `box` or `bar`), `position` (`top` or `bottom`, for the bar), `appearance`, `trail` (bool), `groups` (each with `name`, `description`, `locked` and its `services`), `texts`, `privacy_url`, `imprint_url` |
| `embed.php` | `service` (the ID), `name`, `texts`, `theme` (the appearance) |
| `reopen-button.php` | `label`, `appearance`, `mode` (`auto` or `always`), `position` (`left` or `right`), `icon` (HTML from `silvertrail_reopen_icon`) |

`texts` holds every [text key](texts.md#text-keys) in the visitor's language.

## Brief for the AI

You write `silvertrail/silvertrail.css` for Silvertrail, a cookie consent banner for WordPress. The banner already takes its colours, corners and buttons from the theme, so change only what the site owner asks for.

Reply with the complete file in one `css` code block that starts with `/* silvertrail/silvertrail.css */`, and nothing else. If the site already has the file, return all of it with your changes.

### Tokens

Set tokens first, on `.silvertrail, .silvertrail-embed, .silvertrail-reopen`. Write rules only for what no token covers.

- `--silvertrail-background`, `--silvertrail-text`: the banner, the embed placeholder and the reopen button
- `--silvertrail-muted`, `--silvertrail-line`: secondary text and borders, mixed from text and background
- `--silvertrail-accent`, `--silvertrail-on-accent`: buttons and their labels, switches, focus outlines
- `--silvertrail-radius`, `--silvertrail-button-padding`, `--silvertrail-button-weight`: corners and buttons
- `--silvertrail-width`, `--silvertrail-gutter`: width and inner padding of `.silvertrail`

### Classes

```
.silvertrail                        <dialog>, plus .silvertrail--modal (centred), .silvertrail--box (corner), or .silvertrail--bar with .silvertrail--top or .silvertrail--bottom
  .silvertrail__inner
    .silvertrail__title
    .silvertrail__view              entrance view, then the preferences view
      .silvertrail__text
      .silvertrail__note
      .silvertrail__groups > .silvertrail__group
        .silvertrail__switch > input + .silvertrail__group-name
        .silvertrail__group-text
        .silvertrail__details > summary + .silvertrail__services > .silvertrail__service
          .silvertrail__switch > input + .silvertrail__service-name
          .silvertrail__info        <dl>
      .silvertrail__link.silvertrail__save
      .silvertrail__trail > .silvertrail__trail-title + <dl>
    .silvertrail__footer > .silvertrail__actions
      .silvertrail__button          Accept all
      .silvertrail__button          Only essential
      .silvertrail__link            Choose
    .silvertrail__legal > a
  .silvertrail__close
.silvertrail-embed > .silvertrail-embed__inner > .silvertrail-embed__text, .silvertrail-embed__button, .silvertrail-embed__remember
.silvertrail-reopen                  floating button, plus .silvertrail-reopen--left or .silvertrail-reopen--right
```

### Rules

- Accept all and Only essential must look identical. Style both through `.silvertrail__button`, and never tell them apart with `[data-silvertrail-action]`, an ID, `:first-child`, `:nth-child()` or `order`.
- Select only these classes, plus `[data-silvertrail-appearance="light|dark"]` to scope colours to the appearance the site owner picked.
- Load nothing: no `@import`, no `@font-face`, no `url()`. A consent banner must not contact anyone before a choice. Use the theme's presets, `var(--wp--preset--color--<slug>)` and `var(--wp--preset--font-family--<slug>)`.
- Contrast meets WCAG AA: 4.5:1 for text and button labels, 3:1 for borders and focus outlines. Keep every focus outline visible.
- Never hide, fade or clip content, and keep text at 0.875rem or larger. When you set `display` on a class, also set `[hidden] { display: none }` for it.
- Keep `display`, `position` and `z-index` of `.silvertrail`, `.silvertrail--box`, `.silvertrail--bar` and `.silvertrail-reopen`. Move the box or the reopen button with `inset`.
- Below 30rem the defaults set the box to `inset: auto 0.5rem 0.5rem; width: auto` and the gutter to `1.25rem`. Repeat any change to these inside `@media (max-width: 30rem)`.
- The bar's entrance view lays out `.silvertrail__inner` as a grid with the areas `title`, `text`, `choose`, `legal`, `accept` and `reject`, and sets `.silvertrail__footer` and `.silvertrail__actions` to `display: contents`. From 48rem the buttons sit on the right. Its preferences view is the same card as the other layouts. Keep these rules when you restyle the bar.
- A few defaults pair a class with an element, such as `.silvertrail a`, `.silvertrail__switch input` and `.silvertrail__details summary`. Override them with the same selector.
- The marker of `.silvertrail__details summary` is its `::before`, a chevron drawn with borders in `currentColor`.
- Put motion inside `@media (prefers-reduced-motion: no-preference)`. Never use `!important`.
