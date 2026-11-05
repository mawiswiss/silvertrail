<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

final class Design {

	public const TOKENS = array( 'background', 'text', 'muted', 'line', 'accent', 'on-accent', 'radius', 'button-padding', 'button-weight' );

	/**
	 * @return array<string, string>
	 */
	public static function tokens(): array {
		return self::resolve()[0];
	}

	public static function css(): string {
		list( $tokens, $scheme ) = self::resolve();
		$rules                   = array();
		foreach ( $tokens as $key => $value ) {
			$rules[] = "--silvertrail-{$key}:{$value}";
		}
		if ( '' !== $scheme ) {
			$rules[] = "color-scheme:{$scheme}";
		}
		return array() === $rules ? '' : '.silvertrail,.silvertrail-embed,.silvertrail-reopen{' . implode( ';', $rules ) . '}';
	}

	/**
	 * @return array{0: array<string, string>, 1: string}
	 */
	private static function resolve(): array {
		$mode                      = Frontend::appearance();
		list( $tokens, $resolved ) = self::theme( $mode );
		$filtered                  = apply_filters( 'silvertrail_design', $tokens, $mode );
		$filtered                  = self::valid( is_array( $filtered ) ? $filtered : array() );
		$changed                   = static fn( string $key ): bool => ( $filtered[ $key ] ?? '' ) !== ( $tokens[ $key ] ?? '' );

		$accent = self::luminance( $filtered['accent'] ?? '' );
		if ( $changed( 'accent' ) && ! $changed( 'on-accent' ) && null !== $accent ) {
			$filtered['on-accent'] = $accent > 0.179 ? '#111111' : '#ffffff';
		}
		if ( ( $changed( 'text' ) || $changed( 'background' ) ) && ! $changed( 'muted' ) ) {
			unset( $filtered['muted'] );
		}

		$background = self::luminance( $filtered['background'] ?? '' );
		if ( null === $background && ! $changed( 'background' ) ) {
			$background = self::luminance( $resolved['background'] ?? '' );
		}
		if ( null === $background ) {
			$scheme = 'theme' === $mode ? '' : $mode;
		} else {
			$scheme = $background > 0.179 ? 'light' : 'dark';
		}
		return array( $filtered, $scheme );
	}

	/**
	 * @return array{0: array<string, string>, 1: array<string, string>}
	 */
	private static function theme( string $mode ): array {
		if ( ! wp_theme_has_theme_json() ) {
			return array( array(), array() );
		}
		$styles   = wp_get_global_styles();
		$resolved = wp_get_global_styles( array(), array( 'transforms' => array( 'resolve-variables' ) ) );
		$colors   = static fn( array $from ): array => array(
			'background' => self::at( $from, 'color', 'background' ),
			'text'       => self::at( $from, 'color', 'text' ),
			'accent'     => self::at( $from, 'elements', 'button', 'color', 'background' ),
			'on-accent'  => self::at( $from, 'elements', 'button', 'color', 'text' ),
		);
		$tokens   = $colors( $styles );
		$values   = $colors( $resolved );

		$weight = self::at( $styles, 'elements', 'button', 'typography', 'fontWeight' );
		if ( in_array( $weight, array( '', 'inherit' ), true ) ) {
			$weight = self::at( $styles, 'typography', 'fontWeight' );
		}
		$tokens += array(
			'radius'         => self::sides( $styles['elements']['button']['border']['radius'] ?? '0', array( 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ) ),
			'button-padding' => self::sides( $styles['elements']['button']['spacing']['padding'] ?? '', array( 'top', 'right', 'bottom', 'left' ) ),
			'button-weight'  => $weight,
		);

		if ( 'theme' !== $mode ) {
			$lightest = 'background';
			$darkest  = 'text';
			$known    = array_filter( array_map( array( self::class, 'luminance' ), $values ), 'is_float' );
			if ( isset( $known['background'], $known['text'] ) ) {
				arsort( $known );
				$lightest = array_key_first( $known ) ?? $lightest;
				asort( $known );
				$darkest = array_key_first( $known ) ?? $darkest;
			}
			$behind = 'dark' === $mode ? $darkest : $lightest;
			$front  = 'dark' === $mode ? $lightest : $darkest;
			$tokens = array(
				'background' => $tokens[ $behind ],
				'text'       => $tokens[ $front ],
			) + $tokens;
			$values = array(
				'background' => $values[ $behind ],
				'text'       => $values[ $front ],
			) + $values;

			$accent   = self::luminance( $values['accent'] );
			$backdrop = self::luminance( $values['background'] );
			$readable = null !== $accent && null !== $backdrop ? self::contrast( $accent, $backdrop ) >= 3 : 'background' === $behind;
			if ( ! $readable ) {
				$tokens = self::swap( $tokens, 'accent', 'on-accent' );
				$values = self::swap( $values, 'accent', 'on-accent' );
			}
		}

		$text = self::rgb( $values['text'] );
		$back = self::rgb( $values['background'] );
		if ( null !== $text && null !== $back ) {
			// The same 72% mix as --silvertrail-muted in silvertrail.css.
			$muted = array_map( static fn( float $t, float $b ): float => 0.72 * $t + 0.28 * $b, $text, $back );
			if ( self::contrast( self::relative( $muted ), self::relative( $back ) ) < 4.5 ) {
				$tokens['muted'] = $tokens['text'];
			}
		}
		return array( self::valid( $tokens ), $values );
	}

	/**
	 * Keeps the known tokens whose value cannot leave its declaration.
	 *
	 * @param array<mixed> $tokens
	 * @return array<string, string>
	 */
	private static function valid( array $tokens ): array {
		$valid = array();
		foreach ( self::TOKENS as $key ) {
			$value = is_scalar( $tokens[ $key ] ?? null ) ? trim( (string) $tokens[ $key ] ) : '';
			if ( '' !== $value && strlen( $value ) <= 200 && ! str_contains( $value, '/*' ) && preg_match( '/^[a-zA-Z0-9#%(),.\s+\-*\/]+$/', $value ) ) {
				$valid[ $key ] = $value;
			}
		}
		return $valid;
	}

	/**
	 * @param array<mixed> $styles
	 */
	private static function at( array $styles, string ...$path ): string {
		foreach ( $path as $key ) {
			if ( ! is_array( $styles ) || ! array_key_exists( $key, $styles ) ) {
				return '';
			}
			$styles = $styles[ $key ];
		}
		return is_string( $styles ) ? $styles : '';
	}

	/**
	 * @param array<string> $sides
	 */
	private static function sides( mixed $value, array $sides ): string {
		if ( ! is_array( $value ) ) {
			return is_string( $value ) ? $value : '';
		}
		return implode( ' ', array_map( static fn( string $side ): string => is_string( $value[ $side ] ?? null ) ? $value[ $side ] : '0', $sides ) );
	}

	/**
	 * @param array<string, string> $tokens
	 * @return array<string, string>
	 */
	private static function swap( array $tokens, string $a, string $b ): array {
		list( $tokens[ $a ], $tokens[ $b ] ) = array( $tokens[ $b ] ?? '', $tokens[ $a ] ?? '' );
		return $tokens;
	}

	/**
	 * @return array<float>|null
	 */
	private static function rgb( string $color ): ?array {
		if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim( $color ), $match ) ) {
			return null;
		}
		$hex = 3 === strlen( $match[1] ) ? preg_replace( '/(.)/', '$1$1', $match[1] ) : $match[1];
		return array_map( static fn( string $pair ): float => (float) hexdec( $pair ), str_split( (string) $hex, 2 ) );
	}

	private static function luminance( string $color ): ?float {
		$rgb = self::rgb( $color );
		return null === $rgb ? null : self::relative( $rgb );
	}

	/**
	 * @param array<float> $rgb
	 */
	private static function relative( array $rgb ): float {
		$linear = array_map(
			static function ( float $channel ): float {
				$value = $channel / 255;
				return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
			},
			array_values( $rgb )
		);
		return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
	}

	private static function contrast( float $a, float $b ): float {
		return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	}
}
