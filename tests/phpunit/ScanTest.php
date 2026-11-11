<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use Silvertrail\Frontend;
use Silvertrail\Scan;
use Silvertrail\Settings;
use WP_UnitTestCase;

final class ScanTest extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_GET[ Scan::PARAM ], $_COOKIE['silvertrail'], $_COOKIE['silvertrail_scan'], $_COOKIE['wp_consent_marketing'], $_COOKIE['other'] );
		parent::tear_down();
	}

	public function test_a_scan_request_ignores_stored_choices(): void {
		$_COOKIE['silvertrail']          = '{"c":["youtube"],"n":1}';
		$_COOKIE['wp_consent_marketing'] = 'allow';
		$_COOKIE['other']                = 'kept';
		$_GET[ Scan::PARAM ]             = '1';

		Scan::register();

		$this->assertSame( array( 'other' => 'kept' ), array_intersect_key( $_COOKIE, array_flip( array( 'silvertrail', 'wp_consent_marketing', 'other' ) ) ) );
	}

	public function test_a_scan_request_renders_as_a_visitor(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertSame( $admin, Scan::visitor( $admin ) );
		$this->assertFalse( Scan::active() );

		$_GET[ Scan::PARAM ] = '1';

		$this->assertSame( 0, Scan::visitor( $admin ) );
		$this->assertTrue( Scan::active() );
	}

	public function test_a_scan_keeps_its_own_consent_and_the_consent_api_cookies_it_set(): void {
		$_COOKIE['silvertrail']          = '{"c":[],"n":1}';
		$_COOKIE['silvertrail_scan']     = '{"c":["youtube"],"n":1}';
		$_COOKIE['wp_consent_marketing'] = 'allow';
		$_GET[ Scan::PARAM ]             = '1';

		Scan::register();

		$this->assertSame(
			array(
				'silvertrail_scan'     => '{"c":["youtube"],"n":1}',
				'wp_consent_marketing' => 'allow',
			),
			array_intersect_key( $_COOKIE, array_flip( array( 'silvertrail', 'silvertrail_scan', 'wp_consent_marketing' ) ) )
		);
	}

	public function test_only_a_scan_uses_the_scan_cookie(): void {
		$this->assertSame( 'silvertrail', Config::cookie() );
		$this->assertSame( 'silvertrail_scan', Config::cookie( true ) );

		$_GET[ Scan::PARAM ] = '1';

		$this->assertSame( 'silvertrail_scan', Config::cookie() );
		$this->assertSame( 'silvertrail', Config::cookie( false ) );
	}

	public function test_a_scan_records_nothing_and_forgets_its_choice_within_minutes(): void {
		update_option( Settings::OPTION, array( 'records' => true ) );
		$_GET[ Scan::PARAM ] = '1';
		wp_deregister_script( 'silvertrail' );

		Frontend::enqueue();

		$config = implode( '', (array) wp_scripts()->get_data( 'silvertrail', 'before' ) );
		$this->assertStringContainsString( '"cookie":"silvertrail_scan"', $config );
		$this->assertStringContainsString( '"record":""', $config );
		$this->assertStringContainsString( '"recordIds":false', $config );
		$this->assertStringContainsString( '"maxAge":600', $config );
	}
}
