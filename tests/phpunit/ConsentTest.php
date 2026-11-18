<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use Silvertrail\Settings;
use WP_UnitTestCase;

final class ConsentTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Config::reset();
		delete_option( Config::VERSION_OPTION );
		add_action(
			'silvertrail_register',
			static function (): void {
				silvertrail_register_service( 'stats', array( 'group' => 'statistics' ) );
				silvertrail_register_service( 'ads', array( 'group' => 'marketing' ) );
			}
		);
	}

	public function tear_down(): void {
		unset( $_COOKIE[ Config::COOKIE ], $_SERVER['HTTP_SEC_GPC'] );
		delete_option( Settings::OPTION );
		remove_all_actions( 'silvertrail_register' );
		Config::reset();
		parent::tear_down();
	}

	public function test_without_a_cookie_only_essential_services_run(): void {
		$this->assertTrue( silvertrail_has( 'silvertrail' ) );
		$this->assertFalse( silvertrail_has( 'stats' ) );
	}

	public function test_the_cookie_grants_its_services(): void {
		$this->cookie( Config::revision(), array( 'stats' ) );

		$this->assertTrue( silvertrail_has( 'stats' ) );
		$this->assertFalse( silvertrail_has( 'ads' ) );
	}

	public function test_a_newer_cookie_still_counts(): void {
		$this->cookie( Config::revision() + 1, array( 'ads' ) );

		$this->assertTrue( silvertrail_has( 'ads' ) );
	}

	public function test_an_outdated_cookie_grants_nothing(): void {
		$revision = Config::revision();
		$this->cookie( $revision, array( 'stats' ) );
		Config::ask_again();

		$this->assertFalse( silvertrail_has( 'stats' ) );
	}

	public function test_unknown_services_and_broken_cookies_grant_nothing(): void {
		$this->cookie( Config::revision(), array( 'elsewhere' ) );
		$this->assertFalse( silvertrail_has( 'elsewhere' ) );

		$_COOKIE[ Config::COOKIE ] = '{not json';
		$this->assertFalse( silvertrail_has( 'stats' ) );
	}

	public function test_global_privacy_control_overrides_a_choice_made_without_it(): void {
		$this->cookie( Config::revision(), array( 'stats' ) );
		$_SERVER['HTTP_SEC_GPC'] = '1';

		$this->assertFalse( silvertrail_has( 'stats' ) );
		$this->assertTrue( silvertrail_has( 'silvertrail' ) );
	}

	public function test_a_choice_made_under_global_privacy_control_counts(): void {
		$this->cookie( Config::revision(), array( 'stats' ), true );
		$_SERVER['HTTP_SEC_GPC'] = '1';

		$this->assertTrue( silvertrail_has( 'stats' ) );
	}

	public function test_only_a_header_of_exactly_1_is_global_privacy_control(): void {
		$this->cookie( Config::revision(), array( 'stats' ) );
		$_SERVER['HTTP_SEC_GPC'] = 'true';

		$this->assertTrue( silvertrail_has( 'stats' ) );
	}

	public function test_the_header_counts_for_nothing_with_gpc_turned_off(): void {
		update_option( Settings::OPTION, array( 'gpc' => false ) );
		$this->cookie( Config::revision(), array( 'stats' ) );
		$_SERVER['HTTP_SEC_GPC'] = '1';

		$this->assertTrue( silvertrail_has( 'stats' ) );
	}

	/**
	 * @param list<string> $ids
	 */
	private function cookie( int $revision, array $ids, bool $gpc = false ): void {
		$data = array(
			'n' => $revision,
			'c' => $ids,
			't' => time(),
		);
		if ( $gpc ) {
			$data['g'] = 1;
		}
		$_COOKIE[ Config::COOKIE ] = wp_slash( (string) wp_json_encode( $data ) );
	}
}
