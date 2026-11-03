<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use WP_UnitTestCase;

final class ConfigTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Config::reset();
		delete_option( Config::VERSION_OPTION );
	}

	public function tear_down(): void {
		remove_all_actions( 'silvertrail_register' );
		remove_all_filters( 'silvertrail_config' );
		Config::reset();
		parent::tear_down();
	}

	public function test_a_fresh_install_needs_no_banner(): void {
		$this->assertSame( array( 'silvertrail' ), array_keys( Config::get()['services'] ) );
		$this->assertFalse( Config::needs_banner() );
	}

	public function test_a_registered_service_needs_consent(): void {
		$this->register( 'stats', array( 'group' => 'statistics' ) );

		$this->assertSame( array( 'stats' ), Config::consent_ids() );
		$this->assertTrue( Config::needs_banner() );
	}

	public function test_an_exempt_service_needs_no_banner(): void {
		$this->register(
			'matomo',
			array(
				'group'   => 'statistics',
				'consent' => 'exempt',
			)
		);

		$this->assertContains( 'matomo', Config::essential_ids() );
		$this->assertFalse( Config::needs_banner() );
	}

	public function test_the_cookie_is_scoped_to_the_site_path(): void {
		$this->assertSame( '/', Config::cookie_path() );

		update_option( 'home', 'https://example.org/blog/' );
		$this->assertSame( '/blog', Config::cookie_path() );
	}

	public function test_a_service_in_an_unknown_group_is_skipped(): void {
		$this->setExpectedIncorrectUsage( 'silvertrail_config' );
		$this->register( 'orphan', array( 'group' => 'nope' ) );

		$this->assertArrayNotHasKey( 'orphan', Config::get()['services'] );
	}

	public function test_unknown_consent_mode_types_are_dropped(): void {
		$this->setExpectedIncorrectUsage( 'silvertrail_config' );
		$this->register(
			'ads',
			array(
				'group'  => 'marketing',
				'google' => array( 'ad_storage', 'ad_fun' ),
			)
		);

		$this->assertSame( array( 'ad_storage' ), Config::get()['services']['ads']['google'] );
	}

	public function test_the_filter_cannot_remove_the_own_cookie(): void {
		add_filter(
			'silvertrail_config',
			static function ( array $config ): array {
				unset( $config['services']['silvertrail'] );
				return $config;
			}
		);

		$this->assertArrayHasKey( 'silvertrail', Config::get()['services'] );
	}

	public function test_the_revision_rises_only_when_the_services_change(): void {
		$this->register( 'stats', array( 'group' => 'statistics' ) );
		$first = Config::revision();

		Config::reset();
		$this->register( 'stats', array( 'group' => 'statistics' ) );
		$this->assertSame( $first, Config::revision() );

		Config::reset();
		$this->register( 'stats', array( 'group' => 'statistics' ) );
		$this->register( 'ads', array( 'group' => 'marketing' ) );
		$this->assertSame( $first + 1, Config::revision() );
	}

	public function test_ask_again_raises_the_revision(): void {
		$first = Config::revision();

		$this->assertSame( $first + 1, Config::ask_again() );
		Config::reset();
		$this->assertSame( $first + 1, Config::revision() );
	}

	public function test_urls_match_hosts_and_paths(): void {
		$this->register(
			'video',
			array(
				'group' => 'external-media',
				'hosts' => array( 'https://www.youtube.com', 'google.com/maps' ),
			)
		);

		$this->assertSame( 'video', Config::service_for_url( 'https://www.youtube.com/embed/abc' ) );
		$this->assertSame( 'video', Config::service_for_url( '//m.youtube.com/watch?v=abc' ) );
		$this->assertSame( 'video', Config::service_for_url( 'https://google.com/maps/embed?pb=1' ) );
		$this->assertNull( Config::service_for_url( 'https://notyoutube.com/embed' ) );
		$this->assertNull( Config::service_for_url( 'https://google.com/search?q=maps' ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function register( string $id, array $args ): void {
		add_action(
			'silvertrail_register',
			static function () use ( $id, $args ): void {
				silvertrail_register_service( $id, $args );
			}
		);
	}
}
