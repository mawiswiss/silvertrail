<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Admin\Notices;
use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Design;
use Silvertrail\Presets;
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
		unset( $_GET['silvertrail-turn-on'], $_GET['settings-updated'] );
		remove_all_actions( 'silvertrail_register' );
		Config::reset();
		remove_all_filters( 'silvertrail_translation' );
		remove_all_filters( 'silvertrail_settings' );
		Settings::reset();
		delete_option( Settings::OPTION );
		delete_option( SettingsPage::LICENSE_SEEN );
		Notices::reset();
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

		$this->assertArrayNotHasKey( 'appearance', SettingsPage::sanitize( array( '_tab' => 'banner', 'appearance' => 'light' ) ), 'Following the theme is the default.' );
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

	public function test_without_a_tab_a_missing_setting_falls_back_to_its_default(): void {
		update_option( Settings::OPTION, array_replace( Settings::defaults(), array( 'records' => true ) ) );

		$saved = SettingsPage::sanitize( array( 'layout' => 'box' ) );

		$this->assertSame( 'box', $saved['layout'] );
		$this->assertArrayNotHasKey( 'records', $saved, 'A missing setting falls back to its default.' );
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

	public function test_after_a_save_the_leak_check_link_ticks_nothing(): void {
		$_GET['silvertrail-turn-on'] = 'vimeo';
		$_GET['settings-updated']    = 'true';

		$this->assertSame( '', SettingsPage::turning_on() );
	}

	public function test_the_theme_own_scheme_is_checked_by_default(): void {
		if ( ! wp_get_theme( 'twentytwentyfive' )->exists() ) {
			$this->markTestSkipped( 'Twenty Twenty-Five ships with WordPress 6.7.' );
		}
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

	public function test_the_license_line_shows_on_every_tab_until_dismissed_then_in_the_footer(): void {
		$this->assertStringContainsString( SILVERTRAIL_LICENSE_URL, $this->notices( 'services' ) );
		$this->assertStringContainsString( SILVERTRAIL_LICENSE_URL, $this->notices( 'tools' ) );
		$this->assertSame( 'Thank you', SettingsPage::footer( 'Thank you' ) );

		update_option( SettingsPage::LICENSE_SEEN, true );

		$this->assertStringNotContainsString( SILVERTRAIL_LICENSE_URL, $this->notices( 'services' ) );
		$this->assertStringContainsString( SILVERTRAIL_LICENSE_URL, SettingsPage::footer( 'Thank you' ) );
	}

	public function test_the_overview_links_each_warning_to_its_card(): void {
		update_option( 'wp_page_for_privacy_policy', 0 );

		$this->assertSame(
			array(
				'type'    => 'warning',
				'summary' => 'No privacy policy page is published.',
				'url'     => SettingsPage::url( 'banner' ) . '#silvertrail-links',
				'where'   => 'Banner › Links',
			),
			Notices::overview( 'services' )[0]
		);
		$this->assertSame( '#silvertrail-links', Notices::overview( 'banner' )[0]['url'] );
		$this->assertStringContainsString( 'options-privacy.php', $this->card( 'links' ) );
	}

	public function test_a_texts_error_moves_from_the_top_into_its_card(): void {
		add_settings_error( 'general', 'core', 'Core message' );
		add_settings_error( Settings::OPTION, 'silvertrail-texts', 'Not saved: one' );
		add_settings_error( Settings::OPTION, 'silvertrail-texts', 'Not saved: two' );

		Notices::take_errors();

		$this->assertSame( array( 'core' ), array_column( get_settings_errors(), 'code' ) );
		$this->assertStringContainsString( 'Not saved: two', $this->card( 'texts' ) );
		$this->assertCount( 1, array_keys( array_column( Notices::overview( 'services' ), 'summary' ), 'Some texts were not saved.', true ) );
	}

	public function test_a_tracker_without_a_valid_id_stays_in_the_overview(): void {
		$stored                                 = Settings::defaults();
		$stored['presets']['google-analytics'] = array(
			'enabled' => true,
			'tag_id'  => 'UA-1',
		);
		update_option( Settings::OPTION, $stored );

		$this->assertSame( array(), get_settings_errors( Settings::OPTION ) );
		$this->assertContains( 'Google Analytics needs a valid ID.', array_column( Notices::overview( 'banner' ), 'summary' ) );
		$this->assertStringContainsString( 'G-XXXXXXXXXX', $this->card( 'trackers' ) );

		$stored['presets']['google-analytics']['tag_id'] = 'G-ABC123';
		update_option( Settings::OPTION, $stored );
		Settings::reset();
		Config::reset();

		$this->assertSame( '', $this->card( 'trackers' ) );
	}

	public function test_the_floating_button_cards_follow_the_settings(): void {
		$this->assertSame( 'never', $this->floating_choice() );

		update_option(
			Settings::OPTION,
			array(
				'floating_button'   => 'auto',
				'floating_position' => 'right',
			)
		);
		$this->assertSame( 'right', $this->floating_choice() );

		add_filter( 'silvertrail_settings', static fn( array $settings ): array => array( 'floating_button' => 'always' ) + $settings );
		$this->assertMatchesRegularExpression( '/name="silvertrail\[floating\]" value="never"[^>]*disabled/', $this->banner_tab( 'twentytwentyfive' ) );
	}

	public function test_the_banner_tab_saves_the_floating_button(): void {
		$saved = SettingsPage::sanitize(
			array(
				'_tab'     => 'banner',
				'floating' => 'right',
			)
		);
		$this->assertSame( 'auto', $saved['floating_button'] );
		$this->assertSame( 'right', $saved['floating_position'] );

		update_option( Settings::OPTION, $saved );
		$saved = SettingsPage::sanitize(
			array(
				'_tab'     => 'banner',
				'floating' => 'never',
			)
		);
		$this->assertArrayNotHasKey( 'floating_button', $saved );
		$this->assertArrayNotHasKey( 'floating_position', $saved );
	}

	public function test_switching_off_a_new_site_leaves_the_presets_at_their_defaults(): void {
		SettingsPage::setting();
		delete_option( Settings::OPTION );

		update_option(
			Settings::OPTION,
			array(
				'_tab'    => 'switch',
				'enabled' => false,
			)
		);

		$this->assertSame( array( 'enabled' => false ), get_option( Settings::OPTION ) );
		$this->assertTrue( Presets::switched_on( 'youtube' ) );
	}

	public function test_a_save_keeps_only_what_differs_from_the_defaults(): void {
		update_option( Settings::OPTION, Settings::defaults() + array( 'expiry_months' => 6 ) );

		$saved = SettingsPage::sanitize(
			array(
				'_tab'           => 'banner',
				'layout'         => 'box',
				'require_choice' => '1',
			)
		);

		$this->assertSame( array( 'layout' => 'box' ), $saved );
	}

	public function test_the_switch_changes_only_whether_silvertrail_is_on(): void {
		update_option( Settings::OPTION, array_replace( Settings::defaults(), array( 'layout' => 'box' ) ) );

		$saved = SettingsPage::sanitize(
			array(
				'_tab'    => 'switch',
				'enabled' => false,
				'layout'  => 'modal',
			)
		);

		$this->assertFalse( $saved['enabled'] );
		$this->assertSame( 'box', $saved['layout'] );
	}

	public function test_switched_off_the_status_offers_a_preview(): void {
		update_option( Settings::OPTION, array( 'enabled' => false ) );

		$html = $this->notices( 'banner' );

		$this->assertStringContainsString( 'Silvertrail is off', $html );
		$this->assertStringContainsString( '?silvertrail-preview=1', $html );
		$this->assertStringContainsString( 'aria-pressed="false"', $html );
	}

	private function notices( string $tab ): string {
		ob_start();
		require dirname( SILVERTRAIL_FILE ) . '/includes/Admin/status.php';
		return (string) ob_get_clean();
	}

	private function card( string $card ): string {
		ob_start();
		Notices::card( $card );
		return (string) ob_get_clean();
	}

	private function floating_choice(): string {
		preg_match( '/name="silvertrail\[floating\]" value="(\w+)"\s+checked/', $this->banner_tab( 'twentytwentyfive' ), $match );
		return $match[1] ?? '';
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
