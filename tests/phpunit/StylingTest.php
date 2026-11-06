<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Admin\StylingPrompt;
use Silvertrail\Design;
use Silvertrail\Frontend;
use Silvertrail\Settings;
use WP_UnitTestCase;

final class StylingTest extends WP_UnitTestCase {

	private string $css = '';

	public function set_up(): void {
		parent::set_up();
		$GLOBALS['wp_styles'] = null;
	}

	public function tear_down(): void {
		remove_all_filters( 'theme_file_path' );
		remove_all_filters( 'theme_file_uri' );
		delete_option( Settings::OPTION );
		if ( '' !== $this->css ) {
			wp_delete_file( $this->css );
		}
		$GLOBALS['wp_styles'] = null;
		parent::tear_down();
	}

	public function test_the_guide_names_every_class_and_token(): void {
		$root  = dirname( SILVERTRAIL_FILE );
		$guide = (string) file_get_contents( $root . '/styling.md' );

		$markup = implode( '', array_map( 'file_get_contents', array_merge( glob( $root . '/templates/*.php' ) ?: array(), glob( $root . '/blocks/*/render.php' ) ?: array() ) ) );
		preg_match_all( '/class="([^"]*)"/', $markup, $attributes );
		$classes = preg_grep( '/^silvertrail[\w-]*[a-z]$/', preg_split( '/\s+/', implode( ' ', $attributes[1] ) ) ?: array() ) ?: array();
		$classes = array_merge( $classes, array( 'silvertrail--modal', 'silvertrail--box' ) );

		preg_match_all( '/--silvertrail-[a-z-]+/', (string) file_get_contents( $root . '/assets/silvertrail.css' ), $properties );
		$tokens = array_map( static fn( string $key ): string => "--silvertrail-{$key}", Design::TOKENS );

		foreach ( array_unique( $classes ) as $class ) {
			$this->assertStringContainsString( $class, $guide, "styling.md shows the class {$class}." );
		}
		foreach ( array_unique( array_merge( $properties[0], $tokens ) ) as $property ) {
			$this->assertStringContainsString( "`{$property}`", $guide, "styling.md lists the token {$property}." );
		}
	}

	public function test_the_prompt_covers_the_floating_button_only_when_it_is_on(): void {
		$this->assertStringContainsString( '- Floating button: off', StylingPrompt::text() );

		update_option(
			Settings::OPTION,
			array(
				'floating_button'   => 'auto',
				'floating_position' => 'right',
			)
		);

		$this->assertStringContainsString( '- Floating button: on, in the bottom right corner (`.silvertrail-reopen--right`), on pages without a link', StylingPrompt::text() );
	}

	public function test_the_theme_stylesheet_loads_after_the_default_styles(): void {
		$this->theme_css( '.silvertrail { box-shadow: none; }' );

		Frontend::enqueue();

		$this->assertTrue( wp_style_is( 'silvertrail-theme' ) );
		$this->assertSame( array( 'silvertrail' ), wp_styles()->registered['silvertrail-theme']->deps );
	}

	public function test_without_the_default_styles_the_theme_stylesheet_stands_alone(): void {
		update_option( Settings::OPTION, array( 'load_css' => false ) );
		$this->theme_css( '.silvertrail { color: red; }' );

		Frontend::enqueue();

		$this->assertFalse( wp_style_is( 'silvertrail' ) );
		$this->assertTrue( wp_style_is( 'silvertrail-theme' ) );
		$this->assertSame( array(), wp_styles()->registered['silvertrail-theme']->deps );
	}

	public function test_no_theme_stylesheet_loads_nothing_extra(): void {
		Frontend::enqueue();

		$this->assertFalse( wp_style_is( 'silvertrail-theme', 'registered' ) );
	}

	public function test_the_prompt_holds_the_brief_and_the_site(): void {
		$this->theme_css( '.silvertrail { --silvertrail-radius: 2rem; }' );

		$prompt = StylingPrompt::text();

		$this->assertStringStartsWith( '## Brief for the AI', $prompt );
		$this->assertStringContainsString( '## This site', $prompt );
		$this->assertStringContainsString( "```css\n.silvertrail { --silvertrail-radius: 2rem; }\n```", $prompt );
		$this->assertStringNotContainsString( '.silvertrail__button {', $prompt, 'The defaults stay out while they load.' );
		$this->assertStringEndsWith( "## What I want\n\n", $prompt );
	}

	public function test_the_prompt_says_when_the_default_styles_are_off(): void {
		update_option( Settings::OPTION, array( 'load_css' => false ) );

		$prompt = StylingPrompt::text();

		$this->assertStringContainsString( 'the whole stylesheet', $prompt );
		$this->assertStringContainsString( '.silvertrail__button {', $prompt );
		$this->assertStringContainsString( 'has no silvertrail/silvertrail.css yet', $prompt );
	}

	private function theme_css( string $css ): void {
		$this->css = (string) wp_tempnam( 'silvertrail.css' );
		file_put_contents( $this->css, $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$path = $this->css;
		add_filter( 'theme_file_path', static fn( string $file, string $name ): string => Frontend::THEME_CSS === $name ? $path : $file, 10, 2 );
		add_filter( 'theme_file_uri', static fn( string $url, string $name ): string => Frontend::THEME_CSS === $name ? 'https://example.test/silvertrail.css' : $url, 10, 2 );
	}
}
