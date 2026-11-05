<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Settings;
use WP_UnitTestCase;

final class SettingsPageTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_saving_one_tab_keeps_the_other_tabs(): void {
		$stored                                  = Settings::defaults();
		$stored['presets']['youtube']['enabled'] = true;
		$stored['presets']['youtube']['tag_id']  = '';
		$stored['records']                       = true;
		$stored['leak_check']                    = true;
		update_option( Settings::OPTION, $stored );

		$saved = SettingsPage::sanitize(
			array(
				'_tab'   => 'banner',
				'layout' => 'box',
			)
		);

		$this->assertSame( 'box', $saved['layout'] );
		$this->assertFalse( $saved['load_css'], 'An unchecked box on the saved tab turns off.' );
		$this->assertTrue( $saved['presets']['youtube']['enabled'] );
		$this->assertTrue( $saved['records'] );
		$this->assertTrue( $saved['leak_check'] );
		$this->assertArrayNotHasKey( '_tab', $saved );
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
}
