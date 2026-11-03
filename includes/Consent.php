<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * Reads the consent cookie. Only meaningful on uncached requests such as form posts.
 */
final class Consent {

	/**
	 * @return array{n: int, c: list<string>, t: int}|null
	 */
	public static function cookie(): ?array {
		$name = Config::cookie();
		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every field is validated below.
		$data = json_decode( wp_unslash( $_COOKIE[ $name ] ), true );
		if ( ! is_array( $data ) || ! isset( $data['n'], $data['c'] ) || ! is_array( $data['c'] ) ) {
			return null;
		}
		return array(
			'n' => (int) $data['n'],
			'c' => array_values( array_filter( $data['c'], 'is_string' ) ),
			't' => (int) ( $data['t'] ?? 0 ),
		);
	}

	/**
	 * @return list<string>
	 */
	public static function consented_ids(): array {
		$cookie = self::cookie();
		if ( null === $cookie || $cookie['n'] < Config::revision() ) {
			return array();
		}
		return array_values( array_intersect( $cookie['c'], array_keys( Config::get()['services'] ) ) );
	}

	public static function has( string $service ): bool {
		if ( ! isset( Config::get()['services'][ $service ] ) ) {
			return false;
		}
		return in_array( $service, Config::essential_ids(), true ) || in_array( $service, self::consented_ids(), true );
	}
}
