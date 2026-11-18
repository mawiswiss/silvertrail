<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Presets;
use Silvertrail\Settings;
use WP_Error;
use WP_UnitTestCase;

final class AbilitiesTest extends WP_UnitTestCase {

	private const PUBLIC = array( 'list-services', 'get-settings', 'validate', 'get-styling-brief', 'update-presets', 'ask-again' );

	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities need WordPress 6.9.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		remove_all_actions( 'silvertrail_register' );
		remove_all_filters( 'silvertrail_settings' );
		remove_all_filters( 'wp_ai_client_prevent_prompt' );
		Config::reset();
		Settings::reset();
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_registers_the_abilities_for_mcp_in_their_own_category(): void {
		foreach ( self::PUBLIC as $name ) {
			$ability = wp_get_ability( "silvertrail/{$name}" );
			$this->assertNotNull( $ability, $name );
			$this->assertSame( 'silvertrail', $ability->get_category() );
			$this->assertTrue( $ability->get_meta()['public'], $name );
		}
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->assertFalse( wp_get_ability( 'silvertrail/write-styles' )->get_meta()['public'], 'MCP clients write the styles themselves.' );
			$this->assertTrue( wp_get_ability( 'silvertrail/write-styles' )->get_meta()['show_in_rest'], 'The Banner tab calls it over REST.' );
		}
	}

	public function test_only_admins_run_them(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertInstanceOf( WP_Error::class, $this->run_ability( 'validate' ) );
		$this->assertInstanceOf( WP_Error::class, $this->run_ability( 'ask-again' ) );
	}

	public function test_settings_name_where_each_value_comes_from(): void {
		update_option( Settings::OPTION, array( 'layout' => 'box' ) );
		add_filter(
			'silvertrail_settings',
			static function ( array $settings ): array {
				$settings['expiry_months'] = 12;
				return $settings;
			}
		);

		$sources = array_column( $this->run_ability( 'get-settings' )['settings'], 'source', 'key' );

		$this->assertSame( 'code', $sources['expiry_months'] );
		$this->assertSame( 'admin', $sources['layout'] );
		$this->assertSame( 'default', $sources['gpc'] );
		$this->assertArrayNotHasKey( 'presets', $sources );
	}

	public function test_turning_a_preset_on_stores_what_the_services_form_stores(): void {
		$result = $this->run_ability(
			'update-presets',
			array( 'presets' => array( array( 'id' => 'google-tag-manager', 'enabled' => true, 'tag_id' => 'gtm-test123' ) ) )
		);
		$stored = get_option( Settings::OPTION );

		delete_option( Settings::OPTION );
		$form = array();
		foreach ( array_keys( Presets::all() ) as $id ) {
			$form[ $id ] = array( 'enabled' => Presets::switched_on( $id ) ? '1' : '' );
		}
		$form['google-tag-manager'] = array(
			'enabled' => '1',
			'tag_id'  => 'gtm-test123',
		);
		$this->assertSame(
			SettingsPage::sanitize(
				array(
					'_tab'    => 'services',
					'presets' => $form,
				)
			),
			$stored
		);

		$gtm = array_column( $result['presets'], null, 'id' )['google-tag-manager'];
		$this->assertTrue( $gtm['live'] );
		$this->assertSame( 'GTM-TEST123', $gtm['tag_id'] );
	}

	public function test_refuses_an_id_that_does_not_fit_and_a_tracker_without_one(): void {
		$wrong = $this->run_ability(
			'update-presets',
			array( 'presets' => array( array( 'id' => 'google-tag-manager', 'enabled' => true, 'tag_id' => 'UA-123' ) ) )
		);
		$none  = $this->run_ability( 'update-presets', array( 'presets' => array( array( 'id' => 'google-ads', 'enabled' => true ) ) ) );
		$embed = $this->run_ability( 'update-presets', array( 'presets' => array( array( 'id' => 'youtube', 'tag_id' => 'X' ) ) ) );

		$this->assertInstanceOf( WP_Error::class, $wrong );
		$this->assertStringContainsString( 'GTM-XXXXXXX', $wrong->get_error_message() );
		$this->assertInstanceOf( WP_Error::class, $none );
		$this->assertInstanceOf( WP_Error::class, $embed );
		$this->assertFalse( get_option( Settings::OPTION ) );
	}

	public function test_leaves_a_preset_that_code_turns_on_to_code(): void {
		add_action(
			'silvertrail_register',
			static function (): void {
				silvertrail_register_service( 'google-analytics', array( 'tag_id' => 'G-ABC123' ) );
			}
		);
		Config::rebuild();

		$result = $this->run_ability( 'update-presets', array( 'presets' => array( array( 'id' => 'google-analytics', 'enabled' => false ) ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'Code turns Google Analytics on', $result->get_error_message() );
		$this->assertSame( 'on', array_column( $this->run_ability( 'list-services' )['presets'], 'code', 'id' )['google-analytics'] );
	}

	public function test_asking_again_raises_the_revision(): void {
		$before = Config::revision();

		$this->assertSame( $before + 1, $this->run_ability( 'ask-again' )['revision'] );
	}

	public function test_validate_reports_problems_and_asks_for_support_only_when_there_are_none(): void {
		$clean = $this->run_ability( 'validate' );
		$this->assertTrue( $clean['valid'] );
		$this->assertStringContainsString( SILVERTRAIL_LICENSE_URL, $clean['support'] );

		$this->setExpectedIncorrectUsage( 'silvertrail_settings' );
		add_filter(
			'silvertrail_settings',
			static function ( array $settings ): array {
				$settings['colour'] = 'red';
				return $settings;
			}
		);
		$broken = $this->run_ability( 'validate' );

		$this->assertFalse( $broken['valid'] );
		$this->assertStringContainsString( 'colour', implode( ' ', $broken['problems'] ) );
		$this->assertArrayNotHasKey( 'support', $broken );
	}

	public function test_writing_styles_stops_when_the_site_prevents_prompts(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'The AI Client needs WordPress 7.0.' );
		}
		add_filter( 'wp_ai_client_prevent_prompt', '__return_true' );

		$result = $this->run_ability( 'write-styles', array( 'want' => 'A dark bar' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'silvertrail_no_ai', $result->get_error_code() );
	}

	/**
	 * @param array<string, mixed>|null $input
	 */
	private function run_ability( string $name, ?array $input = null ): mixed {
		return wp_get_ability( "silvertrail/{$name}" )->execute( $input );
	}
}
