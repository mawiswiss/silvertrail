<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail\Admin;

use Silvertrail\Design;
use Silvertrail\Frontend;
use Silvertrail\Settings;

final class StylingPrompt {

	public static function text(): string {
		return self::read( 'styling.md', '## Brief for the AI' ) . "\n\n" . self::site() . "\n\n## What I want\n\n";
	}

	private static function site(): string {
		$settings = Settings::all();
		$lines    = array(
			'## This site',
			'',
			'- Theme: ' . wp_get_theme()->get( 'Name' ) . ( wp_theme_has_theme_json() ? ', with theme.json' : ', without theme.json' ),
			'- Position: ' . ( 'box' === $settings['layout'] ? 'box in the corner' : 'centred' ) . ', appearance: ' . Frontend::appearance(),
		);

		$tokens = array();
		foreach ( Design::tokens() as $key => $value ) {
			$tokens[] = "--silvertrail-{$key}: {$value}";
		}
		if ( $settings['load_css'] && array() !== $tokens ) {
			$lines[] = '- Tokens from the theme: `' . implode( '; ', $tokens ) . '`';
		}
		if ( has_filter( 'silvertrail_design' ) ) {
			$lines[] = '- PHP sets some tokens through the silvertrail_design filter.';
		}
		$palette = self::presets( array( 'color', 'palette' ), 'color' );
		if ( '' !== $palette ) {
			$lines[] = "- Colour presets: {$palette}";
		}
		$fonts = self::presets( array( 'typography', 'fontFamilies' ), 'fontFamily' );
		if ( '' !== $fonts ) {
			$lines[] = "- Font presets: {$fonts}";
		}

		$file    = get_theme_file_path( Frontend::THEME_CSS );
		$current = is_file( $file ) ? trim( (string) file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$lines[] = '' === $current ? '- The theme has no ' . Frontend::THEME_CSS . ' yet.' : "- The theme's current file:\n\n```css\n{$current}\n```";

		if ( ! $settings['load_css'] ) {
			$lines[] = "- The default styles are off, so your file is the whole stylesheet. Start from the defaults:\n\n```css\n" . self::read( 'assets/silvertrail.css' ) . "\n```";
		}

		return implode( "\n", $lines );
	}

	private static function read( string $file, string $from = '' ): string {
		$text  = (string) file_get_contents( dirname( SILVERTRAIL_FILE ) . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$start = '' === $from ? 0 : strpos( $text, $from );
		return false === $start ? '' : trim( substr( $text, $start ) );
	}

	/**
	 * @param array<string> $path
	 */
	private static function presets( array $path, string $value ): string {
		$sets  = wp_get_global_settings( $path );
		$items = array();
		foreach ( array( 'theme', 'custom' ) as $origin ) {
			foreach ( is_array( $sets[ $origin ] ?? null ) ? $sets[ $origin ] : array() as $preset ) {
				if ( is_array( $preset ) && is_string( $preset['slug'] ?? null ) && is_string( $preset[ $value ] ?? null ) ) {
					$items[] = "`{$preset['slug']}` {$preset[ $value ]}";
				}
			}
		}
		return implode( ', ', $items );
	}
}
