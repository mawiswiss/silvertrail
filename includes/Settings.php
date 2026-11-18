<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type PresetSetting array{enabled: bool, tag_id: string}
 * @phpstan-type SettingsArray array{
 *     enabled: bool,
 *     layout: 'modal'|'box',
 *     require_choice: bool|null,
 *     appearance: 'theme'|'light'|'dark',
 *     texts: array<string, array<string, string>>,
 *     floating_button: 'auto'|'always'|'never',
 *     floating_position: 'left'|'right',
 *     load_css: bool,
 *     imprint_page: int,
 *     presets: array<string, PresetSetting>,
 *     expiry_months: int,
 *     records: bool,
 *     retention_days: int,
 *     gpc: bool,
 *     trail: bool,
 *     webmcp: bool,
 *     webmcp_tokens: list<string>
 * }
 */
final class Settings {

	public const OPTION = 'silvertrail';

	/**
	 * Settings with a code path of their own: presets through silvertrail_register_service(), texts through the theme's JSON.
	 */
	private const NOT_FILTERED = array( 'presets', 'texts' );

	/**
	 * @var array<string, true>
	 */
	private static array $reported = array();

	/**
	 * @return SettingsArray
	 */
	public static function defaults(): array {
		return array(
			'enabled'           => true,
			'layout'            => 'modal',
			'require_choice'    => null,
			'appearance'        => 'theme',
			'texts'             => array(),
			'floating_button'   => 'never',
			'floating_position' => 'left',
			'load_css'          => true,
			'imprint_page'      => 0,
			'presets'           => array(),
			'expiry_months'     => 6,
			'records'           => false,
			'retention_days'    => 365,
			'gpc'               => true,
			'trail'             => true,
			'webmcp'            => true,
			'webmcp_tokens'     => array(),
		);
	}

	/**
	 * What the admin saved, without code.
	 *
	 * @return SettingsArray
	 */
	public static function stored(): array {
		/** @var SettingsArray */
		return self::normalize( array_replace( self::defaults(), self::saved() ) );
	}

	/**
	 * Only what the admin changed. A setting left at its default follows a new default.
	 *
	 * @return array<string, mixed>
	 */
	public static function saved(): array {
		$saved = get_option( self::OPTION, array() );
		return array_intersect_key( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * @return SettingsArray
	 */
	public static function all(): array {
		$stored = self::stored();
		/** @var SettingsArray */
		$all                     = array_replace( $stored, self::filtered( $stored, true ) );
		$all['require_choice'] ??= self::requires_choice( $all['layout'] );
		return $all;
	}

	public static function requires_choice( string $layout ): bool {
		return 'modal' === $layout;
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Whether code sets the value, so the admin cannot change it.
	 */
	public static function in_code( string $key ): bool {
		if ( in_array( $key, self::NOT_FILTERED, true ) || ! has_filter( 'silvertrail_settings' ) ) {
			return false;
		}
		$stored   = self::stored();
		$defaults = self::defaults();
		return ( self::filtered( $stored )[ $key ] ?? null ) !== ( $stored[ $key ] ?? null )
			|| ( self::filtered( $defaults )[ $key ] ?? null ) !== ( $defaults[ $key ] ?? null );
	}

	/**
	 * @param array<mixed> $values
	 * @return array<string, mixed>
	 */
	public static function normalize( array $values ): array {
		$defaults = self::defaults();
		$clean    = array();
		foreach ( $values as $key => $value ) {
			$clean[ $key ] = match ( $key ) {
				'layout'            => 'box' === $value ? 'box' : 'modal',
				'appearance'        => in_array( $value, array( 'light', 'dark' ), true ) ? $value : 'theme',
				'imprint_page'      => absint( $value ),
				'expiry_months'     => self::clamp( $value, 1, 13, $defaults['expiry_months'] ),
				'retention_days'    => self::clamp( $value, 30, 3650, $defaults['retention_days'] ),
				'floating_button'   => self::floating_button( $value ),
				'floating_position' => 'right' === $value ? 'right' : 'left',
				'webmcp_tokens'     => self::tokens( $value ),
				'require_choice'    => null === $value ? null : (bool) $value,
				'enabled', 'load_css', 'records', 'gpc', 'trail', 'webmcp' => (bool) $value,
				default             => $value,
			};
		}
		return $clean;
	}

	/**
	 * @param SettingsArray $values
	 * @return array<string, mixed>
	 */
	private static function filtered( array $values, bool $report = false ): array {
		$input = array_diff_key( $values, array_flip( self::NOT_FILTERED ) );
		if ( ! has_filter( 'silvertrail_settings' ) ) {
			return $input;
		}

		/**
		 * Sets any admin setting in code. The admin then shows it as set in code.
		 *
		 * @param array<string, mixed> $settings Every setting except presets and texts.
		 */
		$filtered = apply_filters( 'silvertrail_settings', $input );
		$filtered = is_array( $filtered ) ? $filtered : array();
		$known    = array_intersect_key( $filtered, $input );
		$clean    = self::normalize( $known );

		if ( $report ) {
			foreach ( array_diff_key( $filtered, $input ) as $key => $value ) {
				/* translators: %s: setting key */
				self::wrong( (string) $key, sprintf( __( 'The silvertrail_settings filter cannot set "%s".', 'silvertrail' ), $key ) );
			}
			foreach ( $known as $key => $value ) {
				if ( $clean[ $key ] !== $value && ! ( is_scalar( $value ) && (string) $clean[ $key ] === (string) $value ) ) {
					/* translators: 1: setting key, 2: the value used instead */
					self::wrong( (string) $key, sprintf( __( 'The silvertrail_settings filter returned an invalid "%1$s", so Silvertrail uses %2$s.', 'silvertrail' ), $key, (string) wp_json_encode( $clean[ $key ] ) ) );
				}
			}
		}
		return $clean;
	}

	private static function floating_button( mixed $value ): string {
		if ( true === $value ) {
			return 'always';
		}
		return in_array( $value, array( 'always', 'never' ), true ) ? $value : 'auto';
	}

	/**
	 * @return list<string>
	 */
	private static function tokens( mixed $value ): array {
		$tokens = array();
		foreach ( is_array( $value ) ? $value : array( $value ) as $token ) {
			if ( is_string( $token ) && '' !== trim( $token ) ) {
				$tokens[] = trim( $token );
			}
		}
		return array_values( array_unique( $tokens ) );
	}

	private static function clamp( mixed $value, int $min, int $max, int $fallback ): int {
		return is_numeric( $value ) ? max( $min, min( $max, (int) $value ) ) : $fallback;
	}

	private static function wrong( string $key, string $message ): void {
		if ( isset( self::$reported[ $key ] ) ) {
			return;
		}
		self::$reported[ $key ] = true;
		_doing_it_wrong( 'silvertrail_settings', esc_html( $message ), '1.0.0' );
	}

	public static function reset(): void {
		self::$reported = array();
	}
}
