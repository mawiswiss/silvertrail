<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Scan;
use WP_UnitTestCase;

final class ScanTest extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_GET[ Scan::PARAM ], $_COOKIE['silvertrail'], $_COOKIE['wp_consent_marketing'], $_COOKIE['other'] );
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
}
