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
