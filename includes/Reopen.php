<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * Ways back to the preferences: the shortcode, the block and the floating button.
 */
final class Reopen {

	public const ICON = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-4.6-4.2A4 4 0 0 1 12 2Zm-3.5 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm7 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-6 2a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>';

	private const SVG_TAGS = array( 'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon' );

	private const SVG_ATTRIBUTES = array( 'xmlns', 'viewbox', 'width', 'height', 'class', 'aria-hidden', 'focusable', 'fill', 'fill-rule', 'clip-rule', 'opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'transform', 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'points' );

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
				'position'   => (string) Settings::get( 'floating_position' ),
				'icon'       => self::icon(),
			)
		);
	}

	/**
	 * The icon inside the floating button, as inline SVG or an image. Other markup is stripped.
	 */
	public static function icon(): string {
		/**
		 * Replaces the icon of the floating button. The button keeps its label for screen readers.
		 *
		 * @param string $icon Inline SVG or an <img>.
		 */
		$icon = (string) apply_filters( 'silvertrail_reopen_icon', self::ICON );
		return wp_kses(
			$icon,
			array_fill_keys( self::SVG_TAGS, array_fill_keys( self::SVG_ATTRIBUTES, true ) )
				+ array( 'img' => array_fill_keys( array( 'src', 'alt', 'width', 'height', 'class' ), true ) )
		);
	}
}
