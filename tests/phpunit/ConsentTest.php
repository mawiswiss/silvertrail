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

	public function test_a_group_counts_once_all_its_services_are_allowed(): void {
		$media = array_keys( array_filter( silvertrail_config()['services'], static fn( array $service ): bool => 'external-media' === $service['group'] ) );
		$this->assertGreaterThan( 1, count( $media ) );
		$this->assertTrue( silvertrail_has( 'essential' ) );
		$this->assertFalse( silvertrail_has( 'statistics' ) );
		$this->assertFalse( silvertrail_has( 'preferences' ) );

		$this->cookie( Config::revision(), array( 'stats', $media[0] ) );
		$this->assertTrue( silvertrail_has( 'statistics' ) );
		$this->assertFalse( silvertrail_has( 'external-media' ) );

		$this->cookie( Config::revision(), $media );
		$this->assertTrue( silvertrail_has( 'external-media' ) );
		$this->assertFalse( silvertrail_has( 'statistics' ) );
	}

	public function test_the_state_lists_which_services_of_a_group_are_granted(): void {
		$media = array_keys( array_filter( silvertrail_config()['services'], static fn( array $service ): bool => 'external-media' === $service['group'] ) );
		$this->assertSame( $this->state( 'denied', array(), $media ), silvertrail_state( 'external-media' ) );
		$this->assertSame( $this->state( 'denied', array(), array( 'unknown' ) ), silvertrail_state( 'unknown' ) );
		$this->assertSame( $this->state( 'denied', array(), array( 'preferences' ) ), silvertrail_state( 'preferences' ) );
		$this->assertSame( 'granted', silvertrail_state( 'essential' )['status'] );

		$this->cookie( Config::revision(), array( $media[0] ) );
		$this->assertSame( $this->state( 'mixed', array( $media[0] ), array_slice( $media, 1 ) ), silvertrail_state( 'external-media' ) );
		$this->assertSame( $this->state( 'granted', array( $media[0] ), array() ), silvertrail_state( $media[0] ) );
		$this->assertFalse( silvertrail_has( 'external-media' ) );

		$this->cookie( Config::revision(), $media );
		$this->assertSame( $this->state( 'granted', $media, array() ), silvertrail_state( 'external-media' ) );
	}

	public function test_a_service_wins_over_a_group_of_the_same_id(): void {
		add_action(
			'silvertrail_register',
			static function (): void {
				silvertrail_register_service( 'marketing', array( 'group' => 'statistics' ) );
			}
		);
		$this->cookie( Config::revision(), array( 'marketing' ) );

		$this->assertTrue( silvertrail_has( 'marketing' ) );
		$this->assertFalse( silvertrail_has( 'ads' ) );
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
	 * @param list<string> $granted
	 * @param list<string> $denied
	 * @return array{status: string, granted: list<string>, denied: list<string>}
	 */
	private function state( string $status, array $granted, array $denied ): array {
		return array(
			'status'  => $status,
			'granted' => $granted,
			'denied'  => $denied,
		);
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
