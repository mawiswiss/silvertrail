<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
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
		unset( $_COOKIE[ Config::COOKIE ] );
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

	/**
	 * @param list<string> $ids
	 */
	private function cookie( int $revision, array $ids ): void {
		$_COOKIE[ Config::COOKIE ] = wp_slash(
			(string) wp_json_encode(
				array(
					'n' => $revision,
					'c' => $ids,
					't' => time(),
				)
			)
		);
	}
}
