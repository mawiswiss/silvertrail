<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use Silvertrail\ConsentMode;
use Silvertrail\Settings;
use Silvertrail\Snippets;
use WP_UnitTestCase;

final class SnippetsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Config::reset();
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		remove_all_filters( 'silvertrail_config' );
		Config::reset();
		parent::tear_down();
	}

	public function test_a_preset_with_a_valid_id_prints_an_inert_tag(): void {
		$this->enable( 'google-tag-manager', 'GTM-ABC123' );

		$html = get_echo( array( Snippets::class, 'print' ) );

		$this->assertStringContainsString( 'type="text/plain"', $html );
		$this->assertStringContainsString( 'data-silvertrail="google-tag-manager"', $html );
		$this->assertStringContainsString( '"GTM-ABC123"', $html );
		$this->assertStringNotContainsString( '<noscript', $html );
	}

	public function test_an_invalid_id_prints_nothing(): void {
		$this->enable( 'google-tag-manager', 'GTM-abc");alert(1);//' );

		$this->assertSame( '', get_echo( array( Snippets::class, 'print' ) ) );
	}

	public function test_advanced_mode_leaves_google_tags_open(): void {
		$this->enable( 'google-analytics', 'G-ABC123' );
		add_filter(
			'silvertrail_config',
			static function ( array $config ): array {
				$config['google']['mode'] = 'advanced';
				return $config;
			}
		);

		$html = get_echo( array( Snippets::class, 'print' ) );

		$this->assertStringContainsString( 'googletagmanager.com/gtag/js?id=G-ABC123', $html );
		$this->assertStringNotContainsString( 'text/plain', $html );
	}

	public function test_consent_mode_prints_only_with_google_services(): void {
		$this->assertSame( '', get_echo( array( ConsentMode::class, 'print' ) ) );

		$this->enable( 'google-analytics', 'G-ABC123' );
		Config::reset();
		$html = get_echo( array( ConsentMode::class, 'print' ) );

		$this->assertStringContainsString( 'gtag("consent","default",{"ad_storage":"denied"', $html );
		$this->assertStringContainsString( '"analytics_storage":["google-analytics"]', $html );
	}

	private function enable( string $preset, string $tag_id ): void {
		update_option(
			Settings::OPTION,
			array(
				'presets' => array(
					$preset => array(
						'enabled' => true,
						'tag_id'  => $tag_id,
					),
				),
			)
		);
	}
}
