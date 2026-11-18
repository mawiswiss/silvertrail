# Records

Records keep a trace of each decision without knowing who made it. They are off by default, and a site can keep them in its own system instead.

## What is stored

With records turned on under Tools, or `records` set to `true` in the [settings filter](https://silvertrail.marcwieland.name/docs/settings.md), every decision is posted to `/wp-json/silvertrail/v1/record` and stored with:

| Column | What it holds |
| --- | --- |
| `consent_id` | A random ID of 32 hex characters, kept in the visitor's cookie, so later decisions of the same browser share it. |
| `revision` | The revision of the services the visitor decided on. |
| `choices` | The services the visitor agreed to, as a JSON list, without the essential ones. |
| `source` | `banner`, `embed`, `gpc`, `hook` or `agent`. |
| `created_at` | The time, in UTC. |

No IP address and no browser details are stored. Records live in the `{prefix}silvertrail_records` table and are deleted after `retention_days`, 365 by default, by a daily scheduled job. Choices made in a preview or by the Leak check are not recorded. The data comes from the browser and is not verified, because cached pages carry no nonce and anyone may post to the endpoint.

The Tools tab and `wp silvertrail records export` download the records as CSV, with the columns above.

When records are on, the privacy policy guide under Settings › Privacy suggests a paragraph on them.

## Keep records elsewhere

The `silvertrail_recorded` action fires for every decision, whether records are on or not. Hooking it is enough to make the endpoint exist and the browser post to it. It receives one array:

```php
[
	'consent_id' => '9f86d081884c7d659a2feaa0c55ad015', // '' while records are off.
	'revision'   => 3,
	'choices'    => [ 'google-analytics', 'youtube' ],
	'source'     => 'banner',
	'created_at' => '2026-11-18 14:05:09',
]
```

While records are off, the browser keeps no consent ID, so `consent_id` is `''`. The action runs inside the REST request, so keep it fast or hand the work to a scheduled event. [Recipes](https://silvertrail.marcwieland.name/docs/recipes.md#send-each-decision-to-another-system) shows a complete example.
