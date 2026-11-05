<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Settings;
use Silvertrail\Texts;
use WP_UnitTestCase;

final class SettingsPageTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';
		Texts::reset();
		$GLOBALS['wp_settings_errors'] = array();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		unset( $_GET['silvertrail-turn-on'] );
		remove_all_actions( 'silvertrail_register' );
		Config::reset();
		remove_all_filters( 'silvertrail_translation' );
		remove_all_filters( 'silvertrail_settings' );
		Settings::reset();
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_saving_one_tab_keeps_the_other_tabs(): void {
		$stored                                  = Settings::defaults();
		$stored['presets']['youtube']['enabled'] = true;
		$stored['presets']['youtube']['tag_id']  = '';
		$stored['records']                       = true;
		$stored['trail']                         = false;
		update_option( Settings::OPTION, $stored );

		$saved = SettingsPage::sanitize(
			array(
				'_tab'   => 'banner',
				'layout' => 'box',
			)
		);

		$this->assertSame( 'box', $saved['layout'] );
		$this->assertFalse( $saved['require_choice'], 'An unchecked box on the saved tab turns off.' );
		$this->assertTrue( $saved['presets']['youtube']['enabled'] );
		$this->assertTrue( $saved['records'] );
		$this->assertFalse( $saved['trail'] );
		$this->assertArrayNotHasKey( '_tab', $saved );
	}

	public function test_saving_keeps_the_admin_value_of_a_setting_in_code(): void {
		update_option( Settings::OPTION, array_replace( Settings::defaults(), array( 'layout' => 'box' ) ) );
		add_filter( 'silvertrail_settings', static fn( array $settings ): array => array( 'layout' => 'modal' ) + $settings );

		$saved = SettingsPage::sanitize(
			array(
				'_tab'       => 'banner',
				'appearance' => 'dark',
			)
		);

		$this->assertSame( 'box', $saved['layout'] );
		$this->assertSame( 'dark', $saved['appearance'] );
	}

	public function test_a_saved_tab_survives_a_second_pass(): void {
		$first = SettingsPage::sanitize(
			array(
				'_tab'    => 'services',
				'presets' => array( 'youtube' => array( 'enabled' => '1' ) ),
			)
		);
		$second = SettingsPage::sanitize( $first );

		ksort( $first );
		ksort( $second );
		$this->assertSame( $first, $second );
		$this->assertTrue( $second['presets']['youtube']['enabled'] );
	}

	public function test_without_a_tab_every_setting_comes_from_the_input(): void {
		update_option( Settings::OPTION, array_replace( Settings::defaults(), array( 'records' => true ) ) );

		$saved = SettingsPage::sanitize( array( 'layout' => 'box' ) );

		$this->assertSame( 'box', $saved['layout'] );
		$this->assertFalse( $saved['records'] );
	}

	public function test_posted_json_keeps_only_what_differs_from_the_translation(): void {
		add_filter( 'silvertrail_translation', static fn() => array( 'intro' => 'Developer intro' ) );
		$texts          = Texts::base( 'en_US' );
		$texts['title'] = 'Your "privacy" choices';

		$saved = SettingsPage::sanitize(
			array(
				'_tab'       => 'banner',
				'texts_json' => (string) wp_json_encode( $texts ),
			)
		);

		$this->assertSame( array( 'en_US' => array( 'title' => 'Your "privacy" choices' ) ), $saved['texts'] );
	}

	public function test_posted_json_keeps_the_other_languages(): void {
		$stored          = Settings::defaults();
		$stored['texts'] = array( 'de_CH' => array( 'title' => 'Datenschutz' ) );
		update_option( Settings::OPTION, $stored );

		$saved = SettingsPage::sanitize(
			array(
				'_tab'       => 'banner',
				'texts_json' => '{ "title": "Privacy" }',
			)
		);

		$this->assertSame(
			array(
				'de_CH' => array( 'title' => 'Datenschutz' ),
				'en_US' => array( 'title' => 'Privacy' ),
			),
			$saved['texts']
		);
	}

	public function test_invalid_json_keeps_the_stored_texts_and_the_draft(): void {
		$stored          = Settings::defaults();
		$stored['texts'] = array( 'en_US' => array( 'title' => 'Kept' ) );
		update_option( Settings::OPTION, $stored );

		$saved = SettingsPage::sanitize(
			array(
				'_tab'       => 'banner',
				'texts_json' => '{ "title": "Broken", ',
			)
		);

		$this->assertSame( array( 'en_US' => array( 'title' => 'Kept' ) ), $saved['texts'] );
		$this->assertSame( 'silvertrail-texts', get_settings_errors( Settings::OPTION )[0]['code'] );
		$this->assertSame( '{ "title": "Broken", ', SettingsPage::draft() );
		$this->assertNull( SettingsPage::draft() );
	}

	public function test_broken_entries_are_reported_and_the_rest_is_saved(): void {
		$saved = SettingsPage::sanitize(
			array(
				'_tab'       => 'banner',
				'texts_json' => '{ "title": "Saved", "bogus": "x", "embed_text": "no placeholder" }',
			)
		);

		$this->assertSame( array( 'en_US' => array( 'title' => 'Saved' ) ), $saved['texts'] );
		$this->assertCount( 2, get_settings_errors( Settings::OPTION ) );
		$this->assertStringContainsString( '"bogus"', (string) SettingsPage::draft() );
	}

	public function test_the_leak_check_ticks_only_a_preset_that_code_leaves_alone(): void {
		add_action(
			'silvertrail_register',
			static function (): void {
				silvertrail_register_service( 'vimeo', array() );
			}
		);
		Config::reset();

		$_GET['silvertrail-turn-on'] = 'youtube';
		$this->assertSame( 'youtube', SettingsPage::turning_on() );
		$_GET['silvertrail-turn-on'] = 'vimeo';
		$this->assertSame( '', SettingsPage::turning_on() );
		$_GET['silvertrail-turn-on'] = 'hubspot';
		$this->assertSame( '', SettingsPage::turning_on() );
	}
}
