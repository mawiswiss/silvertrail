# Style the banner

The banner takes its colours, corners and buttons from the theme's `theme.json`. For anything beyond that, an AI assistant writes one CSS file for the theme, and the second half of this file is its brief.

## How

1. Under Settings › Silvertrail › Banner › Custom styles, copy the prompt. Paste it into an AI assistant and describe the look you want, or add a screenshot of the site. A coding agent working in the theme reads this file instead.
2. The assistant answers with one CSS block. Save it in the theme, or the child theme, as `silvertrail/silvertrail.css`. Silvertrail loads it after its own styles, and the Banner tab confirms that it found the file.
3. Check the centred banner and the box, the preferences view behind Choose, every appearance, an embed placeholder, the reopen button, a 320px wide screen and the keyboard. The Leak check tab should list no new connection.

The theme's `theme.json` sets the defaults, the `silvertrail_design` filter overrides them in PHP, and the theme's `silvertrail/silvertrail.css` wins over both. With the default styles turned off, that file is the whole stylesheet, and `assets/silvertrail.css` in the plugin folder is the place to start from.

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
.silvertrail                        <dialog>, plus .silvertrail--modal (centred) or .silvertrail--box (corner)
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
.silvertrail-reopen
```

### Rules

- Accept all and Only essential must look identical. Style both through `.silvertrail__button`, and never tell them apart with `[data-silvertrail-action]`, an ID, `:first-child`, `:nth-child()` or `order`.
- Select only these classes, plus `[data-silvertrail-appearance="theme|light|dark"]` to scope colours to the appearance the site owner picked.
- Load nothing: no `@import`, no `@font-face`, no `url()`. A consent banner must not contact anyone before a choice. Use the theme's presets, `var(--wp--preset--color--<slug>)` and `var(--wp--preset--font-family--<slug>)`.
- Contrast meets WCAG AA: 4.5:1 for text and button labels, 3:1 for borders and focus outlines. Keep every focus outline visible.
- Never hide, fade or clip content, and keep text at 0.875rem or larger. When you set `display` on a class, also set `[hidden] { display: none }` for it.
- Keep `display`, `position` and `z-index` of `.silvertrail`, `.silvertrail--box` and `.silvertrail-reopen`. Move the box or the reopen button with `inset`.
- Below 30rem the defaults set the box to `inset: auto 0.5rem 0.5rem; width: auto` and the gutter to `1.25rem`. Repeat any change to these inside `@media (max-width: 30rem)`.
- A few defaults pair a class with an element, such as `.silvertrail a`, `.silvertrail__switch input` and `.silvertrail__details summary`. Override them with the same selector.
- Put motion inside `@media (prefers-reduced-motion: no-preference)`. Never use `!important`.
