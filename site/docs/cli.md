# WP-CLI

The `wp silvertrail` commands show what is active and where each value comes from, so a person or an agent can check their own work. Their output is written to be read by both.

```bash
wp silvertrail services
wp silvertrail settings --format=json
wp silvertrail validate
wp silvertrail ask-again
wp silvertrail write-styles "A dark bar with rounded buttons" > silvertrail.css
wp silvertrail records export --file=records.csv
```

## services

Lists every service with its `id`, `group`, `name`, `consent` (`required` or `not needed`), `hosts` and `handles`. `--format` takes `table`, `csv`, `json` or `yaml`.

## settings

Lists every setting with its `key`, `value` and `source`: `code`, `admin` or `default`. The presets and the texts are left out, because they have their own code paths. `--format` takes `table`, `csv`, `json` or `yaml`, and JSON and YAML keep the values' types.

## validate

Builds the settings, the services and the texts of every installed language, and reports every problem in them: unknown keys, invalid values, a tag ID that does not fit its preset, a service in a missing group, a broken translation file and a WebMCP token that expired or is for another address. It also notes where each language's texts come from and until when each WebMCP token is valid.

When the privacy policy guide has not shown Silvertrail's current text yet, it warns and prints the text. With problems it fails with their number. Without, it reports success with the number of services. Run it after each change until it reports no problem.

## ask-again

Asks every visitor again on their next page view, as if they had never chosen, and prints the new revision. Silvertrail does this by itself whenever the set of services changes.

## write-styles

Sends the [styling brief](https://silvertrail.marcwieland.name/docs/styling.md#write-with-ai) and the look you describe to the AI provider set up under Settings › Connectors, and prints the stylesheet it writes. Nothing is saved. It needs WordPress 7.0 or later.

```bash
wp silvertrail write-styles "A dark bar with rounded buttons" > wp-content/themes/my-theme/silvertrail/silvertrail.css
```

## records export

Writes the [records](https://silvertrail.marcwieland.name/docs/records.md) as CSV to the standard output, or to the file given with `--file`. It fails while records are turned off.
