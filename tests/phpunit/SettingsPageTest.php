<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Design;
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
		delete_option( SettingsPage::LICENSE_SEEN );
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

	public function test_the_theme_own_scheme_is_saved_as_following_the_theme(): void {
		switch_theme( 'twentytwentyfive' );
		Design::reset();

		$this->assertSame( 'theme', SettingsPage::sanitize( array( '_tab' => 'banner', 'appearance' => 'light' ) )['appearance'] );
		$this->assertSame( 'dark', SettingsPage::sanitize( array( '_tab' => 'banner', 'appearance' => 'dark' ) )['appearance'] );
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
				silvertrail_register_service( 'google-analytics', array( 'tag_id' => 'G-CODE1' ) );
			}
		);
		Config::reset();

		$_GET['silvertrail-turn-on'] = 'youtube';
		$this->assertSame( 'youtube', SettingsPage::turning_on() );
		$_GET['silvertrail-turn-on'] = 'google-analytics';
		$this->assertSame( '', SettingsPage::turning_on() );
		$_GET['silvertrail-turn-on'] = 'hubspot';
		$this->assertSame( '', SettingsPage::turning_on() );
	}

	public function test_the_theme_own_scheme_is_checked_by_default(): void {
		$html = $this->banner_tab( 'twentytwentyfive' );

		$this->assertStringNotContainsString( 'value="theme"', $html );
		$this->assertMatchesRegularExpression( '/value="light"\s+checked/', $html );
		$this->assertStringContainsString( 'As the theme sets it', $html );
		$this->assertStringContainsString( '<span class="silvertrail-source__layers">theme.json</span>', $html );
		$this->assertStringContainsString( 'site-editor.php?path=/wp_global_styles', $html );
		$this->assertStringContainsString( '<span style="background:#', $html, 'The design shows the theme palette.' );
	}

	public function test_a_design_in_code_with_one_look_leaves_nothing_to_choose(): void {
		add_filter(
			'silvertrail_design',
			static fn( array $design ): array => array(
				'background' => '#000000',
				'text'       => '#ffffff',
				'accent'     => '#ffcc00',
				'on-accent'  => '#000000',
			) + $design
		);

		$html = $this->banner_tab( 'twentytwentyfive' );
		remove_all_filters( 'silvertrail_design' );

		$this->assertStringNotContainsString( 'value="dark"', $html );
		$this->assertStringContainsString( 'nothing to choose', $html );
		$this->assertStringContainsString( '+ silvertrail_design', $html );
		$this->assertStringNotContainsString( '<span style="background:#', $html, 'The design shows the colors from code.' );
		$this->assertStringContainsString( 'silvertrail-swatch__dot--accent', $html );
	}

	public function test_a_classic_theme_offers_light_and_dark_only(): void {
		$html = $this->banner_tab( 'twentytwentyone' );

		$this->assertStringNotContainsString( 'value="theme"', $html );
		$this->assertMatchesRegularExpression( '/value="light"\s+checked/', $html );
		$this->assertStringContainsString( 'Twenty Twenty-One has no <code>theme.json</code>', $html );
		$this->assertStringNotContainsString( 'site-editor.php', $html );
	}

	public function test_the_license_notice_shows_until_dismissed(): void {
		$this->assertStringContainsString( SILVERTRAIL_LICENSE_URL, $this->notices( 'services' ) );
		$this->assertStringNotContainsString( SILVERTRAIL_LICENSE_URL, $this->notices( 'tools' ) );

		update_option( SettingsPage::LICENSE_SEEN, true );

		$this->assertStringNotContainsString( SILVERTRAIL_LICENSE_URL, $this->notices( 'services' ) );
	}

	private function notices( string $tab ): string {
		ob_start();
		require dirname( SILVERTRAIL_FILE ) . '/includes/Admin/notices.php';
		return (string) ob_get_clean();
	}

	private function banner_tab( string $theme ): string {
		switch_theme( $theme );
		wp_clean_theme_json_cache();
		Design::reset();
		ob_start();
		require dirname( SILVERTRAIL_FILE ) . '/includes/Admin/tabs/banner.php';
		$html = (string) ob_get_clean();
		switch_theme( WP_DEFAULT_THEME );
		wp_clean_theme_json_cache();
		Design::reset();
		return $html;
	}
}
