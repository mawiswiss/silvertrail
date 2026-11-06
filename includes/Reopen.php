<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * Ways back to the preferences: the shortcode, the block and the floating button.
 */
final class Reopen {

	public static function register(): void {
		add_shortcode( 'silvertrail_link', array( self::class, 'shortcode' ) );
		add_action( 'init', array( self::class, 'block' ) );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public static function shortcode( array|string $atts, ?string $content = null ): string {
		return self::link( (string) $content );
	}

	public static function link( string $text, string $attributes = '' ): string {
		if ( ! Plugin::live() ) {
			return '';
		}
		$text = '' !== trim( $text ) ? $text : Texts::all()['reopen'];
		return sprintf( '<a href="#silvertrail" data-silvertrail-open%s>%s</a>', $attributes, esc_html( $text ) );
	}

	public static function block(): void {
		register_block_type( dirname( SILVERTRAIL_FILE ) . '/blocks/reopen-link' );
	}

	public static function button(): void {
		$mode = Settings::get( 'floating_button' );
		if ( 'never' === $mode || array() === Config::consent_ids() ) {
			return;
		}
		Templates::render(
			'reopen-button',
			array(
				'label'      => Texts::all()['reopen'],
				'appearance' => Frontend::appearance(),
				'mode'       => (string) $mode,
			)
		);
	}
}
