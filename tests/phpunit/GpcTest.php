<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Gpc;
use Silvertrail\Settings;
use WP_UnitTestCase;
use WPDieException;

final class GpcTest extends WP_UnitTestCase {

	private string $uri = '';

	public function set_up(): void {
		parent::set_up();
		$this->uri = $_SERVER['REQUEST_URI'] ?? '';
	}

	public function tear_down(): void {
		$_SERVER['REQUEST_URI'] = $this->uri;
		delete_option( Settings::OPTION );
		delete_option( Gpc::OPTION );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		parent::tear_down();
	}

	public function test_the_resource_states_support_with_a_date_that_stays(): void {
		$first = Gpc::resource();

		$this->assertTrue( $first['gpc'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $first['lastUpdate'] );

		update_option( Gpc::OPTION, '2026-01-02' );
		$this->assertSame( '2026-01-02', Gpc::resource()['lastUpdate'] );
	}

	public function test_the_well_known_path_answers_with_json(): void {
		$_SERVER['REQUEST_URI'] = '/.well-known/gpc.json?ver=1';

		$body = $this->serve();

		$this->assertSame( Gpc::resource(), json_decode( $body, true ) );
	}

	public function test_other_paths_and_a_turned_off_setting_pass_through(): void {
		$_SERVER['REQUEST_URI'] = '/.well-known/other.json';
		$this->assertSame( '', $this->serve() );

		update_option( Settings::OPTION, array( 'gpc' => false ) );
		$_SERVER['REQUEST_URI'] = Gpc::PATH;
		$this->assertSame( '', $this->serve() );
	}

	public function test_the_privacy_text_follows_the_settings(): void {
		$this->assertStringContainsString( 'Global Privacy Control', Gpc::privacy_text() );

		update_option( Settings::OPTION, array( 'gpc' => false ) );
		$this->assertSame( '', Gpc::privacy_text() );
	}

	private function serve(): string {
		// wp_send_json() ends in die() unless the request counts as Ajax.
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static fn() => static function (): void {
				throw new WPDieException();
			}
		);
		ob_start();
		try {
			Gpc::serve();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		return (string) ob_get_clean();
	}
}
