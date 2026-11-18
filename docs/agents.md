# Agents and Silvertrail

Agents meet Silvertrail in three roles. A developer's agent sets the site up. An admin's agent checks what the site loads before and after consent. A visitor's agent browses the site for a person and meets the banner. This file is the guide for all three, and its last two sections are the briefs that Settings › Silvertrail › Agents copies as prompts.

## How

### Set up a site

A coding agent with a shell sets Silvertrail up in code, reads `README.md` for every hook and key, and runs `wp silvertrail validate` until it reports no problem. Under Settings › Silvertrail › Agents, Set up with an agent copies a prompt with this brief and the services the site has now. Coding agents that read skills can also install the Silvertrail skill:

```sh
npx skills add mawiswiss/silvertrail
```

The skill ships in the plugin folder as well, under `skills/silvertrail/`.

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

Visitors' browsers offer WebMCP only on a site that holds a trial token, until WebMCP ships. Register the site's address for the WebMCP origin trial in Chrome, and in Edge if you like, and add each token in code:

```php
add_filter( 'silvertrail_settings', function ( $settings ) {
	$settings['webmcp_tokens'] = [ 'YOUR-CHROME-TOKEN' ];
	return $settings;
} );
```

Silvertrail prints each token in the page head and stops once it has expired. The Agents tab and `wp silvertrail validate` say when a token runs out or does not fit the site's address. Once WebMCP ships in Chrome, the tools work without a token, and the token can go.

`README.md` lists the five tools under WebMCP.

## Brief: check the site

You check the consent setup of a WordPress site that runs Silvertrail, a cookie consent banner. Silvertrail holds back every service that needs consent until the visitor agrees. Your job is to find what loads anyway. The site owner asked for this test, so you may allow services without asking again. Change nothing on the site.

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

Reply with one table per page: state, host, service or "unknown", allowed or not. Then list each leak with its likely cause, such as a script or embed that is not gated, a service without its hosts, or a host the site should register as a service. Point to the place in `README.md` that fixes it. Finish by resetting the choice.

## Brief: set up the site

You set up Silvertrail, a cookie consent banner, on a WordPress site. `README.md` in the plugin folder is the complete reference. Read it first.

Work in code, not in the admin. Register services in a small plugin or the theme with `silvertrail_register_service()` on the `silvertrail_register` action, and set settings with the `silvertrail_settings` filter. A preset turns on by its ID, and a tracker needs its tag ID. Every other script or embed from another host becomes a service with its hosts or its script handles.

Run `wp silvertrail validate` until it reports no problem, and `wp silvertrail services` to see what is active. Then check the site in a browser as the brief "Check the site" in `docs/agents.md` describes, and fix what it finds. Follow the README's note to agents on the licence.
