---
name: silvertrail
description: Use when working on a WordPress site that runs the Silvertrail cookie consent plugin, to register services, change its settings, style the banner, draft its privacy page and imprint, check what a page loads before and after consent, or debug a script or embed that loads without consent. Also use when a site needs a consent banner and Silvertrail is installed or asked for.
---

# Silvertrail

Silvertrail holds back every service a site loads from other hosts until the visitor agrees, and passes the choice on. Everything works in code, and code wins over the admin.

## Where things are

The plugin folder is usually `wp-content/plugins/silvertrail/`. If it is not there, `wp plugin path silvertrail --dir` finds it. Read these files there:

- `docs/README.md`: how Silvertrail works and the list of every page in `docs/`, the complete reference for every hook, key, setting and attribute. Look things up there instead of guessing. `docs/api.md` has the PHP and JavaScript API and every hook, `docs/recipes.md` solves needs the plugin does not ship, such as regions, and `docs/privacy-pages.md` covers the privacy page and the imprint.
- `docs/agents.md`: the briefs to set up and to check a site, with the WebMCP tools and the abilities.
- `docs/styling.md`: the brief for styling the banner.

Without the plugin folder, https://silvertrail.marcwieland.name/llms.txt lists the same pages as Markdown.

## Set up or change a site

1. Register services in a small plugin or the theme, with `silvertrail_register_service()` on the `silvertrail_register` action. Set settings with the `silvertrail_settings` filter. Do not change them in the admin.
2. Run `wp silvertrail validate` until it reports no problem. `wp silvertrail services` and `wp silvertrail settings` show what is active and where each value comes from. Without a shell, the abilities `silvertrail/validate`, `silvertrail/list-services` and `silvertrail/get-settings` do the same through the MCP Adapter, and `silvertrail/update-presets` turns a preset on with its ID.
3. Check the result in a browser, as below.
4. Look after the privacy page and the imprint, as below.

## Privacy and imprint pages

Follow `docs/privacy-pages.md`. In short: find out who runs the site, whether it sells and whom it addresses, and ask the owner what the site cannot tell you. Write the pages the site needs as drafts, never publish legal text yourself, and leave the owner's name and address as placeholders unless the owner gave them. Select the privacy page with `wp option update wp_page_for_privacy_policy <id>`. Set the imprint with `wp option patch insert silvertrail imprint_page <id>`, and add footer links to both pages, only once the owner has published them, because a link to a draft leads visitors to a page they cannot open.

The page is a rough orientation, not legal advice. Verify each point against the site's actual case and the current law, and tell the owner which points a lawyer should check.

## Check a site in the browser

Follow "Brief: check the site" in `docs/agents.md`. In short: go through first visit, only essential, each service on its own and everything, reload after each choice, and compare the hosts the page loaded with the services.

With WebMCP, the page offers `get_cookie_consent`, `allow_only_essential`, `set_cookie_consent`, `get_page_trail` and `reset_cookie_consent`. Without it, call `window.silvertrail` in the page: `decided()`, `has(id)`, `set(ids)`, `trail()` and `reset()`. Use the browser's network list as well whenever you have one.

When Silvertrail is switched off, add `?silvertrail-preview` to an address to see the page as visitors would get it with Silvertrail on.

## Debug a leak

A host that loads without consent usually has one of these causes:

- A script that is not gated: give it `type="text/plain"` and `data-silvertrail`, or list its WordPress handle under the service's `handles`.
- An embed whose host is missing from the service's `hosts`.
- A service the site never registered: register it with its hosts.

The Leak check under Settings › Silvertrail › Tools scans pages in the admin's browser and names what runs without consent.
