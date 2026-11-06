<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Plugin;
use Silvertrail\Reopen;
use Silvertrail\Scan;
use Silvertrail\Settings;
use WP_UnitTestCase;

final class PluginTest extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_GET[ Plugin::PREVIEW ], $_GET[ Scan::PARAM ] );
		delete_option( Settings::OPTION );
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
}
