<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use Silvertrail\Records;
use Silvertrail\Settings;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class RecordsTest extends WP_UnitTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		Config::reset();
		add_action(
			'silvertrail_register',
			static function (): void {
				silvertrail_register_service( 'stats', array( 'group' => 'statistics' ) );
			}
		);
	}

	public function tear_down(): void {
		remove_all_actions( 'silvertrail_register' );
		remove_all_actions( 'silvertrail_recorded' );
		remove_all_filters( 'pre_option_' . Settings::OPTION );
		wp_clear_scheduled_hook( Records::CRON );
		delete_option( Settings::OPTION );
		Config::reset();
		parent::tear_down();
	}

	public function test_no_endpoint_while_nothing_listens(): void {
		$this->boot_rest();

		$this->assertArrayNotHasKey( '/silvertrail/v1/record', $this->server->get_routes() );
	}

	public function test_a_decision_is_stored_without_personal_data(): void {
		update_option( Settings::OPTION, array( 'records' => true ) );
		Records::install();
		$this->boot_rest();

		$response = $this->post(
			array(
				'i'      => str_repeat( 'ab', 16 ),
				'n'      => Config::revision(),
				'c'      => array( 'stats', 'made-up' ),
				'source' => 'banner',
			)
		);

		$this->assertSame( 204, $response->get_status() );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i', Records::table() ), ARRAY_A );
		$this->assertSame( str_repeat( 'ab', 16 ), $row['consent_id'] );
		$this->assertSame( '["stats"]', $row['choices'] );
		$this->assertSame( array( 'id', 'consent_id', 'revision', 'choices', 'source', 'created_at' ), array_keys( $row ) );
	}

	public function test_a_listener_gets_the_record_without_a_table(): void {
		$received = array();
		add_action(
			'silvertrail_recorded',
			static function ( array $record ) use ( &$received ): void {
				$received[] = $record;
			}
		);
		$this->boot_rest();

		$this->assertSame(
			204,
			$this->post(
				array(
					'n'      => 1,
					'c'      => array(),
					'source' => 'gpc',
				)
			)->get_status()
		);
		$this->assertSame( 'gpc', $received[0]['source'] );
	}

	public function test_a_preview_while_switched_off_records_nothing(): void {
		$received = array();
		add_action(
			'silvertrail_recorded',
			static function ( array $record ) use ( &$received ): void {
				$received[] = $record;
			}
		);
		update_option(
			Settings::OPTION,
			array(
				'enabled' => false,
				'records' => true,
			)
		);
		$this->boot_rest();

		$response = $this->post(
			array(
				'i'      => str_repeat( 'ab', 16 ),
				'n'      => Config::revision(),
				'c'      => array( 'stats' ),
				'source' => 'banner',
			)
		);

		$this->assertSame( 204, $response->get_status() );
		$this->assertSame( array(), $received );
	}

	public function test_invalid_records_are_refused(): void {
		update_option( Settings::OPTION, array( 'records' => true ) );
		$this->boot_rest();

		$this->assertSame(
			400,
			$this->post(
				array(
					'i'      => 'not-hex',
					'n'      => 1,
					'c'      => array(),
					'source' => 'banner',
				)
			)->get_status()
		);
		$this->assertSame(
			400,
			$this->post(
				array(
					'i'      => str_repeat( 'ab', 16 ),
					'n'      => 1,
					'c'      => array(),
					'source' => 'somewhere',
				)
			)->get_status()
		);
	}

	public function test_the_input_is_checked_without_records_too(): void {
		$received = array();
		add_action(
			'silvertrail_recorded',
			static function ( array $record ) use ( &$received ): void {
				$received[] = $record;
			}
		);
		$this->boot_rest();
		$record = array(
			'n'      => 1,
			'c'      => array( array( 'stats' ), 'stats', 7 ),
			'source' => 'banner',
		);

		$this->assertSame( 204, $this->post( $record )->get_status() );
		$this->assertSame( array( 'stats' ), $received[0]['choices'] );
		$this->assertSame( 400, $this->post( array( 'i' => str_repeat( 'x', 4000 ) ) + $record )->get_status() );
		$this->assertSame( 400, $this->post( array( 'n' => Config::revision() + 1 ) + $record )->get_status() );
		$this->assertCount( 1, $received );
	}

	public function test_old_records_are_pruned_after_records_are_turned_off(): void {
		update_option( Settings::OPTION, array( 'records' => true ) );
		global $wpdb;
		$wpdb->insert(
			Records::table(),
			array(
				'consent_id' => str_repeat( 'ab', 16 ),
				'revision'   => 1,
				'choices'    => '[]',
				'source'     => 'banner',
				'created_at' => '2020-01-01 00:00:00',
			)
		);

		update_option( Settings::OPTION, array( 'records' => false ) );
		$this->assertNotFalse( wp_next_scheduled( Records::CRON ) );

		Records::prune();
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Records::table() ) ) );
		$this->assertFalse( wp_next_scheduled( Records::CRON ) );
	}

	public function test_records_turned_on_past_the_option_hooks_get_a_table_and_a_job(): void {
		add_filter( 'pre_option_' . Settings::OPTION, static fn (): array => array( 'records' => true ) );
		$this->boot_rest();

		$response = $this->post(
			array(
				'i'      => str_repeat( 'cd', 16 ),
				'n'      => 1,
				'c'      => array( 'stats' ),
				'source' => 'banner',
			)
		);

		$this->assertSame( 204, $response->get_status() );
		$this->assertNotFalse( wp_next_scheduled( Records::CRON ) );
		global $wpdb;
		$this->assertSame( str_repeat( 'cd', 16 ), $wpdb->get_var( $wpdb->prepare( 'SELECT consent_id FROM %i', Records::table() ) ) );
	}

	private function boot_rest(): void {
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $this->server );
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private function post( array $body ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/silvertrail/v1/record' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		return $this->server->dispatch( $request );
	}
}
