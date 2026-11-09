## Direction

- Silvertrail is a small cookie banner for developers who found the big tools too much. It is an alternative to them, not a competitor, and it stays small on purpose, because every feature is also a maintenance duty on a side project.
- The plugin is complete and free, for good. No pro version, no hosted service, no feature behind a key, no licence check, and the plugin itself contacts no outside service. What a new thing does, it does for everyone.
- Money comes from the honour licence, sold through Polar: free on your own site, a yearly site licence when someone is paid to put it on a client's site, and an agency licence for any number of sites. The prices stand in the README.
- TODO: integrate Polar once the LLC is ready. Until then, every licence link goes to https://marcwieland.name/support/. Then switch `SILVERTRAIL_LICENSE_URL`, the README, readme.txt's Donate link and text, composer.json, `.github/FUNDING.yml` and `site/index.html` to https://polar.sh/silvertrail.
- The licence ask is one sentence, from `Plugin::license()`, with the link from `SILVERTRAIL_LICENSE_URL`. It appears where the value is felt and nowhere else: the README and readme.txt, activation and validate on the shell, a clean Leak check, the admin's status panel until dismissed and then the footer of the settings page, the plugin row, composer.json and the funding file. Never a nag, never a modal, never a second sentence. A licensee gets the invoice and the upkeep, nothing else.
- Custom development is paid work on top of the free plugin, by email. Whatever fits every site goes into the plugin.
- Agents set this plugin up as often as people do. README.md is the complete reference, the shell output is written for them, and a new behaviour is reachable from code and the shell before it gets an admin field. Code wins over the admin, and the admin holds only decisions.

## Conventions

- Prose is short, complete sentences with no em-dashes and no marketing. The README, readme.txt and docs/styling.md are British English. The admin's source strings are US English, as WordPress core's are, and en_GB gets its spelling through the translation.
- Every UI string goes through the `silvertrail` text domain. After changing one, regenerate `languages/silvertrail.pot` with `wp i18n make-pot . languages/silvertrail.pot` in its own `chore(i18n)` commit.
- Commits are Conventional Commits, signed, without a sign-off trailer.

## Quality Assurance

Run `composer lint`, `composer analyse`, `npm run test:php` and `npx playwright test` before every commit. The last two need `npx wp-env start`.
