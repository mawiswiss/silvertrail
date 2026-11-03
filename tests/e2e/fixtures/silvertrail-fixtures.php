<?php
/**
 * Test services and gated scripts for the e2e suite. Loaded only in the wp-env tests site.
 *
 * @package Silvertrail
 */

declare(strict_types=1);

// The PHPUnit suite runs in the same container and brings its own services.
if ( defined( 'DIR_TESTDATA' ) ) {
	return;
}

add_action(
	'silvertrail_register',
	static function (): void {
		silvertrail_register_service(
			'test-analytics',
			array(
				'group'    => 'statistics',
				'name'     => 'Test Analytics',
				'provider' => 'Example Analytics',
				'purpose'  => 'Counts visits in the e2e suite.',
				'hosts'    => array( 'analytics.example.test' ),
			)
		);
		silvertrail_register_service(
			'test-gated',
			array(
				'group'   => 'statistics',
				'name'    => 'Test Gated Handle',
				'handles' => array( 'silvertrail-test-gated' ),
			)
		);
		silvertrail_register_service(
			'test-video',
			array(
				'group' => 'external-media',
				'name'  => 'Test Video',
				'hosts' => array( 'video.example.test' ),
			)
		);
		silvertrail_register_service(
			'test-pixel',
			array(
				'group'    => 'marketing',
				'name'     => 'Test Pixel',
				'provider' => 'Example Ads',
				'purpose'  => 'Marks conversions in the e2e suite.',
				'hosts'    => array( 'pixel.example.test' ),
			)
		);
	}
);

add_action(
	'wp_footer',
	static function (): void {
		echo '<script type="text/plain" data-silvertrail="test-analytics">window.silvertrailTest = (window.silvertrailTest || []).concat("analytics");</script>';
		echo '<script type="text/plain" data-silvertrail="test-pixel">window.silvertrailTest = (window.silvertrailTest || []).concat("pixel");</script>';
	},
	100
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_script( 'silvertrail-test-gated', content_url( 'mu-plugins/gated.js' ), array(), '1', true );
		wp_add_inline_script( 'silvertrail-test-gated', 'window.silvertrailTest.push("gated-after");' );
	}
);

add_action(
	'init',
	static function (): void {
		if ( get_page_by_path( 'silvertrail-embeds' ) ) {
			return;
		}
		// Front-end requests run kses, which would strip the iframe.
		kses_remove_filters();
		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Embeds',
				'post_name'    => 'silvertrail-embeds',
				'post_content' => '<iframe src="https://video.example.test/embed/1" title="Test video"></iframe>',
			)
		);
	}
);
