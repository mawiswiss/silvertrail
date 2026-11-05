<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail\Admin;

use WP_Screen;

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

	// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- example markup, printed escaped.
	private const SCRIPT = <<<'HTML'
<script type="text/plain" data-silvertrail="hubspot" src="https://js.hs-scripts.com/123.js"></script>
HTML;
	// phpcs:enable

	private const BROWSER = <<<'JS'
window.silvertrail.has('hubspot');
window.silvertrail.open();

window.addEventListener('silvertrail:change', (event) => {
	console.log(event.detail.consents);
});
JS;

	private const TEXTS = <<<'PHP'
// From a plugin instead of the theme:
add_filter( 'silvertrail_translation', function ( $translation, $locale ) {
	return __DIR__ . "/texts/{$locale}.json";
}, 10, 2 );
PHP;

	private const CLI = <<<'SH'
wp silvertrail services
wp silvertrail validate
wp silvertrail ask-again
wp silvertrail records export --file=records.csv
SH;

	public static function add( WP_Screen $screen ): void {
		$screen->add_help_tab(
			array(
				'id'      => 'silvertrail-service',
				'title'   => __( 'Add a service', 'silvertrail' ),
				'content' => self::section(
					__( 'Register services in a plugin or the theme. The groups are essential, preferences, statistics, marketing and external-media. The silvertrail_config filter edits or removes anything at the end.', 'silvertrail' ),
					self::SERVICE
				),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'silvertrail-adjust',
				'title'   => __( 'Change a service', 'silvertrail' ),
				'content' => self::section(
					__( 'Register a service again by its ID with only the fields to change. A preset’s ID turns the preset on with its defaults, so trackers need only their tag_id. The silvertrail_config filter runs last and can edit or remove any service.', 'silvertrail' ),
					self::ADJUST
				),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'silvertrail-script',
				'title'   => __( 'Gate a script', 'silvertrail' ),
				'content' => self::section(
					__( 'The browser ignores the tag until the visitor agrees to the service. Scripts enqueued with a handle listed under handles are gated without changes. Any link to #silvertrail opens the preferences.', 'silvertrail' ),
					self::SCRIPT
				),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'silvertrail-browser',
				'title'   => __( 'JavaScript', 'silvertrail' ),
				'content' => self::section( __( 'Read and react to choices in the browser.', 'silvertrail' ), self::BROWSER ),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'silvertrail-texts',
				'title'   => __( 'Texts', 'silvertrail' ),
				'content' => self::section(
					__( 'Put the JSON from the Banner tab into the theme as silvertrail/texts-{locale}.json, or as silvertrail/texts.json for every language. A child theme wins over its parent. The silvertrail_translation filter can return another path or an array. The admin’s edits win over the file, and the silvertrail_texts filter runs last.', 'silvertrail' ),
					self::TEXTS
				),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'silvertrail-cli',
				'title'   => __( 'WP-CLI', 'silvertrail' ),
				'content' => self::section( __( 'List and check the services, ask everyone again, or export the records.', 'silvertrail' ), self::CLI ),
			)
		);
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'silvertrail' ) . '</strong></p>'
			. '<p><a href="https://github.com/mawiswiss/silvertrail#readme">' . esc_html__( 'Documentation on GitHub', 'silvertrail' ) . '</a></p>'
		);
	}

	private static function section( string $text, string $code ): string {
		return '<p>' . esc_html( $text ) . '</p><pre><code>' . esc_html( $code ) . '</code></pre>';
	}
}
