# Agents and Silvertrail

Silvertrail is built for agents as much as for people, and each agent gets an interface for its job. A developer's agent sets the site up in code and checks its work with WP-CLI, or works through WordPress's abilities over MCP. An admin's agent checks what the site loads before and after consent, with WebMCP tools in the browser. A visitor's agent browses the site for a person and answers through the same tools instead of the banner, where the browser offers them. This file is the guide for all three, and its last two sections are the briefs that Settings › Silvertrail › Agents copies as prompts.

## How

### Set up a site

A coding agent with a shell sets Silvertrail up in code, reads `docs/README.md` and the pages it links for every hook and key, and runs `wp silvertrail validate` until it reports no problem. Under Settings › Silvertrail › Agents, Set up with an agent copies a prompt with this brief, the services the site has now, and its privacy and imprint pages with whether each is published. Coding agents that read skills can also install the Silvertrail skill:

```sh
npx skills add mawiswiss/silvertrail
```

The skill ships in the plugin folder as well, under `skills/silvertrail/`.

### Set up a site through MCP

An agent without access to the site's code works through Silvertrail's abilities instead, from WordPress 6.9. Install the MCP Adapter plugin, create an application password for an administrator and connect the agent's MCP client to `/wp-json/mcp/mcp-adapter-default-server`. The agent then reads the services and settings, turns presets on with their IDs, asks every visitor again and validates the config. It cannot register services that are not presets, because those live in code. [Abilities and MCP](#abilities-and-mcp) below lists the abilities.

### Check a site

An agent that controls a browser can go through every consent state of a page and list what loads before the visitor agrees. Copy the prompt under Check the site with an agent, add the pages to check, and give it to the agent. It reports each host per state and the likely cause of each leak, and it changes nothing on the site. With records on, its choices count as decisions too, marked with the source `agent`.

The check is fastest with WebMCP, where Silvertrail gives the agent a tool for each step instead of a banner to click. WebMCP is an experiment in Chrome and may still change. Chrome DevTools MCP starts a Chrome with WebMCP on and lets any MCP client list and call the tools. For Claude Code:

```sh
claude mcp add chrome-devtools -- npx -y chrome-devtools-mcp@latest --categoryExperimentalWebmcp --categoryExperimentalThirdParty --chromeArg=--enable-features=WebMCP
```

`--categoryExperimentalThirdParty` adds the developer tools, where Chrome DevTools MCP lists `reset_cookie_consent`.

To try the tools by hand, turn on `chrome://flags/#enable-webmcp-testing` in your own Chrome. DevTools › Application › WebMCP then lists them and runs each one. An agent without WebMCP clicks the banner and calls `window.silvertrail` in the page, which offers the same steps.

The Leak check under Tools does a similar job without an agent. It runs in your own browser over the pages you list, and it writes a prompt that asks an assistant about each unknown service it found.

### Visiting agents

A browser that sends Global Privacy Control gets "only essential" without a banner. An agent that speaks WebMCP reads the services and answers through tools. It may allow only essential on its own, because nothing extra loads, and it allows more only with its person's answer. Every other agent sees the banner like a person does, and with the default modal it sees nothing else until it answers.

Visitors' browsers offer WebMCP only on a site that holds a trial token, until WebMCP ships. [WebMCP](#webmcp) below lists the five tools and how to add a token.

## WebMCP

WebMCP lets a page offer tools to an agent in the browser. Silvertrail offers five on every page where a service needs consent, unless `webmcp` is off:

| Tool | Does |
| --- | --- |
| `get_cookie_consent` | Reads the services that need consent, with provider, purpose and whether each is allowed, the essential services, whether a choice was made and whether Global Privacy Control applies. |
| `allow_only_essential` | Stores "only essential" and closes the banner. An agent may call it without asking its person, because nothing extra loads. |
| `set_cookie_consent` | Stores exactly the services in `services` and closes the banner. It is marked as consequential, so an agent asks its person first. An unknown id stores nothing and returns `{ error, services }` with the valid ids. |
| `get_page_trail` | Reads the services the page held back and the other hosts it loaded, each with its service and whether that one is allowed. |
| `reset_cookie_consent` | Forgets the choice in this browser, so the next page load is a first visit. Marked as a debugging tool, so agents that act for a visitor leave it out. |

Choices made through the tools carry `agent` as their source, in `silvertrail:change` and in the records. The tools load as a separate module, `assets/webmcp.js`, and only in browsers that offer WebMCP.

WebMCP is not shipped yet. Chrome runs it as an origin trial and behind `chrome://flags/#enable-webmcp-testing`, Edge runs its own trial, and the API may still change. In a browser without it, Silvertrail offers nothing and the banner works as before. With the flag on, your own Chrome gets the tools on any site, which is all it takes to check one. Visitors' browsers get them only on a site with a trial token for its address. Register the site for the WebMCP origin trial in Chrome, and in Edge if you like, and add the tokens in code:

```php
add_filter( 'silvertrail_settings', function ( $settings ) {
	$settings['webmcp_tokens'] = [ 'YOUR-CHROME-TOKEN' ];
	return $settings;
} );
```

Silvertrail prints each token as a meta tag in the page head and leaves it out once it has expired. `wp silvertrail validate` and the Agents tab report a token that expired or is for another address, and the admin warns two weeks before a token runs out. Once WebMCP ships, the tools work without a token, and the token can go.

## Abilities and MCP

From WordPress 6.9, Silvertrail registers abilities in the `silvertrail` category. An administrator's agent reaches them through the [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) and the REST API. Each one needs the `manage_options` capability, so an agent can do no more than the administrator it signs in as.

| Ability | Input | Returns |
| --- | --- | --- |
| `silvertrail/list-services` | none | `services`: each with `id`, `name`, `group`, `consent`, `hosts`, `handles`, `provider`, `purpose`, `privacy_url` and `from` (`preset` or `code`). `presets`: each with `id`, `name`, `kind` (`tracker` or `embed`), `enabled` (the admin turned it on), `live` (the site runs it), `code` (`''`, `on`, `removed` or `changed`), and for trackers `tag_id`, `tag_id_label` and `tag_id_example`. |
| `silvertrail/get-settings` | none | `settings`: each with `key`, `value` and `source` (`code`, `admin` or `default`). |
| `silvertrail/validate` | none | `valid`, `services` (the count), `problems`, `notes`, and `privacy_policy` while the Policy Guide has not shown Silvertrail's text. Once valid, `support` says how to support Silvertrail. |
| `silvertrail/get-styling-brief` | none | `brief`, the styling prompt with the theme's colours, fonts and current stylesheet, and `file`, the path in the theme to save the answer as. |
| `silvertrail/update-presets` | `presets`: a list of `{ id, enabled, tag_id }`, where only `id` is required | The `presets` as `list-services` returns them, and the `revision`. |
| `silvertrail/ask-again` | none | The new `revision`. |

The first four are read-only. `update-presets` turns presets on or off and sets their IDs, as the Services tab does. It refuses a preset that code turns on, an ID on an embed, an ID that does not fit and a tracker turned on without its ID, and then changes nothing. Because a new set of services asks every visitor again, an agent confirms the change with its person first. `ask-again` is marked as destructive, so an agent asks its person first.

To connect an MCP client, install the MCP Adapter, create an application password for an administrator and point the client at `/wp-json/mcp/mcp-adapter-default-server`. The adapter's discover-abilities tool lists Silvertrail's abilities next to core's. On the shell, `wp mcp-adapter serve --server=mcp-adapter-default-server --user=admin` serves the same over STDIO.

`silvertrail/write-styles`, from WordPress 7.0, serves Write with AI and stays out of MCP. To keep the others out of MCP as well, set `public` to `false`. They stay in the REST API for administrators:

```php
add_filter( 'wp_register_ability_args', function ( $args, $name ) {
	if ( str_starts_with( $name, 'silvertrail/' ) ) {
		$args['meta']['public'] = false;
	}
	return $args;
}, 10, 2 );
```

## Brief: check the site

You check the consent setup of a WordPress site that runs Silvertrail, a consent plugin. Silvertrail holds back every service that needs consent until the visitor agrees. Your job is to find what loads anyway. The site owner asked for this test, so you may allow services without asking again. Change nothing on the site.

Use a browser you control, with no ad blocker, because ad blockers hide requests that real visitors make. If the page offers WebMCP tools, use them:

- `get_cookie_consent`: the services that need consent, the essential ones and the current choice.
- `allow_only_essential` and `set_cookie_consent`: store a choice and close the banner.
- `get_page_trail`: the services the page holds back and the hosts it loaded so far.
- `reset_cookie_consent`: forget the choice, so the next load is a first visit. It is marked as a debugging tool, so your client may list it among developer tools.

Without WebMCP, click the banner's buttons and call the same things in the page through `window.silvertrail`: `decided()`, `has(id)`, `set(ids)`, `trail()` and `reset()`. Whenever you can list the page's network requests, use them as well. They see more than `get_page_trail`, which knows only what the page recorded.

Go through these states on each page:

1. First visit. Reset the choice, reload and touch nothing. Nothing that needs consent may load.
2. Only essential. Allow only essential and reload. Still nothing that needs consent may load.
3. Each service on its own. Allow just that service and reload. Its hosts may load, the hosts of every other service may not.
4. Everything. Allow every service and reload. Note every host that belongs to no service.

After each state, compare the hosts the page loaded with the services below. A host of a service that is not allowed is a leak. A host that belongs to no service is unknown: name it and say what it probably is.

Reply with one table per page: state, host, service or "unknown", allowed or not. Then list each leak with its likely cause, such as a script or embed that is not gated, a service without its hosts, or a host the site should register as a service. Point to the place in the documentation that fixes it, from `docs/` in the plugin folder or https://silvertrail.marcwieland.name/docs/. Finish by resetting the choice.

## Brief: set up the site

You set up Silvertrail on a WordPress site. Silvertrail holds back each service until the visitor agrees, and passes the choice on to Google, to other plugins and to the site's own code. The documentation in the plugin folder is the complete reference: start at `docs/README.md` and read the pages it links for your task. The same pages are at https://silvertrail.marcwieland.name/docs/, and https://silvertrail.marcwieland.name/llms.txt lists them as Markdown.

Work in code, not in the admin. Register services in a small plugin or the theme with `silvertrail_register_service()` on the `silvertrail_register` action, and set settings with the `silvertrail_settings` filter. A preset turns on by its ID, and a tracker needs its tag ID. Every other script or embed from another host becomes a service with its hosts or its script handles.

Run `wp silvertrail validate` until it reports no problem, and `wp silvertrail services` to see what is active. Then check the site in a browser as the brief "Check the site" in `docs/agents.md` describes, and fix what it finds.

Then look after the privacy page and the imprint, as `docs/privacy-pages.md` describes. Find out who runs the site, whether it sells and whom it addresses, and ask the owner what the site cannot tell you. Write the pages the site needs as drafts for the owner to review, and never publish legal text yourself. That page is a rough orientation, not legal advice: verify each point against the site's actual case and the current law, and tell the owner which points a lawyer should check.

Follow the README's note to agents on the licence.
