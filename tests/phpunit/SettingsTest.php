<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Settings;
use WP_UnitTestCase;

final class SettingsTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'silvertrail_settings' );
		delete_option( Settings::OPTION );
		Settings::reset();
		parent::tear_down();
	}

	public function test_code_wins_over_the_admin(): void {
		update_option( Settings::OPTION, array( 'layout' => 'modal' ) );
		$this->set( array( 'layout' => 'box' ) );

		$this->assertSame( 'box', Settings::get( 'layout' ) );
		$this->assertSame( 'modal', Settings::stored()['layout'] );
		$this->assertTrue( Settings::in_code( 'layout' ) );
		$this->assertFalse( Settings::in_code( 'records' ) );
	}

	public function test_code_that_matches_the_admin_still_counts_as_code(): void {
		update_option( Settings::OPTION, array( 'layout' => 'box' ) );
		$this->set( array( 'layout' => 'box' ) );

		$this->assertTrue( Settings::in_code( 'layout' ) );
	}

	public function test_only_the_centred_banner_requires_a_choice_unless_set(): void {
		$this->assertTrue( Settings::get( 'require_choice' ) );

		foreach ( array( 'box' ) as $layout ) {
			update_option( Settings::OPTION, array( 'layout' => $layout ) );
			$this->assertSame( $layout, Settings::get( 'layout' ) );
			$this->assertFalse( Settings::get( 'require_choice' ), $layout );
		}

		update_option( Settings::OPTION, array( 'layout' => 'modal' ) );
		$this->set( array( 'layout' => 'box' ) );
		$this->assertFalse( Settings::get( 'require_choice' ), 'The position set in code decides the default.' );

		update_option( Settings::OPTION, array( 'require_choice' => true ) );
		$this->assertTrue( Settings::get( 'require_choice' ), 'A box can still require a choice.' );
	}

	public function test_invalid_values_are_corrected_and_reported(): void {
		$this->setExpectedIncorrectUsage( 'silvertrail_settings' );
		$this->set(
			array(
				'expiry_months'   => 24,
				'layout'          => 'sidebar',
				'records'         => 1,
				'floating_button' => true,
			)
		);

		$this->assertSame( 13, Settings::get( 'expiry_months' ) );
		$this->assertSame( 'modal', Settings::get( 'layout' ) );
		$this->assertTrue( Settings::get( 'records' ) );
		$this->assertSame( 'always', Settings::get( 'floating_button' ) );
	}

	public function test_unknown_keys_presets_and_texts_are_refused(): void {
		$this->setExpectedIncorrectUsage( 'silvertrail_settings' );
		$this->set(
			array(
				'colour'  => 'red',
				'presets' => array( 'youtube' => array( 'enabled' => true ) ),
			)
		);

		$all = Settings::all();
		$this->assertArrayNotHasKey( 'colour', $all );
		$this->assertSame( array(), $all['presets'] );
		$this->assertFalse( Settings::in_code( 'presets' ) );
	}

	/**
	 * @param array<string, mixed> $values
	 */
	private function set( array $values ): void {
		add_filter( 'silvertrail_settings', static fn( array $settings ): array => array_replace( $settings, $values ) );
	}
}
