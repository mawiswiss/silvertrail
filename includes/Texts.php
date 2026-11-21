<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * Texts stack up from the English source with its official translation, through the
 * developer translation and the admin's edits, to the silvertrail_texts filter.
 */
final class Texts {

	private const PLACEHOLDER = '/%(?:\d+\$)?s/';

	/**
	 * @var array<string, array<mixed>|null>
	 */
	private static array $files = array();

	/**
	 * @var array<string, true>
	 */
	private static array $reported = array();

	/**
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'title'             => __( 'Privacy settings', 'silvertrail' ),
			'intro'             => __( 'We use cookies and similar technologies. Some are needed for this site to work. Everything else only runs if you agree.', 'silvertrail' ),
			'accept_all'        => __( 'Accept all', 'silvertrail' ),
			'reject_all'        => __( 'Only essential', 'silvertrail' ),
			'preferences'       => __( 'Choose', 'silvertrail' ),
			'preferences_intro' => __( 'Choose what may run on this site. You can change it at any time.', 'silvertrail' ),
			'save'              => __( 'Save choices', 'silvertrail' ),
			'close'             => __( 'Close', 'silvertrail' ),
			'provider'          => __( 'Provider', 'silvertrail' ),
			'purpose'           => __( 'Purpose', 'silvertrail' ),
			'cookies'           => __( 'Cookies', 'silvertrail' ),
			'expiry'            => __( 'Lifetime', 'silvertrail' ),
			'privacy_url'       => __( 'Privacy policy', 'silvertrail' ),
			'privacy_link'      => __( 'Privacy policy', 'silvertrail' ),
			'imprint_link'      => __( 'Imprint', 'silvertrail' ),
			/* translators: %s: service name */
			'embed_text'        => __( 'This content comes from %s. Loading it tells them about your visit.', 'silvertrail' ),
			'embed_load'        => __( 'Load content', 'silvertrail' ),
			/* translators: %s: service name */
			'embed_remember'    => __( 'Always allow %s', 'silvertrail' ),
			'reopen'            => __( 'Privacy settings', 'silvertrail' ),
			'gpc'               => __( 'Your browser sends Global Privacy Control, so only essential services run unless you allow more here.', 'silvertrail' ),
			'trail_title'       => __( 'Loaded on this page', 'silvertrail' ),
			'trail_held'        => __( 'Held back on this page', 'silvertrail' ),
			'trail_unknown'     => __( 'not listed here', 'silvertrail' ),
			'trail_none'        => __( 'Nothing from other sites.', 'silvertrail' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function all(): array {
		$locale = determine_locale();
		$texts  = array_replace( self::base( $locale ), self::custom( $locale ) );

		/**
		 * @param array<string, string> $texts
		 */
		$filtered = apply_filters( 'silvertrail_texts', $texts );
		return is_array( $filtered ) ? array_map( 'strval', $filtered + $texts ) : $texts;
	}

	/**
	 * The texts in a language before the admin's edits.
	 *
	 * @return array<string, string>
	 */
	public static function base( string $locale ): array {
		$switched = switch_to_locale( $locale );
		$texts    = self::defaults();
		$source   = self::translation( $locale );
		if ( null !== $source ) {
			list( $valid, $problems ) = self::validate( $source['texts'] );
			foreach ( $problems as $problem ) {
				/* translators: 1: where the translation comes from, 2: what is wrong */
				self::report( sprintf( __( 'The Silvertrail translation from %1$s: %2$s', 'silvertrail' ), $source['from'], $problem ) );
			}
			$texts = array_replace( $texts, $valid );
		}
		if ( $switched ) {
			restore_previous_locale();
		}
		return $texts;
	}

	/**
	 * The admin's edits in a language.
	 *
	 * @return array<string, string>
	 */
	public static function custom( string $locale ): array {
		$stored = Settings::get( 'texts' );
		$texts  = is_array( $stored ) && is_array( $stored[ $locale ] ?? null ) ? $stored[ $locale ] : array();
		return self::validate( $texts )[0];
	}

	/**
	 * Where the developer translation for a language comes from, and its texts.
	 *
	 * @return array{from: string, texts: array<mixed>}|null
	 */
	public static function translation( string $locale ): ?array {
		/**
		 * A developer translation: a path to a JSON file, or an array of texts.
		 *
		 * @param string|array<string, string> $translation The theme's silvertrail/texts-{locale}.json or silvertrail/texts.json, or ''.
		 * @param string                       $locale
		 */
		$translation = apply_filters( 'silvertrail_translation', locate_template( array( "silvertrail/texts-{$locale}.json", 'silvertrail/texts.json' ) ), $locale );
		if ( is_array( $translation ) ) {
			return array() === $translation ? null : array(
				'from'  => 'silvertrail_translation',
				'texts' => $translation,
			);
		}
		if ( ! is_string( $translation ) || '' === $translation || ! is_readable( $translation ) ) {
			return null;
		}
		if ( ! array_key_exists( $translation, self::$files ) ) {
			$decoded                     = json_decode( (string) file_get_contents( $translation ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
			self::$files[ $translation ] = is_array( $decoded ) ? $decoded : null;
		}
		if ( null === self::$files[ $translation ] ) {
			/* translators: %s: file path */
			self::report( sprintf( __( 'The Silvertrail translation %s is not a JSON object.', 'silvertrail' ), $translation ) );
			return null;
		}
		return array(
			'from'  => $translation,
			'texts' => self::$files[ $translation ],
		);
	}

	/**
	 * Keeps the known keys with usable text, and says what is wrong with the rest.
	 *
	 * @param array<mixed> $texts
	 * @return array{0: array<string, string>, 1: list<string>}
	 */
	public static function validate( array $texts ): array {
		$defaults = self::defaults();
		$valid    = array();
		$problems = array();
		foreach ( $texts as $key => $text ) {
			if ( ! isset( $defaults[ $key ] ) ) {
				/* translators: %s: text key */
				$problems[] = sprintf( __( '%s is not a Silvertrail text.', 'silvertrail' ), $key );
				continue;
			}
			if ( ! is_string( $text ) ) {
				/* translators: %s: text key */
				$problems[] = sprintf( __( '%s must be a string.', 'silvertrail' ), $key );
				continue;
			}
			if ( '' === trim( $text ) ) {
				continue;
			}
			if ( ! self::placeholders_match( $defaults[ $key ], $text ) ) {
				/* translators: %s: text key */
				$problems[] = sprintf( __( '%s needs exactly one %%s where the service name goes, and no other %% sign.', 'silvertrail' ), $key );
				continue;
			}
			$valid[ (string) $key ] = $text;
		}
		return array( $valid, $problems );
	}

	public static function reset(): void {
		self::$files    = array();
		self::$reported = array();
	}

	private static function placeholders_match( string $original, string $text ): bool {
		$expected = preg_match_all( self::PLACEHOLDER, $original );
		if ( 0 === $expected ) {
			return true;
		}
		$rest = (string) preg_replace( array( '/%%/', self::PLACEHOLDER ), '', $text );
		return preg_match_all( self::PLACEHOLDER, $text ) === $expected && ! str_contains( $rest, '%' );
	}

	private static function report( string $message ): void {
		if ( isset( self::$reported[ $message ] ) ) {
			return;
		}
		self::$reported[ $message ] = true;
		_doing_it_wrong( __CLASS__, esc_html( $message ), '1.0.0' );
	}
}
