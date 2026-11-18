<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail\Admin;

use WP_Screen;

defined( 'ABSPATH' ) || exit;

final class Help {

	private const SERVICE = <<<'PHP'
add_action( 'silvertrail_register', function () {
	silvertrail_register_service( 'hubspot', [
		'group'       => 'marketing',
		'name'        => 'HubSpot',
		'provider'    => 'HubSpot, Inc.',
		'purpose'     => 'Tracks form conversions.',
		'privacy_url' => 'https://legal.hubspot.com/privacy-policy',
		'hosts'       => [ 'js.hs-scripts.com' ],
		'handles'     => [ 'hubspot-tracking' ],
	] );
} );
PHP;

	private const ADJUST = <<<'PHP'
add_action( 'silvertrail_register', function () {
	silvertrail_register_service( 'google-tag-manager', [ 'tag_id' => 'GTM-XXXXXXX' ] );
	silvertrail_register_service( 'youtube', [ 'cookies' => 'VISITOR_INFO1_LIVE, YSC' ] );
} );

add_filter( 'silvertrail_config', function ( $config ) {
	unset( $config['services']['google-maps'] );
	return $config;
} );
PHP;

	// Stored as entities: esc_html() in section() does not encode them twice.
	private const SCRIPT = <<<'HTML'
&lt;script type="text/plain" data-silvertrail="hubspot" src="https://js.hs-scripts.com/123.js"&gt;&lt;/script&gt;
HTML;

	private const SETTINGS = <<<'PHP'
add_filter( 'silvertrail_settings', function ( $settings ) {
	$settings['expiry_months'] = 12;
	$settings['records']       = true;
	return $settings;
} );
PHP;

	private const TEXTS = <<<'JSON'
{
	"title": "Datenschutz",
	"accept_all": "Alle akzeptieren",
	"embed_remember": "%s immer zulassen"
}
JSON;

	private const DESIGN = <<<'PHP'
add_filter( 'silvertrail_design', function ( $design, $appearance ) {
	$design['accent'] = 'dark' === $appearance ? '#ff8a80' : '#c62828';
	$design['radius'] = '0.25rem';
	return $design;
}, 10, 2 );
PHP;

	private const LINKS = <<<'HTML'
<a href="#silvertrail">Privacy settings</a>

[silvertrail_link]Cookie settings[/silvertrail_link]
HTML;

	private const FLOATING = <<<'PHP'
add_filter( 'silvertrail_settings', function ( $settings ) {
	$settings['floating_button'] = 'always';
	return $settings;
} );

add_filter( 'silvertrail_reopen_icon', function () {
	return '<img src="' . esc_url( get_theme_file_uri( 'images/privacy.svg' ) ) . '" alt="">';
} );
PHP;

	private const BROWSER = <<<'JS'
window.silvertrail.has('hubspot');
window.silvertrail.open();

window.addEventListener('silvertrail:change', (event) => {
	console.log(event.detail.consents);
});

window.addEventListener('silvertrail:before-prompt', (event) => {
	event.detail.respondWith(fetch('/my-region-check').then((r) => r.text()));
});
JS;

	private const AGENTS = <<<'PHP'
add_filter( 'silvertrail_settings', function ( $settings ) {
	$settings['webmcp_tokens'] = [ 'YOUR-CHROME-TOKEN' ];
	return $settings;
} );
PHP;

	private const CLI = <<<'SH'
wp silvertrail services      # every service and what it blocks
wp silvertrail settings      # every setting and where it comes from
wp silvertrail validate      # mistakes in the settings, the config and the texts
wp silvertrail ask-again     # asks every visitor again
wp silvertrail records export --file=records.csv
SH;

	public static function add( WP_Screen $screen ): void {
		foreach ( self::tabs() as $id => $tab ) {
			$screen->add_help_tab(
				array(
					'id'      => $id,
					'title'   => $tab['title'],
					'content' => self::section( $tab['intro'], $tab['code'], $tab['notes'] ),
				)
			);
		}
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'Full reference', 'silvertrail' ) . '</strong></p>'
			. '<p>' . self::code( __( '`docs/` in the plugin folder covers every hook, key and attribute. Point an AI assistant at `docs/README.md`.', 'silvertrail' ) ) . '</p>'
			. '<p>' . self::code( __( 'Coding agents can install the skill: `npx skills add mawiswiss/silvertrail`', 'silvertrail' ) ) . '</p>'
			. '<p><a href="https://silvertrail.marcwieland.name/docs/">' . esc_html__( 'Documentation', 'silvertrail' ) . '</a></p>'
		);
	}

	/**
	 * @return array<string, array{title: string, intro: string, code: string, notes: list<string>}>
	 */
	public static function tabs(): array {
		return array(
			'silvertrail-service'  => array(
				'title' => __( 'Add a service', 'silvertrail' ),
				'intro' => __( 'Register a service on the `silvertrail_register` action, in a plugin or the theme.', 'silvertrail' ),
				'code'  => self::SERVICE,
				'notes' => array(
					__( '`group`: `essential`, `preferences`, `statistics`, `marketing` or `external-media`. `silvertrail_register_group()` adds your own.', 'silvertrail' ),
					__( '`hosts`: embeds from these hosts wait behind a placeholder.', 'silvertrail' ),
					__( '`handles`: scripts enqueued under these handles wait for consent.', 'silvertrail' ),
					__( 'Keep the config the same on every request. Every visitor is asked again when the set of services changes.', 'silvertrail' ),
				),
			),
			'silvertrail-adjust'   => array(
				'title' => __( 'Change a service', 'silvertrail' ),
				'intro' => __( 'Register a service again by its ID, with only the fields to change.', 'silvertrail' ),
				'code'  => self::ADJUST,
				'notes' => array(
					__( 'A preset’s ID turns the preset on with its defaults. A tracker needs only its `tag_id`.', 'silvertrail' ),
					__( 'The `silvertrail_config` filter runs last. It edits or removes any service, even one turned on here.', 'silvertrail' ),
					__( 'The Services tab marks each preset that code turned on, changed or removed.', 'silvertrail' ),
				),
			),
			'silvertrail-script'   => array(
				'title' => __( 'Gate a script', 'silvertrail' ),
				'intro' => __( 'Give the script the type `text/plain` and the service ID. The browser runs it once the visitor agrees.', 'silvertrail' ),
				'code'  => self::SCRIPT,
				'notes' => array(
					__( 'Inline and external scripts keep their order.', 'silvertrail' ),
					__( 'A script that WordPress enqueues needs no change: list its handle under the service’s `handles`.', 'silvertrail' ),
				),
			),
			'silvertrail-settings' => array(
				'title' => __( 'Settings in code', 'silvertrail' ),
				'intro' => __( 'The `silvertrail_settings` filter sets any setting. A field set in code shows “Set in code” on this page and cannot be changed here.', 'silvertrail' ),
				'code'  => self::SETTINGS,
				'notes' => array(
					__( 'On this page: `enabled`, `layout`, `bar_position`, `appearance`, `require_choice`, `imprint_page`, `floating_button`, `floating_position`, `records`.', 'silvertrail' ),
					__( 'Only in code: `load_css`, `expiry_months`, `retention_days`, `gpc`, `trail`, `webmcp`, `webmcp_tokens`.', 'silvertrail' ),
					__( '`wp silvertrail settings` lists each value and where it comes from.', 'silvertrail' ),
				),
			),
			'silvertrail-texts'    => array(
				'title' => __( 'Texts', 'silvertrail' ),
				'intro' => __( 'Ship the texts with the theme as `silvertrail/texts-{locale}.json`, or `silvertrail/texts.json` for every language. The Texts tab shows the JSON with every key.', 'silvertrail' ),
				'code'  => self::TEXTS,
				'notes' => array(
					__( 'A child theme wins over its parent.', 'silvertrail' ),
					__( 'From a plugin, return another path or an array in the `silvertrail_translation` filter.', 'silvertrail' ),
					__( 'Edits on the Texts tab win over the file. The `silvertrail_texts` filter runs last.', 'silvertrail' ),
					__( '`wp silvertrail validate` reports unknown keys and broken files.', 'silvertrail' ),
				),
			),
			'silvertrail-design'   => array(
				'title' => __( 'Design', 'silvertrail' ),
				'intro' => __( 'The banner takes its colors, corners and buttons from the theme’s `theme.json`. The `silvertrail_design` filter changes them.', 'silvertrail' ),
				'code'  => self::DESIGN,
				'notes' => array(
					__( 'Keys: `background`, `text`, `muted`, `line`, `accent`, `on-accent`, `radius`, `button-padding`, `button-weight`. Each becomes a `--silvertrail-<key>` custom property.', 'silvertrail' ),
					__( 'For anything else, add `silvertrail/silvertrail.css` to the theme. It loads after the default styles.', 'silvertrail' ),
					__( '`docs/styling.md` in the plugin folder is a brief for an AI assistant that writes this stylesheet.', 'silvertrail' ),
					__( 'To change the markup, copy a file from `templates/` into the theme’s `silvertrail/` folder and keep the `data-silvertrail-*` attributes.', 'silvertrail' ),
				),
			),
			'silvertrail-links'    => array(
				'title' => __( 'Links to the preferences', 'silvertrail' ),
				'intro' => __( 'Any link to `#silvertrail` opens the preferences, and so does any element with `data-silvertrail-open`.', 'silvertrail' ),
				'code'  => self::LINKS,
				'notes' => array(
					__( 'The `[silvertrail_link]` shortcode and the Privacy settings link block print such a link.', 'silvertrail' ),
					__( 'Every page needs such a link, unless the floating button is on.', 'silvertrail' ),
				),
			),
			'silvertrail-floating' => array(
				'title' => __( 'Floating button', 'silvertrail' ),
				'intro' => __( 'Both corners on the Design tab hide the button on pages with another link to `#silvertrail`. The `silvertrail_settings` filter shows it on every page.', 'silvertrail' ),
				'code'  => self::FLOATING,
				'notes' => array(
					__( '`floating_button`: `never`, the default, `auto` for pages without a link to `#silvertrail`, or `always`.', 'silvertrail' ),
					__( '`floating_position`: `left` or `right`.', 'silvertrail' ),
					__( 'The `silvertrail_reopen_icon` filter swaps the icon for inline SVG or an `<img>`. The button keeps its label for screen readers.', 'silvertrail' ),
					__( 'Style it in the theme’s `silvertrail/silvertrail.css` through `.silvertrail-reopen`, `.silvertrail-reopen--left` and `.silvertrail-reopen--right`.', 'silvertrail' ),
				),
			),
			'silvertrail-browser'  => array(
				'title' => __( 'JavaScript', 'silvertrail' ),
				'intro' => __( '`window.silvertrail` reads and changes the choice in the browser.', 'silvertrail' ),
				'code'  => self::BROWSER,
				'notes' => array(
					__( '`has( id )`: whether the visitor agreed to the service. Essential services are always `true`.', 'silvertrail' ),
					__( '`open()`: opens the preferences.', 'silvertrail' ),
					__( '`reset()`: forgets the choice in this browser, so the next page view is a first visit.', 'silvertrail' ),
					__( '`silvertrail:change`: fires on `window` after every choice.', 'silvertrail' ),
					__( '`silvertrail:before-prompt`: answer `prompt`, `accept-all` or `reject-all` within three seconds, for example by region.', 'silvertrail' ),
				),
			),
			'silvertrail-agents'   => array(
				'title' => __( 'Agents', 'silvertrail' ),
				'intro' => __( 'In a browser with WebMCP, every page offers agents five tools. A trial token in `webmcp_tokens` lets visitors’ browsers offer them too, until WebMCP ships.', 'silvertrail' ),
				'code'  => self::AGENTS,
				'notes' => array(
					__( '`get_cookie_consent` and `get_page_trail` read the services, the choice and what the page loaded.', 'silvertrail' ),
					__( '`allow_only_essential`, `set_cookie_consent` and `reset_cookie_consent` change the choice. `window.silvertrail` does the same without WebMCP.', 'silvertrail' ),
					__( 'WebMCP is an origin trial in Chrome. Without a token, only a Chrome with `chrome://flags/#enable-webmcp-testing` offers the tools.', 'silvertrail' ),
					__( '`webmcp` set to `false` turns the tools off. `wp silvertrail validate` reports a token that expired or does not fit this site.', 'silvertrail' ),
					__( 'The Agents tab copies prompts to check and to set up the site with an agent. `docs/agents.md` in the plugin folder is the guide.', 'silvertrail' ),
				),
			),
			'silvertrail-cli'      => array(
				'title' => __( 'WP-CLI', 'silvertrail' ),
				'intro' => __( 'Check and run Silvertrail from the command line. `services` and `settings` take `--format=json` for scripts.', 'silvertrail' ),
				'code'  => self::CLI,
				'notes' => array(),
			),
		);
	}

	/**
	 * @param list<string> $notes
	 */
	private static function section( string $intro, string $code, array $notes ): string {
		$html = '<p>' . self::code( $intro ) . '</p><pre><code>' . esc_html( $code ) . '</code></pre>';
		if ( array() !== $notes ) {
			$html .= '<ul>' . implode( '', array_map( static fn( string $note ): string => '<li>' . self::code( $note ) . '</li>', $notes ) ) . '</ul>';
		}
		return $html;
	}

	private static function code( string $text ): string {
		return (string) preg_replace( '/`([^`]+)`/', '<code>$1</code>', esc_html( $text ) );
	}
}
