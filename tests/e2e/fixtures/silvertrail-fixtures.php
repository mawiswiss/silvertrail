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
				'google'   => array( 'analytics_storage' ),
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
				'google'   => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
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
		$pages = array(
			'silvertrail-embeds'    => '<iframe src="https://video.example.test/embed/1" title="Test video"></iframe>',
			'silvertrail-shortcode' => '[silvertrail_link]',
			'silvertrail-block'     => '<!-- wp:silvertrail/reopen-link {"text":"Cookie settings"} /-->',
			'silvertrail-leak'      => 'This page loads a script from another host without asking.',
			'silvertrail-vimeo'     => '<iframe src="https://player.vimeo.com/video/1" title="Vimeo video"></iframe>',
			'silvertrail-youtube'   => '<iframe src="https://www.youtube-nocookie.com/embed/1" title="YouTube video"></iframe>',
		);
		foreach ( $pages as $slug => $content ) {
			if ( get_page_by_path( $slug ) ) {
				continue;
			}
			// Front-end requests run kses, which would strip the iframe.
			kses_remove_filters();
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $slug,
					'post_name'    => $slug,
					'post_content' => $content,
				)
			);
		}
	}
);

// Lets a test switch settings per browser context without touching the shared database.
// The presets are the same for every test, whatever an admin test saved: GTM on, Vimeo off so the
// scan has a preset to suggest, the rest at their defaults. Switching services per test would change the shared revision.
$silvertrail_test_settings = static function ( $value ) {
	$value            = is_array( $value ) ? $value : array();
	$value['presets'] = array(
		'google-tag-manager' => array(
			'enabled' => true,
			'tag_id'  => 'GTM-TEST123',
		),
		'vimeo'              => array(
			'enabled' => false,
			'tag_id'  => '',
		),
	);
	if ( empty( $_COOKIE['silvertrail_test_settings'] ) ) {
		return $value;
	}
	$override = json_decode( wp_unslash( $_COOKIE['silvertrail_test_settings'] ), true );
	return is_array( $override ) ? array_replace( $value, $override ) : $value;
};
add_filter( 'option_silvertrail', $silvertrail_test_settings );
add_filter( 'default_option_silvertrail', $silvertrail_test_settings );

// Settings from code, through the public filter, per browser context.
add_filter(
	'silvertrail_settings',
	static function ( array $settings ): array {
		$code = json_decode( wp_unslash( $_COOKIE['silvertrail_test_code'] ?? '' ), true );
		return is_array( $code ) ? array_replace( $settings, $code ) : $settings;
	}
);

// Asks every group in place, as on a site with embeds only. The services and the revision stay the same.
add_filter(
	'silvertrail_config',
	static function ( array $config ): array {
		if ( ! empty( $_COOKIE['silvertrail_test_in_place'] ) ) {
			foreach ( $config['groups'] as $id => $group ) {
				$config['groups'][ $id ]['in_place'] = true;
			}
		}
		return $config;
	}
);

// An ungated script from another host, for the trail, the leak check and expect-no-leaks.
add_action(
	'wp_head',
	static function (): void {
		if ( is_page( 'silvertrail-leak' ) ) {
			echo '<script src="http://127.0.0.1:8892/wp-content/mu-plugins/leak.js"></script>';
		}
	}
);
