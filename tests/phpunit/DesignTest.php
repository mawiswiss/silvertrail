<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Design;
use Silvertrail\Settings;
use WP_UnitTestCase;

final class DesignTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->use_theme( 'twentytwentyfive' );
	}

	public function tear_down(): void {
		remove_all_filters( 'silvertrail_design' );
		delete_option( Settings::OPTION );
		$this->use_theme( WP_DEFAULT_THEME );
		parent::tear_down();
	}

	public function test_the_banner_takes_the_theme_design(): void {
		$this->assertSame(
			array(
				'background'     => 'var(--wp--preset--color--base)',
				'text'           => 'var(--wp--preset--color--contrast)',
				'accent'         => 'var(--wp--preset--color--contrast)',
				'on-accent'      => 'var(--wp--preset--color--base)',
				'radius'         => '0',
				'button-padding' => '1rem 2.25rem 1rem 2.25rem',
				'button-weight'  => '300',
			),
			Design::tokens()
		);
		$this->assertStringContainsString( 'color-scheme:light', Design::css() );
	}

	public function test_dark_puts_the_darker_theme_colour_behind_and_keeps_the_buttons_readable(): void {
		update_option( Settings::OPTION, array( 'appearance' => 'dark' ) );

		$tokens = Design::tokens();

		$this->assertSame( 'var(--wp--preset--color--contrast)', $tokens['background'] );
		$this->assertSame( 'var(--wp--preset--color--base)', $tokens['text'] );
		$this->assertSame( 'var(--wp--preset--color--base)', $tokens['accent'] );
		$this->assertSame( 'var(--wp--preset--color--contrast)', $tokens['on-accent'] );
		$this->assertStringContainsString( 'color-scheme:dark', Design::css() );
	}

	public function test_light_on_a_light_theme_changes_nothing(): void {
		$theme = Design::tokens();
		update_option( Settings::OPTION, array( 'appearance' => 'light' ) );

		$this->assertSame( $theme, Design::tokens() );
	}

	public function test_the_filter_wins_and_unsafe_values_are_dropped(): void {
		add_filter(
			'silvertrail_design',
			static fn( array $design ): array => array(
				'accent'     => '#cc0000',
				'background' => 'red;}</style><script>',
				'text'       => 'url("x")',
				'radius'     => '4px /* x */',
				'unknown'    => '#000000',
			) + $design
		);

		$tokens = Design::tokens();

		$this->assertSame( '#cc0000', $tokens['accent'] );
		$this->assertSame( '#ffffff', $tokens['on-accent'], 'A new hex accent gets readable text.' );
		$this->assertArrayNotHasKey( 'background', $tokens );
		$this->assertArrayNotHasKey( 'text', $tokens );
		$this->assertArrayNotHasKey( 'radius', $tokens );
		$this->assertArrayNotHasKey( 'unknown', $tokens );
		$this->assertStringNotContainsString( '<', Design::css() );
	}

	public function test_grey_theme_text_keeps_the_secondary_text_readable(): void {
		add_filter(
			'wp_theme_json_data_theme',
			static fn( $data ) => $data->update_with(
				array(
					'version' => 3,
					'styles'  => array(
						'color' => array(
							'background' => '#f8f7f5',
							'text'       => '#5f5f5f',
						),
					),
				)
			)
		);
		wp_clean_theme_json_cache();

		$this->assertSame( '#5f5f5f', Design::tokens()['muted'] );
		$this->assertArrayNotHasKey( 'muted', $this->tokens_without_grey_text() );
	}

	public function test_a_classic_theme_keeps_the_built_in_look(): void {
		$this->use_theme( 'twentytwentyone' );

		$this->assertSame( array(), Design::tokens() );
		$this->assertSame( '', Design::css() );
	}

	/**
	 * @return array<string, string>
	 */
	private function tokens_without_grey_text(): array {
		remove_all_filters( 'wp_theme_json_data_theme' );
		wp_clean_theme_json_cache();
		return Design::tokens();
	}

	private function use_theme( string $theme ): void {
		switch_theme( $theme );
		wp_clean_theme_json_cache();
	}
}
