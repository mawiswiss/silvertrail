## Direction

- Silvertrail is a small cookie banner for developers who found the big tools too much. It is an alternative to them, not a competitor, and it stays small on purpose, because every feature is also a maintenance duty on a side project.
- Silvertrail is the backbone for everything a site loads from outside services. It knows each service, holds it back until consent and passes the choice on. The banner is only the part that asks, and it steps aside wherever the browser answers, as with Global Privacy Control. As browsers take over more of the asking, the banner may go away entirely, so new work strengthens the backbone and keeps the banner replaceable.
- The plugin is complete and free, for good. No pro version, no hosted service, no feature behind a key, no licence check, and the plugin contacts no outside service on its own. The one exception is Write with AI: an admin's click sends the styling prompt to the AI provider the site set up in WordPress. What a new thing does, it does for everyone.
- Money comes from the honour licence, sold through Polar: free on your own site, a yearly site licence when someone is paid to put it on a client's site, and an agency licence for any number of sites. The prices stand in the README.
- TODO: integrate Polar once the LLC is ready. Until then, every licence link goes to https://marcwieland.name/support/. Then switch `SILVERTRAIL_LICENSE_URL`, the README, readme.txt's Donate link and text, composer.json, `.github/FUNDING.yml` and `site/index.html` to https://polar.sh/silvertrail.
- The licence ask is one sentence, from `Plugin::license()`, with the link from `SILVERTRAIL_LICENSE_URL`. It appears where the value is felt and nowhere else: the README and readme.txt, activation and validate on the shell and as an ability, a clean Leak check, the admin's status panel until dismissed and then the footer of the settings page, the plugin row, composer.json and the funding file. Never a nag, never a modal, never a second sentence. A licensee gets the invoice and the upkeep, nothing else.
- Custom development is paid feature work on top of the free plugin, by email, never technical support. Its code is not proprietary, and whatever most users would benefit from goes into the plugin.
- Agents set this plugin up as often as people do. `docs/` is the complete reference and ships in the plugin, the README stays the short introduction, the shell output is written for them, and a new behaviour is reachable from code and the shell before it gets an admin field. Code wins over the admin, and the admin holds only decisions.

## Conventions

- Prose is short, complete sentences with no em-dashes and no marketing. The README, readme.txt and everything in `docs/` are British English. The admin's source strings are US English, as WordPress core's are, and en_GB gets its spelling through the translation.
- A change in behaviour updates `docs/` in the same commit. `docs/README.md` lists every page under `## Pages`, and `tests/phpunit/DocsTest.php` fails when the docs miss a setting, hook, command, ability or tool. The briefs in `docs/agents.md` and `docs/styling.md` stay the last `##` sections, because the admin copies them from their heading to the end.
- `docs/` is the only source of the documentation site. `npm run docs` builds `site/docs/` and `site/llms.txt` from it, and git ignores both.
- No text claims or implies that Silvertrail makes a site legally compliant, because WordPress.org guideline 9 forbids it. Describe what the plugin does instead, such as loading nothing before consent.
- Every UI string goes through the `silvertrail` text domain. After changing one, regenerate `languages/silvertrail.pot` with `wp i18n make-pot . languages/silvertrail.pot --exclude=build` in its own `chore(i18n)` commit.
- Commits are Conventional Commits, signed, without a sign-off trailer.

## Releasing

- A GitHub release publishes a version. Its tag must match the `Version` header, `SILVERTRAIL_VERSION` and readme.txt's `Stable tag`. The release workflow builds the package from `.distignore`, runs Plugin Check on it and attaches `silvertrail.zip` to the release.
- With the repository variable `WPORG_DEPLOY` set to `true` and the secrets `SVN_USERNAME` and `SVN_PASSWORD`, it also commits the package to WordPress.org. The icon, banners and screenshots for the plugin page go into `.wordpress-org/`.
- A published release also runs the Deploy site workflow. It publishes `site/` to silvertrail.marcwieland.name with `wrangler deploy`, which runs `npm run docs` first, so the site shows the released docs. It needs the secrets `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID`. Run it by hand to deploy a docs fix from a branch or tag between releases.
- The account ID never goes into the repository. A local `npx wrangler deploy` takes it from `CLOUDFLARE_ACCOUNT_ID`, in the shell or in a `.env` file, which git ignores.

## Quality Assurance

Run `composer lint`, `composer analyse`, `npm run test:php` and `npx playwright test` before every commit. The last two need `npx wp-env start`.
