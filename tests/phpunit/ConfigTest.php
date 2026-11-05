<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use Silvertrail\Settings;
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

	public function test_a_fresh_install_blocks_embeds_but_needs_no_banner(): void {
		$this->assertSame( array( 'silvertrail', 'youtube', 'vimeo', 'google-maps' ), array_keys( Config::get()['services'] ) );
		$this->assertSame( array( 'youtube', 'vimeo', 'google-maps' ), Config::consent_ids() );
		$this->assertFalse( Config::needs_banner() );
	}

	public function test_an_embed_preset_stays_off_once_the_admin_turns_it_off(): void {
		update_option( Settings::OPTION, array( 'presets' => array( 'youtube' => array( 'enabled' => false ) ) ) );

		$this->assertArrayNotHasKey( 'youtube', Config::get()['services'] );
		$this->assertArrayHasKey( 'vimeo', Config::get()['services'] );
		delete_option( Settings::OPTION );
	}

	public function test_a_registered_service_needs_consent(): void {
		$this->register( 'stats', array( 'group' => 'statistics' ) );

		$this->assertContains( 'stats', Config::consent_ids() );
		$this->assertTrue( Config::needs_banner() );
	}

	public function test_a_group_that_asks_in_place_needs_no_banner(): void {
		add_action(
			'silvertrail_register',
			static function (): void {
				silvertrail_register_group( 'posts', array( 'in_place' => true ) );
			}
		);
		$this->register( 'social', array( 'group' => 'posts' ) );

		$this->assertContains( 'social', Config::consent_ids() );
		$this->assertFalse( Config::needs_banner() );
	}

	public function test_scripts_in_a_group_that_asks_in_place_are_reported(): void {
		$this->setExpectedIncorrectUsage( 'silvertrail_config' );
		$this->register(
			'chat',
			array(
				'group'   => 'external-media',
				'handles' => array( 'chat-widget' ),
			)
		);

		$this->assertArrayHasKey( 'chat', Config::get()['services'] );
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

	public function test_url_passthrough_is_off_until_code_turns_it_on(): void {
		$this->assertFalse( Config::get()['google']['url_passthrough'] );

		Config::reset();
		add_filter(
			'silvertrail_config',
			static function ( array $config ): array {
				$config['google']['url_passthrough'] = true;
				return $config;
			}
		);

		$this->assertTrue( Config::get()['google']['url_passthrough'] );
	}

	public function test_a_misspelled_key_is_reported(): void {
		$this->setExpectedIncorrectUsage( 'silvertrail_config' );
		$this->register(
			'chat',
			array(
				'group' => 'preferences',
				'host'  => 'chat.example.org',
			)
		);

		$this->assertSame( array(), Config::get()['services']['chat']['hosts'] );
	}

	public function test_a_tag_id_from_code_that_does_not_fit_is_reported(): void {
		$this->setExpectedIncorrectUsage( 'silvertrail_config' );
		$this->register( 'google-tag-manager', array( 'tag_id' => 'gtm-x' ) );

		$this->assertSame( 'gtm-x', Config::get()['services']['google-tag-manager']['tag_id'] );
	}

	public function test_an_unknown_consent_mode_is_reported(): void {
		$this->setExpectedIncorrectUsage( 'silvertrail_config' );
		add_filter(
			'silvertrail_config',
			static function ( array $config ): array {
				$config['google']['mode'] = 'full';
				return $config;
			}
		);

		$this->assertSame( 'basic', Config::get()['google']['mode'] );
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
				'hosts' => array( 'https://www.videos.example', 'example.org/maps' ),
			)
		);

		$this->assertSame( 'video', Config::service_for_url( 'https://www.videos.example/embed/abc' ) );
		$this->assertSame( 'video', Config::service_for_url( '//m.videos.example/watch?v=abc' ) );
		$this->assertSame( 'video', Config::service_for_url( 'https://example.org/maps/embed?pb=1' ) );
		$this->assertNull( Config::service_for_url( 'https://notvideos.example/embed' ) );
		$this->assertNull( Config::service_for_url( 'https://example.org/search?q=maps' ) );
	}

	public function test_a_preset_id_in_code_turns_the_preset_on(): void {
		$this->register( 'google-tag-manager', array( 'tag_id' => 'GTM-CODE1' ) );

		$service = Config::get()['services']['google-tag-manager'];

		$this->assertSame( 'Google Tag Manager', $service['name'] );
		$this->assertSame( 'marketing', $service['group'] );
		$this->assertSame( 'GTM-CODE1', $service['tag_id'] );
		$this->assertContains( 'googletagmanager.com/gtm.js', $service['hosts'] );
	}

	public function test_code_changes_only_the_given_fields_of_a_preset(): void {
		update_option(
			Settings::OPTION,
			array(
				'presets' => array(
					'youtube' => array(
						'enabled' => true,
						'tag_id'  => '',
					),
				),
			)
		);
		$this->register( 'youtube', array( 'cookies' => 'YSC' ) );

		$service = Config::get()['services']['youtube'];

		$this->assertSame( 'YSC', $service['cookies'] );
		$this->assertSame( 'YouTube', $service['name'] );
		$this->assertSame( 'external-media', $service['group'] );
		delete_option( Settings::OPTION );
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
