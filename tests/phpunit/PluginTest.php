<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use Silvertrail\Plugin;
use Silvertrail\Reopen;
use Silvertrail\Scan;
use Silvertrail\Settings;
use WP_UnitTestCase;

final class PluginTest extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_GET[ Plugin::PREVIEW ], $_GET[ Scan::PARAM ] );
		delete_option( Settings::OPTION );
		remove_all_actions( 'silvertrail_register' );
		remove_all_filters( 'silvertrail_reopen_icon' );
		Config::reset();
		parent::tear_down();
	}

	public function test_silvertrail_is_on_from_the_start(): void {
		$this->assertTrue( Plugin::live() );
	}

	public function test_switched_off_it_works_only_in_a_preview_or_a_scan(): void {
		update_option( Settings::OPTION, array( 'enabled' => false ) );
		$this->assertFalse( Plugin::live() );

		$_GET[ Plugin::PREVIEW ] = '1';
		$this->assertTrue( Plugin::live() );

		unset( $_GET[ Plugin::PREVIEW ] );
		$_GET[ Scan::PARAM ] = '1';
		$this->assertTrue( Plugin::live() );
	}

	public function test_switched_off_the_settings_link_prints_nothing(): void {
		$this->assertStringContainsString( 'href="#silvertrail"', Reopen::link( 'Privacy' ) );

		update_option( Settings::OPTION, array( 'enabled' => false ) );

		$this->assertSame( '', Reopen::link( 'Privacy' ) );
	}

	public function test_the_floating_button_shows_only_once_turned_on(): void {
		add_action( 'silvertrail_register', static fn() => silvertrail_register_service( 'stats', array( 'group' => 'statistics' ) ) );
		Config::reset();

		$this->assertSame( '', $this->button() );

		update_option(
			Settings::OPTION,
			array(
				'floating_button'   => 'auto',
				'floating_position' => 'right',
			)
		);
		$html = $this->button();

		$this->assertStringContainsString( 'silvertrail-reopen silvertrail-reopen--right', $html );
		$this->assertStringContainsString( 'data-silvertrail-reopen="auto"', $html );
		$this->assertMatchesRegularExpression( '/<svg viewbox="0 0 24 24"/i', $html );
		$this->assertStringContainsString( '<path fill="currentColor" d="M12 2a10', $html );
	}

	public function test_the_icon_filter_takes_an_image_and_strips_the_rest(): void {
		add_filter( 'silvertrail_reopen_icon', static fn(): string => '<img src="/icon.svg" alt="" onerror="alert(1)"><script>alert(2)</script>' );

		$icon = Reopen::icon();

		$this->assertStringContainsString( '<img src="/icon.svg" alt="">', $icon );
		$this->assertStringNotContainsString( 'onerror', $icon );
		$this->assertStringNotContainsString( '<script', $icon );
	}

	public function test_the_icon_filter_keeps_an_outline_icon_whole(): void {
		$outline = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline><line x1="1" y1="1" x2="5" y2="5"></line></svg>';
		add_filter( 'silvertrail_reopen_icon', static fn(): string => $outline );

		$icon = Reopen::icon();

		$this->assertStringContainsString( 'stroke-linecap="round"', $icon );
		$this->assertStringContainsString( '<polyline points="20 6 9 17 4 12">', $icon );
		$this->assertStringContainsString( '<line x1="1" y1="1" x2="5" y2="5">', $icon );
	}

	private function button(): string {
		ob_start();
		Reopen::button();
		return (string) ob_get_clean();
	}
}
